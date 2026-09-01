<?php
require __DIR__ . '/_bootstrap.php';
$auth->erzwingeLogin('login.php');

$stmt = $pdo->query(
    "SELECT p.*,
        COUNT(l.id) AS anzahl_leads,
        SUM(l.status = 'qualifiziert') AS anzahl_qualifiziert
     FROM partner p
     LEFT JOIN leads l ON l.partner_code = p.code
     GROUP BY p.id
     ORDER BY p.erstellt_am DESC"
);
$partner = $stmt->fetchAll();
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Partner — Zählerstand Admin</title>
<link rel="stylesheet" href="admin.css">
</head>
<body>
<header class="admin-header">
  <div class="admin-header-inner">
    <h1>Zählerstand — Partner</h1>
    <nav>
      <a href="index.php">Leads</a>
      <a href="logout.php">Abmelden</a>
    </nav>
  </div>
</header>

<main class="admin-main">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Angemeldet</th><th>Name</th><th>Betrieb</th><th>Typ</th><th>PLZ-Gebiet</th><th>Code</th><th>Vermittelt</th><th>Davon qualifiziert</th></tr>
      </thead>
      <tbody>
        <?php if (!$partner): ?>
          <tr><td colspan="8" class="empty">Noch keine Partner angemeldet.</td></tr>
        <?php endif; ?>
        <?php foreach ($partner as $p): ?>
        <tr>
          <td><?= htmlspecialchars(substr((string) $p['erstellt_am'], 0, 16), ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $p['name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $p['betrieb'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $p['partnertyp'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $p['plz_gebiet'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><code><?= htmlspecialchars((string) $p['code'], ENT_QUOTES, 'UTF-8') ?></code></td>
          <td><?= (int) $p['anzahl_leads'] ?></td>
          <td><?= (int) $p['anzahl_qualifiziert'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>
