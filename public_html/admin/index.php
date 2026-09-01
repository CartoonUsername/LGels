<?php
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_filters.php';
$auth->erzwingeLogin('login.php');

const KONTAKT_STATI = ['offen', 'kontaktiert', 'termin', 'abgeschlossen', 'kein_interesse'];
const PRO_SEITE = 25;

$statusFilter = $_GET['status'] ?? 'qualifiziert';
$kontaktFilter = $_GET['kontakt'] ?? 'alle';
$zeitraum = $_GET['zeitraum'] ?? 'alle';
$seite = max(1, (int) ($_GET['seite'] ?? 1));

[$whereSql, $parameter] = baueLeadFilter($_GET);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM leads {$whereSql}");
$countStmt->execute($parameter);
$gesamtAnzahl = (int) $countStmt->fetchColumn();
$seitenAnzahl = max(1, (int) ceil($gesamtAnzahl / PRO_SEITE));
$seite = min($seite, $seitenAnzahl);
$offset = ($seite - 1) * PRO_SEITE;

$listStmt = $pdo->prepare(
    "SELECT * FROM leads {$whereSql} ORDER BY erstellt_am DESC LIMIT " . PRO_SEITE . " OFFSET {$offset}"
);
$listStmt->execute($parameter);
$leads = $listStmt->fetchAll();

$statsStmt = $pdo->query(
    "SELECT
        SUM(status = 'qualifiziert') AS qualifiziert,
        SUM(status = 'qualifiziert' AND erstellt_am >= (NOW() - INTERVAL 7 DAY)) AS qualifiziert_7t,
        SUM(status = 'unqualifiziert') AS unqualifiziert,
        SUM(status = 'in_progress') AS in_progress,
        SUM(kontakt_status = 'offen' AND status = 'qualifiziert') AS offen
     FROM leads"
);
$stats = $statsStmt->fetch();

function waLink(string $telefon): string
{
    $n = preg_replace('/\D/', '', $telefon);
    if (str_starts_with($n, '0')) {
        $n = '49' . substr($n, 1);
    }
    return 'https://wa.me/' . $n;
}

function baueUrl(array $overrides): string
{
    $params = array_merge($_GET, $overrides);
    return '?' . http_build_query($params);
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Leads — Zählerstand Admin</title>
<link rel="stylesheet" href="admin.css">
</head>
<body>
<header class="admin-header">
  <div class="admin-header-inner">
    <h1>Zählerstand — Leads</h1>
    <nav>
      <a href="partner.php">Partner</a>
      <a href="export.php?<?= http_build_query($_GET) ?>">CSV exportieren</a>
      <a href="logout.php">Abmelden</a>
    </nav>
  </div>
</header>

<main class="admin-main">
  <div class="stat-row">
    <div class="stat-card"><span class="stat-value"><?= (int) $stats['qualifiziert'] ?></span><span class="stat-label">Qualifiziert gesamt</span></div>
    <div class="stat-card"><span class="stat-value"><?= (int) $stats['qualifiziert_7t'] ?></span><span class="stat-label">Qualifiziert (7 Tage)</span></div>
    <div class="stat-card"><span class="stat-value"><?= (int) $stats['offen'] ?></span><span class="stat-label">Noch nicht kontaktiert</span></div>
    <div class="stat-card"><span class="stat-value"><?= (int) $stats['in_progress'] ?></span><span class="stat-label">Abgebrochen (in_progress)</span></div>
  </div>

  <form class="filter-bar" method="get">
    <label>Status
      <select name="status" onchange="this.form.submit()">
        <option value="qualifiziert" <?= $statusFilter === 'qualifiziert' ? 'selected' : '' ?>>Qualifiziert</option>
        <option value="unqualifiziert" <?= $statusFilter === 'unqualifiziert' ? 'selected' : '' ?>>Unqualifiziert</option>
        <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>Abgebrochen</option>
        <option value="alle" <?= $statusFilter === 'alle' ? 'selected' : '' ?>>Alle</option>
      </select>
    </label>
    <label>Kontaktstatus
      <select name="kontakt" onchange="this.form.submit()">
        <option value="alle" <?= $kontaktFilter === 'alle' ? 'selected' : '' ?>>Alle</option>
        <?php foreach (KONTAKT_STATI as $k): ?>
          <option value="<?= $k ?>" <?= $kontaktFilter === $k ? 'selected' : '' ?>><?= ucfirst($k) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Zeitraum
      <select name="zeitraum" onchange="this.form.submit()">
        <option value="alle" <?= $zeitraum === 'alle' ? 'selected' : '' ?>>Alle</option>
        <option value="7" <?= $zeitraum === '7' ? 'selected' : '' ?>>Letzte 7 Tage</option>
        <option value="30" <?= $zeitraum === '30' ? 'selected' : '' ?>>Letzte 30 Tage</option>
      </select>
    </label>
  </form>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Datum</th><th>Vorname</th><th>Telefon</th><th>PLZ</th><th>Alter</th>
          <th>Wohnsituation</th><th>Interesse</th><th>Kosten</th><th>Baujahr</th><th>Score</th>
          <th>Status</th><th>Quelle</th><th>Partner</th><th>Kontakt</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$leads): ?>
          <tr><td colspan="15" class="empty">Keine Leads für diesen Filter.</td></tr>
        <?php endif; ?>
        <?php foreach ($leads as $lead): ?>
        <tr data-id="<?= (int) $lead['id'] ?>">
          <td><?= htmlspecialchars(substr((string) $lead['erstellt_am'], 0, 16), ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $lead['vorname'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $lead['telefon'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $lead['plz'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $lead['alter_int'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $lead['wohnsituation'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $lead['interesse'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $lead['energiekosten'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $lead['baujahr_bucket'], ENT_QUOTES, 'UTF-8') ?></td>
          <td>
            <?php if ($lead['lead_score'] !== null): ?>
              <span class="badge badge-score-<?= Scorer::einstufung((int) $lead['lead_score']) ?>"><?= (int) $lead['lead_score'] ?></span>
            <?php endif; ?>
          </td>
          <td><span class="badge badge-<?= htmlspecialchars((string) $lead['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $lead['status'], ENT_QUOTES, 'UTF-8') ?><?= $lead['ist_notfall'] ? ' · Notfall' : '' ?></span></td>
          <td><?= htmlspecialchars((string) $lead['quelle'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $lead['partner_code'], ENT_QUOTES, 'UTF-8') ?></td>
          <td>
            <select class="kontakt-select" data-id="<?= (int) $lead['id'] ?>">
              <?php foreach (KONTAKT_STATI as $k): ?>
                <option value="<?= $k ?>" <?= $lead['kontakt_status'] === $k ? 'selected' : '' ?>><?= ucfirst($k) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td>
            <?php if (!empty($lead['telefon'])): ?>
              <a href="<?= htmlspecialchars(waLink((string) $lead['telefon']), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">WhatsApp</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($seitenAnzahl > 1): ?>
  <div class="pagination">
    <?php for ($p = 1; $p <= $seitenAnzahl; $p++): ?>
      <a href="<?= baueUrl(['seite' => $p]) ?>" class="<?= $p === $seite ? 'active' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</main>

<script>
document.querySelectorAll('.kontakt-select').forEach(function (select) {
  select.addEventListener('change', function () {
    const id = select.dataset.id;
    const kontakt_status = select.value;
    fetch('update-status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'id=' + encodeURIComponent(id) + '&kontakt_status=' + encodeURIComponent(kontakt_status),
    }).then(function (r) {
      if (!r.ok) alert('Speichern fehlgeschlagen.');
    });
  });
});
</script>
</body>
</html>
