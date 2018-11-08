# IPSymconOpenWeatherMap

[![IPS-Version](https://img.shields.io/badge/Symcon_Version-5.0-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Module-Version](https://img.shields.io/badge/Modul_Version-1.9-blue.svg)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![StyleCI](https://github.styleci.io/repos/126683101/shield?branch=master)](https://github.styleci.io/repos/146979798)

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

Das Modul behandelt nur die kostenlosen Zugriffe.

_OpenWeatherData_:
- aktuellen Daten (_Current weather data_)
- stündlichen Vorhersagen (_5 day / 3 hour forecast_)

_OpenWeatherStation_:
- Übertragng von Daten einer lokalen Wetterstation an _OpenWeather_

## 2. Voraussetzungen

 - IP-Symcon ab Version 5

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

siehe [OpenWeatherData](OpenWeatherData/README.md#3-installation) und [OpenWeatherStation](OpenWeatherStation/README.md#3-installation)

## 4. Funktionsreferenz

siehe [OpenWeatherData](OpenWeatherData/README.md#4-funktionsreferenz) und [OpenWeatherStation](OpenWeatherStation/README.md#4-funktionsreferenz)

## 5. Konfiguration

siehe [OpenWeatherData](OpenWeatherData/README.md#5-konfiguration) und [OpenWeatherStation](OpenWeatherStation/README.md#5-konfiguration)

## 6. Anhang

GUIDs

- Modul: `{BCAEF996-FC2B-420D-A801-5C0B4A021225}`
- Instanzen:
  - OpenWeatherData: `{8072158E-53BF-482A-B925-F4FBE522CEF2}`
  - OpenWeatherStation: `{604AD7FF-7883-47E7-A2A8-0C6D3C343BE9}`

Verweise:
- https://openweathermap.org/api


## 7. Versions-Historie

- 1.9 @ 04.11.2018 17:36<br>
  - offizielle defines der Status-Codes verwendet sowie eigenen Status-Codes relativ zu _IS_EBASE_ angelegt

- 1.8 @ 28.10.2018 09:23<br>
  - _OpenWeatherStation_ dazu

- 1.7 @ 13.10.2018 17:52<br>
  - Umstellung der internen Speicherung zur Vermeidung der Warnung _Puffer > 8kb_.

- 1.6 @ 12.10.2018 19:29<br>
  - Bugfix: z.T. fehlende Suffixe bei Vorhersage-Variablen, falsche Windgeschwindigkeit in der HTML-Darstellung
  - in der HTML-Darstellung wird die WIndgeschwindigkeit ohne Nachkommastellen ausgegeben

- 1.5 @ 11.10.2018 18:08<br>
  - _ConditionIcons_ und _ConditionIds_ (Plural) ersetzt durch _ConditionIcon_ und _ConditionId_ (Singular).
  Es wird nur noch der wichtigste Eintrag gespeichert - laut _OpenWeatherMap_ ist das jeweils der erste Eintrag.
  - zusätzliche temporäre Ablage der Originaldaten in internen Buffern und Funktion zum Abruf der Daten (_OpenWeatherMap_GetRawData()_)

- 1.4 @ 10.10.2018 15:27<br>
  - optionale Übernahme der Ids der Wetterbedingungen

- 1.3 @ 09.10.2018 17:38<br>
  - optische Aufbereitung der Wetterinformationen

- 1.2 @ 08.10.2018 22:21<br>
  - Korrektur des Zugriffs auf _Location_

- 1.1 @ 07.10.2018 10:27<br>
  - Sprache der texuellen Informationen per Konfigurationsdialog einstellbar
  - Angabe der Einheiten bestimmer Felder im Konfigurationsdialog

- 1.0 @ 25.09.2018 17:35<br>
  Initiale Version
