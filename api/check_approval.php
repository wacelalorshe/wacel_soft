<?php
require_once '../config.php';
header('Content-Type: application/json');

$username = $_POST['username'] ?? '';

if ($username) {
    $stmt = $pdo->prepare("SELECT verification_status, rejection_reason FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo json_encode([
            'approved' => $user['verification_status'] === 'approved',
            'rejected' => $user['verification_status'] === 'rejected',
            'pending' => $user['verification_status'] === 'pending',
            'reason' => $user['rejection_reason']
        ]);
    } else {
        echo json_encode(['error' => 'المستخدم غير موجود']);
    }
} else {
    echo json_encode(['error' => 'اسم المستخدم مطلوب']);
}