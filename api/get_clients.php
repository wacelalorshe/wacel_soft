<?php
require_once '../config.php';

$currency = isset($_GET['currency']) ? $_GET['currency'] : 'local';

$allowedCurrencies = ['local', 'saudi', 'dollar'];
if (!in_array($currency, $allowedCurrencies)) {
    $currency = 'local';
}

$query = "SELECT p.id, p.name, 
    COALESCE(SUM(CASE WHEN t.transaction_type = 'credit' THEN t.amount ELSE -t.amount END), 0) as balance
FROM partners p
LEFT JOIN transactions t ON p.id = t.partner_id AND t.currency_type = ?
WHERE p.status = 'active'
GROUP BY p.id
ORDER BY p.name";

$stmt = $pdo->prepare($query);
$stmt->execute([$currency]);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalDebit = 0;
$totalCredit = 0;

foreach ($clients as $client) {
    if ($client['balance'] < 0) {
        $totalDebit += abs($client['balance']);
    } else {
        $totalCredit += $client['balance'];
    }
}

header('Content-Type: application/json');
echo json_encode([
    'clients' => $clients,
    'summary' => [
        'total_debit' => $totalDebit,
        'total_credit' => $totalCredit,
        'currency' => $currency
    ]
]);
?>