<?php
function getTodaySummary($pdo) {
    $sql = "SELECT 
        COALESCE(SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE 0 END), 0) as total_credit,
        COALESCE(SUM(CASE WHEN transaction_type = 'debit' THEN amount ELSE 0 END), 0) as total_debit
    FROM transactions 
    WHERE date = CURDATE() AND currency_type = 'local'";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getTotalBalance($pdo) {
    $sql = "SELECT 
        COALESCE(SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE -amount END), 0) as balance
    FROM transactions 
    WHERE currency_type = 'local'";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetch(PDO::FETCH_COLUMN);
}

function getPartnersWithBalance($pdo) {
    $sql = "SELECT p.id, p.name, 
        COALESCE(SUM(CASE WHEN t.transaction_type = 'credit' THEN t.amount ELSE -t.amount END), 0) as balance,
        COUNT(t.id) as transaction_count
    FROM partners p
    LEFT JOIN transactions t ON p.id = t.partner_id AND t.currency_type = 'local'
    GROUP BY p.id, p.name
    ORDER BY p.name";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPartnerStatement($pdo, $partner_id) {
    $sql = "SELECT t.*, p.name as partner_name
    FROM transactions t
    JOIN partners p ON t.partner_id = p.id
    WHERE t.partner_id = ? AND t.currency_type = 'local'
    ORDER BY t.date DESC, t.id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$partner_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addTransaction($pdo, $data) {
    $sql = "INSERT INTO transactions (partner_id, date, details, amount, currency_type, transaction_type, quantity) 
    VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $data['partner_id'],
        $data['date'],
        $data['details'],
        $data['amount'],
        $data['currency_type'],
        $data['transaction_type'],
        $data['quantity'] ?? 0
    ]);
}
?>