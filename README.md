# IPSymconOpenWeatherMap

[![IPS-Version](https://img.shields.io/badge/Symcon_Version-6+-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)

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

_OpenWeatherMap_ (https://openweathermap.org) ist eine Web-Seite, die Wetterdaten bereit stellt. Es gibt eine API, die sowohl einen kostenlosen Zugriff erlaubt als auch komerzielle Angebote beinhaltet.

Das Modul behandelt nur die kostenlosen Zugriffe bzw. den _"One Call by Call" subscription plan_, der aber ausreichen kostenlose Zugriffe auf die _One Call API 3.0_ ermöglicht.

_OpenWeatherOneCall_:
- Daten aus [One Call API 2.5](https://openweathermap.org/api/one-call-api) bzw. [One Call API 3.0](https://openweathermap.org/api/one-call-3)

_OpenWeatherData_:
- aktuellen Daten [Current weather data](https://openweathermap.org/current)
- Vorhersagen in 3-Stunden-Schritten [5 day weather forecast](https://openweathermap.org/forecast5)

_OpenWeatherStation_:
- Übertragung von Daten einer lokalen Wetterstation an _OpenWeather_ [Weather Stations](https://openweathermap.org/stations)

## 2. Voraussetzungen

 - IP-Symcon ab Version 6

## 3. Installation

Die Konsole von IP-Symcon öffnen. Im Objektbaum unter Kerninstanzen die Instanz __*Modules*__ durch einen doppelten Mausklick öffnen.

In der _Modules_ Instanz rechts oben auf den Button __*Hinzufügen*__ drücken.

In dem sich öffnenden Fenster folgende URL hinzufügen:

`https://github.com/demel42/IPSymconOpenWeatherMap.git`

und mit _OK_ bestätigen.

Anschließend erscheint ein Eintrag für das Modul in der Liste der Instanz _Modules_

### Anmeldung bei _OpenWeatherMap_
Man muss hier (_https://home.openweathermap.org/users/sign_up_) einen Account erstellen. Nach erfolgter Anmeldung kann man in dem Punkt _API keys_ einen API-Key erzeugen bzw. diese verwalten.

### Einrichtung in IPS

siehe [OpenWeatherOneCall](OpenWeatherOneCall/README.md#3-installation), [OpenWeatherData](OpenWeatherData/README.md#3-installation) und [OpenWeatherStation](OpenWeatherStation/README.md#3-installation)

## 4. Funktionsreferenz

siehe [OpenWeatherOneCall](OpenWeatherOneCall/README.md#3-funktionsreferenz), [OpenWeatherData](OpenWeatherData/README.md#4-funktionsreferenz) und [OpenWeatherStation](OpenWeatherStation/README.md#4-funktionsreferenz)

## 5. Konfiguration

siehe [OpenWeatherOneCall](OpenWeatherOneCall/README.md#3-konfiguration), [OpenWeatherData](OpenWeatherData/README.md#5-konfiguration) und [OpenWeatherStation](OpenWeatherStation/README.md#5-konfiguration)

## 6. Anhang

GUIDs

- Modul: `{BCAEF996-FC2B-420D-A801-5C0B4A021225}`
- Instanzen:
  - OpenWeatherOneCall: `{99C682F3-C735-9EBD-5F74-B1F19518228B}`
  - OpenWeatherData: `{8072158E-53BF-482A-B925-F4FBE522CEF2}`
  - OpenWeatherStation: `{604AD7FF-7883-47E7-A2A8-0C6D3C343BE9}`

Verweise:
- https://openweathermap.org/api

Beispielscripte:
- Zusammenfassung der Wetter-Bedingungen: [docs/oneCallStatus.php](docs/oneCallStatus.php)
- Setzen einer HTML-Box mit dem 'ConditionIcon': [docs/oneCallConditionIcon.php](docs/oneCallConditionIcon.php)

## 7. Versions-Historie

- 2.10 @ 18.08.2023 10:49
  - Fix: Fehlermeldung bei Verwendung von "GetRawData()" vor dem ersten Datenabruf
  - update submodule CommonStubs
    - die Daten für das Panel "Referenzen" werden erst geladen, wenn das Panel geöffnet wird. Auf langsameren Systemen erzeugte das ein unübersehbares Performanceproblem.

- 2.9 @ 15.08.2023 09:31
  - Fix: Wertebereich diverser Variablenprofile angepasst
	- OpenWeatherMap.Temperatur: -10..30 -> -25..45 °C
	- OpenWeatherMap.Humidity: n/a -> 0..100 %
	- OpenWeatherMap.absHumidity: 10..100 -> 0..80 g/m³
	- OpenWeatherMap.Dewpoint: 0..30 -> -10..40 °C
	- OpenWeatherMap.Heatindex: 0..100 -> 0..60 °C
	- OpenWeatherMap.WindSpeed: 0..100 -> 0..150 km/h
	- OpenWeatherMap.RainProbability: n/a -> 0..100 %

- 2.8 @ 07.08.2023 08:46
  - Fix: minütliche Niederschlagvorhersage wurde fälschlicherweise als Regenwahrscheinlichkeit interpretiert
  - update submodule CommonStubs

- 2.7 @ 04.07.2023 14:44
  - Vorbereitung auf IPS 7 / PHP 8.2
  - update submodule CommonStubs
    - Absicherung bei Zugriff auf Objekte und Inhalte

- 2.6 @ 03.01.2023 09:57
  - Neu: Führen einer Statistik der API-Calls, Anzeige als Popup im Experten-Bereich
  - Neu (OpenWeatherOneCall): Unterstürzung der API-Version 3.0
  - update submodule CommonStubs

- 2.5.1 @ 07.10.2022 13:59
  - update submodule CommonStubs
    Fix: Update-Prüfung wieder funktionsfähig

- 2.5 @ 05.07.2022 16:41
  - update submodule CommonStubs
    Fix: Ausgabe des nächsten Timer-Zeitpunkts
  - einige Funktionen (GetFormElements, GetFormActions) waren fehlerhafterweise "protected" und nicht "private"
  - interne Funktionen sind nun private und ggfs nur noch via IPS_RequestAction() erreichbar
  - Fix: Angabe der Kompatibilität auf 6.2 korrigiert
  - Verbesserung: IPS-Status wird nur noch gesetzt, wenn er sich ändert

- 2.4.6 @ 21.05.2022 07:57
  - Fix in OpenWeatherOneCall_GetRawData()
  - Korrektur von README.md

- 2.4.5 @ 20.05.2022 15:30
  - Fix: OpenWeatherData - Absturz beim Öffnen des Experten-Bereichs

- 2.4.4 @ 17.05.2022 15:38
  - update submodule CommonStubs
    Fix: Absicherung gegen fehlende Objekte

- 2.4.3 @ 10.05.2022 15:06
  - update submodule CommonStubs
  - SetLocation() -> GetConfiguratorLocation()
  - weitere Absicherung ungültiger ID's

- 2.4.2 @ 29.04.2022 12:51
  - Überlagerung von Translate und Aufteilung von locale.json in 3 translation.json (Modul, libs und CommonStubs)

- 2.4.1 @ 26.04.2022 12:10
  - Korrektur: self::$IS_DEACTIVATED wieder IS_INACTIVE
  - IPS-Version ist nun minimal 6.0

- 2.4 @ 25.04.2022 16:17
  - Übersetzung vervollständigt
  - Implememtierung einer Update-Logik
  - diverse interne Änderungen

- 2.3.4 @ 16.04.2022 12:09
  - potentieller Namenskonflikt behoben (trait CommonStubs)
  - Aktualisierung von submodule CommonStubs

- 2.3.3 @ 11.04.2022 12:06
  - Ausgabe der Instanz-Timer unter "Referenzen"

- 2.3.2 @ 09.04.2022 18:22
  - README um Hinweis auf Beispiel-Scripte ergänzt

- 2.3.1 @ 08.04.2022 15:29
  - Abfangen von fehlenden Vorhersage-Daten (daily, hourly, minutely)

- 2.3 @ 08.04.2022 09:54
  - Korrektur in OpenWeatherOneCall: Windgeschwindigkeit wurde nicht von 'm/s' zu 'km/h' umgerechnet
  - automatischer Retry nach HTTP-Server-Error
  - Anpassungen an IPS 6.2 (Prüfung auf ungültige ID's)
  - Möglichkeit der Anzeige der Instanz-Referenzen
  - libs/common.php -> CommonStubs
  - Abfangen von fehlenden Vorhersage-Daten (daily, hourly, minutely)

- 2.2 @ 28.12.2021 13:35
  - Anzeige der Modul/Bibliotheks-Informationen
  - Absicherung von 'Modul deaktivieren'

- 2.1 @ 15.10.2021 12:17
  - OpenWeatherOneCall ergänzt:
    - Korrektur für "letzte Messung"
    - stündliche Vorhersage + Regenmenge / Schneefall
    - tägliche Vorhersage + Regenmenge
    - Muster-Script für die Erstellung einer HTML-Box mit Übersicht und für Wetterbedingung-Icon

- 2.0 @ 11.09.2021 12:16
  - neues Modul OpenWeatherOneCall für die OneCall-API

- 1.25 @ 26.08.2021 18:37
  - Überarbeitung der Darstellung der Wetter-Zusammenfassung
  - HTML-Box mit dem Icon der aktuellen Wetterbedingung

- 1.24 @ 14.07.2021 18:51
  - Schalter "Instanz ist deaktiviert" umbenannt in "Instanz deaktivieren"

- 1.23 @ 20.12.2020 14:35
  - PHP_CS_FIXER_IGNORE_ENV=1 in github/workflows/style.yml eingefügt
  - Regenwahrscheinlichkeit bei der Vorhersage hinzugefügt

- 1.22 @ 30.08.2020 12:25
  - LICENSE.md hinzugefügt
  - define's durch statische Klassen-Variablen ersetzt
  - lokale Funktionen aus common.php in locale.php verlagert
  - Reihenfolge Breiten- und Längengrad geändert

- 1.21 @ 30.12.2019 10:56
  - Anpassungen an IPS 5.3
    - auch in der Dokumentation das 'PHP Long Tag' verwenden

- 1.20 @ 07.01.2020 15:53
  - Nutzung von RegisterReference() für im Modul genutze Objekte (Scripte, Kategorien etc)
  - SetTimerInterval() erst nach KR_READY

- 1.19 @ 30.12.2019 10:56
  - Anpassungen an IPS 5.3
    - Formular-Elemente: 'label' in 'caption' geändert

- 1.18 @ 26.10.2019 17:34
  - Fix wegen strict_types=1

- 1.17 @ 10.10.2019 17:27
  - Anpassungen an IPS 5.2
    - IPS_SetVariableProfileValues(), IPS_SetVariableProfileDigits() nur bei INTEGER, FLOAT
    - Dokumentation-URL in module.json
  - Umstellung auf strict_types=1
  - Umstellung von StyleCI auf php-cs-fixer

- 1.16 @ 09.08.2019 14:32
  - Schreibfehler korrigiert

- 1.15 @ 26.04.2019 16:36
  - Schreibfehler korrigiert

- 1.14 @ 29.03.2019 16:19
  - SetValue() abgesichert

- 1.13 @ 22.03.2019 15:15
  - Anpassungen IPS 5
  - Schalter, um ein Modul (temporär) zu deaktivieren
  - Konfigurations-Element IntervalBox -> NumberSpinner

- 1.12 @ 23.01.2019 18:18
  - curl_errno() abfragen

- 1.11 @ 22.12.2018 12:10
  - Fehler in der http-Kommunikation nun nicht mehr mit _echo_ (also als **ERROR**) sondern mit _LogMessage_ als **NOTIFY**

- 1.10 @ 21.12.2018 13:10
  - Standard-Konstanten verwenden

- 1.9 @ 04.11.2018 17:36
  - offizielle defines der Status-Codes verwendet sowie eigenen Status-Codes relativ zu _IS_EBASE_ angelegt

- 1.8 @ 28.10.2018 09:23
  - _OpenWeatherStation_ dazu

- 1.7 @ 13.10.2018 17:52
  - Umstellung der internen Speicherung zur Vermeidung der Warnung _Puffer > 8kb_.

- 1.6 @ 12.10.2018 19:29
  - Bugfix: z.T. fehlende Suffixe bei Vorhersage-Variablen, falsche Windgeschwindigkeit in der HTML-Darstellung
  - in der HTML-Darstellung wird die WIndgeschwindigkeit ohne Nachkommastellen ausgegeben

- 1.5 @ 11.10.2018 18:08
  - _ConditionIcons_ und _ConditionIds_ (Plural) ersetzt durch _ConditionIcon_ und _ConditionId_ (Singular).
  Es wird nur noch der wichtigste Eintrag gespeichert - laut _OpenWeatherMap_ ist das jeweils der erste Eintrag.
  - zusätzliche temporäre Ablage der Originaldaten in internen Buffern und Funktion zum Abruf der Daten (_OpenWeatherData_GetRawData()_)

- 1.4 @ 10.10.2018 15:27
  - optionale Übernahme der Ids der Wetterbedingungen

- 1.3 @ 09.10.2018 17:38
  - optische Aufbereitung der Wetterinformationen

- 1.2 @ 08.10.2018 22:21
  - Korrektur des Zugriffs auf _Location_

- 1.1 @ 07.10.2018 10:27
  - Sprache der texuellen Informationen per Konfigurationsdialog einstellbar
  - Angabe der Einheiten bestimmer Felder im Konfigurationsdialog

- 1.0 @ 25.09.2018 17:35
  - Initiale Version
