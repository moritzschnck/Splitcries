<?php
session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';

require_login();

$pdo = db();

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

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$error = null;

function make_code(int $len = 6): string {
  $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  $code = '';
  for ($i=0; $i<$len; $i++) {
    $code .= $alphabet[random_int(0, strlen($alphabet)-1)];
  }
  return $code;
}

/* Gruppe erstellen */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_group') {
  $code = make_code();
  $stmt = $pdo->prepare("INSERT INTO groups (code, owner_user_id) VALUES (:c, :o)");
  $stmt->execute([':c' => $code, ':o' => current_user_id()]);
  $_SESSION['group_id'] = (int)$pdo->lastInsertId();
  header('Location: /group.php');
  exit;
}

/* Gruppe beitreten */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'join_group') {
  $code = strtoupper(trim($_POST['code'] ?? ''));
  $stmt = $pdo->prepare("SELECT id FROM groups WHERE code = :c");
  $stmt->execute([':c' => $code]);
  $group = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$group) {
    $error = "Gruppe nicht gefunden.";
  } else {
    $_SESSION['group_id'] = (int)$group['id'];
    header('Location: /group.php');
    exit;
  }
}

$username = current_username();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Splitcries – Gruppen</title>
  <style>
    body { font-family: system-ui, Arial; max-width: 900px; margin: 24px auto; padding: 0 12px; }
    .card { border: 1px solid #ddd; border-radius: 10px; padding: 14px; margin-bottom: 14px; }
    input { padding: 8px; }
    button { padding: 8px 12px; cursor: pointer; }
    .error { color: #b00020; }
  </style>
</head>
<body>
  <h1>Splitcries</h1>
  <p>Angemeldet als <strong><?= h($username) ?></strong> – <a href="/logout.php">Logout</a></p>

  <h2>Gruppen</h2>

<?php if (isset($_SESSION['group_id'])): ?>
  <p>Aktive Gruppe-ID: <strong><?= (int)$_SESSION['group_id'] ?></strong> – <a href="/group.php">Zur Gruppe</a></p>
<?php endif; ?>

  <?php if ($error): ?><p class="error"><?= h($error) ?></p><?php endif; ?>

  <div class="card">
    <h3>Gruppe erstellen</h3>
    <form method="post">
      <input type="hidden" name="action" value="create_group">
      <button type="submit">Neue Gruppe erstellen</button>
    </form>
  </div>

  <div class="card">
    <h3>Gruppe beitreten</h3>
    <form method="post">
      <input type="hidden" name="action" value="join_group">
      <input name="code" placeholder="Code z.B. A7K9Q2" required>
      <button type="submit">Beitreten</button>
    </form>
  </div>
</body>
</html>