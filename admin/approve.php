<?php
require_once '../config.php';
require_once '../includes/auth.php';
requireAdmin();

$code = $_GET['code'] ?? '';
$message = '';
$messageType = '';

if ($code) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE verification_code = ? AND verification_status = 'pending'");
    $stmt->execute([$code]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // الموافقة على المستخدم
        $stmt = $pdo->prepare("UPDATE users SET verification_status = 'approved', status = 'active', verified_at = NOW(), verified_by = ?, login_attempts = 0 WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $user['id']]);
        $message = "✅ تمت الموافقة على المستخدم: {$user['fullname']}";
        $messageType = 'success';
    } else {
        $message = '❌ كود التحقق غير صالح أو تمت معالجته مسبقاً';
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الموافقة على مستخدم - دفتر الحسابات</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #0f0f1a, #1a1a2e);
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #fff;
        }
        .container { width: 100%; max-width: 450px; text-align: center; }
        .card {
            background: rgba(26,26,46,0.8);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px 24px;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .icon { font-size: 70px; display: block; margin-bottom: 16px; }
        h1 { font-size: 20px; margin-bottom: 10px; }
        p { color: #8888a0; font-size: 14px; margin-bottom: 20px; }
        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            margin: 16px 0;
        }
        .alert-success { background: rgba(0,214,143,0.15); color: #00d68f; }
        .alert-error { background: rgba(255,71,87,0.15); color: #ff6b6b; }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #6c5ce7;
            color: #fff;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            margin: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <?php if ($message): ?>
                <span class="icon"><?php echo $messageType === 'success' ? '✅' : '❌'; ?></span>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
            <?php else: ?>
                <span class="icon">🤔</span>
                <h1>كود تحقق غير محدد</h1>
                <p>يرجى استخدام الرابط المرسل في واتساب أو تليجرام</p>
            <?php endif; ?>
            <a href="pending_users.php" class="btn">📋 عرض الطلبات المعلقة</a>
            <a href="../index.php" class="btn">🏠 الرئيسية</a>
        </div>
    </div>
</body>
</html>