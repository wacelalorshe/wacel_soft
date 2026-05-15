<?php
/**
 * sync.php - مزامنة مع دعم CORS كامل
 */

// ========== إعدادات CORS ==========
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// الرد على طلب OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ========== الاتصال بقاعدة البيانات ==========
require_once __DIR__ . '/config.php';

// ========== استقبال البيانات ==========
$action = $_POST['action'] ?? '';
$rawData = $_POST['data'] ?? '{}';
$data = json_decode($rawData, true);

if (!is_array($data)) {
    $data = [];
}

// الحصول على user_id
$syncUserId = (int)($data['user_id'] ?? 0);
if ($syncUserId <= 0) {
    try {
        $firstUser = $pdo->query("SELECT id FROM users ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $syncUserId = $firstUser ? (int)$firstUser['id'] : 1;
    } catch (Exception $e) {
        $syncUserId = 1;
    }
}

try {
    switch ($action) {
        case 'partner_add':
            $name = trim($data['name'] ?? '');
            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'اسم العميل مطلوب']);
                exit;
            }
            
            $check = $pdo->prepare("SELECT id FROM partners WHERE name = ? AND user_id = ?");
            $check->execute([$name, $syncUserId]);
            $existingId = $check->fetchColumn();
            if ($existingId) {
                echo json_encode(['success' => true, 'message' => 'موجود مسبقاً', 'server_id' => (int)$existingId]);
                exit;
            }
            
            $stmt = $pdo->prepare("INSERT INTO partners (name, phone, type, user_id, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->execute([$name, $data['phone'] ?? '', $data['type'] ?? 'local', $syncUserId]);
            
            echo json_encode(['success' => true, 'server_id' => (int)$pdo->lastInsertId()]);
            break;
            
        case 'transaction_add':
            $partnerId = (int)($data['partner_id'] ?? 0);
            $amount = abs((float)($data['amount'] ?? 0));
            
            if ($partnerId <= 0 || $amount <= 0) {
                echo json_encode(['success' => false, 'message' => 'بيانات غير مكتملة']);
                exit;
            }
            
            $stmt = $pdo->prepare("INSERT INTO transactions (partner_id, date, details, amount, currency_type, transaction_type, quantity, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $partnerId,
                $data['date'] ?? date('Y-m-d'),
                $data['details'] ?? '',
                $amount,
                $data['currency_type'] ?? 'local',
                $data['transaction_type'] ?? 'debit',
                $data['quantity'] ?? 0,
                $syncUserId
            ]);
            
            echo json_encode(['success' => true, 'server_id' => (int)$pdo->lastInsertId()]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'إجراء غير معروف: ' . $action]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
}