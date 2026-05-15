<?php
/**
 * API المزامنة - يدعم Offline و Online
 */
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// للاختبار فقط - عرض الأخطاء
error_reporting(E_ALL);
ini_set('display_errors', 0);

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    // ========== استقبال البيانات ==========
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $data = json_decode($_POST['data'] ?? '{}', true);
        
        if (!is_array($data)) {
            echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة']);
            exit;
        }
        
        // الحصول على user_id من البيانات المرسلة
        $syncUserId = (int)($data['user_id'] ?? 0);
        
        // إذا لم يوجد user_id، نبحث عن المستخدم الأول في قاعدة البيانات
        if ($syncUserId <= 0) {
            $firstUser = $pdo->query("SELECT id FROM users ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $syncUserId = $firstUser ? (int)$firstUser['id'] : 1;
        }
        
        switch ($action) {
            case 'partner_add':
                $name = trim($data['name'] ?? '');
                if (empty($name)) {
                    echo json_encode(['success' => false, 'message' => 'اسم العميل مطلوب']);
                    exit;
                }
                
                // التحقق من عدم التكرار
                $checkStmt = $pdo->prepare("SELECT id FROM partners WHERE name = ? AND user_id = ?");
                $checkStmt->execute([$name, $syncUserId]);
                $existingId = $checkStmt->fetchColumn();
                if ($existingId) {
                    echo json_encode(['success' => true, 'message' => 'موجود مسبقاً', 'server_id' => (int)$existingId]);
                    exit;
                }
                
                $stmt = $pdo->prepare("INSERT INTO partners (name, phone, type, user_id, status) VALUES (?, ?, ?, ?, 'active')");
                $stmt->execute([$name, $data['phone'] ?? '', $data['type'] ?? 'local', $syncUserId]);
                $newId = $pdo->lastInsertId();
                
                echo json_encode(['success' => true, 'server_id' => (int)$newId, 'message' => 'تمت الإضافة']);
                break;
                
            case 'transaction_add':
                $partnerId = (int)($data['partner_id'] ?? 0);
                $amount = abs((float)($data['amount'] ?? 0));
                
                if (empty($partnerId) || $amount <= 0) {
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
                
                echo json_encode(['success' => true, 'server_id' => (int)$pdo->lastInsertId(), 'message' => 'تمت الإضافة']);
                break;
                
            case 'sync_all':
                $synced = ['partners' => 0, 'transactions' => 0];
                
                if (isset($data['partners']) && is_array($data['partners'])) {
                    foreach ($data['partners'] as $p) {
                        $name = trim($p['name'] ?? '');
                        if (empty($name)) continue;
                        
                        $checkStmt = $pdo->prepare("SELECT id FROM partners WHERE name = ? AND user_id = ?");
                        $checkStmt->execute([$name, $syncUserId]);
                        if ($checkStmt->fetch()) continue;
                        
                        $stmt = $pdo->prepare("INSERT INTO partners (name, phone, type, user_id, status) VALUES (?, ?, ?, ?, 'active')");
                        $stmt->execute([$name, $p['phone'] ?? '', $p['type'] ?? 'local', $syncUserId]);
                        $synced['partners']++;
                    }
                }
                
                if (isset($data['transactions']) && is_array($data['transactions'])) {
                    foreach ($data['transactions'] as $tx) {
                        $txPartnerId = (int)($tx['partner_id'] ?? 0);
                        $txAmount = abs((float)($tx['amount'] ?? 0));
                        if (empty($txPartnerId) || $txAmount <= 0) continue;
                        
                        $stmt = $pdo->prepare("INSERT INTO transactions (partner_id, date, details, amount, currency_type, transaction_type, quantity, user_id) VALUES (?, ?, ?, ?, 'local', ?, ?, ?)");
                        $stmt->execute([$txPartnerId, $tx['date'] ?? date('Y-m-d'), $tx['details'] ?? '', $txAmount, $tx['type'] ?? 'debit', $tx['quantity'] ?? 0, $syncUserId]);
                        $synced['transactions']++;
                    }
                }
                
                echo json_encode(['success' => true, 'synced' => $synced]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'إجراء غير معروف: ' . $action]);
        }
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'استخدم POST للمزامنة']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
}