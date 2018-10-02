<?php

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen

if (!defined('vtBoolean')) {
    define('vtBoolean', 0);
    define('vtInteger', 1);
    define('vtFloat', 2);
    define('vtString', 3);
    define('vtArray', 8);
    define('vtObject', 9);
}

class OpenWeatherData extends IPSModule
{
    use OpenWeatherMapCommon;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('appid', '');
        $this->RegisterPropertyFloat('longitude', 0);
        $this->RegisterPropertyFloat('latitude', 0);
        $this->RegisterPropertyFloat('altitude', 0);

        $this->RegisterPropertyBoolean('with_absolute_pressure', false);
        $this->RegisterPropertyBoolean('with_absolute_humidity', false);
        $this->RegisterPropertyBoolean('with_dewpoint', false);
        $this->RegisterPropertyBoolean('with_windchill', false);
        $this->RegisterPropertyBoolean('with_heatindex', false);
        $this->RegisterPropertyBoolean('with_windstrength', false);
        $this->RegisterPropertyBoolean('with_windstrength2text', false);
        $this->RegisterPropertyBoolean('with_windangle', false);
        $this->RegisterPropertyBoolean('with_winddirection', true);
        $this->RegisterPropertyBoolean('with_cloudiness', false);
        $this->RegisterPropertyBoolean('with_conditions', false);

        $this->RegisterPropertyInteger('hourly_forecast_count', 0);

        $this->RegisterPropertyInteger('update_interval', 5);

        $this->CreateVarProfile('OpenWeatherMap.Temperatur', vtFloat, ' °C', -10, 30, 0, 1, 'Temperature');
        $this->CreateVarProfile('OpenWeatherMap.Humidity', vtFloat, ' %', 0, 0, 0, 0, 'Drops');
        $this->CreateVarProfile('OpenWeatherMap.absHumidity', vtFloat, ' g/m³', 10, 100, 0, 0, 'Drops');
        $this->CreateVarProfile('OpenWeatherMap.Dewpoint', vtFloat, ' °C', 0, 30, 0, 0, 'Drops');
        $this->CreateVarProfile('OpenWeatherMap.Heatindex', vtFloat, ' °C', 0, 100, 0, 0, 'Temperature');
        $this->CreateVarProfile('OpenWeatherMap.Pressure', vtFloat, ' mbar', 500, 1200, 0, 0, 'Gauge');
        $this->CreateVarProfile('OpenWeatherMap.WindSpeed', vtFloat, ' km/h', 0, 100, 0, 1, 'WindSpeed');
        $this->CreateVarProfile('OpenWeatherMap.WindStrength', vtInteger, ' bft', 0, 13, 0, 0, 'WindSpeed');
        $this->CreateVarProfile('OpenWeatherMap.WindAngle', vtInteger, ' °', 0, 360, 0, 0, 'WindDirection');
        $this->CreateVarProfile('OpenWeatherMap.WindDirection', vtString, '', 0, 0, 0, 0, 'WindDirection');
        $this->CreateVarProfile('OpenWeatherMap.Rainfall', vtFloat, ' mm', 0, 60, 0, 1, 'Rainfall');
        $this->CreateVarProfile('OpenWeatherMap.Snowfall', vtFloat, ' mm', 0, 60, 0, 1, 'Snow');
        $this->CreateVarProfile('OpenWeatherMap.Cloudiness', vtFloat, ' %', 0, 0, 0, 0, 'Cloud');

        $this->RegisterTimer('UpdateData', 0, 'OpenWeatherData_UpdateData(' . $this->InstanceID . ');');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

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
        $hourly_forecast_count = $this->ReadPropertyInteger('hourly_forecast_count');

