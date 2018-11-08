# IPSymconOpenWeatherMap/OpenWeatherStation

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

In IP-Symcon nun _Instanz hinzufügen_ (_CTRL+1_) auswählen unter der Kategorie, unter der man die Instanz hinzufügen will, und Hersteller _(sonstiges)_ und als Gerät _OpenWeatherStation_ auswählen.

Da es bei _OpenWeather_ kein Web-Interface sondern nur ein API-Interface gibt, um eine Station zu registrieren, sind mehrere Schritte erforderlich.

1. Ausfüllen der Daten der Station (externe ID, Name sowie geographische Position und Höhe) im Konfigurationsformular.
2. Erstellen eines kleines PHP-Scripts (siehe _OpenWeatherStation_RegisterStation()_) und auѕführen
3. die zurückgelieferte _ID_ als Stations-ID im Konfigurationsformular eintragen.

Spätere Anpassungen der Daten zur Stationn kann man über _OpenWeatherStation_UpdateStation()_ durchführen.
Sollte man versehentlich mehrere Stationen angelegt haben, können diese mittels _OpenWeatherStation_DeleteStation()_ gelöscht werden.

4. die zu übertragenden Variablen eintragen, ggfs ein _convert_script_ einrichten.


## 4. Funktionsreferenz

`bool OpenWeatherStation_TransmitMeasurements(int $InstanzID)`

überträgt die Daten der eigenen Wetterstation zu _OpenWeatherMap_ ab. Wird automatisch zyklisch durch die Instanz durchgeführt im Abstand wie in der Konfiguration angegeben.

### Hilfsfunktionen

`bool OpenWeatherStation_FetchMeasurements(int $InstanzID, int $from, int $to, string $type, int $limit = 100)`

liefert die Daten der aktuallen Station zurück

| Name              | Bedeutung               |
| :---------------: | :---------------------: |
| type              | Auflösung der Daten (m=Minute, h=Stunde, d=Tag) |
| limit             | maximale Anzahl der Datensätze |

dient im Wesentlichen zur Überprüfung der Übertragung.

`array OpenWeatherStation_RegisterStation(int $InstanzID)`

Registrierung einer Station. Beim Aufruf wird eine JSON-kodiertes Struktur zurückgeliefert, das u.a. die _ID_ der Station liefert; diese muss dann auf der Konfigurationsseite eingetragen werden.

```
$r = OpenWeatherStation_RegisterStation(4711 /*[OpenWeatherMap - Station]*/);
echo print_r($r, true) . PHP_EOL;

Array
(
    [ID] => xxxxxxxxxxxxxxxxxxxxxxxx
    [updated_at] => 2018-10-28T08:48:31.344773726Z
    [created_at] => 2018-10-28T08:48:31.344773696Z
    [user_id] => yyyyyyyyyyyyyyyyyyyyyyyy
    [external_id] => Zuhause
    [name] => Meine Stadt, meine Straße
    [latitude] => 17,13
    [longitude] => 7,1
    [altitude] => 89
    [rank] => 10
    [source_type] => 5
)

```

Achtung: ist eine Station-ID in der Konfiguration eingetragen, wird _false_ zurück geliefert.

`bool OpenWeatherStation_UpdateStation(int $InstanzID)`

Aktualisierung der auf der Konfigurationsseite angegebenen, ggfs. geänderten, Daten zur Station.

```
$r = OpenWeatherStation_UpdateStation(4711 /*[OpenWeatherMap - Station]*/);
echo print_r($r, true) . PHP_EOL;

Array
(
    [id] => xxxxxxxxxxxxxxxxxxxxxxxx
    [created_at] => 2018-10-21T17:03:51.485Z
    [updated_at] => 2018-10-28T08:58:00.081129639Z
    [external_id] => Zuhause
    [name] => Meine Stadt, meine Straße
    [longitude] => 7,1
    [latitude] => 17,130994761485
    [altitude] => 90
    [rank] => 0
)


```

`array OpenWeatherStation_ListStations(int $InstanzID)`

Liefert die Liste der für diesen OpenWeather-Account angelegten Stationen (Arry vom JSON-kodierten Strukturen)

```
$r = OpenWeatherStation_ListStations(4711 /*[OpenWeatherMap - Station]*/);
echo print_r($r, true) . PHP_EOL;

Array
(
    [0] => Array
        (
            [id] => xxxxxxxxxxxxxxxxxxxxxxxx
            [created_at] => 2018-10-28T08:48:31.344Z
            [updated_at] => 2018-10-28T08:48:31.344Z
            [external_id] => Zuhause
            [name] => Meine Stadt, meine Straße
            [longitude] => 7,1
            [latitude] => 17,13
            [altitude] => 89
            [rank] => 10
        )
)


```


`bool OpenWeatherStation_DeleteStation(int $InstanzID, string $station_id)`

Löscht eine versehentlich angelegt Station

```

$r = OpenWeatherStation_DeleteStation(4711 /*[OpenWeatherMap - Station]*/, 'xxxxxxxxxxxxxxxxxxxxxxxx');
echo print_r($r, true) . PHP_EOL;

```

## 5. Konfiguration

### Variablen

| Eigenschaft               | Typ     | Standardwert | Beschreibung                               |
| :-----------------------: | :-----: | :----------: | :----------------------------------------: |
| appid                     | string  |              | API-Schlüssel von _OpenWeatherMap_         |
|                           |         |              |                                            |
| station_id                | string  |              | ID der Station (siehe _RegisterStation()_  |
|                           |         |              |                                            |
| external_id               | string  |              | eine beliebige eigenen ID                  |
| name                      | string  |              | eine beliebige Bezeichnung                 |
| longitude                 | float   |              | Längengrad der Station                     |
| latitude                  | float   |              | Breitengrad der Station                    |
| altitude                  | float   |              | Höhe der Station über dem Meeresspiegel in Metern |
|                           |         |              |                                            |
| _Variablen-IDs_           | integer |              | Variablen-ID's der zu übermittelnden Messwerte |
|                           |         |              |                                            |
| convert_script            | integer | 0            | ID eines Scriptes zur Umrechnung von Messwerten |
|                           |         |              |                                            |
| update_interval           | integer | 60           | Aktualisierungsintervall in Minuten        |

Wenn _longitude_ und _latitude_ auf **0** stehen, werden die Daten aus dem Modul _Location_ verwendet. Die Angabe von _altitude_ ist nur erforderlich zur Berechnung des absoluten Luftdrucks.

Erläuterung zu _convert_script_:
mit diesem Scripten können Messwerte umgerechnet werden, Beispiel:

```
<?

$instID = $_IPS['InstanceID'];
$values = json_decode($_IPS['values'], true);

$values['timestamp'] = time();

$wind_speed = $values['wind_speed'];
if (is_numeric($wind_speed) && $wind_speed != 0) {
	$wind_speed /= 3.6; // km/h -> m/s
	$values['wind_speed'] = $wind_speed;
}

$wind_gust = $values['wind_gust'];
if (is_numeric($wind_gust) && $wind_gust != 0) {
	$wind_gust /= 3.6; // km/h -> m/s
	$values['wind_gust'] = $wind_gust;
}

echo json_encode($values);

```

#### Schaltflächen

| Bezeichnung                  | Beschreibung              |
| :--------------------------: | :-----------------------: |
| Wetterdaten übertragen       | Übertragun der aktuellen Daten |

## 6. Anhang

siehe [hier](../README.md#6-anhang)

Verweise:
- https://openweathermap.org/stations

## 7. Versions-Historie

siehe [hier](../README.md#7-versions-historie)
