<?php
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_filters.php';
$auth->erzwingeLogin('login.php');

[$whereSql, $parameter] = baueLeadFilter($_GET);

$stmt = $pdo->prepare(
    "SELECT erstellt_am, vorname, telefon, email, plz, alter_int, wohnsituation, interesse,
            energiekosten, baujahr_bucket, lead_score, status, kontakt_status, quelle,
            partner_code, ist_notfall, ablehnungsgrund
     FROM leads {$whereSql} ORDER BY erstellt_am DESC"
);
$stmt->execute($parameter);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="leads-' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // BOM, damit Excel Umlaute korrekt anzeigt
fputcsv($out, ['Datum', 'Vorname', 'Telefon', 'E-Mail', 'PLZ', 'Alter', 'Wohnsituation', 'Interesse', 'Energiekosten', 'Baujahr', 'Score', 'Status', 'Kontaktstatus', 'Quelle', 'Partner-Code', 'Notfall', 'Ablehnungsgrund'], ';');

while ($row = $stmt->fetch()) {
    fputcsv($out, [
        $row['erstellt_am'], $row['vorname'], $row['telefon'], $row['email'], $row['plz'],
        $row['alter_int'], $row['wohnsituation'], $row['interesse'], $row['energiekosten'],
        $row['baujahr_bucket'], $row['lead_score'], $row['status'], $row['kontakt_status'],
        $row['quelle'], $row['partner_code'], $row['ist_notfall'] ? 'ja' : 'nein', $row['ablehnungsgrund'],
    ], ';');
}
fclose($out);
