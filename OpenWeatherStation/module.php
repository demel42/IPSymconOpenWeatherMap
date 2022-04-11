<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/CommonStubs/common.php'; // globale Funktionen
require_once __DIR__ . '/../libs/local.php';   // lokale Funktionen

class OpenWeatherStation extends IPSModule
{
    use StubsCommonLib;
    use OpenWeatherMapLocalLib;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('appid', '');
        $this->RegisterPropertyString('station_id', '');

        $this->RegisterPropertyString('external_id', '');
        $this->RegisterPropertyString('name', '');
        $this->RegisterPropertyFloat('longitude', 0);
        $this->RegisterPropertyFloat('latitude', 0);
        $this->RegisterPropertyFloat('altitude', 0);

        $this->RegisterPropertyInteger('dt_var', 0);
        $this->RegisterPropertyInteger('temperature_var', 0);
        $this->RegisterPropertyInteger('wind_speed_var', 0);
        $this->RegisterPropertyInteger('wind_gust_var', 0);
        $this->RegisterPropertyInteger('wind_deg_var', 0);
        $this->RegisterPropertyInteger('pressure_var', 0);
        $this->RegisterPropertyInteger('humidity_var', 0);
        $this->RegisterPropertyInteger('rain_1h_var', 0);
        $this->RegisterPropertyInteger('rain_6h_var', 0);
        $this->RegisterPropertyInteger('rain_24h_var', 0);
        $this->RegisterPropertyInteger('snow_1h_var', 0);
        $this->RegisterPropertyInteger('snow_6h_var', 0);
        $this->RegisterPropertyInteger('snow_24h_var', 0);

        $this->RegisterPropertyInteger('convert_script', 0);

        $this->RegisterPropertyInteger('transmit_interval', 5);