        $vpos = 0;
        $this->MaintainVariable('Temperature', $this->Translate('Temperature'), vtFloat, 'OpenWeatherMap.Temperatur', $vpos++, true);
        $this->MaintainVariable('Humidity', $this->Translate('Humidity'), vtFloat, 'OpenWeatherMap.Humidity', $vpos++, true);
        $this->MaintainVariable('AbsoluteHumidity', $this->Translate('absolute humidity'), vtFloat, 'OpenWeatherMap.absHumidity', $vpos++, $with_absolute_humidity);
        $this->MaintainVariable('Dewpoint', $this->Translate('Dewpoint'), vtFloat, 'OpenWeatherMap.Dewpoint', $vpos++, $with_dewpoint);
        $this->MaintainVariable('Heatindex', $this->Translate('Heatindex'), vtFloat, 'OpenWeatherMap.Heatindex', $vpos++, $with_heatindex);
        $this->MaintainVariable('Windchill', $this->Translate('Windchill'), vtFloat, 'OpenWeatherMap.Temperatur', $vpos++, $with_windchill);
        $this->MaintainVariable('Pressure', $this->Translate('Air pressure'), vtFloat, 'OpenWeatherMap.Pressure', $vpos++, true);
        $this->MaintainVariable('AbsolutePressure', $this->Translate('absolute pressure'), vtFloat, 'OpenWeatherMap.Pressure', $vpos++, $with_absolute_pressure);
        $this->MaintainVariable('WindSpeed', $this->Translate('Windspeed'), vtFloat, 'OpenWeatherMap.WindSpeed', $vpos++, true);
        $this->MaintainVariable('WindStrength', $this->Translate('Windstrength'), vtInteger, 'OpenWeatherMap.WindStrength', $vpos++, $with_windstrength);
        $this->MaintainVariable('WindStrengthText', $this->Translate('Windstrength'), vtString, '', $vpos++, $with_windstrength2text);
        $this->MaintainVariable('WindAngle', $this->Translate('Winddirection'), vtInteger, 'OpenWeatherMap.WindAngle', $vpos++, $with_windangle);
        $this->MaintainVariable('WindDirection', $this->Translate('Winddirection'), vtString, 'OpenWeatherMap.WindDirection', $vpos++, $with_winddirection);
        $this->MaintainVariable('Rain_3h', $this->Translate('Rainfall of last 3 hours'), vtFloat, 'OpenWeatherMap.Rainfall', $vpos++, true);
        $this->MaintainVariable('Snow_3h', $this->Translate('Snowfall of last 3 hours'), vtFloat, 'OpenWeatherMap.Snowfall', $vpos++, true);
        $this->MaintainVariable('Cloudiness', $this->Translate('Cloudiness'), vtFloat, 'OpenWeatherMap.Cloudiness', $vpos++, $with_cloudiness);
        $this->MaintainVariable('Conditions', $this->Translate('Conditions'), vtString, '', $vpos++, $with_conditions);
        $this->MaintainVariable('LastMeasurement', $this->Translate('last measurement'), vtInteger, '~UnixTimestamp', $vpos++, true);

