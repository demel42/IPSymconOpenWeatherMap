<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class OpenWeatherData extends IPSModule
{
    use OpenWeather\StubsCommonLib;
    use OpenWeatherMapLocalLib;

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
        $this->RegisterPropertyFloat('longitude', 0);
        $this->RegisterPropertyFloat('latitude', 0);
        $this->RegisterPropertyFloat('altitude', 0);

        $lang = '';
        if (isset($_ENV['LANG']) && preg_match('/([a-z]*)_.*/', $_ENV['LANG'], $r)) {
            $lang = $r[1];
        }
        $this->RegisterPropertyString('lang', $lang);

        $this->RegisterPropertyBoolean('with_absolute_pressure', false);
        $this->RegisterPropertyBoolean('with_absolute_humidity', false);
        $this->RegisterPropertyBoolean('with_dewpoint', false);
        $this->RegisterPropertyBoolean('with_windchill', false);
        $this->RegisterPropertyBoolean('with_heatindex', false);
        $this->RegisterPropertyBoolean('with_windstrength', false);
        $this->RegisterPropertyBoolean('with_windstrength2text', false);
        $this->RegisterPropertyBoolean('with_windangle', true);
        $this->RegisterPropertyBoolean('with_winddirection', false);
        $this->RegisterPropertyBoolean('with_rain_probability', false);
        $this->RegisterPropertyBoolean('with_cloudiness', false);
        $this->RegisterPropertyBoolean('with_conditions', false);
        $this->RegisterPropertyBoolean('with_icon', false);
        $this->RegisterPropertyBoolean('with_condition_id', false);

        $this->RegisterPropertyInteger('hourly_forecast_count', 0);

        $this->RegisterPropertyInteger('update_interval', 15);

        $this->RegisterPropertyBoolean('with_current_condition', false);

        $this->RegisterPropertyBoolean('with_summary', false);
        $this->RegisterPropertyInteger('summary_script', 0);

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ApiCallStats', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->InstallVarProfiles(false);

        $this->SetMultiBuffer('Current', '');
        $this->SetMultiBuffer('HourlyForecast', '');

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

        if ($this->version2num($oldInfo) < $this->version2num('2.9')) {
            $r[] = $this->Translate('Adjusting the value range of various variable profiles');
        }

        return $r;
    }

    private function CompleteModuleUpdate(array $oldInfo, array $newInfo)
    {
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

        $propertyNames = ['summary_script'];
        $this->MaintainReferences($propertyNames);

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
        $with_windstrength = $this->ReadPropertyBoolean('with_windstrength');
        $with_windstrength2text = $this->ReadPropertyBoolean('with_windstrength2text');
        $with_windangle = $this->ReadPropertyBoolean('with_windangle');
        $with_winddirection = $this->ReadPropertyBoolean('with_winddirection');
        $with_rain_probability = $this->ReadPropertyBoolean('with_rain_probability');
        $with_cloudiness = $this->ReadPropertyBoolean('with_cloudiness');
        $with_conditions = $this->ReadPropertyBoolean('with_conditions');
        $with_icon = $this->ReadPropertyBoolean('with_icon');
        $with_condition_id = $this->ReadPropertyBoolean('with_condition_id');
        $hourly_forecast_count = $this->ReadPropertyInteger('hourly_forecast_count');
        $with_summary = $this->ReadPropertyBoolean('with_summary');
        $with_current_condition = $this->ReadPropertyBoolean('with_current_condition');

        $vpos = 0;
        $this->MaintainVariable('Temperature', $this->Translate('Temperature'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Temperatur', $vpos++, true);
        $this->MaintainVariable('Humidity', $this->Translate('Humidity'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Humidity', $vpos++, true);
        $this->MaintainVariable('AbsoluteHumidity', $this->Translate('absolute humidity'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.absHumidity', $vpos++, $with_absolute_humidity);
        $this->MaintainVariable('Dewpoint', $this->Translate('Dewpoint'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Dewpoint', $vpos++, $with_dewpoint);
        $this->MaintainVariable('Heatindex', $this->Translate('Heatindex'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Heatindex', $vpos++, $with_heatindex);
        $this->MaintainVariable('Windchill', $this->Translate('Windchill'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Temperatur', $vpos++, $with_windchill);
        $this->MaintainVariable('Pressure', $this->Translate('Air pressure'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Pressure', $vpos++, true);
        $this->MaintainVariable('AbsolutePressure', $this->Translate('absolute pressure'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Pressure', $vpos++, $with_absolute_pressure);
        $this->MaintainVariable('WindSpeed', $this->Translate('Windspeed'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.WindSpeed', $vpos++, true);
        $this->MaintainVariable('WindStrength', $this->Translate('Windstrength'), VARIABLETYPE_INTEGER, 'OpenWeatherMap.WindStrength', $vpos++, $with_windstrength);
        $this->MaintainVariable('WindStrengthText', $this->Translate('Windstrength'), VARIABLETYPE_STRING, '', $vpos++, $with_windstrength2text);
        $this->MaintainVariable('WindAngle', $this->Translate('Winddirection'), VARIABLETYPE_INTEGER, 'OpenWeatherMap.WindAngle', $vpos++, $with_windangle);
        $this->MaintainVariable('WindDirection', $this->Translate('Winddirection'), VARIABLETYPE_STRING, 'OpenWeatherMap.WindDirection', $vpos++, $with_winddirection);
        $this->MaintainVariable('Rain_3h', $this->Translate('Rainfall of last 3 hours'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Rainfall', $vpos++, true);
        $this->MaintainVariable('Snow_3h', $this->Translate('Snowfall of last 3 hours'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Snowfall', $vpos++, true);
        $this->MaintainVariable('Cloudiness', $this->Translate('Cloudiness'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Cloudiness', $vpos++, $with_cloudiness);
        $this->MaintainVariable('Conditions', $this->Translate('Conditions'), VARIABLETYPE_STRING, '', $vpos++, $with_conditions);
        $this->MaintainVariable('ConditionIcon', $this->Translate('Condition-icon'), VARIABLETYPE_STRING, '', $vpos++, $with_icon);
        $this->MaintainVariable('ConditionId', $this->Translate('Condition-id'), VARIABLETYPE_STRING, '', $vpos++, $with_condition_id);
        $this->MaintainVariable('LastMeasurement', $this->Translate('last measurement'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);
        $this->MaintainVariable('CurrentCondition', $this->Translate('Current weather condition'), VARIABLETYPE_STRING, '~HTMLBox', $vpos++, $with_current_condition);
        $this->MaintainVariable('WeatherSummary', $this->Translate('Summary of weather'), VARIABLETYPE_STRING, '~HTMLBox', $vpos++, $with_summary);

        for ($i = 0; $i < 40; $i++) {
            $vpos = 1000 + (100 * $i);
            $use = $i < $hourly_forecast_count;
            $s = ' #' . ($i + 1);
            $pre = 'HourlyForecast';
            $post = '_' . sprintf('%02d', $i);

            $this->MaintainVariable($pre . 'Begin' . $post, $this->Translate('Begin of forecast-period') . $s, VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, $use);
            $this->MaintainVariable($pre . 'TemperatureMin' . $post, $this->Translate('minimum temperature') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Temperatur', $vpos++, $use);
            $this->MaintainVariable($pre . 'TemperatureMax' . $post, $this->Translate('maximum temperature') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Temperatur', $vpos++, $use);
            $this->MaintainVariable($pre . 'Humidity' . $post, $this->Translate('Humidity') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Humidity', $vpos++, $use);
            $this->MaintainVariable($pre . 'Pressure' . $post, $this->Translate('Air pressure') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Pressure', $vpos++, $use);
            $this->MaintainVariable($pre . 'AbsolutePressure' . $post, $this->Translate('absolute pressure') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Pressure', $vpos++, $use && $with_absolute_pressure);
            $this->MaintainVariable($pre . 'WindSpeed' . $post, $this->Translate('Windspeed') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.WindSpeed', $vpos++, $use);
            $this->MaintainVariable($pre . 'WindStrength' . $post, $this->Translate('Windstrength') . $s, VARIABLETYPE_INTEGER, 'OpenWeatherMap.WindStrength', $vpos++, $use && $with_windstrength);
            $this->MaintainVariable($pre . 'WindStrengthText' . $post, $this->Translate('Windstrength') . $s, VARIABLETYPE_STRING, '', $vpos++, $use && $with_windstrength2text);
            $this->MaintainVariable($pre . 'WindAngle' . $post, $this->Translate('Winddirection') . $s, VARIABLETYPE_INTEGER, 'OpenWeatherMap.WindAngle', $vpos++, $use && $with_windangle);
            $this->MaintainVariable($pre . 'WindDirection' . $post, $this->Translate('Winddirection') . $s, VARIABLETYPE_STRING, 'OpenWeatherMap.WindDirection', $vpos++, $use && $with_winddirection);
            $this->MaintainVariable($pre . 'Rain_3h' . $post, $this->Translate('Rainfall') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Rainfall', $vpos++, $use);
            $this->MaintainVariable($pre . 'Snow_3h' . $post, $this->Translate('Snowfall') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Snowfall', $vpos++, $use);
            $this->MaintainVariable($pre . 'RainProbability' . $post, $this->Translate('Rain propability') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.RainProbability', $vpos++, $use && $with_rain_probability);
            $this->MaintainVariable($pre . 'Cloudiness' . $post, $this->Translate('Cloudiness') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Cloudiness', $vpos++, $use && $with_cloudiness);
            $this->MaintainVariable($pre . 'Conditions' . $post, $this->Translate('Conditions') . $s, VARIABLETYPE_STRING, '', $vpos++, $use && $with_conditions);
            $this->MaintainVariable($pre . 'ConditionIcon' . $post, $this->Translate('Condition-icon') . $s, VARIABLETYPE_STRING, '', $vpos++, $use && $with_icon);
            $this->MaintainVariable($pre . 'ConditionId' . $post, $this->Translate('Condition-id') . $s, VARIABLETYPE_STRING, '', $vpos++, $use && $with_condition_id);
        }

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainTimer('UpdateData', 0);
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $apiLimits = [
            [
                'value' => 60,
                'unit'  => 'minute',
            ],
            [
                'value' => 1000000,
                'unit'  => 'month',
            ],
        ];

        $apiNotes = '';

        $this->ApiCallsSetInfo($apiLimits, $apiNotes);

        $this->MaintainStatus(IS_ACTIVE);

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
                    'type'    => 'NumberSpinner',
                    'digits'  => 5,
                    'name'    => 'latitude',
                    'caption' => 'Latitude',
                    'suffix'  => '°'
                ],
                [
                    'type'    => 'NumberSpinner',
                    'digits'  => 5,
                    'name'    => 'longitude',
                    'caption' => 'Longitude',
                    'suffix'  => '°'
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
                    'type'    => 'CheckBox',
                    'name'    => 'with_summary',
                    'caption' => ' ... html-box with summary of weather'
                ],
                [
                    'type'    => 'Label',
                    'caption' => 'script for alternate weather summary'
                ],
                [
                    'type'    => 'SelectScript',
                    'name'    => 'summary_script',
                    'caption' => 'script'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_current_condition',
                    'caption' => ' ... html-box with current weather condition'
                ],
                [
                    'type'    => 'Label',
                    'caption' => '3-hour forecast (max 5 days every 3rd hour = 40)'
                ],
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'hourly_forecast_count',
                    'caption' => 'Count'
                ],
                [
                    'type'    => 'Label',
                    'caption' => ' ... attention: decreasing the number deletes the unused variables!'
                ],
            ],
            'caption' => 'optional weather data'
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

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => [
                $this->GetInstallVarProfilesFormItem(),
                $this->GetApiCallStatsFormItem(),
            ],
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }

        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        switch ($ident) {
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
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

        $this->UpdateCurrent();
        $this->UpdateHourlyForecast();

        $with_summary = $this->ReadPropertyBoolean('with_summary');
        if ($with_summary) {
            $summary_script = $this->ReadPropertyInteger('summary_script');
            if (IPS_ScriptExists($summary_script)) {
                $html = IPS_RunScriptWaitEx($summary_script, ['InstanceID' => $this->InstanceID]);
            } else {
                $html = $this->Build_WeatherSummary();
            }
            $this->SetValue('WeatherSummary', $html);
        }

        $with_current_condition = $this->ReadPropertyBoolean('with_current_condition');
        if ($with_current_condition) {
            $html = $this->Build_CurrentCondition();
            $this->SetValue('CurrentCondition', $html);
        }

        $this->SendDebug(__FUNCTION__, $this->PrintTimer('UpdateData'), 0);
    }

    public function UpdateCurrent()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $lat = $this->ReadPropertyFloat('latitude');
        $lng = $this->ReadPropertyFloat('longitude');
        if ($lat == 0 || $lng == 0) {
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

        $jdata = $this->do_HttpRequest('data/2.5/weather', $args);
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        if ($jdata == '') {
            $this->SetMultiBuffer('Current', '');
            return;
        }

        if (isset($jdata['weather'])) {
            $weather = $jdata['weather'];
            $this->SendDebug(__FUNCTION__, 'weather=' . print_r($weather, true), 0);
        }

        $with_absolute_pressure = $this->ReadPropertyBoolean('with_absolute_pressure');
        $with_absolute_humidity = $this->ReadPropertyBoolean('with_absolute_humidity');
        $with_dewpoint = $this->ReadPropertyBoolean('with_dewpoint');
        $with_windchill = $this->ReadPropertyBoolean('with_windchill');
        $with_heatindex = $this->ReadPropertyBoolean('with_heatindex');
        $with_windstrength = $this->ReadPropertyBoolean('with_windstrength');
        $with_windstrength2text = $this->ReadPropertyBoolean('with_windstrength2text');
        $with_windangle = $this->ReadPropertyBoolean('with_windangle');
        $with_winddirection = $this->ReadPropertyBoolean('with_winddirection');
        $with_cloudiness = $this->ReadPropertyBoolean('with_cloudiness');
        $with_conditions = $this->ReadPropertyBoolean('with_conditions');
        $with_icon = $this->ReadPropertyBoolean('with_icon');
        $with_condition_id = $this->ReadPropertyBoolean('with_condition_id');

        $timestamp = $this->GetArrayElem($jdata, 'dt', 0);
        $temperature = $this->GetArrayElem($jdata, 'main.temp', 0);
        $pressure = $this->GetArrayElem($jdata, 'main.pressure', 0);
        $humidity = $this->GetArrayElem($jdata, 'main.humidity', 0);

        $visibility = $this->GetArrayElem($jdata, 'visibility', 0);

        $wind_speed = $this->GetArrayElem($jdata, 'wind.speed', 0);
        $wind_speed = (int) $this->ms2kmh($wind_speed);
        $wind_deg = $this->GetArrayElem($jdata, 'wind.deg', 0);

        $rain_3h = $this->GetArrayElem($jdata, 'rain.3h', 0);
        $snow_3h = $this->GetArrayElem($jdata, 'snow.3h', 0);

        $clouds = $this->GetArrayElem($jdata, 'clouds.all', 0);

        $conditions = '';
        $icon = '';
        $id = '';
        $weather = $this->GetArrayElem($jdata, 'weather', '');
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

        $this->SetValue('WindSpeed', $wind_speed);
        if ($with_windangle) {
            $this->SetValue('WindAngle', $wind_deg);
        }
        if ($with_windstrength) {
            $windstrength = $this->ConvertWindSpeed2Strength($wind_speed);
            $this->SetValue('WindStrength', $windstrength);
        }
        if ($with_windstrength2text) {
            $bft = $this->ConvertWindSpeed2Strength($wind_speed);
            $windstrength = $this->ConvertWindStrength2Text($bft);
            $this->SetValue('WindStrengthText', $windstrength);
        }

        if ($with_winddirection) {
            $dir = $this->ConvertWindDirection2Text((int) $wind_deg) . ' (' . $wind_deg . '°)';
            $this->SetValue('WindDirection', $dir);
        }

        $this->SetValue('Rain_3h', $rain_3h);

        $this->SetValue('Snow_3h', $snow_3h);

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

        if ($with_dewpoint) {
            $dewpoint = $this->CalcDewpoint($temperature, $humidity);
            $this->SetValue('Dewpoint', $dewpoint);
        }

        if ($with_windchill) {
            $windchill = $this->CalcWindchill($temperature, $wind_speed);
            $this->SetValue('Windchill', $windchill);
        }

        if ($with_heatindex) {
            $heatindex = $this->CalcHeatindex($temperature, $humidity);
            $this->SetValue('Heatindex', $heatindex);
        }

        $this->SetValue('LastMeasurement', $timestamp);

        $this->SetMultiBuffer('Current', json_encode($jdata));

        $this->MaintainStatus(IS_ACTIVE);
    }

    public function UpdateHourlyForecast()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $hourly_forecast_count = $this->ReadPropertyInteger('hourly_forecast_count');
        if (!$hourly_forecast_count) {
            return;
        }

        $lat = $this->ReadPropertyFloat('latitude');
        $lng = $this->ReadPropertyFloat('longitude');
        if ($lat == 0 || $lng == 0) {
            $id = IPS_GetInstanceListByModuleID('{45E97A63-F870-408A-B259-2933F7EABF74}')[0];
            $loc = json_decode(IPS_GetProperty($id, 'Location'), true);
            $lat = $loc['latitude'];
            $lng = $loc['longitude'];
        }

        $args = [
            'lat'   => number_format($lat, 6, '.', ''),
            'lon'   => number_format($lng, 6, '.', ''),
            'cnt'   => $hourly_forecast_count,
            'units' => 'metric'
        ];

        $lang = $this->ReadPropertyString('lang');
        if ($lang != '') {
            $args['lang'] = $lang;
        }

        $jdata = $this->do_HttpRequest('data/2.5/forecast', $args);
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        if ($jdata == '') {
            $this->SetMultiBuffer('HourlyForecast', '');
            return;
        }

        if (isset($jdata['list'])) {
            $list = $jdata['list'];
            $this->SendDebug(__FUNCTION__, 'list=' . print_r($list, true), 0);
        }

        $with_absolute_pressure = $this->ReadPropertyBoolean('with_absolute_pressure');
        $with_windstrength = $this->ReadPropertyBoolean('with_windstrength');
        $with_windstrength2text = $this->ReadPropertyBoolean('with_windstrength2text');
        $with_windangle = $this->ReadPropertyBoolean('with_windangle');
        $with_winddirection = $this->ReadPropertyBoolean('with_winddirection');
        $with_rain_probability = $this->ReadPropertyBoolean('with_rain_probability');
        $with_cloudiness = $this->ReadPropertyBoolean('with_cloudiness');
        $with_conditions = $this->ReadPropertyBoolean('with_conditions');
        $with_icon = $this->ReadPropertyBoolean('with_icon');
        $with_condition_id = $this->ReadPropertyBoolean('with_condition_id');

        for ($i = 0; $i < 40; $i++) {
            if ($i == $hourly_forecast_count) {
                break;
            }

            $pre = 'HourlyForecast';
            $post = '_' . sprintf('%02d', $i);

            $ent = isset($list[$i]) ? $list[$i] : '';

            $timestamp = $this->GetArrayElem($ent, 'dt', 0);
            $temperature_min = $this->GetArrayElem($ent, 'main.temp_min', 0);
            $temperature_max = $this->GetArrayElem($ent, 'main.temp_max', 0);
            $pressure = $this->GetArrayElem($ent, 'main.grnd_level', 0);
            $abs_pressure = $this->GetArrayElem($ent, 'main.sea_level', 0);
            $humidity = $this->GetArrayElem($ent, 'main.humidity', 0);

            $visibility = $this->GetArrayElem($ent, 'visibility', 0);

            $wind_speed = $this->GetArrayElem($ent, 'wind.speed', 0);
            $wind_speed = (int) $this->ms2kmh($wind_speed);
            $wind_deg = $this->GetArrayElem($ent, 'wind.deg', 0);

            $rain_3h = $this->GetArrayElem($ent, 'rain.3h', 0);
            $snow_3h = $this->GetArrayElem($ent, 'snow.3h', 0);
            $pop = $this->GetArrayElem($ent, 'pop', 0);

            $clouds = $this->GetArrayElem($ent, 'clouds.all', 0);
            $conditions = $this->GetArrayElem($ent, 'weather.0.description', '');

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

            $this->SetValue($pre . 'Begin' . $post, $timestamp);

            $this->SetValue($pre . 'TemperatureMin' . $post, $temperature_min);
            $this->SetValue($pre . 'TemperatureMax' . $post, $temperature_max);

            $this->SetValue($pre . 'Pressure' . $post, $pressure);
            if ($with_absolute_pressure) {
                $this->SetValue($pre . 'AbsolutePressure' . $post, $abs_pressure);
            }

            $this->SetValue($pre . 'Humidity' . $post, $humidity);

            $this->SetValue($pre . 'WindSpeed' . $post, $wind_speed);
            if ($with_windangle) {
                $this->SetValue($pre . 'WindAngle' . $post, $wind_deg);
            }
            if ($with_windstrength) {
                $windstrength = $this->ConvertWindSpeed2Strength($wind_speed);
                $this->SetValue($pre . 'WindStrength' . $post, $windstrength);
            }
            if ($with_windstrength2text) {
                $bft = $this->ConvertWindSpeed2Strength($wind_speed);
                $windstrength = $this->ConvertWindStrength2Text($bft);
                $this->SetValue($pre . 'WindStrengthText' . $post, $windstrength);
            }

            if ($with_winddirection) {
                $dir = $this->ConvertWindDirection2Text((int) $wind_deg) . ' (' . $wind_deg . '°)';
                $this->SetValue($pre . 'WindDirection' . $post, $dir);
            }

            $this->SetValue($pre . 'Rain_3h' . $post, $rain_3h);

            $this->SetValue($pre . 'Snow_3h' . $post, $snow_3h);

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

        $this->SetMultiBuffer('HourlyForecast', json_encode($jdata));

        $this->MaintainStatus(IS_ACTIVE);
    }

    private function Build_WeatherSummary()
    {
        $img_url = 'http://openweathermap.org/img/w/';

        $hourly_forecast_count = $this->ReadPropertyInteger('hourly_forecast_count');

        $temperature = $this->GetValue('Temperature');
        $humidity = $this->GetValue('Humidity');
        $wind_speed = $this->GetValue('WindSpeed');
        $rain_3h = $this->GetValue('Rain_3h');
        $clouds = $this->GetValue('Cloudiness');
        $icon = $this->GetValue('ConditionIcon');

        $wind_speed = round($wind_speed);

        $html = '
<table>
  <tr>

    <td align="center" valign="top" style="width: 140px; padding: 0px; padding-left: 20px;">
      ' . $this->Translate('current') . '<br>';
        if ($icon != '') {
            $html .= '
      <img src="' . $img_url . $icon . '.png" style="float: left; padding-left: 17px;">';
        }
        $html .= '
      <div style="float: right; font-size: 13px; padding-right: 17px;">
        ' . round($temperature) . '°C<br>
        ' . round($humidity) . '%<br>
      </div>
      <div style="clear: both; font-size: 11px; padding: 0px">
        <table>
          <tr>
            <td>' . $this->Translate('Ø Wind') . '</td>
            <td>' . $wind_speed . '&nbsp;km/h</td>
          </tr>
          <tr>
            <td>' . $this->Translate('Rain 3h') . '</td>
            <td>' . $rain_3h . '&nbsp;mm</td>
          </tr>
          <tr>
            <td>' . $this->Translate('Cloudiness') . '</td>
            <td>' . $clouds . '&nbsp;%</td>
          </tr>
        </table>
      </div>
    </td>
';

        for ($i = 0; $i < $hourly_forecast_count; $i++) {
            $pre = 'HourlyForecast';
            $post = '_' . sprintf('%02d', $i);

            $timestamp = $this->GetValue($pre . 'Begin' . $post);
            $temperature_min = $this->GetValue($pre . 'TemperatureMin' . $post);
            $temperature_max = $this->GetValue($pre . 'TemperatureMax' . $post);
            $wind_speed = $this->GetValue($pre . 'WindSpeed' . $post);
            $rain_3h = $this->GetValue($pre . 'Rain_3h' . $post);
            $clouds = $this->GetValue($pre . 'Cloudiness' . $post);
            $icon = $this->GetValue($pre . 'ConditionIcon' . $post);

            $wind_speed = round($wind_speed);
            $is_today = date('d.m.Y', $timestamp) == date('d.m.Y', time());
            $weekDay = $is_today ? 'today' : date('l', $timestamp);
            $time = date('H:i', $timestamp);

            $html .= '
    <td align="center" valign="top" style="width: 140px; padding: 0px; padding-left: 20px;">
      ' . $this->Translate($weekDay) . ' <font size="2">' . $time . '</font><br>';
            if ($icon != '') {
                $html .= '
      <img src="' . $img_url . $icon . '.png" style="float: left; padding-left: 17px;">';
            }
            $html .= '
      <div style="float: right; font-size: 13px; padding-right: 17px;">
        ' . round($temperature_min) . '°C<br>
        ' . round($temperature_max) . '°C<br>
      </div>
      <div style="clear: both; font-size: 11px; padding: 0px">
        <table>
          <tr>
            <td>' . $this->Translate('Ø Wind') . '</td>
            <td>' . $wind_speed . '&nbsp;km/h</td>
          </tr>
          <tr>
            <td>' . $this->Translate('Rain 3h') . '</td>
            <td>' . $rain_3h . '&nbsp;mm</td>
          </tr>
          <tr>
            <td>' . $this->Translate('Cloudiness') . '</td>
            <td>' . $clouds . '&nbsp;%</td>
          </tr>
        </table>
      </div>
    </td>
';
        }

        $html .= '
  </tr>
</table>';

        return $html;
    }

    private function Build_CurrentCondition()
    {
        $img_url = 'http://openweathermap.org/img/w/';

        $icon = $this->GetValue('ConditionIcon');
        $html = '<img src="' . $img_url . $icon . '.png">';
        return $html;
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

        $this->ApiCallsCollect($url, $err, $statuscode);

        return $jdata;
    }

    // Taupunkt berechnen
    //   Quelle: https://www.wetterochs.de/wetter/feuchte.html
    public function CalcDewpoint(float $temp, float $humidity)
    {
        if ($temp > 0) {
            $k2 = 17.62;
            $k3 = 243.12;
        } else {
            $k2 = 22.46;
            $k3 = 272.62;
        }
        $dewpoint = $k3 * (($k2 * $temp) / ($k3 + $temp) + log($humidity / 100));
        $dewpoint = $dewpoint / (($k2 * $k3) / ($k3 + $temp) - log($humidity / 100));
        $dewpoint = round($dewpoint, 0);
        return $dewpoint;
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

    // Temperautur in Windchill umrechnen
    //   Quelle: https://de.wikipedia.org/wiki/Windchill
    public function CalcWindchill(float $temp, int $speed)
    {
        if ($speed >= 5.0) {
            $wct = 13.12 + (0.6215 * $temp) - (11.37 * pow($speed, 0.16)) + (0.3965 * $temp * pow($speed, 0.16));
            $wct = round($wct * 10) / 10; // auf eine NK runden
        } else {
            $wct = $temp;
        }
        return $wct;
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

    public function GetRawData(string $name)
    {
        $data = $this->GetMultiBuffer($name);
        $this->SendDebug(__FUNCTION__, 'name=' . $name . ', size=' . strlen((string) $data) . ', data=' . $data, 0);
        return $data;
    }
}
