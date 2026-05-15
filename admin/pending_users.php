<?php
require_once '../config.php';
require_once '../includes/auth.php';
requireAdmin();

$stmt = $pdo->query("SELECT * FROM users WHERE verification_status = 'pending' ORDER BY created_at DESC");
$pendingUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// الموافقة السريعة
if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    $stmt = $pdo->prepare("UPDATE users SET verification_status = 'approved', status = 'active', verified_at = NOW(), verified_by = ? WHERE id = ? AND verification_status = 'pending'");
    $stmt->execute([$_SESSION['user_id'], $id]);
    header('Location: pending_users.php?msg=approved');
    exit;
}

// رفض سريع
if (isset($_GET['reject'])) {
    $id = (int)$_GET['reject'];
    $stmt = $pdo->prepare("UPDATE users SET verification_status = 'rejected', status = 'inactive' WHERE id = ? AND verification_status = 'pending'");
    $stmt->execute([$id]);
    header('Location: pending_users.php?msg=rejected');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الطلبات المعلقة - دفتر الحسابات</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #0f0f1a;
            color: #fff; padding: 20px;
        }
        .container { max-width: 600px; margin: 0 auto; }
        h1 { font-size: 20px; margin-bottom: 16px; text-align: center; }
        .user-card {
            background: #1a1a2e;
            border: 1px solid #2a2a45;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 10px;
        }
        .user-name { font-size: 15px; font-weight: 700; margin-bottom: 4px; }
        .user-info { font-size: 11px; color: #8888a0; margin-bottom: 10px; }
        .user-code {
            display: inline-block;
            background: rgba(108,92,231,0.2);
            color: #ffd93d;
            padding: 4px 10px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 13px;
            margin-bottom: 10px;
        }
        .actions { display: flex; gap: 8px; }
        .btn {
            flex: 1; padding: 10px; border-radius: 8px; text-align: center;
            font-weight: 700; font-size: 12px; cursor: pointer; text-decoration: none;
            border: none; font-family: inherit;
        }
        .btn-approve { background: #00d68f; color: #fff; }
        .btn-reject { background: #ff4757; color: #fff; }
        .btn-back { display: block; text-align: center; margin-top: 16px; color: #6c5ce7; text-decoration: none; font-weight: 600; }
        .empty { text-align: center; padding: 40px; color: #8888a0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 الطلبات المعلقة (<?php echo count($pendingUsers); ?>)</h1>
        
        <?php if (empty($pendingUsers)): ?>
            <div class="empty">
                <div style="font-size:50px;">✅</div>
                <div>لا توجد طلبات معلقة</div>
            </div>
        <?php else: ?>
            <?php foreach ($pendingUsers as $user): ?>
            <div class="user-card">
                <div class="user-name">👤 <?php echo htmlspecialchars($user['fullname']); ?></div>
                <div class="user-info">
                    🗓 <?php echo date('d/m/Y', strtotime($user['created_at'])); ?> | 
                    📱 <?php echo htmlspecialchars($user['phone'] ?? 'لا يوجد'); ?> |
                    🔑 <?php echo htmlspecialchars($user['username']); ?>
                </div>
                <div class="user-code">🔢 <?php echo htmlspecialchars($user['verification_code']); ?></div>
                <div class="actions">
                    <a href="?approve=<?php echo $user['id']; ?>" class="btn btn-approve">✅ موافقة</a>
                    <a href="?reject=<?php echo $user['id']; ?>" class="btn btn-reject">❌ رفض</a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <a href="../index.php" class="btn-back">← العودة للرئيسية</a>
    </div>
</body>
</html>