        for ($i = 0; $i < 40; $i++) {
            $vpos = 1000 + (100 * $i);
            $use = $i < $hourly_forecast_count;
            $s = ' - #' . ($i + 1);
            $pre = 'HourlyForecast';
            $post = '_' . sprintf('%02d', $i);

            $this->MaintainVariable($pre . 'Begin' . $post, $this->Translate('Begin of forecast-period') . $s, vtInteger, '~UnixTimestamp', $vpos++, $use);
            $this->MaintainVariable($pre . 'TemperatureMin' . $post, $this->Translate('minimum temperature') . $s, vtFloat, 'OpenWeatherMap.Temperatur', $vpos++, $use);
            $this->MaintainVariable($pre . 'TemperatureMax' . $post, $this->Translate('maximum temperature') . $s, vtFloat, 'OpenWeatherMap.Temperatur', $vpos++, $use);
            $this->MaintainVariable($pre . 'Humidity' . $post, $this->Translate('Humidity') . $s, vtFloat, 'OpenWeatherMap.Humidity', $vpos++, $use);
            $this->MaintainVariable($pre . 'Pressure' . $post, $this->Translate('Air pressure') . $s, vtFloat, 'OpenWeatherMap.Pressure', $vpos++, $use);
            $this->MaintainVariable($pre . 'AbsolutePressure' . $post, $this->Translate('absolute pressure') . $s, vtFloat, 'OpenWeatherMap.Pressure', $vpos++, $use && $with_absolute_pressure);
            $this->MaintainVariable($pre . 'WindSpeed' . $post, $this->Translate('Windspeed') . $s, vtFloat, 'OpenWeatherMap.WindSpeed', $vpos++, $use);
            $this->MaintainVariable($pre . 'WindStrength' . $post, $this->Translate('Windstrength') . $s, vtInteger, 'OpenWeatherMap.WindStrength', $vpos++, $use && $with_windstrength);
            $this->MaintainVariable($pre . 'WindStrengthText' . $post, $this->Translate('Windstrength') . $s, vtString, '', $vpos++, $use && $with_windstrength2text);
            $this->MaintainVariable($pre . 'WindAngle' . $post, $this->Translate('Winddirection') . $s, vtInteger, 'OpenWeatherMap.WindAngle', $vpos++, $use && $with_windangle);
            $this->MaintainVariable($pre . 'WindDirection' . $post, $this->Translate('Winddirection') . $s, vtString, 'OpenWeatherMap.WindDirection', $vpos++, $use && $with_winddirection);
            $this->MaintainVariable($pre . 'Rain_3h' . $post, $this->Translate('Rainfall') . $s, vtFloat, 'OpenWeatherMap.Rainfall', $vpos++, $use);
            $this->MaintainVariable($pre . 'Snow_3h' . $post, $this->Translate('Snowfall') . $s, vtFloat, 'OpenWeatherMap.Snowfall', $vpos++, $use);
            $this->MaintainVariable($pre . 'Cloudiness' . $post, $this->Translate('Cloudiness') . $s, vtFloat, 'OpenWeatherMap.Cloudiness', $vpos++, $use && $with_cloudiness);
            $this->MaintainVariable($pre . 'Conditions' . $post, $this->Translate('Conditions') . $s, vtString, '', $vpos++, $use && $with_conditions);
        }

        $appid = $this->ReadPropertyString('appid');
        if ($appid == '') {
            $this->SetStatus(201);
        } else {
            $this->SetStatus(102);
        }

