<?php

declare(strict_types=1);

// Einrichtung:
// String-Variable mit Profil "~HTML-Box" anlegen, VariablenID weiter unten eintragen
//
// ID der Instanz von "OpenWeatherMap - OneCall-Datenabruf" konfigurieren
//
// das Script auslösen bei Änderung einer Variablen, z.B "letzte Messung" ('LastMeasurement')
//
//
// die Einstellungen im Script nach Belieben anpassen

// HTML-Box
$varID = xxxxx;
// Instanz von "OpenWeatherMap - OneCall-Datenabruf"
$instID = yyyyy;

$scriptName = IPS_GetName($_IPS['SELF']) . '(' . $_IPS['SELF'] . ')';

$wday2name = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

$daily_forecast_count = IPS_GetProperty($instID, 'daily_forecast_count');

$temperature = GetValueFormatted(IPS_GetObjectIDByIdent('Temperature', $instID));
$humidity = GetValueFormatted(IPS_GetObjectIDByIdent('Humidity', $instID));
$wind_speed = GetValueFormatted(IPS_GetObjectIDByIdent('WindSpeed', $instID));
$has_rain = GetValue(IPS_GetObjectIDByIdent('Rain_1h', $instID)) > 0;
$rain_1h = GetValueFormatted(IPS_GetObjectIDByIdent('Rain_1h', $instID));
$has_snow = GetValue(IPS_GetObjectIDByIdent('Snow_1h', $instID)) > 0;
$snow_1h = GetValueFormatted(IPS_GetObjectIDByIdent('Snow_1h', $instID));
$clouds = GetValueFormatted(IPS_GetObjectIDByIdent('Cloudiness', $instID));
$icon = GetValueString(IPS_GetObjectIDByIdent('ConditionIcon', $instID));

if ($icon != '') {
    $img = '<img src="https://openweathermap.org/img/wn/' . $icon . '@2x.png" style="float: left; padding-left: 10px; padding-right: 5px;" />';
} else {
    $img = '';
}

$col_width = 180;
$total_width = $col_width + $daily_forecast_count * ($col_width + 20);

$day_font_size = 15;
$val_font_size = 13;

$html = '';

