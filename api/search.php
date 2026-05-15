<?php
/**
 * API البحث - نسخة مصححة للعمل مع MariaDB
 */
require_once '../config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// الحصول على userId
$userId = $_SESSION['user_id'] ?? 0;
$userRole = $_SESSION['user_role'] ?? 'user';
$isAdmin = ($userRole === 'admin');

$query = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'partners';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

try {
    // إذا لم يكن هناك userId، نرجع فارغ
    if ($userId === 0 && !$isAdmin) {
        echo json_encode(['success' => true, 'results' => [], 'count' => 0]);
        exit;
    }
    
    // ========== البحث عن العملاء ==========
    if ($isAdmin) {
        // المشرف يرى الكل
        if (empty($query)) {
            $sql = "SELECT id, name, phone, type FROM partners WHERE status = 'active' ORDER BY name LIMIT {$limit}";
            $stmt = $pdo->query($sql);
        } else {
            $searchTerm = '%' . $query . '%';
            $stmt = $pdo->prepare("SELECT id, name, phone, type FROM partners WHERE status = 'active' AND (name LIKE ? OR phone LIKE ?) ORDER BY name LIMIT {$limit}");
            $stmt->execute([$searchTerm, $searchTerm]);
        }
    } else {
        // مستخدم عادي - يرى عمله فقط
        if (empty($query)) {
            $stmt = $pdo->prepare("SELECT id, name, phone, type FROM partners WHERE status = 'active' AND user_id = ? ORDER BY name LIMIT {$limit}");
            $stmt->execute([$userId]);
        } else {
            $searchTerm = '%' . $query . '%';
            $stmt = $pdo->prepare("SELECT id, name, phone, type FROM partners WHERE status = 'active' AND user_id = ? AND (name LIKE ? OR phone LIKE ?) ORDER BY name LIMIT {$limit}");
            $stmt->execute([$userId, $searchTerm, $searchTerm]);
        }
    }
    
    $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($partners)) $partners = [];
    
    // إضافة balance و tx_count لكل عميل
    $results = [];
    foreach ($partners as $p) {
        // جلب عدد المعاملات والرصيد
        $txStmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE -amount END), 0) as bal FROM transactions WHERE partner_id = ? AND currency_type = 'local'");
        $txStmt->execute([$p['id']]);
        $txData = $txStmt->fetch(PDO::FETCH_ASSOC);
        
        $results[] = [
            'id' => (int)$p['id'],
            'name' => $p['name'],
            'initial' => mb_substr($p['name'], 0, 1),
            'phone' => $p['phone'] ?? '',
            'balance' => (float)($txData['bal'] ?? 0),
            'details' => ($txData['cnt'] ?? 0) . ' معاملة',
            'url' => 'client.php?id=' . $p['id'],
        ];
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'count' => count($results),
        'query' => $query
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطأ: ' . $e->getMessage(),
        'results' => []
    ]);
}