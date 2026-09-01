<?php
require __DIR__ . '/_bootstrap.php';

if ($auth->istEingeloggt()) {
    header('Location: index.php');
    exit;
}

$fehler = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($auth->versuchLogin($username, $password)) {
        header('Location: index.php');
        exit;
    }
    $fehler = 'Benutzername oder Passwort falsch.';
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin-Login — Zählerstand</title>
<link rel="stylesheet" href="admin.css">
</head>
<body class="admin-login-body">
  <form class="login-card" method="post" novalidate>
    <h1>Zählerstand</h1>
    <p class="muted">Admin-Bereich</p>
    <?php if ($fehler): ?><p class="error"><?= htmlspecialchars($fehler, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <label for="username">Benutzername</label>
    <input type="text" id="username" name="username" required autofocus>
    <label for="password">Passwort</label>
    <input type="password" id="password" name="password" required>
    <button type="submit" class="btn">Anmelden</button>
  </form>
</body>
</html>
