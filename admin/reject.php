<?php
require_once '../config.php';
require_once '../includes/auth.php';
requireAdmin();

$code = $_GET['code'] ?? '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    
    $stmt = $pdo->prepare("UPDATE users SET verification_status = 'rejected', status = 'inactive', rejection_reason = ? WHERE verification_code = ? AND verification_status = 'pending'");
    $stmt->execute([$reason, $code]);
    
    $message = '✅ تم رفض المستخدم';
} elseif ($code) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE verification_code = ? AND verification_status = 'pending'");
    $stmt->execute([$code]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>رفض مستخدم - دفتر الحسابات</title>
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
        .container { width: 100%; max-width: 420px; }
        .card {
            background: rgba(26,26,46,0.8);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 28px 22px;
            border: 1px solid rgba(255,255,255,0.08);
        }
        h1 { font-size: 18px; margin-bottom: 16px; text-align: center; }
        .alert { padding: 14px; border-radius: 10px; margin-bottom: 14px; font-size: 13px; font-weight: 600; }
        .alert-success { background: rgba(0,214,143,0.15); color: #00d68f; }
        .form-group { margin-bottom: 14px; }
        label { display: block; font-size: 12px; color: #8888a0; margin-bottom: 5px; font-weight: 600; }
        input, textarea {
            width: 100%; padding: 12px; background: rgba(15,22,41,0.6);
            border: 1.5px solid rgba(255,255,255,0.1); border-radius: 10px;
            color: #fff; font-size: 14px; font-family: inherit;
        }
        textarea { resize: vertical; min-height: 80px; }
        input:focus, textarea:focus { outline: none; border-color: #ff6b6b; }
        .btn {
            width: 100%; padding: 13px; background: #ff4757; color: #fff;
            border: none; border-radius: 10px; font-weight: 700; font-size: 14px;
            cursor: pointer; font-family: inherit;
        }
        .btn:active { transform: scale(0.97); }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>❌ رفض مستخدم</h1>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
                <a href="pending_users.php" class="btn" style="display:block;text-align:center;text-decoration:none;background:#6c5ce7;">📋 عرض الطلبات المعلقة</a>
            <?php elseif (isset($user)): ?>
                <p style="text-align:center;color:#8888a0;margin-bottom:16px;">
                    رفض المستخدم: <strong><?php echo htmlspecialchars($user['fullname']); ?></strong>
                </p>
                <form method="POST">
                    <input type="hidden" name="code" value="<?php echo $code; ?>">
                    <div class="form-group">
                        <label>سبب الرفض</label>
                        <textarea name="reason" placeholder="اذكر سبب الرفض..."></textarea>
                    </div>
                    <button type="submit" class="btn">❌ تأكيد الرفض</button>
                </form>
            <?php else: ?>
                <p style="text-align:center;color:#8888a0;">كود تحقق غير صالح</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>