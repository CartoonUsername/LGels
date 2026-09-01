<?php

/**
 * Baut WHERE-Klausel + Parameter aus den GET-Filtern, geteilt zwischen
 * index.php (Ansicht) und export.php (CSV), damit beide immer dieselben
 * Datensätze zeigen/exportieren.
 */
function baueLeadFilter(array $get): array
{
    $bedingungen = [];
    $parameter = [];

    $status = $get['status'] ?? 'qualifiziert';
    if (in_array($status, ['qualifiziert', 'unqualifiziert', 'in_progress'], true)) {
        $bedingungen[] = 'status = :status';
        $parameter['status'] = $status;
    }

    $kontakt = $get['kontakt'] ?? 'alle';
    if (in_array($kontakt, ['offen', 'kontaktiert', 'termin', 'abgeschlossen', 'kein_interesse'], true)) {
        $bedingungen[] = 'kontakt_status = :kontakt';
        $parameter['kontakt'] = $kontakt;
    }

    $zeitraum = $get['zeitraum'] ?? 'alle';
    if ($zeitraum === '7' || $zeitraum === '30') {
        $bedingungen[] = 'erstellt_am >= (NOW() - INTERVAL :tage DAY)';
        $parameter['tage'] = (int) $zeitraum;
    }

    $whereSql = $bedingungen ? 'WHERE ' . implode(' AND ', $bedingungen) : '';
    return [$whereSql, $parameter];
}
