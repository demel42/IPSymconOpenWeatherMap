<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class OpenWeatherOneCall extends IPSModule
{
    use OpenWeather\StubsCommonLib;
    use OpenWeatherMapLocalLib;

    public static $MAX_MINUTELY_FORECAST = 60;
    public static $MAX_HOURLY_FORECAST = 48;
    public static $MAX_DAILY_FORECAST = 7;

    public static $API_VERSION_2_5 = 0;
    public static $API_VERSION_3_0 = 1;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonContruct(__DIR__);
    }

    public function __destruct()
    {
        $this->CommonDestruct();
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('appid', '');
        $this->RegisterPropertyString('location', json_encode(['latitude' => 0, 'longitude' => 0]));
        $this->RegisterPropertyFloat('altitude', 0);

        $lang = '';
        if (isset($_ENV['LANG']) && preg_match('/([a-z]*)_.*/', $_ENV['LANG'], $r)) {
            $lang = $r[1];
        }
        $this->RegisterPropertyString('lang', $lang);

        $this->RegisterPropertyInteger('api_version', self::$API_VERSION_2_5);

        $this->RegisterPropertyBoolean('with_absolute_pressure', false);
        $this->RegisterPropertyBoolean('with_absolute_humidity', false);
        $this->RegisterPropertyBoolean('with_dewpoint', false);
        $this->RegisterPropertyBoolean('with_windchill', false);
        $this->RegisterPropertyBoolean('with_heatindex', false);
        $this->RegisterPropertyBoolean('with_uv_index', false);
        $this->RegisterPropertyBoolean('with_windstrength', false);
        $this->RegisterPropertyBoolean('with_windstrength2text', false);
        $this->RegisterPropertyBoolean('with_windangle', true);
        $this->RegisterPropertyBoolean('with_winddirection', false);
        $this->RegisterPropertyBoolean('with_rain_probability', false);
        $this->RegisterPropertyBoolean('with_cloudiness', false);
        $this->RegisterPropertyBoolean('with_conditions', false);
        $this->RegisterPropertyBoolean('with_icon', false);
        $this->RegisterPropertyBoolean('with_condition_id', false);

        $this->RegisterPropertyInteger('minutely_forecast_count', 0);
        $this->RegisterPropertyInteger('hourly_forecast_count', 0);
        $this->RegisterPropertyInteger('daily_forecast_count', 0);

        $this->RegisterPropertyInteger('update_interval', 15);

        $this->RegisterPropertyBoolean('collectApiCallStats', true);

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->InstallVarProfiles(false);

        $this->SetMultiBuffer('Data', '');

        $this->RegisterTimer('UpdateData', 0, $this->GetModulePrefix() . '_UpdateData(' . $this->InstanceID . ');');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function MessageSink($tstamp, $senderID, $message, $data)
    {
        parent::MessageSink($tstamp, $senderID, $message, $data);

        if ($message == IPS_KERNELMESSAGE && $data[0] == KR_READY) {
            $this->SetUpdateInterval();
        }
    }

    private function CheckModuleConfiguration()
    {
        $r = [];

        $appid = $this->ReadPropertyString('appid');
        if ($appid == '') {
            $this->SendDebug(__FUNCTION__, '"appid" is needed', 0);
            $r[] = $this->Translate('API key must be specified');
        }

        return $r;
    }

    private function CheckModuleUpdate(array $oldInfo, array $newInfo)
    {
        $r = [];

        if ($this->version2num($oldInfo) < $this->version2num('2.8')) {
            $old_minutely = false;
            $minutely_forecast_count = $this->ReadPropertyInteger('minutely_forecast_count');
            for ($i = 0; $i < $minutely_forecast_count; $i++) {
                $pre = 'MinutelyForecast';
                $post = '_' . sprintf('%02d', $i);
                @$varID = $this->GetIDForIdent($pre . 'RainProbability' . $post);
                if (@$varID != false) {
                    $old_minutely = true;
                    break;
                }
            }
            if ($old_minutely) {
                $r[] = $this->Translate('Rename variables beginning with \'MinutelyForecastRainProbability_\'');
            }
        }

        if ($this->version2num($oldInfo) < $this->version2num('2.9')) {
            $r[] = $this->Translate('Adjusting the value range of various variable profiles');
        }

        return $r;
    }

    private function CompleteModuleUpdate(array $oldInfo, array $newInfo)
    {
        if ($this->version2num($oldInfo) < $this->version2num('2.8')) {
            $minutely_forecast_count = $this->ReadPropertyInteger('minutely_forecast_count');
            $with_rain_probability = $this->ReadPropertyBoolean('with_rain_probability');
            for ($i = 0; $i < $minutely_forecast_count; $i++) {
                $vpos = 1000 + (100 * $i);
                $s = ' #M' . ($i + 1);
                $pre = 'MinutelyForecast';
                $post = '_' . sprintf('%02d', $i);
                @$varID = $this->GetIDForIdent($pre . 'RainProbability' . $post);
                if (@$varID != false) {
                    IPS_SetIdent($varID, $pre . 'Precipitation' . $post);
                    IPS_SetName($varID, $this->Translate('Precipitation') . $s);
                    $this->MaintainVariable($pre . 'Precipitation' . $post, $this->Translate('Precipitation') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Rainfall', $vpos++, $with_rain_probability);
                }
            }
        }

        if ($this->version2num($oldInfo) < $this->version2num('2.9')) {
            $vps = [
                'OpenWeatherMap.Temperatur',
                'OpenWeatherMap.Humidity',
                'OpenWeatherMap.absHumidity',
                'OpenWeatherMap.Dewpoint',
                'OpenWeatherMap.Heatindex',
                'OpenWeatherMap.WindSpeed',
                'OpenWeatherMap.RainProbability',
            ];
            foreach ($vps as $vp) {
                if (IPS_VariableProfileExists($vp)) {
                    IPS_DeleteVariableProfile($vp);
                }
            }
            $this->InstallVarProfiles(false);
        }

        return '';
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainReferences();

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainTimer('UpdateData', 0);
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainTimer('UpdateData', 0);
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainTimer('UpdateData', 0);
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $with_absolute_pressure = $this->ReadPropertyBoolean('with_absolute_pressure');
        $with_absolute_humidity = $this->ReadPropertyBoolean('with_absolute_humidity');
        $with_dewpoint = $this->ReadPropertyBoolean('with_dewpoint');
        $with_windchill = $this->ReadPropertyBoolean('with_windchill');
        $with_heatindex = $this->ReadPropertyBoolean('with_heatindex');
        $with_uv_index = $this->ReadPropertyBoolean('with_uv_index');
        $with_windstrength = $this->ReadPropertyBoolean('with_windstrength');
        $with_windstrength2text = $this->ReadPropertyBoolean('with_windstrength2text');
        $with_windangle = $this->ReadPropertyBoolean('with_windangle');
        $with_winddirection = $this->ReadPropertyBoolean('with_winddirection');
        $with_rain_probability = $this->ReadPropertyBoolean('with_rain_probability');
        $with_cloudiness = $this->ReadPropertyBoolean('with_cloudiness');
        $with_conditions = $this->ReadPropertyBoolean('with_conditions');
        $with_icon = $this->ReadPropertyBoolean('with_icon');
        $with_condition_id = $this->ReadPropertyBoolean('with_condition_id');
        $minutely_forecast_count = $this->ReadPropertyInteger('minutely_forecast_count');
        $hourly_forecast_count = $this->ReadPropertyInteger('hourly_forecast_count');
        $daily_forecast_count = $this->ReadPropertyInteger('daily_forecast_count');

        $vpos = 1;
        $this->MaintainVariable('Temperature', $this->Translate('Temperature'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Temperatur', $vpos++, true);
        $this->MaintainVariable('Humidity', $this->Translate('Humidity'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Humidity', $vpos++, true);
        $this->MaintainVariable('AbsoluteHumidity', $this->Translate('absolute humidity'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.absHumidity', $vpos++, $with_absolute_humidity);
        $this->MaintainVariable('Dewpoint', $this->Translate('Dewpoint'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Dewpoint', $vpos++, $with_dewpoint);
        $this->MaintainVariable('Heatindex', $this->Translate('Heatindex'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Heatindex', $vpos++, $with_heatindex);
        $this->MaintainVariable('Windchill', $this->Translate('Windchill'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Temperatur', $vpos++, $with_windchill);
        $this->MaintainVariable('UVIndex', $this->Translate('UV-Index'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.UVIndex', $vpos++, $with_uv_index);
        $this->MaintainVariable('Pressure', $this->Translate('Air pressure'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Pressure', $vpos++, true);
        $this->MaintainVariable('AbsolutePressure', $this->Translate('absolute pressure'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Pressure', $vpos++, $with_absolute_pressure);
        $this->MaintainVariable('WindSpeed', $this->Translate('Windspeed'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.WindSpeed', $vpos++, true);
        $this->MaintainVariable('WindStrength', $this->Translate('Windstrength'), VARIABLETYPE_INTEGER, 'OpenWeatherMap.WindStrength', $vpos++, $with_windstrength);
        $this->MaintainVariable('WindStrengthText', $this->Translate('Windstrength'), VARIABLETYPE_STRING, '', $vpos++, $with_windstrength2text);
        $this->MaintainVariable('WindAngle', $this->Translate('Winddirection'), VARIABLETYPE_INTEGER, 'OpenWeatherMap.WindAngle', $vpos++, $with_windangle);
        $this->MaintainVariable('WindDirection', $this->Translate('Winddirection'), VARIABLETYPE_STRING, 'OpenWeatherMap.WindDirection', $vpos++, $with_winddirection);
        $this->MaintainVariable('WindGust', $this->Translate('Windgust'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.WindSpeed', $vpos++, true);
        $this->MaintainVariable('Rain_1h', $this->Translate('Rainfall of last hour'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Rainfall', $vpos++, true);
        $this->MaintainVariable('Snow_1h', $this->Translate('Snowfall of last hour'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Snowfall', $vpos++, true);
        $this->MaintainVariable('Cloudiness', $this->Translate('Cloudiness'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Cloudiness', $vpos++, $with_cloudiness);
        $this->MaintainVariable('Conditions', $this->Translate('Conditions'), VARIABLETYPE_STRING, '', $vpos++, $with_conditions);
        $this->MaintainVariable('ConditionIcon', $this->Translate('Condition-icon'), VARIABLETYPE_STRING, '', $vpos++, $with_icon);
        $this->MaintainVariable('ConditionId', $this->Translate('Condition-id'), VARIABLETYPE_STRING, '', $vpos++, $with_condition_id);

        for ($i = 0; $i < self::$MAX_MINUTELY_FORECAST; $i++) {
            $vpos = 1000 + (100 * $i);
            $use = $i < $minutely_forecast_count;
            $s = ' #M' . ($i + 1);
            $pre = 'MinutelyForecast';
            $post = '_' . sprintf('%02d', $i);

            $this->MaintainVariable($pre . 'Begin' . $post, $this->Translate('Begin of minutely forecast-period') . $s, VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, $use);
            $this->MaintainVariable($pre . 'Precipitation' . $post, $this->Translate('Precipitation') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Rainfall', $vpos++, $use && $with_rain_probability);
        }

        for ($i = 0; $i < self::$MAX_HOURLY_FORECAST; $i++) {
            $vpos = 2000 + (100 * $i);
            $use = $i < $hourly_forecast_count;
            $s = ' #H' . ($i + 1);
            $pre = 'HourlyForecast';
            $post = '_' . sprintf('%02d', $i);

            $this->MaintainVariable($pre . 'Begin' . $post, $this->Translate('Begin of hourly forecast-period') . $s, VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, $use);
            $this->MaintainVariable($pre . 'Temperature' . $post, $this->Translate('Temperature') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Temperatur', $vpos++, $use);
            $this->MaintainVariable($pre . 'UVIndex' . $post, $this->Translate('UV-Index') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.UVIndex', $vpos++, $use);
            $this->MaintainVariable($pre . 'Humidity' . $post, $this->Translate('Humidity') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Humidity', $vpos++, $use);
            $this->MaintainVariable($pre . 'Pressure' . $post, $this->Translate('Air pressure') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Pressure', $vpos++, $use);
            $this->MaintainVariable($pre . 'AbsolutePressure' . $post, $this->Translate('absolute pressure') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Pressure', $vpos++, $use && $with_absolute_pressure);
            $this->MaintainVariable($pre . 'WindSpeed' . $post, $this->Translate('Windspeed') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.WindSpeed', $vpos++, $use);
            $this->MaintainVariable($pre . 'WindStrength' . $post, $this->Translate('Windstrength') . $s, VARIABLETYPE_INTEGER, 'OpenWeatherMap.WindStrength', $vpos++, $use && $with_windstrength);
            $this->MaintainVariable($pre . 'WindStrengthText' . $post, $this->Translate('Windstrength') . $s, VARIABLETYPE_STRING, '', $vpos++, $use && $with_windstrength2text);
            $this->MaintainVariable($pre . 'WindAngle' . $post, $this->Translate('Winddirection') . $s, VARIABLETYPE_INTEGER, 'OpenWeatherMap.WindAngle', $vpos++, $use && $with_windangle);
            $this->MaintainVariable($pre . 'WindDirection' . $post, $this->Translate('Winddirection') . $s, VARIABLETYPE_STRING, 'OpenWeatherMap.WindDirection', $vpos++, $use && $with_winddirection);
            $this->MaintainVariable($pre . 'WindGust' . $post, $this->Translate('Windgust') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.WindSpeed', $vpos++, $use);
            $this->MaintainVariable($pre . 'Rain_1h' . $post, $this->Translate('Rainfall per hour') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Rainfall', $vpos++, $use);
            $this->MaintainVariable($pre . 'Snow_1h' . $post, $this->Translate('Snowfall per hour') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Snowfall', $vpos++, $use);
            $this->MaintainVariable($pre . 'RainProbability' . $post, $this->Translate('Rain propability') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.RainProbability', $vpos++, $use && $with_rain_probability);
            $this->MaintainVariable($pre . 'Cloudiness' . $post, $this->Translate('Cloudiness') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Cloudiness', $vpos++, $use && $with_cloudiness);
            $this->MaintainVariable($pre . 'Conditions' . $post, $this->Translate('Conditions') . $s, VARIABLETYPE_STRING, '', $vpos++, $use && $with_conditions);
            $this->MaintainVariable($pre . 'ConditionIcon' . $post, $this->Translate('Condition-icon') . $s, VARIABLETYPE_STRING, '', $vpos++, $use && $with_icon);
            $this->MaintainVariable($pre . 'ConditionId' . $post, $this->Translate('Condition-id') . $s, VARIABLETYPE_STRING, '', $vpos++, $use && $with_condition_id);
        }

        for ($i = 0; $i < self::$MAX_DAILY_FORECAST; $i++) {
            $vpos = 3000 + (100 * $i);
            $use = $i < $daily_forecast_count;
            $s = ' #D' . ($i + 1);
            $pre = 'DailyForecast';
            $post = '_' . sprintf('%02d', $i);

            $this->MaintainVariable($pre . 'Begin' . $post, $this->Translate('Begin of daily forecast-period') . $s, VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, $use);
            $this->MaintainVariable($pre . 'TemperatureMorning' . $post, $this->Translate('Morning temperature') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Temperatur', $vpos++, $use);
            $this->MaintainVariable($pre . 'TemperatureDay' . $post, $this->Translate('Day temperature') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Temperatur', $vpos++, $use);
            $this->MaintainVariable($pre . 'TemperatureEvening' . $post, $this->Translate('Evening temperature') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Temperatur', $vpos++, $use);
            $this->MaintainVariable($pre . 'TemperatureNight' . $post, $this->Translate('Night temperature') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Temperatur', $vpos++, $use);
            $this->MaintainVariable($pre . 'TemperatureMin' . $post, $this->Translate('minimum temperature') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Temperatur', $vpos++, $use);
            $this->MaintainVariable($pre . 'TemperatureMax' . $post, $this->Translate('maximum temperature') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Temperatur', $vpos++, $use);
            $this->MaintainVariable($pre . 'UVIndex' . $post, $this->Translate('UV-Index') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.UVIndex', $vpos++, $use);
            $this->MaintainVariable($pre . 'Humidity' . $post, $this->Translate('Humidity') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Humidity', $vpos++, $use);
            $this->MaintainVariable($pre . 'Pressure' . $post, $this->Translate('Air pressure') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Pressure', $vpos++, $use);
            $this->MaintainVariable($pre . 'AbsolutePressure' . $post, $this->Translate('absolute pressure') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Pressure', $vpos++, $use && $with_absolute_pressure);
            $this->MaintainVariable($pre . 'WindSpeed' . $post, $this->Translate('Windspeed') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.WindSpeed', $vpos++, $use);
            $this->MaintainVariable($pre . 'WindStrength' . $post, $this->Translate('Windstrength') . $s, VARIABLETYPE_INTEGER, 'OpenWeatherMap.WindStrength', $vpos++, $use && $with_windstrength);
            $this->MaintainVariable($pre . 'WindStrengthText' . $post, $this->Translate('Windstrength') . $s, VARIABLETYPE_STRING, '', $vpos++, $use && $with_windstrength2text);
            $this->MaintainVariable($pre . 'WindAngle' . $post, $this->Translate('Winddirection') . $s, VARIABLETYPE_INTEGER, 'OpenWeatherMap.WindAngle', $vpos++, $use && $with_windangle);
            $this->MaintainVariable($pre . 'WindDirection' . $post, $this->Translate('Winddirection') . $s, VARIABLETYPE_STRING, 'OpenWeatherMap.WindDirection', $vpos++, $use && $with_winddirection);
            $this->MaintainVariable($pre . 'WindGust' . $post, $this->Translate('Windgust') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.WindSpeed', $vpos++, $use);
            $this->MaintainVariable($pre . 'RainProbability' . $post, $this->Translate('Rain propability') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.RainProbability', $vpos++, $use && $with_rain_probability);
            $this->MaintainVariable($pre . 'Rain' . $post, $this->Translate('Rainfall') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Rainfall', $vpos++, $use);
            $this->MaintainVariable($pre . 'Cloudiness' . $post, $this->Translate('Cloudiness') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Cloudiness', $vpos++, $use && $with_cloudiness);
            $this->MaintainVariable($pre . 'Conditions' . $post, $this->Translate('Conditions') . $s, VARIABLETYPE_STRING, '', $vpos++, $use && $with_conditions);
            $this->MaintainVariable($pre . 'ConditionIcon' . $post, $this->Translate('Condition-icon') . $s, VARIABLETYPE_STRING, '', $vpos++, $use && $with_icon);
            $this->MaintainVariable($pre . 'ConditionId' . $post, $this->Translate('Condition-id') . $s, VARIABLETYPE_STRING, '', $vpos++, $use && $with_condition_id);
        }

        $vpos = 9000;
        $this->MaintainVariable('LastMeasurement', $this->Translate('last measurement'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainTimer('UpdateData', 0);
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $this->MaintainStatus(IS_ACTIVE);

        $apiLimits = [
            [
                'value' => 1000,
                'unit'  => 'day',
            ],
        ];

        $apiNotes = '';
        $s = $this->Translate('After the free number has been used, there will be a cost, see here for details');
        $apiNotes .= $s . ': ' . 'https://home.openweathermap.org/subscriptions' . PHP_EOL;

        $this->ApiCallSetInfo($apiLimits, $apiNotes);

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->SetUpdateInterval();
        }
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('OpenWeatherMap - fetch current observations and forecast');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => [
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'appid',
                    'caption' => 'API key'
                ],
                [
                    'type'    => 'Label',
                    'caption' => 'station data - if position is not set, Modue \'Location\' is used'
                ],
                [
                    'type'    => 'SelectLocation',
                    'name'    => 'location',
                    'caption' => 'Location',
                ],
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'altitude',
                    'caption' => 'Altitude',
                    'suffix'  => 'm'
                ],
                [
                    'type'    => 'Label',
                    'caption' => 'Language setting for textual weather-information (de, en, ...)'
                ],
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'lang',
                    'caption' => 'Language code'
                ],
                [
                    'type'    => 'Select',
                    'name'    => 'api_version',
                    'options' => [
                        [
                            'caption' => '2.5',
                            'value'   => self::$API_VERSION_2_5,
                        ],
                        [
                            'caption' => '3.0',
                            'value'   => self::$API_VERSION_3_0,
                        ],
                    ],
                    'caption' => 'API version',
                ],
            ],
            'caption' => 'Basic settings',
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => [
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_absolute_pressure',
                    'caption' => ' ... absolute Pressure'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_absolute_humidity',
                    'caption' => ' ... absolute Humidity'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_dewpoint',
                    'caption' => ' ... Dewpoint'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_heatindex',
                    'caption' => ' ... Heatindex'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_uv_index',
                    'caption' => ' ... UV-Index'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_windchill',
                    'caption' => ' ... Windchill'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_windstrength',
                    'caption' => ' ... Windstrength'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_windstrength2text',
                    'caption' => ' ... Windstrength as text'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_windangle',
                    'caption' => ' ... Winddirection in degrees'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_winddirection',
                    'caption' => ' ... Winddirection with label'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_rain_probability',
                    'caption' => ' ... Rain propability'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_cloudiness',
                    'caption' => ' ... Cloudiness'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_conditions',
                    'caption' => ' ... Conditions'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_icon',
                    'caption' => ' ... Condition-icon'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_condition_id',
                    'caption' => ' ... Condition-id'
                ],
                [
                    'type'    => 'Label',
                    'caption' => 'precipitation forecast by the minute (max 60 minutes)'
                ],
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'NumberSpinner',
                            'name'    => 'minutely_forecast_count',
                            'caption' => 'Count',
                            'maximum' => self::$MAX_MINUTELY_FORECAST
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => ' ... attention: decreasing the number deletes the unused variables!'
                        ]
                    ]
                ],
                [
                    'type'    => 'Label',
                    'caption' => 'hourly forecast (max 48 hour)'
                ],
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'NumberSpinner',
                            'name'    => 'hourly_forecast_count',
                            'caption' => 'Count',
                            'maximum' => self::$MAX_HOURLY_FORECAST
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => ' ... attention: decreasing the number deletes the unused variables!'
                        ]
                    ]
                ],
                [
                    'type'    => 'Label',
                    'caption' => 'daily forecast (max 7 days)'
                ],
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'NumberSpinner',
                            'name'    => 'daily_forecast_count',
                            'caption' => 'Count',
                            'maximum' => self::$MAX_DAILY_FORECAST
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => ' ... attention: decreasing the number deletes the unused variables!'
                        ]
                    ]
                ],
            ],
            'caption' => 'optional weather data',
        ];

        $formElements[] = [
            'type'    => 'RowLayout',
            'items'   => [
                [
                    'type'    => 'NumberSpinner',
                    'minimum' => 0,
                    'suffix'  => 'Minutes',
                    'width'   => '200px',
                    'name'    => 'update_interval',
                    'caption' => 'Update interval'
                ],
                [
                    'type'    => 'Label',
                    'caption' => '   ',
                ],
                [
                    'type'    => 'Label',
                    'caption' => 'Hint: the update frequency of the OpenWeather model is not higher than once in 10 minutes (https://openweathermap.org/appid)',
                ],
            ],
        ];

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'collectApiCallStats',
            'caption' => 'Collect data of API calls'
        ];

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            $formActions[] = $this->GetCompleteUpdateFormAction();

            $formActions[] = $this->GetInformationFormAction();
            $formActions[] = $this->GetReferencesFormAction();

            return $formActions;
        }

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Update weatherdata',
            'onClick' => $this->GetModulePrefix() . '_UpdateData($id);'
        ];

        $items = [
            $this->GetInstallVarProfilesFormItem(),
        ];

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $items[] = $this->GetApiCallStatsFormItem();
        }

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => $items,
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    public function RequestAction($Ident, $Value)
    {
        if ($this->CommonRequestAction($Ident, $Value)) {
            return;
        }

        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        switch ($Ident) {
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $Ident, 0);
                break;
        }
    }

    protected function SetUpdateInterval()
    {
        $min = $this->ReadPropertyInteger('update_interval');
        $msec = $min > 0 ? $min * 1000 * 60 : 0;
        $this->MaintainTimer('UpdateData', $msec);
    }

    public function UpdateData()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $lat = 0;
        $lng = 0;
        $loc = json_decode($this->ReadPropertyString('location'), true);
        if ($loc != false) {
            $lat = $loc['latitude'];
            $lng = $loc['longitude'];
        }
        if ($lat == 0 && $lng == 0) {
            $loc = $this->GetSystemLocation();
            $lat = $loc['latitude'];
            $lng = $loc['longitude'];
        }

        $args = [
            'lat'   => number_format($lat, 6, '.', ''),
            'lon'   => number_format($lng, 6, '.', ''),
            'units' => 'metric'
        ];

        $lang = $this->ReadPropertyString('lang');
        if ($lang != '') {
            $args['lang'] = $lang;
        }
        $args['units'] = 'metric';

        $excludeS = [];
        $minutely_forecast_count = $this->ReadPropertyInteger('minutely_forecast_count');
        if ($minutely_forecast_count == 0) {
            $excludeS[] = 'minutely';
        }
        $hourly_forecast_count = $this->ReadPropertyInteger('hourly_forecast_count');
        if ($hourly_forecast_count == 0) {
            $excludeS[] = 'hourly';
        }
        $daily_forecast_count = $this->ReadPropertyInteger('daily_forecast_count');
        if ($daily_forecast_count == 0) {
            $excludeS[] = 'daily';
        }
        if ($excludeS != []) {
            $args['exclude'] = implode(',', $excludeS);
        }

        $api_version = $this->ReadPropertyInteger('api_version');
        if ($api_version == self::$API_VERSION_3_0) {
            $endpoint = 'data/3.0/onecall';
        } else {
            $endpoint = 'data/2.5/onecall';
        }

        $jdata = $this->do_HttpRequest($endpoint, $args);
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        if ($jdata == '') {
            $this->SetMultiBuffer('Data', '');
            return;
        }

        $with_absolute_pressure = $this->ReadPropertyBoolean('with_absolute_pressure');
        $with_absolute_humidity = $this->ReadPropertyBoolean('with_absolute_humidity');
        $with_dewpoint = $this->ReadPropertyBoolean('with_dewpoint');
        $with_windchill = $this->ReadPropertyBoolean('with_windchill');
        $with_heatindex = $this->ReadPropertyBoolean('with_heatindex');
        $with_uv_index = $this->ReadPropertyBoolean('with_uv_index');
        $with_windstrength = $this->ReadPropertyBoolean('with_windstrength');
        $with_windstrength2text = $this->ReadPropertyBoolean('with_windstrength2text');
        $with_windangle = $this->ReadPropertyBoolean('with_windangle');
        $with_winddirection = $this->ReadPropertyBoolean('with_winddirection');
        $with_rain_probability = $this->ReadPropertyBoolean('with_rain_probability');
        $with_cloudiness = $this->ReadPropertyBoolean('with_cloudiness');
        $with_conditions = $this->ReadPropertyBoolean('with_conditions');
        $with_icon = $this->ReadPropertyBoolean('with_icon');
        $with_condition_id = $this->ReadPropertyBoolean('with_condition_id');

        $timestamp = $this->GetArrayElem($jdata, 'current.dt', 0);
        $temperature = $this->GetArrayElem($jdata, 'current.temp', 0);
        $pressure = $this->GetArrayElem($jdata, 'current.pressure', 0);
        $humidity = $this->GetArrayElem($jdata, 'current.humidity', 0);

        $visibility = $this->GetArrayElem($jdata, 'current.visibility', 0);

        $wind_speed = $this->GetArrayElem($jdata, 'current.wind_speed', 0);
        $wind_speed = (int) $this->ms2kmh($wind_speed);
        $wind_deg = $this->GetArrayElem($jdata, 'current.wind_deg', 0);
        $wind_gust = $this->GetArrayElem($jdata, 'current.wind_gust', 0);

        $clouds = $this->GetArrayElem($jdata, 'current.clouds', 0);

        $conditions = '';
        $icon = '';
        $id = '';
        $weather = $this->GetArrayElem($jdata, 'current.weather', '');
        if ($weather != '') {
            foreach ($weather as $w) {
                $description = $this->GetArrayElem($w, 'description', '');
                if ($description != '') {
                    $conditions .= ($conditions != '' ? ', ' : '') . $description;
                }
            }
            $icon = $this->GetArrayElem($weather, '0.icon', '');
            $id = $this->GetArrayElem($weather, '0.id', '');
        }

        $this->SetValue('Temperature', $temperature);

        if ($with_dewpoint) {
            $dewpoint = $this->GetArrayElem($jdata, 'current.dew_point', 0);
            $this->SetValue('Dewpoint', $dewpoint);
        }

        if ($with_windchill) {
            $windchill = $this->GetArrayElem($jdata, 'current.feels_like', 0);
            $this->SetValue('Windchill', $windchill);
        }

        if ($with_heatindex) {
            $heatindex = $this->CalcHeatindex($temperature, $humidity);
            $this->SetValue('Heatindex', $heatindex);
        }

        $this->SetValue('Pressure', $pressure);
        if ($with_absolute_pressure) {
            $altitude = $this->ReadPropertyFloat('altitude');
            $abs_pressure = $this->CalcAbsolutePressure($pressure, $temperature, $altitude);
            $this->SetValue('AbsolutePressure', $abs_pressure);
        }

        $this->SetValue('Humidity', $humidity);
        if ($with_absolute_humidity) {
            $abs_humidity = $this->CalcAbsoluteHumidity($temperature, $humidity);
            $this->SetValue('AbsoluteHumidity', $abs_humidity);
        }

        if ($with_uv_index) {
            $uvi = $this->GetArrayElem($jdata, 'current.uvi', 0);
            $this->SetValue('UVIndex', (float) $uvi);
        }

        $this->SetValue('WindSpeed', $wind_speed);
        if ($with_windangle) {
            $this->SetValue('WindAngle', $wind_deg);
        }
        if ($with_windstrength) {
            $windstrength = $this->ConvertWindSpeed2Strength((int) $wind_speed);
            $this->SetValue('WindStrength', $windstrength);
        }
        if ($with_windstrength2text) {
            $bft = $this->ConvertWindSpeed2Strength((int) $wind_speed);
            $windstrength = $this->ConvertWindStrength2Text($bft);
            $this->SetValue('WindStrengthText', $windstrength);
        }
        if ($with_winddirection) {
            $dir = $this->ConvertWindDirection2Text((int) $wind_deg) . ' (' . $wind_deg . '°)';
            $this->SetValue('WindDirection', $dir);
        }
        $this->SetValue('WindGust', $wind_gust);

        $rain_1h = $this->GetArrayElem($jdata, 'rain.1h', 0);
        $this->SetValue('Rain_1h', $rain_1h);

        $snow_1h = $this->GetArrayElem($jdata, 'snow.1h', 0);
        $this->SetValue('Snow_1h', $snow_1h);

        if ($with_cloudiness) {
            $this->SetValue('Cloudiness', $clouds);
        }

        if ($with_conditions) {
            $this->SetValue('Conditions', $conditions);
        }

        if ($with_icon) {
            $this->SetValue('ConditionIcon', $icon);
        }

        if ($with_condition_id) {
            $this->SetValue('ConditionId', $id);
        }

        $minutely_forecast_count = $this->ReadPropertyInteger('minutely_forecast_count');
        for ($i = 0; $i < $minutely_forecast_count; $i++) {
            $pre = 'MinutelyForecast';
            $post = '_' . sprintf('%02d', $i);

            $ent = $this->GetArrayElem($jdata, 'minutely.' . $i, '');
            if ($ent == false) {
                $this->SendDebug(__FUNCTION__, 'minutely[' . $i . '] not in data', 0);
                break;
            }
            $this->SendDebug(__FUNCTION__, 'minutely[' . $i . ']=' . print_r($ent, true), 0);

            $begin_ts = $this->GetArrayElem($ent, 'dt', 0);
            $precipitation = $this->GetArrayElem($ent, 'precipitation', 0);

            $this->SetValue($pre . 'Begin' . $post, $begin_ts);
            $this->SetValue($pre . 'Precipitation' . $post, $precipitation);
        }

        $hourly_forecast_count = $this->ReadPropertyInteger('hourly_forecast_count');
        for ($i = 0; $i < $hourly_forecast_count; $i++) {
            $pre = 'HourlyForecast';
            $post = '_' . sprintf('%02d', $i);

            $ent = $this->GetArrayElem($jdata, 'hourly.' . $i, '');
            if ($ent == false) {
                $this->SendDebug(__FUNCTION__, 'hourly[' . $i . '] not in data', 0);
                break;
            }
            $this->SendDebug(__FUNCTION__, 'hourly[' . $i . ']=' . print_r($ent, true), 0);

            $begin_ts = $this->GetArrayElem($ent, 'dt', 0);
            $temperature = $this->GetArrayElem($ent, 'temp', 0);
            $uvi = $this->GetArrayElem($ent, 'uvi', 0);
            $pressure = $this->GetArrayElem($ent, 'pressure', 0);
            $humidity = $this->GetArrayElem($ent, 'humidity', 0);

            $visibility = $this->GetArrayElem($ent, 'visibility', 0);

            $wind_speed = $this->GetArrayElem($ent, 'wind_speed', 0);
            $wind_speed = (int) $this->ms2kmh($wind_speed);
            $wind_deg = $this->GetArrayElem($ent, 'wind_deg', 0);
            $wind_gust = $this->GetArrayElem($ent, 'wind_gust', 0);

            $rain_1h = $this->GetArrayElem($ent, 'rain.1h', 0);
            $snow_1h = $this->GetArrayElem($ent, 'snow.1h', 0);

            $pop = $this->GetArrayElem($ent, 'pop', 0);

            $clouds = $this->GetArrayElem($ent, 'clouds', 0);

            $conditions = '';
            $weather = $this->GetArrayElem($ent, 'weather', '');
            if ($weather != '') {
                foreach ($weather as $w) {
                    $description = $this->GetArrayElem($w, 'description', '');
                    if ($description != '') {
                        $conditions .= ($conditions != '' ? ', ' : '') . $description;
                    }
                }
                $icon = $this->GetArrayElem($weather, '0.icon', '');
                $id = $this->GetArrayElem($weather, '0.id', '');
            }

            $this->SetValue($pre . 'Begin' . $post, $begin_ts);

            $this->SetValue($pre . 'Temperature' . $post, $temperature);

            $this->SetValue($pre . 'Pressure' . $post, $pressure);
            if ($with_absolute_pressure) {
                $abs_humidity = $this->CalcAbsoluteHumidity($temperature, $humidity);
                $this->SetValue($pre . 'AbsolutePressure' . $post, $abs_pressure);
            }

            $this->SetValue($pre . 'UVIndex' . $post, (float) $uvi);

            $this->SetValue($pre . 'Humidity' . $post, $humidity);

            $this->SetValue($pre . 'WindSpeed' . $post, $wind_speed);
            if ($with_windangle) {
                $this->SetValue($pre . 'WindAngle' . $post, $wind_deg);
            }
            if ($with_windstrength) {
                $windstrength = $this->ConvertWindSpeed2Strength((int) $wind_speed);
                $this->SetValue($pre . 'WindStrength' . $post, $windstrength);
            }
            if ($with_windstrength2text) {
                $bft = $this->ConvertWindSpeed2Strength((int) $wind_speed);
                $windstrength = $this->ConvertWindStrength2Text($bft);
                $this->SetValue($pre . 'WindStrengthText' . $post, $windstrength);
            }
            if ($with_winddirection) {
                $dir = $this->ConvertWindDirection2Text((int) $wind_deg) . ' (' . $wind_deg . '°)';
                $this->SetValue($pre . 'WindDirection' . $post, $dir);
            }
            $this->SetValue($pre . 'WindGust' . $post, $wind_gust);

            $this->SetValue($pre . 'Rain_1h' . $post, $rain_1h);
            $this->SetValue($pre . 'Snow_1h' . $post, $snow_1h);

            if ($with_rain_probability) {
                $this->SetValue($pre . 'RainProbability' . $post, (float) $pop * 100);
            }

            if ($with_cloudiness) {
                $this->SetValue($pre . 'Cloudiness' . $post, $clouds);
            }

            if ($with_conditions) {
                $this->SetValue($pre . 'Conditions' . $post, $conditions);
            }

            if ($with_icon) {
                $this->SetValue($pre . 'ConditionIcon' . $post, $icon);
            }

            if ($with_condition_id) {
                $this->SetValue($pre . 'ConditionId' . $post, $id);
            }
        }

        $daily_forecast_count = $this->ReadPropertyInteger('daily_forecast_count');
        for ($i = 0; $i < $daily_forecast_count; $i++) {
            $pre = 'DailyForecast';
            $post = '_' . sprintf('%02d', $i);

            $ent = $this->GetArrayElem($jdata, 'daily.' . $i, '');
            if ($ent == false) {
                $this->SendDebug(__FUNCTION__, 'daily[' . $i . '] not in data', 0);
                break;
            }
            $this->SendDebug(__FUNCTION__, 'daily[' . $i . ']=' . print_r($ent, true), 0);

            $begin_ts = $this->GetArrayElem($ent, 'dt', 0);
            $temperature_morning = $this->GetArrayElem($ent, 'temp.morn', 0);
            $temperature_day = $this->GetArrayElem($ent, 'temp.day', 0);
            $temperature_evening = $this->GetArrayElem($ent, 'temp.eve', 0);
            $temperature_night = $this->GetArrayElem($ent, 'temp.night', 0);
            $temperature_min = $this->GetArrayElem($ent, 'temp.min', 0);
            $temperature_max = $this->GetArrayElem($ent, 'temp.max', 0);

            $uvi = $this->GetArrayElem($ent, 'uvi', 0);
            $pressure = $this->GetArrayElem($ent, 'pressure', 0);
            $humidity = $this->GetArrayElem($ent, 'humidity', 0);

            $visibility = $this->GetArrayElem($ent, 'visibility', 0);

            $wind_speed = $this->GetArrayElem($ent, 'wind_speed', 0);
            $wind_speed = (int) $this->ms2kmh($wind_speed);
            $wind_deg = $this->GetArrayElem($ent, 'wind_deg', 0);
            $wind_gust = $this->GetArrayElem($ent, 'wind_gust', 0);

            $pop = $this->GetArrayElem($ent, 'pop', 0);
            $rain = $this->GetArrayElem($ent, 'rain', 0);

            $clouds = $this->GetArrayElem($ent, 'clouds', 0);

            $conditions = '';
            $weather = $this->GetArrayElem($ent, 'weather', '');
            if ($weather != '') {
                foreach ($weather as $w) {
                    $description = $this->GetArrayElem($w, 'description', '');
                    if ($description != '') {
                        $conditions .= ($conditions != '' ? ', ' : '') . $description;
                    }
                }
                $icon = $this->GetArrayElem($weather, '0.icon', '');
                $id = $this->GetArrayElem($weather, '0.id', '');
            }

            $this->SetValue($pre . 'Begin' . $post, $begin_ts);

            $this->SetValue($pre . 'TemperatureMorning' . $post, $temperature_morning);
            $this->SetValue($pre . 'TemperatureDay' . $post, $temperature_day);
            $this->SetValue($pre . 'TemperatureEvening' . $post, $temperature_evening);
            $this->SetValue($pre . 'TemperatureNight' . $post, $temperature_night);
            $this->SetValue($pre . 'TemperatureMin' . $post, $temperature_min);
            $this->SetValue($pre . 'TemperatureMax' . $post, $temperature_max);

            $this->SetValue($pre . 'Pressure' . $post, $pressure);
            if ($with_absolute_pressure) {
                $abs_humidity = $this->CalcAbsoluteHumidity($temperature, $humidity);
                $this->SetValue($pre . 'AbsolutePressure' . $post, $abs_pressure);
            }

            $this->SetValue($pre . 'UVIndex' . $post, (float) $uvi);

            $this->SetValue($pre . 'Humidity' . $post, $humidity);

            $this->SetValue($pre . 'WindSpeed' . $post, $wind_speed);
            if ($with_windangle) {
                $this->SetValue($pre . 'WindAngle' . $post, $wind_deg);
            }
            if ($with_windstrength) {
                $windstrength = $this->ConvertWindSpeed2Strength((int) $wind_speed);
                $this->SetValue($pre . 'WindStrength' . $post, $windstrength);
            }
            if ($with_windstrength2text) {
                $bft = $this->ConvertWindSpeed2Strength((int) $wind_speed);
                $windstrength = $this->ConvertWindStrength2Text($bft);
                $this->SetValue($pre . 'WindStrengthText' . $post, $windstrength);
            }
            if ($with_winddirection) {
                $dir = $this->ConvertWindDirection2Text((int) $wind_deg) . ' (' . $wind_deg . '°)';
                $this->SetValue($pre . 'WindDirection' . $post, $dir);
            }
            $this->SetValue($pre . 'WindGust' . $post, $wind_gust);

            if ($with_rain_probability) {
                $this->SetValue($pre . 'RainProbability' . $post, (float) $pop * 100);
            }
            $this->SetValue($pre . 'Rain' . $post, (float) $rain);

            if ($with_cloudiness) {
                $this->SetValue($pre . 'Cloudiness' . $post, $clouds);
            }

            if ($with_conditions) {
                $this->SetValue($pre . 'Conditions' . $post, $conditions);
            }

            if ($with_icon) {
                $this->SetValue($pre . 'ConditionIcon' . $post, $icon);
            }

            if ($with_condition_id) {
                $this->SetValue($pre . 'ConditionId' . $post, $id);
            }
        }

        $this->SetValue('LastMeasurement', $timestamp);

        $this->SetMultiBuffer('Data', json_encode($jdata));

        $this->MaintainStatus(IS_ACTIVE);

        $this->SendDebug(__FUNCTION__, $this->PrintTimer('UpdateData'), 0);
    }

    private function do_HttpRequest($cmd, $args)
    {
        $appid = $this->ReadPropertyString('appid');

        $url = 'https://api.openweathermap.org/' . $cmd . '?appid=' . $appid;

        if ($args != '') {
            foreach ($args as $arg => $value) {
                $url .= '&' . $arg;
                if ($value != '') {
                    $url .= '=' . rawurlencode((string) $value);
                }
            }
        }

        $this->SendDebug(__FUNCTION__, 'http-get: url=' . $url, 0);

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $cdata = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, '    cdata=' . $cdata, 0);

        $statuscode = 0;
        $err = '';
        $jdata = '';
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } elseif ($httpcode != 200) {
            if ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = "got http-code $httpcode (server error)";
            } else {
                $err = "got http-code $httpcode";
                $statuscode = self::$IS_HTTPERROR;
            }
        } elseif ($cdata == '') {
            $statuscode = self::$IS_INVALIDDATA;
            $err = 'no data';
        } else {
            $jdata = json_decode($cdata, true);
            if ($jdata == '') {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'malformed response';
            }
        }

        if ($statuscode) {
            $this->LogMessage('url=' . $url . ' => statuscode=' . $statuscode . ', err=' . $err, KL_WARNING);
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
        }

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($url, $err, $statuscode);
        }

        return $jdata;
    }

    // relative Luffeuchtigkeit in absolute Feuchte umrechnen
    //   Quelle: https://www.wetterochs.de/wetter/feuchte.html
    public function CalcAbsoluteHumidity(float $temp, float $humidity)
    {
        if ($temp >= 0) {
            $a = 7.5;
            $b = 237.3;
        } else {
            $a = 7.6;
            $b = 240.7;
        }

        $R = 8314.3; // universelle Gaskonstante in J/(kmol*K)
        $mw = 18.016; // Molekulargewicht des Wasserdampfes in kg/kmol

        // Sättigungsdamphdruck in hPa
        $SDD = 6.1078 * pow(10, (($a * $temp) / ($b + $temp)));

        // Dampfdruck in hPa
        $DD = $humidity / 100 * $SDD;

        $v = log10($DD / 6.1078);

        // Taupunkttemperatur in °C
        $TD = $b * $v / ($a - $v);

        // Temperatur in Kelvin
        $TK = $temp + 273.15;

        // absolute Feuchte in g Wasserdampf pro m³ Luft
        $AF = pow(10, 5) * $mw / $R * $DD / $TK;
        $AF = round($AF * 10) / 10; // auf eine NK runden

        return $AF;
    }

    // gemessenen Luftdruck in absoluen Luftdruck (Meereshöhe) umrechnen
    //   Quelle: https://rechneronline.de/barometer/hoehe.php
    public function CalcAbsolutePressure(float $pressure, float $temp, float $altitude)
    {
        // Temperaturgradient (geschätzt)
        $TG = 0.0065;

        // Höhe = Differenz Meereshöhe zu Standort
        $ad = $altitude * -1;

        // Temperatur auf Meereshöhe herunter rechnen
        //     Schätzung: Temperatur auf Meereshöhe = Temperatur + Temperaturgradient * Höhe
        $T = $temp + $TG * $ad;
        // Temperatur in Kelvin
        $TK = $T + 273.15;

        // Luftdruck auf Meereshöhe = Barometeranzeige / (1-Temperaturgradient*Höhe/Temperatur auf Meereshöhe in Kelvin)^(0,03416/Temperaturgradient)
        $AP = $pressure / pow((1 - $TG * $ad / $TK), (0.03416 / $TG));

        return $AP;
    }

    // Windrichtung in Grad als Bezeichnung ausgeben
    //   Quelle: https://www.windfinder.com/wind/windspeed.htm
    public function ConvertWindDirection2Text(int $dir)
    {
        $dir2txt = [
            'N',
            'NNE',
            'NE',
            'ENE',
            'E',
            'ESE',
            'SE',
            'SSE',
            'S',
            'SSW',
            'SW',
            'WSW',
            'W',
            'WNW',
            'NW',
            'NNW',
        ];

        $idx = floor((($dir + 11.25) % 360) / 22.5);
        if ($idx >= 0 && $idx < count($dir2txt)) {
            $txt = $this->Translate($dir2txt[$idx]);
        } else {
            $txt = '';
        }
        return $txt;
    }

    // Windgeschwindigkeit in Beaufort umrechnen
    //   Quelle: https://de.wikipedia.org/wiki/Beaufortskala
    public function ConvertWindSpeed2Strength(int $speed)
    {
        $kn2bft = [1, 4, 7, 11, 16, 22, 28, 34, 41, 48, 56, 64];

        $kn = $speed / 1.852;
        for ($i = 0; $i < count($kn2bft); $i++) {
            if ($kn < $kn2bft[$i]) {
                break;
            }
        }
        return $i;
    }

    // Windstärke als Text ausgeben
    //  Quelle: https://de.wikipedia.org/wiki/Beaufortskala
    public function ConvertWindStrength2Text(int $bft)
    {
        $bft2txt = [
            'Calm',
            'Light air',
            'Light breeze',
            'Gentle breeze',
            'Moderate breeze',
            'Fresh breeze',
            'Strong breeze',
            'High wind',
            'Gale',
            'Strong gale',
            'Storm',
            'Hurricane force',
            'Violent storm'
        ];

        if ($bft >= 0 && $bft < count($bft2txt)) {
            $txt = $this->Translate($bft2txt[$bft]);
        } else {
            $txt = '';
        }
        return $txt;
    }

    // Temperatur als Heatindex umrechnen
    //   Quelle: https://de.wikipedia.org/wiki/Hitzeindex
    public function CalcHeatindex(float $temp, float $hum)
    {
        if ($temp < 27 || $hum < 40) {
            return $temp;
        }
        $c1 = -8.784695;
        $c2 = 1.61139411;
        $c3 = 2.338549;
        $c4 = -0.14611605;
        $c5 = -1.2308094 * pow(10, -2);
        $c6 = -1.6424828 * pow(10, -2);
        $c7 = 2.211732 * pow(10, -3);
        $c8 = 7.2546 * pow(10, -4);
        $c9 = -3.582 * pow(10, -6);

        $hi = $c1
            + $c2 * $temp
            + $c3 * $hum
            + $c4 * $temp * $hum
            + $c5 * pow($temp, 2)
            + $c6 * pow($hum, 2)
            + $c7 * pow($temp, 2) * $hum
            + $c8 * $temp * pow($hum, 2)
            + $c9 * pow($temp, 2) * pow($hum, 2);
        $hi = round($hi); // ohne NK
        return $hi;
    }

    private function ms2kmh($speed)
    {
        return is_numeric($speed) ? $speed * 3.6 : '';
    }

    public function GetRawData()
    {
        $data = $this->GetMultiBuffer('Data');
        $this->SendDebug(__FUNCTION__, 'size=' . strlen($data) . ', data=' . $data, 0);
        return $data;
    }
}
