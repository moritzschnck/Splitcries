<?php
session_start();

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';

require_login();

$pdo = db();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function redirect(string $path): void { header("Location: $path"); exit; }
function eur(int $cents): string { return number_format($cents / 100, 2, ',', '.') . " €"; }

/* Sicherstellen: Tabellen existieren */
$pdo->exec("
CREATE TABLE IF NOT EXISTS groups (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  owner_user_id INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS people (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  group_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  UNIQUE (group_id, name)
);

CREATE TABLE IF NOT EXISTS expenses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  group_id INTEGER NOT NULL,
  paid_by INTEGER NOT NULL,
  amount_cents INTEGER NOT NULL,
  description TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
");

/* Guard: Gruppe muss gesetzt sein */
$groupId = (int)($_SESSION['group_id'] ?? 0);
if ($groupId <= 0) {
  redirect('/groups.php');
}

/* Group-Code laden (für Anzeige) */
$stmt = $pdo->prepare("SELECT code FROM groups WHERE id = :id");
$stmt->execute([':id' => $groupId]);
$group = $stmt->fetch(PDO::FETCH_ASSOC);
$groupCode = $group ? (string)$group['code'] : 'UNKNOWN';

/* Auto-Person: Username automatisch als Person in der Gruppe anlegen */
$username = current_username();
$stmt = $pdo->prepare("INSERT OR IGNORE INTO people (group_id, name) VALUES (:gid, :name)");
$stmt->execute([':gid' => $groupId, ':name' => $username]);

/* POST: Person hinzufügen */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_person') {
  $name = trim($_POST['name'] ?? '');
  if ($name !== '') {
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO people (group_id, name) VALUES (:gid, :name)");
    $stmt->execute([':gid' => $groupId, ':name' => $name]);
  }
  redirect('/group.php');
}

/* POST: Ausgabe hinzufügen */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_expense') {
  $paidBy = (int)($_POST['paid_by'] ?? 0);
  $amountEuro = trim($_POST['amount_eur'] ?? '');
  $desc = trim($_POST['description'] ?? '');

  $amountEuro = str_replace(',', '.', $amountEuro);
  $amountFloat = is_numeric($amountEuro) ? (float)$amountEuro : 0.0;
  $amountCents = (int) round($amountFloat * 100);

  if ($paidBy > 0 && $amountCents > 0 && $desc !== '') {
    $stmt = $pdo->prepare("
      INSERT INTO expenses (group_id, paid_by, amount_cents, description)
      VALUES (:gid, :paid_by, :amount_cents, :description)
    ");
    $stmt->execute([
      ':gid' => $groupId,
      ':paid_by' => $paidBy,
      ':amount_cents' => $amountCents,
      ':description' => $desc
    ]);
  }
  redirect('/group.php');
}

/* Daten laden (nur für diese Gruppe) */
$stmt = $pdo->prepare("SELECT id, name FROM people WHERE group_id = :gid ORDER BY name");
$stmt->execute([':gid' => $groupId]);
$people = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
  SELECT e.id, e.amount_cents, e.description, e.created_at, p.name AS paid_by_name, e.paid_by
  FROM expenses e
  JOIN people p ON p.id = e.paid_by
  WHERE e.group_id = :gid
  ORDER BY e.id DESC
");
$stmt->execute([':gid' => $groupId]);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Abrechnung */
$totalCents = 0;
$paidCentsByPerson = [];
foreach ($people as $p) $paidCentsByPerson[(int)$p['id']] = 0;

foreach ($expenses as $e) {
  $totalCents += (int)$e['amount_cents'];
  $paidCentsByPerson[(int)$e['paid_by']] += (int)$e['amount_cents'];
}

$memberCount = max(1, count($people));
$shareCents = (int) floor($totalCents / $memberCount);

$balance = [];
foreach ($people as $p) {
  $id = (int)$p['id'];
  $balance[$id] = $paidCentsByPerson[$id] - $shareCents;
}

$debtors = [];
$creditors = [];
foreach ($people as $p) {
  $id = (int)$p['id'];
  $b = $balance[$id];
  if ($b < 0) $debtors[] = ['id' => $id, 'amount' => -$b];
  if ($b > 0) $creditors[] = ['id' => $id, 'amount' => $b];
}

$transfers = [];
$i = 0; $j = 0;
while ($i < count($debtors) && $j < count($creditors)) {
  $pay = min($debtors[$i]['amount'], $creditors[$j]['amount']);
  if ($pay > 0) {
    $transfers[] = ['from' => $debtors[$i]['id'], 'to' => $creditors[$j]['id'], 'cents' => $pay];
  }
  $debtors[$i]['amount'] -= $pay;
  $creditors[$j]['amount'] -= $pay;
  if ($debtors[$i]['amount'] === 0) $i++;
  if ($creditors[$j]['amount'] === 0) $j++;
}

$nameById = [];
foreach ($people as $p) $nameById[(int)$p['id']] = $p['name'];
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Splitcries – Gruppe <?= h($groupCode) ?></title>
  <style>
    body { font-family: system-ui, Arial; max-width: 900px; margin: 24px auto; padding: 0 12px; }
    .card { border: 1px solid #ddd; border-radius: 10px; padding: 14px; margin-bottom: 14px; }
    input, select { padding: 8px; }
    button { padding: 8px 12px; cursor: pointer; }
    table { width: 100%; border-collapse: collapse; }
    th, td { text-align: left; padding: 8px; border-bottom: 1px solid #eee; }
  </style>
</head>
<body>
  <h1>Splitcries</h1>
  <p>
    Angemeldet als <strong><?= h($username) ?></strong>
    – Gruppe <strong><?= h($groupCode) ?></strong>
    – <a href="/groups.php">Gruppen</a>
    – <a href="/logout.php">Logout</a>
  </p>

  <div class="card">
    <h2>Person hinzufügen</h2>
    <form method="post">
      <input type="hidden" name="action" value="add_person">
      <input name="name" placeholder="Name" required>
      <button type="submit">Hinzufügen</button>
    </form>
    <p><em>Hinweis: Dein Username wird automatisch als Person in der Gruppe angelegt.</em></p>
  </div>

  <div class="card">
    <h2>Ausgabe hinzufügen</h2>
    <?php if (count($people) < 1): ?>
      <p>Lege zuerst mindestens eine Person an.</p>
    <?php else: ?>
      <form method="post">
        <input type="hidden" name="action" value="add_expense">
        <label>Bezahlt von:</label>
        <select name="paid_by" required>
          <?php foreach ($people as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= h($p['name']) ?></option>
          <?php endforeach; ?>
        </select>

        <label>Betrag (€):</label>
        <input name="amount_eur" placeholder="z.B. 12,50" required>

        <label>Beschreibung:</label>
        <input name="description" placeholder="z.B. Pizza" required>

        <button type="submit">Speichern</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Übersicht</h2>
    <p><strong>Gesamt:</strong> <?= eur($totalCents) ?></p>
    <p><strong>Pro Person (MVP):</strong> <?= eur($shareCents) ?></p>

    <h3>Saldo</h3>
    <ul>
      <?php foreach ($people as $p): ?>
        <?php $b = $balance[(int)$p['id']]; ?>
        <li><?= h($p['name']) ?>: <?= eur($b) ?> (positiv = bekommt Geld)</li>
      <?php endforeach; ?>
    </ul>

    <h3>Wer zahlt wem?</h3>
    <?php if (count($transfers) === 0): ?>
      <p>Keine Transfers nötig (oder zu wenige Daten).</p>
    <?php else: ?>
      <ul>
        <?php foreach ($transfers as $t): ?>
          <li><?= h($nameById[$t['from']]) ?> zahlt <?= h($nameById[$t['to']]) ?> <?= eur($t['cents']) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Ausgaben</h2>
    <?php if (count($expenses) === 0): ?>
      <p>Noch keine Ausgaben.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Bezahlt von</th>
            <th>Betrag</th>
            <th>Beschreibung</th>
            <th>Zeit</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($expenses as $e): ?>
            <tr>
              <td><?= h($e['paid_by_name']) ?></td>
              <td><?= eur((int)$e['amount_cents']) ?></td>
              <td><?= h($e['description']) ?></td>
              <td><?= h($e['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>