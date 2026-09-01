<?php
/**
 * Import-/Filter-Skript (Spec Punkt 1a, 6.2) – Priorität 1.
 *
 * Liest Rohdaten aus einer externen Quelle ein (aktuell: CSV, da das exakte
 * Format der externen Datenbank noch nicht feststeht), normalisiert die
 * Felder, wendet die Qualifizierungslogik aus Punkt 4 an und schreibt nur
 * qualifizierte Leads in die `leads`-Tabelle.
 *
 * Wiederholbar per CLI-Aufruf oder Cronjob (Spec 1a.5):
 *   php import_leads.php /pfad/zu/rohdaten.csv
 *
 * Erwartet eine CSV mit Kopfzeile. Die Spaltennamen müssen nicht exakt
 * passen – siehe Normalizer::FIELD_ALIASES für unterstützte Varianten.
 * Sobald die reale externe Quelle (API, DB-Sync, ...) feststeht, kann hier
 * zusätzlich ein anderer Reader (statt CSV) eingesetzt werden; Normalizer,
 * Qualifier und die DB-Schreiblogik bleiben unverändert wiederverwendbar.
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Nur per CLI ausführbar.');
}

require __DIR__ . '/../lib/Database.php';
require __DIR__ . '/../lib/Normalizer.php';
require __DIR__ . '/../lib/Qualifier.php';
require __DIR__ . '/../lib/Scorer.php';
require __DIR__ . '/../lib/ReferralCode.php';

$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) {
    fwrite(STDERR, "Fehlt: config/config.php (siehe config/config.example.php)\n");
    exit(1);
}
$config = require $configPath;

$inputFile = $argv[1] ?? null;
if ($inputFile === null || !file_exists($inputFile)) {
    fwrite(STDERR, "Nutzung: php import_leads.php /pfad/zu/rohdaten.csv\n");
    exit(1);
}

$pdo = Database::connect($config['db']);
$normalizer = new Normalizer();
$qualifier = new Qualifier($config['qualifizierung']);
$scorer = new Scorer();

$handle = fopen($inputFile, 'r');
if ($handle === false) {
    fwrite(STDERR, "Datei konnte nicht geöffnet werden: {$inputFile}\n");
    exit(1);
}

$header = fgetcsv($handle, 0, ';');
if ($header === false) {
    fwrite(STDERR, "CSV ist leer oder ungültig.\n");
    exit(1);
}
// Falls die Datei mit Komma statt Semikolon getrennt ist, einmal neu versuchen.
if (count($header) === 1) {
    rewind($handle);
    $header = fgetcsv($handle, 0, ',');
    $delimiter = ',';
} else {
    $delimiter = ';';
}

$zeilenGesamt = 0;
$qualifiziertAnzahl = 0;
$abgelehntAnzahl = 0;
$duplikateAnzahl = 0;

$insertStmt = $pdo->prepare(
    'INSERT INTO leads
        (vorname, nachname, telefon, email, plz, alter_int, wohnsituation, interesse, energiekosten, baujahr_bucket, datenschutz_ok, quelle, status, qualifiziert, lead_score, eigener_code)
     VALUES
        (:vorname, :nachname, :telefon, :email, :plz, :alter_int, :wohnsituation, :interesse, :energiekosten, :baujahr_bucket, :datenschutz_ok, :quelle, :status, :qualifiziert, :lead_score, :eigener_code)'
);

$runStmt = $pdo->prepare(
    'INSERT INTO import_runs (dateiname, zeilen_gesamt, qualifiziert_anzahl, abgelehnt_anzahl, duplikate_anzahl, beendet_am)
     VALUES (:dateiname, 0, 0, 0, 0, NULL)'
);
$runStmt->execute(['dateiname' => basename($inputFile)]);
$runId = (int) $pdo->lastInsertId();

while (($zeile = fgetcsv($handle, 0, $delimiter)) !== false) {
    if (count($zeile) === 1 && trim((string) $zeile[0]) === '') {
        continue; // leere Zeile überspringen
    }
    $zeilenGesamt++;

    $rohdaten = @array_combine($header, $zeile);
    if ($rohdaten === false) {
        $abgelehntAnzahl++;
        continue;
    }

    $lead = $normalizer->normalisieren($rohdaten);
    $ergebnis = $qualifier->pruefen($lead);

    if (!$ergebnis['qualifiziert']) {
        $abgelehntAnzahl++;
        fwrite(STDOUT, "Zeile {$zeilenGesamt} abgelehnt: {$ergebnis['grund']}\n");
        continue;
    }

    $lead['lead_score'] = $scorer->berechne($lead);

    $versuche = 0;
    while (true) {
        $versuche++;
        try {
            $insertStmt->execute([
                'vorname' => $lead['vorname'],
                'nachname' => $lead['nachname'],
                'telefon' => $lead['telefon'],
                'email' => $lead['email'],
                'plz' => $lead['plz'],
                'alter_int' => $lead['alter_int'],
                'wohnsituation' => $lead['wohnsituation'],
                'interesse' => $lead['interesse'],
                'energiekosten' => $lead['energiekosten'],
                'baujahr_bucket' => $lead['baujahr_bucket'],
                'datenschutz_ok' => 1,
                'quelle' => 'import:' . basename($inputFile),
                'status' => 'qualifiziert',
                'qualifiziert' => 1,
                'lead_score' => $lead['lead_score'],
                'eigener_code' => ReferralCode::generiere(),
            ]);
            $qualifiziertAnzahl++;
            break;
        } catch (PDOException $e) {
            $istDuplikat = $e->getCode() === '23000';
            $istCodeKollision = $istDuplikat && str_contains($e->getMessage(), 'eigener_code');
            if ($istCodeKollision && $versuche < 5) {
                continue; // extrem seltene Code-Kollision -> neuer Code, nochmal versuchen
            }
            if ($istDuplikat) {
                // Duplikat über telefon/email (UNIQUE KEY) – kein Lead doppelt verkaufen
                $duplikateAnzahl++;
                fwrite(STDOUT, "Zeile {$zeilenGesamt} übersprungen: Duplikat (Telefon/E-Mail bereits vorhanden)\n");
                break;
            }
            throw $e;
        }
    }
}

fclose($handle);

$updateRunStmt = $pdo->prepare(
    'UPDATE import_runs
        SET zeilen_gesamt = :zeilen_gesamt,
            qualifiziert_anzahl = :qualifiziert_anzahl,
            abgelehnt_anzahl = :abgelehnt_anzahl,
            duplikate_anzahl = :duplikate_anzahl,
            beendet_am = NOW()
     WHERE id = :id'
);
$updateRunStmt->execute([
    'zeilen_gesamt' => $zeilenGesamt,
    'qualifiziert_anzahl' => $qualifiziertAnzahl,
    'abgelehnt_anzahl' => $abgelehntAnzahl,
    'duplikate_anzahl' => $duplikateAnzahl,
    'id' => $runId,
]);

fwrite(STDOUT, "\nImport abgeschlossen (Lauf #{$runId}):\n");
fwrite(STDOUT, "  Zeilen gesamt:   {$zeilenGesamt}\n");
fwrite(STDOUT, "  Qualifiziert:    {$qualifiziertAnzahl}\n");
fwrite(STDOUT, "  Abgelehnt:       {$abgelehntAnzahl}\n");
fwrite(STDOUT, "  Duplikate:       {$duplikateAnzahl}\n");
