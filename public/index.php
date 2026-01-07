<?php
session_start();
require_once __DIR__ . '/../app/db.php';

$pdo = db();

/* Tabellen (Auth + Groups) */
$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS groups (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  owner_user_id INTEGER NOT NULL,
  FOREIGN KEY (owner_user_id) REFERENCES users(id)
);
");

/* Wenn eingeloggt -> Gruppen-Seite */
if (isset($_SESSION['user_id'])) {
  header('Location: /groups.php');
  exit;
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$error = null;

/* Registrierung */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
  $username = trim($_POST['username'] ?? '');
  $password = (string)($_POST['password'] ?? '');

  if ($username === '' || $password === '') {
    $error = "Bitte Username und Passwort angeben.";
  } else {
    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
      $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (:u, :h)");
      $stmt->execute([':u' => $username, ':h' => $hash]);
      // Auto-login nach Registrierung
      $_SESSION['user_id'] = (int)$pdo->lastInsertId();
      $_SESSION['username'] = $username;
      header('Location: /groups.php');
      exit;
    } catch (Throwable $e) {
      $error = "Username ist bereits vergeben.";
    }
  }
}

/* Login */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
  $username = trim($_POST['username'] ?? '');
  $password = (string)($_POST['password'] ?? '');

  $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = :u");
  $stmt->execute([':u' => $username]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user || !password_verify($password, $user['password_hash'])) {
    $error = "Login fehlgeschlagen.";
  } else {
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = (string)$user['username'];
    header('Location: /groups.php');
    exit;
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Splitcries – Login</title>
  <style>
    body { font-family: system-ui, Arial; max-width: 900px; margin: 24px auto; padding: 0 12px; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .card { border: 1px solid #ddd; border-radius: 10px; padding: 14px; }
    input { padding: 8px; width: 100%; }
    button { padding: 8px 12px; cursor: pointer; }
    .error { color: #b00020; }
  </style>
</head>
<body>
  <h1>Splitcries</h1>

  <?php if ($error): ?>
    <p class="error"><?= h($error) ?></p>
  <?php endif; ?>

  <div class="grid">
    <div class="card">
      <h2>Login</h2>
      <form method="post">
        <input type="hidden" name="action" value="login">
        <label>Username</label>
        <input name="username" required>
        <label>Passwort</label>
        <input name="password" type="password" required>
        <button type="submit">Einloggen</button>
      </form>
    </div>

    <div class="card">
      <h2>Registrieren</h2>
      <form method="post">
        <input type="hidden" name="action" value="register">
        <label>Username</label>
        <input name="username" required>
        <label>Passwort</label>
        <input name="password" type="password" required>
        <button type="submit">Account anlegen</button>
      </form>
    </div>
  </div>
</body>
</html>