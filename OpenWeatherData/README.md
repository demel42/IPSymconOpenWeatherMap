# IPSymconOpenWeatherMap/OpenWeatherData

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Funktionsreferenz](#4-funktionsreferenz)
5. [Konfiguration](#5-konfiguration)
6. [Anhang](#6-anhang)
7. [Versions-Historie](#7-versions-historie)

## 1. Funktionsumfang

siehe [hier](../README.md#1-funktionsumfang)

## 2. Voraussetzungen

siehe [hier](../README.md#2-voraussetzungen)

## 3. Installation

Allgemeine Einrichtung siehe [hier](../README.md#3-installation)

### Einrichtung in IPS

In IP-Symcon nun _Instanz hinzufügen_ (_CTRL+1_) auswählen unter der Kategorie, unter der man die Instanz hinzufügen will, und Hersteller _(sonstiges)_ und als Gerät _OpenWeatherMap-Datenabruf_ auswählen.

## 4. Funktionsreferenz

`OpenWeatherData_UpdateData(int $InstanzID)`

ruft die Daten von _OpenWeatherMap_ ab. Wird automatisch zyklisch durch die Instanz durchgeführt im Abstand wie in der Konfiguration angegeben.

### Hilfsfunktionen

`string OpenWeatherData_GetRawData(int $InstanzID, string $name)`

liefert die Original-Ergebnisse der HTML-Aufrufe, z.B. zur Darstellung der HTML-Box. Folgende Daten stehen zur Verfügung:

| Name              | Bedeutung               |
| :---------------: | :---------------------: |
| Current           | aktuelle Wetterdaten    |
| HourlyForecast    | 3-stündliche Vorhersage |


`float OpenWeatherData_CalcAbsoluteHumidity(int $InstanzID, float $Temperatur, float $Humidity)`

berechnet aus der Temperatur (in °C) und der relativen Luftfeuchtigkeit (in %) die absolute Feuchte (in g/m³)


`float OpenWeatherData_CalcAbsolutePressure(int $InstanzID, float $Pressure, $Temperatur, int $Altitude)`

berechnet aus dem relativen Luftdruck (in mbar) und der Temperatur (in °C) und Höhe (in m) den absoluten Luftdruck (in mbar)
ist die Höhe nicht angegeben, wird die Höhe der Netatmo-Wetterstation verwendet


`float OpenWeatherData_CalcDewpoint(int $InstanzID, float $Temperatur, float $Humidity)`

berechnet aus der Temperatur (in °C) und der relativen Luftfeuchtigkeit (in %) den Taupunkt (in °C)


`float OpenWeatherData_CalcHeatindex(int $InstanzID, float $Temperatur, float $Humidity)`

berechnet aus der Temperatur (in °C) und der relativen Luftfeuchtigkeit (in %) den Hitzeindex (in °C)


`float OpenWeatherData_CalcWindchill(int $InstanzID, float $Temperatur, float $WindSpeed)`

berechnet aus der Temperatur (in °C) und der Windgeschwindigkeit (in km/h) den Windchill (Windkühle) (in °C)


`string OpenWeatherData_ConvertWindDirection2Text(int $InstanzID, int $WindDirection)`

ermittelt aus der Windrichtung (in °) die korrespondierende Bezeichnung auf der Windrose


`int OpenWeatherData_ConvertWindSpeed2Strength(int $InstanzID, float $WindSpeed)`

berechnet aus der Windgeschwindigkeit (in km/h) die Windstärke (in bft)


`string OpenWeatherData_ConvertWindStrength2Text(int $InstanzID, int $WindStrength)`

ermittelt aus der Windstärke (in bft) die korrespondierende Bezeichnung gemäß Beaufortskala


## 5. Konfiguration

### Variablen

| Eigenschaft               | Typ     | Standardwert | Beschreibung                               |
| :-----------------------: | :-----: | :----------: | :----------------------------------------: |
| appid                     | string  |              | API-Schlüssel von _OpenWeatherMap_         |
|                           |         |              |                                            |
| longitude                 | float   |              | Längengrad der Station                     |
| latitude                  | float   |              | Breitengrad der Station                    |
| altitude                  | float   |              | Höhe der Station über dem Meeresspiegel in Metern |
|                           |         |              |                                            |
| lang                      | string  |              | Spracheinstellung für textuelle Angaben    |
|                           |         |              |                                            |
| with_absolute_humidity    | boolean | false        | absolute Luftfeuchtigkeit                  |
| with_absolute_pressure    | boolean | false        | absoluter Luftdruck                        |
| with_dewpoint             | boolean | false        | Taupunkt                                   |
| with_heatindex            | boolean | false        | Hitzeindex                                 |
| with_windchill            | boolean | false        | Windchill (Windkühle)                      |
| with_windstrength         | boolean | false        | Windstärke                                 |
| with_windstrength2text    | boolean | false        | Windstärke                                 |
| with_windangle            | boolean | true         | Windrichtung in Grad                       |
| with_cloudiness           | boolean | false        | Bewölkung                                  |
| with_conditions           | boolean | false        | Wetterbedingungen                          |
| with_icons                | boolean | false        | Wetterbedingung-Symbole                    |
| with_condition_ids        | boolean | false        | Wetterbedingung-Ids                        |
|                           |         |              |                                            |
| with_summary              | boolean | false        | HTML-Box mit einer Zusammenfassung         |
| summary_script            | integer | 0            | ID eines Scriptes zur alternative Erstellung der HTML-Box |
|                           |         |              |                                            |
| hourly_forecast_count     | integer | 0            | Anzahl der Vorhersagen (max. 5 Tage alle 3 Stunden) |
|                           |         |              |                                            |
| update_interval           | integer | 60           | Aktualisierungsintervall in Minuten        |

Wenn _longitude_ und _latitude_ auf **0** stehen, werden die Daten aus der ersten gefundenen Instanz des Moduls _Location Control_ verwendet. Die Angabe von _altitude_ ist nur erforderlich zur Berechnung des absoluten Luftdrucks.

Die unterstützten Spracheinstellungen finden sich in der API-Dokumentatin unter der Überschrift _Multilingual support_ und sind z.B. (_de_, _en_, _fr_ ...).

Hinweis zu _with_icon_ und _with_condition_id_: diese Attribute können in der Nachricht mehrfach vorkommen. Damit man aber damit gut umgehen kann, wird immer nur der wichtigste Eintrag übernommen; laut _OpenWeatherMap_ ist das jeweils der erste Eintrag.

Hinweis zu _with_conditions_: diese werden alle, durch Komma getrennt, übernommen.

Erläuterung zu _summary_script_:
mit diesem Scripten kann man eine alternative Darstellung realisieren.

Ein passendes Code-Fragment für ein Script:

```
$instID = $_IPS['InstanceID'];

$html = '';

$data = OpenWeatherData_GetData($instID, 'Current');
if ($data) {
	$jdata = json_decode($data, true);

	$temperature = $jdata['main']['temp'];

	$html = 'Temperatur: ' . $temperatur . ' °C<br>';
}

echo $html;

```

#### Schaltflächen

| Bezeichnung                  | Beschreibung              |
| :--------------------------: | :-----------------------: |
| Aktualiseren                 | Wetterdaten aktualisieren |

### Variablenprofile

Es werden folgende Variablenprofile angelegt:
* Integer<br>
OpenWeatherMap.WindStrength, OpenWeatherMap.WindAngle

* Float<br>
OpenWeatherMap.Temperatur, OpenWeatherMap.Humidity, OpenWeatherMap.absHumidity, OpenWeatherMap.Dewpoint, OpenWeatherMap.Heatindex, OpenWeatherMap.Pressure, OpenWeatherMap.WindSpeed, OpenWeatherMap.Rainfall, OpenWeatherMap.Snowfall, OpenWeatherMap.Cloudiness

* String<br>
OpenWeatherMap.WindDirection


## 6. Anhang

siehe [hier](../README.md#6-anhang)

Verweise:
- https://openweathermap.org/current, https://openweathermap.org/forecast5

## 7. Versions-Historie

siehe [hier](../README.md#7-versions-historie)