        $this->RegisterTimer('TransmitMeasurements', 0, 'OpenWeatherStation_TransmitMeasurements(' . $this->InstanceID . ');');
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
            $this->SetTransmitInterval();
        }
    }

    private function CheckConfiguration()
    {
        $s = '';
        $r = [];

        $appid = $this->ReadPropertyString('appid');
        if ($appid == '') {
            $this->SendDebug(__FUNCTION__, '"appid" is needed', 0);
            $r[] = $this->Translate('API-Key must be specified');
        }

        if ($r != []) {
            $s = $this->Translate('The following points of the configuration are incorrect') . ':' . PHP_EOL;
            foreach ($r as $p) {
                $s .= '- ' . $p . PHP_EOL;
            }
        }

        return $s;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $vpos = 0;
        $this->MaintainVariable('LastTransmission', $this->Translate('last transmission'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainTimer('TransmitMeasurements', 0);
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainTimer('TransmitMeasurements', 0);
            $this->SetStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $refs = $this->GetReferenceList();
        foreach ($refs as $ref) {
            $this->UnregisterReference($ref);
        }
        $propertyNames = [
            'convert_script',
            'dt_var',
            'humidity_var',
            'pressure_var',
            'rain_1h_var',
            'rain_24h_var',
            'rain_6h_var',
            'snow_1h_var',
            'snow_24h_var',
            'snow_6h_var',
            'temperature_var',
            'wind_deg_var',
            'wind_gust_var',
            'wind_speed_var'
        ];
        foreach ($propertyNames as $name) {
            $oid = $this->ReadPropertyInteger($name);
            if ($oid >= 10000) {
                $this->RegisterReference($oid);
            }
        }

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->SetTransmitInterval();
        }

        $this->SetStatus(IS_ACTIVE);
    }

    private function GetFormElements()
    {
        $formElements = [];

        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'OpenWeatherMap - Transmission of measurement values of own weather station'
        ];

        @$s = $this->CheckConfiguration();
        if ($s != '') {
            $formElements[] = [
                'type'    => 'Label',
                'caption' => $s
            ];
            $formElements[] = [
                'type'    => 'Label',
            ];
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
        ];

        $formElements[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'appid',
            'caption' => 'API-Key'
        ];

        $formElements[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'station_id',
            'caption' => 'Station-ID'
        ];

        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'station data - if position is not set, Modue \'Location\' is used'
        ];
        $formElements[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'external_id',
            'caption' => 'external ID'
        ];
        $formElements[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'name',
            'caption' => 'Name'
        ];
        $formElements[] = [
            'type'    => 'NumberSpinner',
            'digits'  => 5,
            'name'    => 'longitude',
            'caption' => 'Longitude',
            'suffix'  => '°'
        ];
        $formElements[] = [
            'type'    => 'NumberSpinner',
            'digits'  => 5,
            'name'    => 'latitude',
            'caption' => 'Latitude',
            'suffix'  => '°'
        ];
        $formElements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'altitude',
            'caption' => 'Altitude',
            'suffix'  => 'm'
        ];

        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'Variables with measurement values'
        ];
        $formElements[] = [
            'type'    => 'SelectVariable',
            'name'    => 'dt_var',
            'caption' => 'Time of measurement'
        ];
        $formElements[] = [
            'type'    => 'SelectVariable',
            'name'    => 'temperature_var',
            'caption' => 'Temperature (°C)'
        ];
        $formElements[] = [
            'type'    => 'SelectVariable',
            'name'    => 'wind_speed_var',
            'caption' => 'Wind speed (m/s)'
        ];
        $formElements[] = [
            'type'    => 'SelectVariable',
            'name'    => 'wind_gust_var',
            'caption' => 'Wind gusts (m/s)'
        ];
        $formElements[] = [
            'type'    => 'SelectVariable',
            'name'    => 'wind_deg_var',
            'caption' => 'Wind direction (°)'
        ];
        $formElements[] = [
            'type'    => 'SelectVariable',
            'name'    => 'pressure_var',
            'caption' => 'Pressure (mbar)'
        ];
        $formElements[] = [
            'type'    => 'SelectVariable',
            'name'    => 'humidity_var',
            'caption' => 'Humidity (%)'
        ];
        $formElements[] = [
            'type'    => 'SelectVariable',
            'name'    => 'rain_1h_var',
            'caption' => 'Rainfall 1h (mm)'
        ];
        $formElements[] = [
            'type'    => 'SelectVariable',
            'name'    => 'rain_6h_var',
            'caption' => 'Rainfall 6h (mm)'
        ];
        $formElements[] = [
            'type'    => 'SelectVariable',
            'name'    => 'rain_24h_var',
            'caption' => 'Rainfall today (mm)'
        ];
        $formElements[] = [
            'type'    => 'SelectVariable',
            'name'    => 'snow_1h_var',
            'caption' => 'Snow 1h (mm)'
        ];
        $formElements[] = [
            'type'    => 'SelectVariable',
            'name'    => 'snow_6h_var',
            'caption' => 'Snow 6h (mm)'
        ];
        $formElements[] = [
            'type'    => 'SelectVariable',
            'name'    => 'snow_24h_var',
            'caption' => 'Snow today (mm)'
        ];

        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'script to convert values'
        ];
        $formElements[] = [
            'type'    => 'SelectScript',
            'name'    => 'convert_script',
            'caption' => 'script'
        ];

        $formElements[] = [
            'type'    => 'NumberSpinner',
            'minimum' => 0,
            'suffix'  => 'Minutes',
            'name'    => 'transmit_interval',
            'caption' => 'Transmission interval'
        ];

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Transmit weatherdata',
            'onClick' => 'OpenWeatherStation_TransmitMeasurements($id);'
        ];

        $formActions[] = $this->GetInformationForm();
        $formActions[] = $this->GetReferencesForm();

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

    protected function SetTransmitInterval()
    {
        $min = $this->ReadPropertyInteger('transmit_interval');
        $msec = $min > 0 ? $min * 1000 * 60 : 0;
        $this->MaintainTimer('TransmitMeasurements', $msec);
    }

    public function TransmitMeasurements()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $station_id = $this->ReadPropertyString('station_id');
        if ($station_id == '') {
            return false;
        }

        $now = time();

        $vars = [
            'dt',
            'temperature',
            'wind_speed', 'wind_gust', 'wind_deg',
            'pressure',
            'humidity',
            'rain_1h', 'rain_6h', 'rain_24h',
            'snow_1h', 'snow_6h', 'snow_24h',
        ];

        $values = [];
        foreach ($vars as $var) {
            $varID = $this->ReadPropertyInteger($var . '_var');
            $val = $varID >= 10000 ? GetValue($varID) : '';
            $values[$var] = $val;
        }

        $this->SendDebug(__FUNCTION__, 'values=' . print_r($values, true), 0);

        $convert_script = $this->ReadPropertyInteger('convert_script');
        if ($convert_script >= 10000) {
            $r = IPS_RunScriptWaitEx($convert_script, ['InstanceID' => $this->InstanceID, 'values' => json_encode($values)]);
            if ($r != '') {
                $values = json_decode($r, true);
                $this->SendDebug(__FUNCTION__, 'modified values=' . print_r($values, true), 0);
            }
        }

        if ($values['dt'] == '') {
            $values['dt'] = $now;
        }

        $v = [];
        $v['station_id'] = $station_id;
        foreach ($vars as $var) {
            if (!isset($values[$var])) {
                continue;
            }
            $val = $values[$var];
            if ($val == '') {
                continue;
            }
            $v[$var] = $val;
        }
        $postdata = [];
        $postdata[] = $v;

        $statuscode = $this->do_HttpRequest('data/3.0/measurements', '', $postdata, 'POST', $result);
        $this->SetStatus($statuscode ? $statuscode : IS_ACTIVE);
        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, 'http-request failed', 0);
            return false;
        }

        $this->SetValue('LastTransmission', $now);

		$this->SendDebug(__FUNCTION__, $this->PrintTimer('TransmitMeasurements'), 0);
        return true;
    }

    public function FetchMeasurements(int $from, int $to, string $type = 'm', int $limit = 100)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $station_id = $this->ReadPropertyString('station_id');
        if ($station_id == '') {
            return false;
        }

        $now = time();

        if ($to == '' || $to == 0) {
            $to = $now;
        }
        if ($from == '' || $from == 0 || $from > $to) {
            $from = $now - 24 * 60 * 60;
        }

        $args = [
            'station_id' => $station_id,
            'type'       => $type,
            'limit'      => $limit,
            'from'       => $from,
            'to'         => $to
        ];

        $statuscode = $this->do_HttpRequest('data/3.0/measurements', $args, '', 'GET', $result);
        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, 'http-request failed', 0);
            return false;
        }
        return $result;
    }

    public function RegisterStation()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $station_id = $this->ReadPropertyString('station_id');
        if ($station_id != '') {
            return false;
        }

        $external_id = $this->ReadPropertyString('external_id');
        $name = $this->ReadPropertyString('name');
        $latitude = $this->ReadPropertyFloat('latitude');
        $longitude = $this->ReadPropertyFloat('longitude');
        $altitude = $this->ReadPropertyFloat('altitude');

        if ($latitude == 0 || $longitude == 0) {
            $id = IPS_GetInstanceListByModuleID('{45E97A63-F870-408A-B259-2933F7EABF74}')[0];
            $loc = json_decode(IPS_GetProperty($id, 'Location'), true);
            $latitude = $loc['latitude'];
            $longitude = $loc['longitude'];
        }

        $postdata = [
            'external_id' => $external_id,
            'name'        => $name,
            'latitude'    => $latitude,
            'longitude'   => $longitude,
            'altitude'    => $altitude
        ];

        $statuscode = $this->do_HttpRequest('data/3.0/stations', '', $postdata, 'POST', $result);
        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, 'http-request failed', 0);
            return false;
        }
        return $result;
    }

    public function UpdateStation()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $station_id = $this->ReadPropertyString('station_id');
        if ($station_id == '') {
            return false;
        }

        $external_id = $this->ReadPropertyString('external_id');
        $name = $this->ReadPropertyString('name');
        $latitude = $this->ReadPropertyFloat('latitude');
        $longitude = $this->ReadPropertyFloat('longitude');
        $altitude = $this->ReadPropertyFloat('altitude');

        if ($latitude == 0 || $longitude == 0) {
            $id = IPS_GetInstanceListByModuleID('{45E97A63-F870-408A-B259-2933F7EABF74}')[0];
            $loc = json_decode(IPS_GetProperty($id, 'Location'), true);
            $latitude = $loc['latitude'];
            $longitude = $loc['longitude'];
        }

        $postdata = [
            'external_id' => $external_id,
            'name'        => $name,
            'latitude'    => $latitude,
            'longitude'   => $longitude,
            'altitude'    => $altitude
        ];

        $statuscode = $this->do_HttpRequest('data/3.0/stations/' . $station_id, '', $postdata, 'PUT', $result);
        return $result;
    }

    public function ListStations()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $statuscode = $this->do_HttpRequest('data/3.0/stations', '', '', 'GET', $result);
        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, 'http-request failed', 0);
            return false;
        }
        return $result;
    }

    public function DeleteStation(string $station_id)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $statuscode = $this->do_HttpRequest('data/3.0/stations/' . $station_id, '', '', 'DELETE', $result);
        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, 'http-request failed', 0);
            return false;
        }
        return $result;
    }

    private function do_HttpRequest($cmd, $args, $postdata, $mode, &$result)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

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

        $header = [];
        $header[] = 'Content-Type: application/json';
        if ($postdata != '') {
            $header[] = 'Content-Length: ' . strlen(json_encode($postdata));
        }

        $this->SendDebug(__FUNCTION__, 'http: url=' . $url . ', mode=' . $mode, 0);
        $this->SendDebug(__FUNCTION__, '   header=' . print_r($header, true), 0);
        if ($postdata != '') {
            $this->SendDebug(__FUNCTION__, '    postdata=' . json_encode($postdata), 0);
        }

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        switch ($mode) {
            case 'GET':
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mode);
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mode);
                break;
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
        $result = '';
        if ($cerrno) {
            $statuscode = self::$IS_INVALIDDATA;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } elseif ($httpcode < 200 || $httpcode > 299) {
            if ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = "got http-code $httpcode (server error)";
            } else {
                $err = "got http-code $httpcode";
                $statuscode = self::$IS_HTTPERROR;
            }
        } elseif ($cdata == '') {
            if ($httpcode < 200 || $httpcode > 299) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'no data';
            }
        } else {
            $result = json_decode($cdata, true);
            if ($result == '') {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'malformed response';
            }
        }

        if ($statuscode) {
            $this->LogMessage('url=' . $url . ' => statuscode=' . $statuscode . ', err=' . $err, KL_WARNING);
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err, 0);
        }

        return $statuscode;
    }
}