$html .= '<table style="width: ' . $total_width . 'px;">' . PHP_EOL;
$html .= '	<tbody>' . PHP_EOL;
$html .= '	    <tr>' . PHP_EOL;
$html .= '			<td align="center" valign="top" style="width: ' . $col_width . 'px; padding: 0px; padding-left: 5px; font-size: ' . $day_font_size . 'px;">' . 'aktuell' . '<br />' . $img . PHP_EOL;
$html .= '				<div>' . PHP_EOL;
$html .= '					<table border="0"; padding="0"; cellspacing="0"; cellpadding=0;>' . PHP_EOL;
$html .= '						<tbody>' . PHP_EOL;
$html .= '					    	<tr>' . PHP_EOL;
$html .= '					    		<td>&nbsp;</td>' . PHP_EOL;
$html .= '					    	</tr>' . PHP_EOL;
$html .= '							<tr>' . PHP_EOL;
$html .= '								<td style="font-size: ' . $val_font_size . 'px; text-align: right;">' . $temperature . '</td>' . PHP_EOL;
$html .= '							</tr>' . PHP_EOL;
$html .= '							<tr>' . PHP_EOL;
$html .= '								<td style="font-size: ' . $val_font_size . 'px; text-align: right;">' . $humidity . '</td>' . PHP_EOL;
$html .= '							</tr>' . PHP_EOL;
$html .= '						</tbody>' . PHP_EOL;
$html .= '					</table>' . PHP_EOL;
$html .= '				</div>' . PHP_EOL;
$html .= '				<div>' . PHP_EOL;
$html .= '					<table border="0"; padding="0"; cellspacing="0"; cellpadding=0;>' . PHP_EOL;
$html .= '						<tbody>' . PHP_EOL;
$html .= '							<tr>' . PHP_EOL;
$html .= '								<td style="font-size: ' . $val_font_size . 'px;">' . 'Ø Wind' . '</td>' . PHP_EOL;
$html .= '								<td>&nbsp;</td>' . PHP_EOL;
$html .= '								<td style="font-size: ' . $val_font_size . 'px;">' . $wind_speed . '</td>' . PHP_EOL;
$html .= '							</tr>' . PHP_EOL;
if ($has_rain == true || $has_snow == false) {
    $html .= '							<tr>' . PHP_EOL;
    $html .= '								<td style="font-size: ' . $val_font_size . 'px;">' . 'Regen 1h' . '</td>' . PHP_EOL;
    $html .= '								<td>&nbsp;</td>' . PHP_EOL;
    $html .= '								<td style="font-size: ' . $val_font_size . 'px;">' . $rain_1h . '</td>' . PHP_EOL;
    $html .= '							</tr>' . PHP_EOL;
}
if ($has_snow == true) {
    $html .= '							<tr>' . PHP_EOL;
    $html .= '								<td style="font-size: ' . $val_font_size . 'px;">' . 'Schnee 1h' . '</td>' . PHP_EOL;
    $html .= '								<td>&nbsp;</td>' . PHP_EOL;
    $html .= '								<td style="font-size: ' . $val_font_size . 'px;">' . $snow_1h . '</td>' . PHP_EOL;
    $html .= '							</tr>' . PHP_EOL;
}
$html .= '							<tr>' . PHP_EOL;
$html .= '								<td style="font-size: ' . $val_font_size . 'px;">' . 'Bewölkung' . '</td>' . PHP_EOL;
$html .= '								<td>&nbsp;</td>' . PHP_EOL;
$html .= '								<td style="font-size: ' . $val_font_size . 'px;">' . $clouds . '</td>' . PHP_EOL;
$html .= '							</tr>' . PHP_EOL;
$html .= '						</tbody>' . PHP_EOL;
$html .= '					</table>' . PHP_EOL;
$html .= '				</div>' . PHP_EOL;
$html .= '			</td>' . PHP_EOL;
$html .= '' . PHP_EOL;

