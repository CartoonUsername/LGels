<?php
require __DIR__ . '/_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!$auth->istEingeloggt()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'nicht_eingeloggt']);
    exit;
}

$erlaubt = ['offen', 'kontaktiert', 'termin', 'abgeschlossen', 'kein_interesse'];
$id = (int) ($_POST['id'] ?? 0);
$kontaktStatus = (string) ($_POST['kontakt_status'] ?? '');

if ($id <= 0 || !in_array($kontaktStatus, $erlaubt, true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'ungueltige_eingabe']);
    exit;
}

$stmt = $pdo->prepare('UPDATE leads SET kontakt_status = :kontakt WHERE id = :id');
$stmt->execute(['kontakt' => $kontaktStatus, 'id' => $id]);

echo json_encode(['ok' => true]);
