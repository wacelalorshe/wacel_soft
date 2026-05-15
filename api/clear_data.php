<?php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $pdo->exec("TRUNCATE TABLE transactions");
        $pdo->exec("TRUNCATE TABLE partners");
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        
        echo json_encode(['success' => true, 'message' => 'تم مسح جميع البيانات بنجاح']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ في مسح البيانات']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'طلب غير صالح']);
?>