for ($i = 0; $i < $daily_forecast_count; $i++) {
    $pre = 'DailyForecast';
    $post = '_' . sprintf('%02d', $i);

    $timestamp = GetValueInteger(IPS_GetObjectIDByIdent($pre . 'Begin' . $post, $instID));
    $temperature_min = GetValueFormatted(IPS_GetObjectIDByIdent($pre . 'TemperatureMin' . $post, $instID));
    $temperature_max = GetValueFormatted(IPS_GetObjectIDByIdent($pre . 'TemperatureMax' . $post, $instID));
    $wind_speed = GetValueFormatted(IPS_GetObjectIDByIdent($pre . 'WindSpeed' . $post, $instID));
    $has_rain = GetValue(IPS_GetObjectIDByIdent($pre . 'Rain' . $post, $instID)) > 0;
    $rain = GetValueFormatted(IPS_GetObjectIDByIdent($pre . 'Rain' . $post, $instID));
    $has_snow = GetValue(IPS_GetObjectIDByIdent($pre . 'Snow' . $post, $instID)) > 0;
    $snow = GetValueFormatted(IPS_GetObjectIDByIdent($pre . 'Snow' . $post, $instID));
    $clouds = GetValueFormatted(IPS_GetObjectIDByIdent($pre . 'Cloudiness' . $post, $instID));
    $icon = GetValueString(IPS_GetObjectIDByIdent($pre . 'ConditionIcon' . $post, $instID));

    $is_today = date('d.m.Y', $timestamp) == date('d.m.Y', time());
    $weekDay = $is_today ? 'heute' : $wday2name[date('N', $timestamp) - 1];

    if ($icon != '') {
        $img = '<img src="https://openweathermap.org/img/wn/' . $icon . '@2x.png" style="float: left; padding-left: 10px; padding-right: 5px;" />';
    } else {
        $img = '';
    }

    $html .= '		<td align="center" valign="top" style="width: ' . $col_width . 'px; padding: 0px; padding-left: 5px; font-size: ' . $day_font_size . 'px;">' . $weekDay . '<br />' . $img . PHP_EOL;
    $html .= '			<div>' . PHP_EOL;
    $html .= '				<table border="0"; padding="0"; cellspacing="0"; cellpadding=0;>' . PHP_EOL;
    $html .= '					<tbody>' . PHP_EOL;
    $html .= '						<tr>' . PHP_EOL;
    $html .= '							<td>&nbsp;</td>' . PHP_EOL;
    $html .= '						</tr>' . PHP_EOL;
    $html .= '						<tr>' . PHP_EOL;
    $html .= '							<td style="font-size: ' . $val_font_size . 'px; text-align: right;">' . $temperature_min . '</td>' . PHP_EOL;
    $html .= '						</tr>' . PHP_EOL;
    $html .= '						<tr>' . PHP_EOL;
    $html .= '							<td style="font-size: ' . $val_font_size . 'px; text-align: right;">' . $temperature_max . '</td>' . PHP_EOL;
    $html .= '						</tr>' . PHP_EOL;
    $html .= '					</tbody>' . PHP_EOL;
    $html .= '				</table>' . PHP_EOL;
    $html .= '			</div>' . PHP_EOL;
    $html .= '			<div>' . PHP_EOL;
    $html .= '				<table border="0"; padding="0"; cellspacing="0"; cellpadding=0;>' . PHP_EOL;
    $html .= '					<tbody>' . PHP_EOL;
    $html .= '						<tr>' . PHP_EOL;
    $html .= '							<td style="font-size: ' . $val_font_size . 'px;">' . 'Ø Wind' . '</td>' . PHP_EOL;
    $html .= '							<td>&nbsp;</td>' . PHP_EOL;
    $html .= '							<td style="font-size: ' . $val_font_size . 'px;">' . $wind_speed . '</td>' . PHP_EOL;
    $html .= '						</tr>' . PHP_EOL;
    $html .= '						<tr>' . PHP_EOL;
    if ($has_rain == true || $has_snow == false) {
        $html .= '							<td style="font-size: ' . $val_font_size . 'px;">' . 'Regen' . '</td>' . PHP_EOL;
        $html .= '							<td>&nbsp;</td>' . PHP_EOL;
        $html .= '							<td style="font-size: ' . $val_font_size . 'px;">' . $rain . '</td>' . PHP_EOL;
        $html .= '						</tr>' . PHP_EOL;
    }
    if ($has_snow == true) {
        $html .= '							<td style="font-size: ' . $val_font_size . 'px;">' . 'Schnee' . '</td>' . PHP_EOL;
        $html .= '							<td>&nbsp;</td>' . PHP_EOL;
        $html .= '							<td style="font-size: ' . $val_font_size . 'px;">' . $snow . '</td>' . PHP_EOL;
        $html .= '						</tr>' . PHP_EOL;
    }
    $html .= '						<tr>' . PHP_EOL;
    $html .= '							<td style="font-size: ' . $val_font_size . 'px;">' . 'Bewölkung' . '</td>' . PHP_EOL;
    $html .= '							<td>&nbsp;</td>' . PHP_EOL;
    $html .= '							<td style="font-size: ' . $val_font_size . 'px;">' . $clouds . '</td>' . PHP_EOL;
    $html .= '						</tr>' . PHP_EOL;
    $html .= '					</tbody>' . PHP_EOL;
    $html .= '				</table>' . PHP_EOL;
    $html .= '			</div>' . PHP_EOL;
    $html .= '		</td>' . PHP_EOL;
    $html .= '' . PHP_EOL;
}

$html .= '		</tr>' . PHP_EOL;
$html .= '	</tbody>' . PHP_EOL;
$html .= '</table>' . PHP_EOL;

SetValueString($varID, $html);
