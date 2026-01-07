<?php
require_once __DIR__ . '/../app/db.php';

$pdo = db();

/* Tabellen anlegen (nur beim ersten Start relevant) */
$pdo->exec("
CREATE TABLE IF NOT EXISTS people (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS expenses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  paid_by INTEGER NOT NULL,
  amount REAL NOT NULL,
  description TEXT NOT NULL
);
");

/* Testdaten (nur wenn leer) */
$count = $pdo->query("SELECT COUNT(*) FROM people")->fetchColumn();
if ($count == 0) {
    $pdo->exec("
      INSERT INTO people (name) VALUES
      ('Alice'), ('Bob'), ('Charlie');

      INSERT INTO expenses (paid_by, amount, description) VALUES
      (1, 30.00, 'Einkauf'),
      (2, 15.00, 'Pizza');
    ");
}

/* Rechenlogik */
$people = $pdo->query("SELECT * FROM people")->fetchAll();
$expenses = $pdo->query("SELECT * FROM expenses")->fetchAll();

$total = array_sum(array_column($expenses, 'amount'));
$perPerson = $total / count($people);

/* Berechnung */
$balances = [];
foreach ($people as $p) {
    $balances[$p['id']] = 0;
}

foreach ($expenses as $e) {
    $balances[$e['paid_by']] += $e['amount'];
}

foreach ($balances as $id => $paid) {
    $balances[$id] = round($paid - $perPerson, 2);
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Splitcries – Kostenaufteilung</title>
</head>
<body>
<h1>Kostenaufteilung</h1>

<p><strong>Gesamt:</strong> <?= $total ?> €</p>
<p><strong>Pro Person:</strong> <?= round($perPerson, 2) ?> €</p>

<h2>Saldo</h2>
<ul>
<?php foreach ($people as $p): ?>
  <li>
    <?= htmlspecialchars($p['name']) ?>:
    <?= $balances[$p['id']] ?> €
  </li>
<?php endforeach; ?>
</ul>

<p><em>Positiv = bekommt Geld, negativ = schuldet Geld</em></p>
</body>
</html>