<?php

declare(strict_types=1);

trait OpenWeatherMapLocalLib
{
    public static $IS_SERVERERROR = IS_EBASE + 10;
    public static $IS_HTTPERROR = IS_EBASE + 11;
    public static $IS_INVALIDDATA = IS_EBASE + 12;

    private function GetFormStatus()
    {
        $formStatus = $this->GetCommonFormStatus();

        $formStatus[] = ['code' => self::$IS_SERVERERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (server error)'];
        $formStatus[] = ['code' => self::$IS_HTTPERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (http error)'];
        $formStatus[] = ['code' => self::$IS_INVALIDDATA, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid data)'];

        return $formStatus;
    }

    public static $STATUS_INVALID = 0;
    public static $STATUS_VALID = 1;
    public static $STATUS_RETRYABLE = 2;

    private function CheckStatus()
    {
        switch ($this->GetStatus()) {
            case IS_ACTIVE:
                $class = self::$STATUS_VALID;
                break;
            case self::$IS_SERVERERROR:
            case self::$IS_HTTPERROR:
            case self::$IS_INVALIDDATA:
                $class = self::$STATUS_RETRYABLE;
                break;
            default:
                $class = self::$STATUS_INVALID;
                break;
        }

        return $class;
    }

    private function InstallVarProfiles(bool $reInstall = false)
    {
        if ($reInstall) {
            $this->SendDebug(__FUNCTION__, 'reInstall=' . $this->bool2str($reInstall), 0);
        }

        $this->CreateVarProfile('OpenWeatherMap.Temperatur', VARIABLETYPE_FLOAT, ' °C', -25, 45, 0, 1, 'Temperature', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.Humidity', VARIABLETYPE_FLOAT, ' %', 0, 0, 0, 0, 'Drops', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.absHumidity', VARIABLETYPE_FLOAT, ' g/m³', 0, 80, 0, 0, 'Drops', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.Dewpoint', VARIABLETYPE_FLOAT, ' °C', -10, 40, 0, 0, 'Drops', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.Heatindex', VARIABLETYPE_FLOAT, ' °C', 0, 60, 0, 0, 'Temperature', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.Pressure', VARIABLETYPE_FLOAT, ' mbar', 500, 1200, 0, 0, 'Gauge', [], $reInstall);

        $associations = [
            ['Wert' =>  0, 'Name' => '%.1f', 'Farbe' => 0x80FF00],
            ['Wert' => 3, 'Name' => '%.1f', 'Farbe' => 0xFFFF00],
            ['Wert' => 6, 'Name' => '%.1f', 'Farbe' => 0xFF8040],
            ['Wert' => 8, 'Name' => '%.1f', 'Farbe' => 0xFF0000],
            ['Wert' => 11, 'Name' => '%.1f', 'Farbe' => 0xFF00FF],
        ];
        $this->CreateVarProfile('OpenWeatherMap.UVIndex', VARIABLETYPE_FLOAT, '', 0, 12, 0, 0, 'Sun', $associations, $reInstall);

        $this->CreateVarProfile('OpenWeatherMap.WindSpeed', VARIABLETYPE_FLOAT, ' km/h', 0, 150, 0, 1, 'WindSpeed', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.WindStrength', VARIABLETYPE_INTEGER, ' bft', 0, 13, 0, 0, 'WindSpeed', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.WindAngle', VARIABLETYPE_INTEGER, ' °', 0, 360, 0, 0, 'WindDirection', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.WindDirection', VARIABLETYPE_STRING, '', 0, 0, 0, 0, 'WindDirection', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.Rainfall', VARIABLETYPE_FLOAT, ' mm', 0, 60, 0, 1, 'Rainfall', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.RainProbability', VARIABLETYPE_FLOAT, ' %', 0, 100, 0, 0, 'Rainfall', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.Snowfall', VARIABLETYPE_FLOAT, ' mm', 0, 60, 0, 1, 'Snow', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.Cloudiness', VARIABLETYPE_FLOAT, ' %', 0, 0, 0, 0, 'Cloud', [], $reInstall);
    }
}