        $this->SetUpdateInterval();
    }

    public function GetConfigurationForm()
    {
        $formElements = [];
        $formElements[] = ['type' => 'Label', 'label' => 'OpenWeatherMap'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'appid', 'caption' => 'API-Key'];

        $formElements[] = ['type' => 'Label', 'label' => 'station data - if position is not set, Modue \'Location\' is used'];
        $formElements[] = ['type' => 'NumberSpinner', 'digits' => 5, 'name' => 'longitude', 'caption' => 'Longitude'];
        $formElements[] = ['type' => 'NumberSpinner', 'digits' => 5, 'name' => 'latitude', 'caption' => 'Latitude'];
        $formElements[] = ['type' => 'NumberSpinner', 'name' => 'altitude', 'caption' => 'Altitude'];

        $formElements[] = ['type' => 'Label', 'label' => 'optional weather data'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_absolute_pressure', 'caption' => ' ... absolute Pressure'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_absolute_humidity', 'caption' => ' ... absolute Humidity'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_dewpoint', 'caption' => ' ... Dewpoint'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_heatindex', 'caption' => ' ... Heatindex'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_windchill', 'caption' => ' ... Windchill'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_windstrength', 'caption' => ' ... Windstrength'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_windstrength2text', 'caption' => ' ... Windstrength as text'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_windangle', 'caption' => ' ... Winddirection in degrees'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_winddirection', 'caption' => ' ... Winddirection with label'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_cloudiness', 'caption' => ' ... Cloudiness'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_conditions', 'caption' => ' ... Conditions'];

        $formElements[] = ['type' => 'Label', 'label' => '3-hour forecast (max 5 days every 3rd hour = 40)'];
        $formElements[] = ['type' => 'Label', 'label' => 'Attention: decreasing the number deletes the unused variables!'];
        $formElements[] = ['type' => 'NumberSpinner', 'name' => 'hourly_forecast_count', 'caption' => 'Count'];

        $formElements[] = ['type' => 'Label', 'label' => 'Update weatherdata every X minutes'];
        $formElements[] = ['type' => 'IntervalBox', 'name' => 'update_interval', 'caption' => 'Minutes'];

        $formActions = [];
        $formActions[] = ['type' => 'Button', 'label' => 'Update weatherdata', 'onClick' => 'OpenWeatherData_UpdateData($id);'];
        $formActions[] = ['type' => 'Label', 'label' => '____________________________________________________________________________________________________'];
        $formActions[] = [
                            'type'    => 'Button',
                            'caption' => 'Module description',
                            'onClick' => 'echo "https://github.com/demel42/IPSymconOpenWeatherMap/blob/master/README.md";'
                        ];

        $formStatus = [];
        $formStatus[] = ['code' => '101', 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => '102', 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => '104', 'icon' => 'inactive', 'caption' => 'Instance is inactive'];
        $formStatus[] = ['code' => '201', 'icon' => 'error', 'caption' => 'Instance is inactive (invalid configuration)'];
        $formStatus[] = ['code' => '202', 'icon' => 'error', 'caption' => 'Instance is inactive (server error)'];
        $formStatus[] = ['code' => '203', 'icon' => 'error', 'caption' => 'Instance is inactive (http error)'];
        $formStatus[] = ['code' => '204', 'icon' => 'error', 'caption' => 'Instance is inactive (invalid data)'];
        return json_encode(['elements' => $formElements, 'actions' => $formActions, 'status' => $formStatus]);
    }

    protected function SetUpdateInterval()
    {
        $min = $this->ReadPropertyInteger('update_interval');
        $msec = $min > 0 ? $min * 1000 * 60 : 0;
        $this->SetTimerInterval('UpdateData', $msec);
    }

    public function UpdateData()
    {
        $this->UpdateCurrent();
        $this->UpdateHourlyForecast();
    }

    public function UpdateCurrent()
    {
        $lat = $this->ReadPropertyFloat('latitude');
        $lng = $this->ReadPropertyFloat('longitude');
        if ($lat == 0 || $lng == 0) {
            $id = IPS_GetObjectIDByName('Location', 0);
            $loc = json_decode(IPS_GetProperty($id, 'Location'), true);
            $lat = $loc['latitude'];
            $lng = $loc['longitude'];
        }

        $args = [
                'lat'   => number_format($lat, 6, '.', ''),
                'lon'   => number_format($lng, 6, '.', ''),
                'units' => 'metric'
            ];

		if (preg_match('/([a-z]*)_.*/', $_ENV['LANG'], $r)) {
			$args['lang'] = $r[1];
		}

        $jdata = $this->do_HttpRequest('data/2.5/weather', $args);
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);

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

        $timestamp = $this->GetArrayElem($jdata, 'dt', 0);
        $temperature = $this->GetArrayElem($jdata, 'main.temp', 0);
        $pressure = $this->GetArrayElem($jdata, 'main.pressure', 0);
        $humidity = $this->GetArrayElem($jdata, 'main.humidity', 0);

        $visibility = $this->GetArrayElem($jdata, 'visibility', 0);

        $wind_speed = $this->GetArrayElem($jdata, 'wind.speed', 0);
        $wind_deg = $this->GetArrayElem($jdata, 'wind.deg', 0);

        $rain_3h = $this->GetArrayElem($jdata, 'rain.3h', 0);
        $snow_3h = $this->GetArrayElem($jdata, 'snow.3h', 0);

        $clouds = $this->GetArrayElem($jdata, 'clouds.all', 0);

        $conditions = '';
        $weather = $this->GetArrayElem($jdata, 'weather', '');
        if ($weather != '') {
            foreach ($weather as $w) {
                $description = $this->GetArrayElem($w, 'description', '');
                if ($description != '') {
                    $conditions .= ($conditions != '' ? ', ' : '') . $this->Translate($description);
                }
            }
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
            $dir = $this->ConvertWindDirection2Text($wind_deg) . ' (' . $wind_deg . '°)';
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

        $this->SetStatus(102);
    }

    public function UpdateHourlyForecast()
    {
        $hourly_forecast_count = $this->ReadPropertyInteger('hourly_forecast_count');
        if (!$hourly_forecast_count) {
            return;
        }

        $lat = $this->ReadPropertyFloat('latitude');
        $lng = $this->ReadPropertyFloat('longitude');
        if ($lat == 0 || $lng == 0) {
            $id = IPS_GetObjectIDByName('Location', 0);
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

		if (preg_match('/([a-z]*)_.*/', $_ENV['LANG'], $r)) {
			$args['lang'] = $r[1];
		}

        $jdata = $this->do_HttpRequest('data/2.5/forecast', $args);
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);

        if (isset($jdata['list'])) {
            $list = $jdata['list'];
            $this->SendDebug(__FUNCTION__, 'list=' . print_r($list, true), 0);
        }

        $with_absolute_pressure = $this->ReadPropertyBoolean('with_absolute_pressure');
        $with_windstrength = $this->ReadPropertyBoolean('with_windstrength');
        $with_windstrength2text = $this->ReadPropertyBoolean('with_windstrength2text');
        $with_windangle = $this->ReadPropertyBoolean('with_windangle');
        $with_winddirection = $this->ReadPropertyBoolean('with_winddirection');
        $with_cloudiness = $this->ReadPropertyBoolean('with_cloudiness');
        $with_conditions = $this->ReadPropertyBoolean('with_conditions');

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
            $wind_deg = $this->GetArrayElem($ent, 'wind.deg', 0);

            $rain_3h = $this->GetArrayElem($ent, 'rain.3h', 0);
            $snow_3h = $this->GetArrayElem($ent, 'snow.3h', 0);
            $clouds = $this->GetArrayElem($ent, 'clouds.all', 0);
            $conditions = $this->GetArrayElem($ent, 'weather.0.description', '');

            $conditions = '';
            $weather = $this->GetArrayElem($ent, 'weather', '');
            if ($weather != '') {
                foreach ($weather as $w) {
                    $description = $this->GetArrayElem($w, 'description', '');
                    if ($description != '') {
                        $conditions .= ($conditions != '' ? ', ' : '') . $this->Translate($description);
                    }
                }
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
                $dir = $this->ConvertWindDirection2Text($wind_deg) . ' (' . $wind_deg . '°)';
                $this->SetValue($pre . 'WindDirection' . $post, $dir);
            }

            $this->SetValue($pre . 'Rain_3h' . $post, $rain_3h);

            $this->SetValue($pre . 'Snow_3h' . $post, $snow_3h);

            if ($with_cloudiness) {
                $this->SetValue($pre . 'Cloudiness' . $post, $clouds);
            }

            if ($with_conditions) {
                $this->SetValue($pre . 'Conditions' . $post, $conditions);
            }
        }

        $this->SetStatus(102);
    }

    private function do_HttpRequest($cmd, $args)
    {
        $appid = $this->ReadPropertyString('appid');

        $url = 'https://api.openweathermap.org/' . $cmd . '?appid=' . $appid;

        if ($args != '') {
            foreach ($args as $arg => $value) {
                $url .= '&' . $arg;
                if ($value != '') {
                    $url .= '=' . rawurlencode($value);
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
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        $err = '';
        $jdata = '';
        if ($httpcode != 200) {
            if ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = 202;
                $err = "got http-code $httpcode (server error)";
            } else {
                $err = "got http-code $httpcode";
                $statuscode = 203;
            }
        } elseif ($cdata == '') {
            $statuscode = 204;
            $err = 'no data';
        } else {
            $jdata = json_decode($cdata, true);
            if ($jdata == '') {
                $statuscode = 204;
                $err = 'malformed response';
            }
        }

        if ($statuscode) {
            echo "url=$url => statuscode=$statuscode, err=$err";
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->SetStatus($statuscode);
        }

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
    public function CalcAbsolutePressure(float $pressure, float $temp, int $altitude)
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
        $kmh2bft = [0.3, 1.6, 3.4, 5.5, 8.0, 10.8, 13.9, 17.2, 20.8, 24.5, 28.5, 32.7];

        $ms = $speed / 3.6;
        for ($i = 0; $i < count($kmh2bft); $i++) {
            if ($ms < $kmh2bft[$i]) {
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
}
