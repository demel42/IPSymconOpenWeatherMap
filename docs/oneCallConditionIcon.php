<?php

declare(strict_types=1);

// Einrichtung:
// String-Variable mit Profil "~HTML-Box" anlegen, VariablenID weiter unten eintragen
//
// ID der Instanz von "OpenWeatherMap - OneCall-Datenabruf" konfigurieren
//
// das Script auslösen bei Änderung der entsprechenden Variablen, z.B "Wetterbedingung" ('ConditionIcon')
//
//

// HTML-Box
$varID = xxxx;
// Instanz von "OpenWeatherMap - OneCall-Datenabruf"
$instID = yyyyy;

$scriptName = IPS_GetName($_IPS['SELF']) . '(' . $_IPS['SELF'] . ')';

$icon = GetValueString(IPS_GetObjectIDByIdent('ConditionIcon', $instID));

$html = '<img src="https://openweathermap.org/img/wn/' . $icon . '@2x.png">';

SetValueString($varID, $html);
