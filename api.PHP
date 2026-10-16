<?php
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

const EXPENSE_CATS = ['Food','Transport','Rent','Shopping','Entertainment','Bills','Health'];
const INCOME_CATS  = ['Freelance','Salary','Other Income'];

function out($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

try {
    $action = $_GET['action'] ?? '';
    $pdo = db();

    // ---- LIST: history (month/year optional; 0 = no filter) ----
    if ($action === 'list') {
        $m = (int)($_GET['month'] ?? 0);
        $y = (int)($_GET['year'] ?? 0);
        $where = []; $params = [];
        if ($y > 0) { $where[] = 'YEAR(tx_date) = ?';  $params[] = $y; }
        if ($m > 0) { $where[] = 'MONTH(tx_date) = ?'; $params[] = $m; }
        $sql = 'SELECT id, tx_date, category, note, amount FROM transactions'
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY tx_date DESC, id DESC LIMIT 500';
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) { $r['id'] = (int)$r['id']; $r['amount'] = (float)$r['amount']; }
        out(['items' => $rows]);
    }

    // ---- SUMMARY: stats + chart data for a month/year ----
    if ($action === 'summary') {
        $m = max(1, min(12, (int)($_GET['month'] ?? date('n'))));
        $y = (int)($_GET['year'] ?? date('Y'));

        $st = $pdo->prepare(
            'SELECT COALESCE(SUM(CASE WHEN amount > 0 THEN amount END),0) AS income,
                    COALESCE(SUM(CASE WHEN amount < 0 THEN -amount END),0) AS expense
             FROM transactions WHERE YEAR(tx_date) = ? AND MONTH(tx_date) = ?');
        $st->execute([$y, $m]);
        $tot = $st->fetch();

        $st = $pdo->prepare(
            'SELECT category, SUM(-amount) AS total FROM transactions
             WHERE amount < 0 AND YEAR(tx_date) = ? AND MONTH(tx_date) = ?
             GROUP BY category ORDER BY total DESC');
        $st->execute([$y, $m]);
        $cats = array_map(fn($r) => ['category' => $r['category'], 'total' => (float)$r['total']], $st->fetchAll());

        $st = $pdo->prepare(
            'SELECT MONTH(tx_date) AS m,
                    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) AS income,
                    SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END) AS expense
             FROM transactions WHERE YEAR(tx_date) = ? GROUP BY MONTH(tx_date)');
        $st->execute([$y]);
        $monthly = array_fill(1, 12, ['income' => 0.0, 'expense' => 0.0]);
        foreach ($st->fetchAll() as $r) {
            $monthly[(int)$r['m']] = ['income' => (float)$r['income'], 'expense' => (float)$r['expense']];
        }

        out([
            'income'     => (float)$tot['income'],
            'expense'    => (float)$tot['expense'],
            'net'        => (float)$tot['income'] - (float)$tot['expense'],
            'categories' => $cats,
            'monthly'    => array_values($monthly),
        ]);
    }

    // ---- Write actions: POST + JSON body only ----
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['error' => 'POST required'], 405);
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) out(['error' => 'Invalid JSON'], 400);

    if ($action === 'add') {
        $cat  = (string)($body['category'] ?? '');
        $note = trim((string)($body['note'] ?? ''));
        $date = (string)($body['date'] ?? '');
        $amt  = $body['amount'] ?? null;

        if (!in_array($cat, array_merge(EXPENSE_CATS, INCOME_CATS), true)) out(['error' => 'Choose a valid category'], 422);
        if (!is_numeric($amt) || (float)$amt == 0.0 || abs((float)$amt) > 9999999999) out(['error' => 'Enter an amount other than 0'], 422);
        $d = DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) out(['error' => 'Enter a valid date'], 422);
        if (mb_strlen($note) > 255) out(['error' => 'Note must be 255 characters or fewer'], 422);

        // Sign is set by category: income categories are positive, all others are expenses.
        $amount = round(abs((float)$amt), 2) * (in_array($cat, INCOME_CATS, true) ? 1 : -1);

        $st = $pdo->prepare('INSERT INTO transactions (tx_date, category, note, amount) VALUES (?,?,?,?)');
        $st->execute([$date, $cat, $note, $amount]);
        out(['id' => (int)$pdo->lastInsertId()], 201);
    }

    if ($action === 'delete') {
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) out(['error' => 'Invalid id'], 422);
        $st = $pdo->prepare('DELETE FROM transactions WHERE id = ?');
        $st->execute([$id]);
        out(['deleted' => $st->rowCount()]);
    }

    out(['error' => 'Unknown action'], 400);
} catch (Throwable $e) {
    error_log($e->getMessage());
    out(['error' => 'Server error. Check the database connection in db.php.'], 500);
}