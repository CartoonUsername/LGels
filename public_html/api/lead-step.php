<?php
/**
 * Backend für den "Sofort-Check" (Priorität 2, Fallback) – ersetzt das alte
 * mehrstufige Kontaktformular. Statt einmaliger Formular-Absendung schickt
 * das Frontend nach jedem Tap sofort einen Zwischenstand hierher. Jede
 * Session wird über eine client-generierte session_id über mehrere Requests
 * hinweg zu einer Zeile in `leads` zusammengeführt (Upsert). Erst wenn
 * Telefonnummer + Datenschutz-Einwilligung vorliegen, wird die
 * Qualifizierungslogik (Spec Punkt 4) angewendet und final entschieden.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../lib/Database.php';
require __DIR__ . '/../../lib/Qualifier.php';
require __DIR__ . '/../../lib/Scorer.php';
require __DIR__ . '/../../lib/ReferralCode.php';
require __DIR__ . '/../../lib/SmtpMailer.php';
require __DIR__ . '/../../lib/Mailer.php';

function respond(int $code, array $payload): never
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

$configPath = __DIR__ . '/../../config/config.php';
if (!file_exists($configPath)) {
    respond(500, ['ok' => false, 'error' => 'server_misconfigured']);
}
$config = require $configPath;

// Honeypot: unsichtbares Feld, das nur Bots ausfüllen
if (!empty($_POST['website'])) {
    respond(200, ['ok' => true]);
}

$sessionId = trim((string) ($_POST['session_id'] ?? ''));
if (!preg_match('/^[a-zA-Z0-9_-]{8,64}$/', $sessionId)) {
    respond(422, ['ok' => false, 'error' => 'session_id_ungueltig']);
}

// reCAPTCHA v3 nur am finalen Schritt prüfen (wenn die Telefonnummer mitgeschickt wird)
$recaptchaSecret = $config['recaptcha']['secret_key'] ?? '';
if ($recaptchaSecret !== '' && array_key_exists('telefon', $_POST)) {
    $token = $_POST['recaptcha_token'] ?? '';
    if ($token === '' || !verifyRecaptcha($token, $recaptchaSecret, (float) $config['recaptcha']['min_score'])) {
        respond(400, ['ok' => false, 'error' => 'recaptcha_failed']);
    }
}

$pdo = Database::connect($config['db']);

$sucheStmt = $pdo->prepare('SELECT * FROM leads WHERE session_id = :sid');
$sucheStmt->execute(['sid' => $sessionId]);
$bestehend = $sucheStmt->fetch();

// Alters-Bereiche aus dem Tap-Flow auf einen repräsentativen Wert abbilden;
// entscheidend ist nur, ob er innerhalb 40-60 liegt (siehe Qualifier).
$ALTER_BEREICHE = ['unter40' => 30, '40-60' => 50, 'ueber60' => 65];

$eingaben = [];
if (array_key_exists('wohnsituation', $_POST)) {
    $eingaben['wohnsituation'] = strtolower(trim((string) $_POST['wohnsituation'])) === 'eigentuemer' ? 'eigentuemer' : 'mieter';
}
if (array_key_exists('alter_bucket', $_POST) && isset($ALTER_BEREICHE[$_POST['alter_bucket']])) {
    $eingaben['alter_int'] = $ALTER_BEREICHE[$_POST['alter_bucket']];
}
if (array_key_exists('interesse', $_POST)) {
    $eingaben['interesse'] = strtolower(trim((string) $_POST['interesse']));
}
if (array_key_exists('energiekosten', $_POST)) {
    $eingaben['energiekosten'] = trim((string) $_POST['energiekosten']);
}
if (array_key_exists('plz', $_POST)) {
    $eingaben['plz'] = preg_replace('/\D/', '', (string) $_POST['plz']);
}
if (array_key_exists('vorname', $_POST)) {
    $eingaben['vorname'] = trim((string) $_POST['vorname']);
}
if (array_key_exists('telefon', $_POST)) {
    $eingaben['telefon'] = trim((string) $_POST['telefon']);
}
if (array_key_exists('email', $_POST) && trim((string) $_POST['email']) !== '') {
    $eingaben['email'] = strtolower(trim((string) $_POST['email']));
}
if (array_key_exists('datenschutz_ok', $_POST)) {
    $eingaben['datenschutz_ok'] = !empty($_POST['datenschutz_ok']) ? 1 : 0;
}
if (array_key_exists('baujahr_bucket', $_POST) && in_array($_POST['baujahr_bucket'], ['vor-1980', '1980-2000', 'nach-2000'], true)) {
    $eingaben['baujahr_bucket'] = $_POST['baujahr_bucket'];
}
if (array_key_exists('partner_code', $_POST) && preg_match('/^[A-Za-z0-9-]{1,30}$/', (string) $_POST['partner_code'])) {
    $eingaben['partner_code'] = strtoupper((string) $_POST['partner_code']);
}
if (array_key_exists('ist_notfall', $_POST)) {
    $eingaben['ist_notfall'] = !empty($_POST['ist_notfall']) ? 1 : 0;
}

if (empty($eingaben)) {
    respond(422, ['ok' => false, 'error' => 'keine_daten']);
}

if ($bestehend === false) {
    // Neue Session: Rate-Limit nur hier prüfen (einmal pro Besucher, nicht pro Tap)
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $maxProTag = (int) $config['rate_limit']['max_pro_ip_pro_tag'];
    $pdo->prepare(
        'INSERT INTO rate_limits (ip, tag, anzahl) VALUES (:ip, CURDATE(), 1)
         ON DUPLICATE KEY UPDATE anzahl = anzahl + 1'
    )->execute(['ip' => $ip]);
    $countStmt = $pdo->prepare('SELECT anzahl FROM rate_limits WHERE ip = :ip AND tag = CURDATE()');
    $countStmt->execute(['ip' => $ip]);
    if ((int) $countStmt->fetchColumn() > $maxProTag) {
        respond(429, ['ok' => false, 'error' => 'rate_limited']);
    }

    $spalten = array_merge(
        ['session_id' => $sessionId, 'quelle' => 'sofort-check', 'status' => 'in_progress', 'qualifiziert' => 0],
        $eingaben
    );
    $platzhalter = implode(', ', array_map(fn ($k) => ":{$k}", array_keys($spalten)));
    $spaltenNamen = implode(', ', array_keys($spalten));
    try {
        $pdo->prepare("INSERT INTO leads ({$spaltenNamen}) VALUES ({$platzhalter})")->execute($spalten);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            respond(200, ['ok' => true, 'duplikat' => true]);
        }
        throw $e;
    }
} else {
    $setTeile = implode(', ', array_map(fn ($k) => "{$k} = :{$k}", array_keys($eingaben)));
    try {
        $pdo->prepare("UPDATE leads SET {$setTeile} WHERE session_id = :sid")
            ->execute(array_merge($eingaben, ['sid' => $sessionId]));
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            respond(200, ['ok' => true, 'duplikat' => true]);
        }
        throw $e;
    }
}

// Kanonischen Stand frisch aus der DB laden (enthält garantiert alle Spalten)
$leadStmt = $pdo->prepare('SELECT * FROM leads WHERE session_id = :sid');
$leadStmt->execute(['sid' => $sessionId]);
$lead = $leadStmt->fetch();

// Erst finalisieren, sobald Telefonnummer UND Einwilligung vorliegen (letzter Schritt)
if (!empty($lead['telefon']) && !empty($lead['datenschutz_ok'])) {
    $qualifier = new Qualifier($config['qualifizierung']);
    $ergebnis = $qualifier->pruefen($lead, (bool) $lead['ist_notfall']);

    $eigenerCode = null;
    if ($ergebnis['qualifiziert']) {
        $lead['lead_score'] = (new Scorer())->berechne($lead);
        // Empfehlungscode nur für qualifizierte Leads generieren (mit Retry bei
        // der extrem seltenen Kollision, siehe ReferralCode)
        for ($versuch = 0; $versuch < 5; $versuch++) {
            $eigenerCode = ReferralCode::generiere();
            try {
                $pdo->prepare('UPDATE leads SET eigener_code = :code WHERE session_id = :sid')
                    ->execute(['code' => $eigenerCode, 'sid' => $sessionId]);
                break;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'eigener_code')) {
                    continue;
                }
                throw $e;
            }
        }
    }

    $pdo->prepare(
        'UPDATE leads SET status = :status, qualifiziert = :qualifiziert, ablehnungsgrund = :grund, lead_score = :score WHERE session_id = :sid'
    )->execute([
        'status' => $ergebnis['qualifiziert'] ? 'qualifiziert' : 'unqualifiziert',
        'qualifiziert' => $ergebnis['qualifiziert'] ? 1 : 0,
        'grund' => $ergebnis['grund'],
        'score' => $lead['lead_score'] ?? null,
        'sid' => $sessionId,
    ]);

    if ($ergebnis['qualifiziert']) {
        $mailer = new Mailer($config['mail']);
        $mailer->sendeNeuerLeadBenachrichtigung($lead);
    }

    respond(200, [
        'ok' => true,
        'final' => true,
        'qualifiziert' => $ergebnis['qualifiziert'],
        'grund' => $ergebnis['grund'],
        'eigener_code' => $eigenerCode,
    ]);
}

respond(200, ['ok' => true, 'final' => false]);

function verifyRecaptcha(string $token, string $secret, float $minScore): bool
{
    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['secret' => $secret, 'response' => $token]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        return false;
    }

    $data = json_decode($response, true);
    return !empty($data['success']) && ($data['score'] ?? 0) >= $minScore;
}
