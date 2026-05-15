<?php
// api/save_statement.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // استقبال البيانات
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('بيانات غير صالحة');
    }
    
    $client_id = $input['client_id'] ?? null;
    $content = $input['content'] ?? null;
    $statement_id = $input['statement_id'] ?? null;
    
    if (!$client_id || !$content) {
        throw new Exception('بيانات غير مكتملة');
    }
    
    // تنظيف البيانات
    $client_id = filter_var($client_id, FILTER_VALIDATE_INT);
    if (!$client_id) {
        throw new Exception('رقم العميل غير صالح');
    }
    
    // حفظ في قاعدة البيانات
    $db = new PDO('mysql:host=localhost;dbname=your_database;charset=utf8', 'username', 'password');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($statement_id) {
        // تحديث كشف حساب موجود
        $stmt = $db->prepare("UPDATE statements SET content = ?, updated_at = NOW() WHERE id = ? AND client_id = ?");
        $stmt->execute([$content, $statement_id, $client_id]);
    } else {
        // إنشاء كشف حساب جديد
        $stmt = $db->prepare("INSERT INTO statements (client_id, content, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$client_id, $content]);
        $statement_id = $db->lastInsertId();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'تم حفظ كشف الحساب بنجاح',
        'statement_id' => $statement_id
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}