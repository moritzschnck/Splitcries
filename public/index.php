<?php
session_start();

require_once __DIR__ . '/../app/db.php';

/* LOGIN: Benutzername setzen */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_user') {
  $name = trim($_POST['user_name'] ?? '');
  if ($name !== '') {
    $_SESSION['user_name'] = $name;
  }
  header('Location: /');
  exit;
}

$userName = $_SESSION['user_name'] ?? null;

// Guard: if not "logged in", show login page and stop.
if ($userName === null) {
  ?>
  <!DOCTYPE html>
  <html>
  <head>
    <meta charset="UTF-8">
    <title>Splitcries – Login (MVP)</title>
    <style>
      body { font-family: system-ui, Arial; max-width: 700px; margin: 24px auto; padding: 0 12px; }
      .card { border: 1px solid #ddd; border-radius: 10px; padding: 14px; }
      input { padding: 8px; width: 100%; max-width: 320px; }
      button { padding: 8px 12px; cursor: pointer; }
    </style>
  </head>
  <body>
    <h1>Splitcries</h1>
    <div class="card">
      <h2>Benutzername</h2>
      <form method="post">
        <input type="hidden" name="action" value="set_user">
        <input name="user_name" placeholder="z.B. Alice" required>
        <button type="submit">Start</button>
      </form>
      <p style="margin-top:10px;">
        Hinweis: Für eine Demo mit zwei Nutzern nutze zwei Browser-Kontexte (z.B. normales Fenster + Inkognito).
      </p>
    </div>
  </body>
  </html>
  <?php
  exit;
}

$pdo = db();

/* Tabellen anlegen */
$pdo->exec("
CREATE TABLE IF NOT EXISTS people (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS expenses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  paid_by INTEGER NOT NULL,
  amount_cents INTEGER NOT NULL,
  description TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  FOREIGN KEY (paid_by) REFERENCES people(id)
);
");

/* Helpers */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function redirect(string $path = '/'): void { header("Location: $path"); exit; }

/* POST: Person hinzufügen */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_person') {
  $name = trim($_POST['name'] ?? '');
  if ($name !== '') {
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO people (name) VALUES (:name)");
    $stmt->execute([':name' => $name]);
  }
  redirect('/');
}

/* POST: Ausgabe hinzufügen */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_expense') {
  $paidBy = (int)($_POST['paid_by'] ?? 0);
  $amountEuro = trim($_POST['amount_eur'] ?? '');
  $desc = trim($_POST['description'] ?? '');

  // Betrag robust in Cent umwandeln (z.B. "12.34" oder "12,34")
  $amountEuro = str_replace(',', '.', $amountEuro);
  $amountFloat = is_numeric($amountEuro) ? (float)$amountEuro : 0.0;
  $amountCents = (int) round($amountFloat * 100);

  if ($paidBy > 0 && $amountCents > 0 && $desc !== '') {
    $stmt = $pdo->prepare("
      INSERT INTO expenses (paid_by, amount_cents, description)
      VALUES (:paid_by, :amount_cents, :description)
    ");
    $stmt->execute([
      ':paid_by' => $paidBy,
      ':amount_cents' => $amountCents,
      ':description' => $desc
    ]);
  }
  redirect('/');
}

/* Daten laden */
$people = $pdo->query("SELECT id, name FROM people ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$expenses = $pdo->query("
  SELECT e.id, e.amount_cents, e.description, e.created_at, p.name AS paid_by_name, e.paid_by
  FROM expenses e
  JOIN people p ON p.id = e.paid_by
  ORDER BY e.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

/* Abrechnung berechnen */
$totalCents = 0;
$paidCentsByPerson = [];
foreach ($people as $p) {
  $paidCentsByPerson[$p['id']] = 0;
}

foreach ($expenses as $e) {
  $totalCents += (int)$e['amount_cents'];
  $paidCentsByPerson[(int)$e['paid_by']] += (int)$e['amount_cents'];
}

$memberCount = max(1, count($people));
$shareCents = (int) floor($totalCents / $memberCount); // simple MVP

// Balance: bezahlt - Anteil
$balance = [];
foreach ($people as $p) {
  $id = (int)$p['id'];
  $balance[$id] = $paidCentsByPerson[$id] - $shareCents;
}

/* Transfers erzeugen: Schuldner -> Gläubiger */
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
    $transfers[] = [
      'from' => $debtors[$i]['id'],
      'to' => $creditors[$j]['id'],
      'cents' => $pay
    ];
  }

  $debtors[$i]['amount'] -= $pay;
  $creditors[$j]['amount'] -= $pay;

  if ($debtors[$i]['amount'] === 0) $i++;
  if ($creditors[$j]['amount'] === 0) $j++;
}

/* Namen-Map */
$nameById = [];
foreach ($people as $p) $nameById[(int)$p['id']] = $p['name'];

function eur(int $cents): string {
  return number_format($cents / 100, 2, ',', '.') . " €";
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Splitcries – Kostenaufteilung</title>
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
    Angemeldet als <strong><?= h($userName) ?></strong>
    – <a href="/logout.php">Logout</a>
  </p>

  <div class="card">
    <h2>Person hinzufügen</h2>
    <form method="post">
      <input type="hidden" name="action" value="add_person">
      <input name="name" placeholder="Name" required>
      <button type="submit">Hinzufügen</button>
    </form>
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
          <li>
            <?= h($nameById[$t['from']]) ?> zahlt <?= h($nameById[$t['to']]) ?> <?= eur($t['cents']) ?>
          </li>
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