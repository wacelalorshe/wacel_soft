<?php
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// فقط المشرف يمكنه الدخول
requireAdmin();

$userId = $_SESSION['user_id'] ?? 0;
$userName = $_SESSION['user_fullname'] ?? 'المشرف';

// ========== معالجة الإجراءات السريعة ==========
$msg = '';
$msgType = '';

$action = $_GET['action'] ?? '';
$targetUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// موافقة على مستخدم
if ($action === 'approve' && $targetUserId > 0) {
    $stmt = $pdo->prepare("UPDATE users SET verification_status = 'approved', status = 'active', verified_at = NOW(), verified_by = ?, login_attempts = 0, locked_until = NULL WHERE id = ?");
    $stmt->execute([$userId, $targetUserId]);
    $msg = '✅ تمت الموافقة على المستخدم بنجاح';
    $msgType = 'success';
}

// رفض مستخدم
if ($action === 'reject' && $targetUserId > 0) {
    $reason = $_GET['reason'] ?? 'تم الرفض من قبل الإدارة';
    $stmt = $pdo->prepare("UPDATE users SET verification_status = 'rejected', status = 'inactive', rejection_reason = ? WHERE id = ?");
    $stmt->execute([$reason, $targetUserId]);
    $msg = '❌ تم رفض المستخدم';
    $msgType = 'error';
}

// تعطيل/تفعيل مستخدم
if ($action === 'toggle' && $targetUserId > 0 && $targetUserId != $userId) {
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$targetUserId]);
    $currentStatus = $stmt->fetchColumn();
    $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $targetUserId]);
    $msg = $newStatus === 'active' ? '✅ تم تفعيل المستخدم' : '🚫 تم تعطيل المستخدم';
    $msgType = 'success';
}

// تغيير دور مستخدم
if ($action === 'role' && $targetUserId > 0 && $targetUserId != $userId) {
    $newRole = $_GET['role'] ?? 'user';
    $allowedRoles = ['admin', 'manager', 'user'];
    if (in_array($newRole, $allowedRoles)) {
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$newRole, $targetUserId]);
        $msg = '✅ تم تغيير دور المستخدم';
        $msgType = 'success';
    }
}

// حذف مستخدم
if ($action === 'delete' && $targetUserId > 0 && $targetUserId != $userId) {
    // حذف معاملات المستخدم أولاً
    $pdo->prepare("DELETE FROM transactions WHERE user_id = ?")->execute([$targetUserId]);
    $pdo->prepare("DELETE FROM partners WHERE user_id = ?")->execute([$targetUserId]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$targetUserId]);
    $msg = '🗑️ تم حذف المستخدم وجميع بياناته';
    $msgType = 'error';
}

// ========== الإحصائيات ==========
$stats = [];
$stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stats['active_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$stats['pending_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE verification_status = 'pending'")->fetchColumn();
$stats['total_partners'] = $pdo->query("SELECT COUNT(*) FROM partners WHERE status = 'active'")->fetchColumn();
$stats['total_transactions'] = $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
$stats['today_transactions'] = $pdo->query("SELECT COUNT(*) FROM transactions WHERE date = CURDATE()")->fetchColumn();
$stats['admin_count'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$stats['manager_count'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'manager'")->fetchColumn();
$stats['user_count'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();

// ========== جلب البيانات ==========
$tab = $_GET['tab'] ?? 'dashboard';

// الطلبات المعلقة
$pendingUsers = $pdo->query("SELECT * FROM users WHERE verification_status = 'pending' ORDER BY created_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

// جميع المستخدمين
$allUsers = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);

// إحصائيات المستخدمين (لكل مستخدم عدد عملاءه ومعاملاته)
$usersStats = $pdo->query("
    SELECT u.id, u.username, u.fullname, u.role, u.status, u.created_at, u.last_login,
        (SELECT COUNT(*) FROM partners p WHERE p.user_id = u.id) as partner_count,
        (SELECT COUNT(*) FROM transactions t WHERE t.user_id = u.id) as tx_count
    FROM users u 
    ORDER BY u.created_at DESC 
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// آخر النشاطات
$recentActivities = [];
try {
    $recentActivities = $pdo->query("SELECT al.*, u.username, u.fullname FROM activity_log al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentActivities = [];
}

// المستخدمين الأكثر نشاطاً
$topUsers = $pdo->query("
    SELECT u.id, u.username, u.fullname, u.role, u.status,
        COUNT(t.id) as tx_count,
        COALESCE(SUM(t.amount), 0) as total_amount,
        MAX(t.date) as last_activity
    FROM users u 
    LEFT JOIN transactions t ON u.id = t.user_id 
    GROUP BY u.id 
    ORDER BY tx_count DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>لوحة التحكم - دفتر الحسابات</title>
    <meta name="theme-color" content="#0f0f1a">
    <style>
        :root {
            --bg: #0f0f1a;
            --surface: #1a1a2e;
            --surface-light: #252540;
            --primary: #6c5ce7;
            --primary-light: #a29bfe;
            --success: #00d68f;
            --success-bg: rgba(0,214,143,0.1);
            --danger: #ff6b6b;
            --danger-bg: rgba(255,107,107,0.1);
            --warning: #ffd93d;
            --warning-bg: rgba(255,217,61,0.1);
            --info: #74b9ff;
            --info-bg: rgba(116,185,255,0.1);
            --text: #ffffff;
            --text-secondary: #8888a0;
            --text-muted: #5a5a7a;
            --border: #2a2a45;
            --radius: 14px;
            --radius-sm: 10px;
            --radius-lg: 18px;
            --transition: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100dvh;
            -webkit-tap-highlight-color: transparent;
            -webkit-font-smoothing: antialiased;
        }

        .app { max-width: 600px; margin: 0 auto; min-height: 100dvh; }

        /* ========== الهيدر ========== */
        .header {
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .back-btn {
            width: 36px; height: 36px; border-radius: var(--radius-sm);
            background: var(--surface-light); border: 1px solid var(--border);
            color: var(--text); font-size: 16px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            text-decoration: none; transition: var(--transition); flex-shrink: 0;
        }
        .back-btn:active { transform: scale(0.9); }
        .header-info { flex: 1; }
        .header-title { font-size: 17px; font-weight: 700; }
        .header-sub { font-size: 10px; color: var(--text-secondary); }
        .header-badge {
            background: var(--danger); color: #fff; padding: 5px 12px;
            border-radius: 15px; font-size: 11px; font-weight: 700;
            animation: pulse 2s infinite; text-decoration: none;
        }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.6} }

        /* ========== التبويبات ========== */
        .tabs {
            display: flex; gap: 4px; padding: 10px 14px;
            overflow-x: auto; scrollbar-width: none;
            position: sticky; top: 66px; z-index: 99;
            background: var(--bg); border-bottom: 1px solid var(--border);
        }
        .tabs::-webkit-scrollbar { display: none; }
        .tab {
            padding: 9px 14px; border-radius: 20px; border: 1px solid var(--border);
            background: var(--surface); color: var(--text-secondary);
            font-weight: 600; font-size: 11px; cursor: pointer;
            white-space: nowrap; font-family: inherit; transition: var(--transition);
            text-decoration: none; display: flex; align-items: center; gap: 4px; position: relative;
        }
        .tab.active { background: var(--primary); border-color: var(--primary); color: #fff; }
        .tab .badge {
            position: absolute; top: -6px; right: -6px;
            width: 18px; height: 18px; background: var(--danger);
            border-radius: 50%; font-size: 9px; display: flex;
            align-items: center; justify-content: center; font-weight: 700;
        }

        /* ========== المحتوى ========== */
        .content { padding: 14px; padding-bottom: 30px; }

        /* ========== رسالة ========== */
        .alert {
            padding: 12px 16px; border-radius: var(--radius-sm); margin-bottom: 12px;
            font-size: 13px; font-weight: 600; line-height: 1.6;
        }
        .alert-success { background: var(--success-bg); color: var(--success); border: 1px solid rgba(0,214,143,0.3); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border: 1px solid rgba(255,107,107,0.3); }
        .alert-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid rgba(255,217,61,0.3); }

        /* ========== بطاقات الإحصائيات ========== */
        .stats-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 14px;
        }
        .stat-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 12px 8px; text-align: center;
            transition: var(--transition); cursor: default;
        }
        .stat-card:active { transform: scale(0.96); }
        .stat-icon { font-size: 22px; margin-bottom: 4px; }
        .stat-val { font-size: 18px; font-weight: 800; }
        .stat-lbl { font-size: 9px; color: var(--text-secondary); margin-top: 2px; }
        .stat-val.red { color: var(--danger); } .stat-val.green { color: var(--success); }
        .stat-val.blue { color: var(--info); } .stat-val.yellow { color: var(--warning); }

        /* ========== قسم ========== */
        .section-title {
            font-size: 13px; font-weight: 700; color: var(--text-secondary);
            margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px;
            display: flex; justify-content: space-between; align-items: center;
        }

        /* ========== بطاقة مستخدم ========== */
        .user-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius-lg); padding: 14px 16px; margin-bottom: 8px;
            transition: var(--transition);
        }
        .user-card:hover { border-color: var(--primary); }
        .user-card.pending { border-right: 3px solid var(--warning); }
        .user-card.approved { border-right: 3px solid var(--success); }
        .user-card.rejected { border-right: 3px solid var(--danger); }

        .user-row {
            display: flex; align-items: center; gap: 10px; margin-bottom: 8px;
        }
        .user-avatar {
            width: 42px; height: 42px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 16px; flex-shrink: 0;
        }
        .user-avatar.admin { background: linear-gradient(135deg, #ffd93d, #ffa502); color: #000; }
        .user-avatar.manager { background: linear-gradient(135deg, #6c5ce7, #a29bfe); color: #fff; }
        .user-avatar.user { background: var(--surface-light); color: var(--text-secondary); }
        .user-avatar.pending { background: linear-gradient(135deg, #ffd93d, #ffa502); color: #000; animation: pulse-avatar 2s infinite; }
        @keyframes pulse-avatar { 0%,100%{box-shadow:0 0 0 0 rgba(255,217,61,0.4)} 50%{box-shadow:0 0 15px 5px rgba(255,217,61,0.2)} }

        .user-info { flex: 1; min-width: 0; }
        .user-name { font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 6px; }
        .user-role {
            font-size: 9px; padding: 2px 8px; border-radius: 10px; font-weight: 700;
        }
        .user-role.admin { background: rgba(255,217,61,0.2); color: #ffd93d; }
        .user-role.manager { background: rgba(108,92,231,0.2); color: #a29bfe; }
        .user-role.user { background: rgba(136,136,160,0.2); color: #8888a0; }

        .user-meta { font-size: 10px; color: var(--text-secondary); margin-top: 3px; display: flex; gap: 8px; flex-wrap: wrap; }
        .user-meta span { display: flex; align-items: center; gap: 3px; }

        .user-actions { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 6px; }
        .btn-xs {
            padding: 6px 12px; border-radius: 7px; border: 1px solid var(--border);
            background: var(--surface-light); color: var(--text); font-size: 10px;
            font-weight: 600; cursor: pointer; font-family: inherit; text-decoration: none;
            transition: var(--transition); white-space: nowrap; display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-xs:active { transform: scale(0.93); }
        .btn-approve { background: var(--success); border-color: var(--success); color: #fff; }
        .btn-reject { background: var(--danger); border-color: var(--danger); color: #fff; }
        .btn-warning { background: var(--warning); border-color: var(--warning); color: #000; }
        .btn-info { background: var(--primary); border-color: var(--primary); color: #fff; }

        /* ========== نشاط ========== */
        .activity-item {
            display: flex; align-items: center; gap: 10px; padding: 10px 12px;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius-sm); margin-bottom: 6px;
        }
        .activity-icon {
            width: 34px; height: 34px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; flex-shrink: 0; background: var(--surface-light);
        }
        .activity-info { flex: 1; min-width: 0; }
        .activity-text { font-size: 12px; font-weight: 500; }
        .activity-time { font-size: 10px; color: var(--text-secondary); }
        .activity-user { font-size: 10px; color: var(--primary-light); }

        /* ========== فارغ ========== */
        .empty-state { text-align: center; padding: 30px; color: var(--text-secondary); }
        .empty-icon { font-size: 45px; margin-bottom: 8px; }

        /* ========== مودال ========== */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px); z-index: 200;
            display: flex; align-items: flex-end; justify-content: center;
            animation: fadeIn 0.2s ease;
        }
        .modal-sheet {
            background: var(--surface); width: 100%; max-width: 500px;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            padding: 8px 18px 20px; animation: slideUp 0.3s ease;
        }
        .modal-handle { width: 32px; height: 4px; background: var(--border); border-radius: 2px; margin: 0 auto 14px; }
        .modal-title { font-size: 16px; font-weight: 700; text-align: center; margin-bottom: 14px; }
        .input-field {
            width: 100%; padding: 12px; background: var(--surface-light);
            border: 1px solid var(--border); border-radius: var(--radius-sm);
            color: var(--text); font-size: 14px; font-family: inherit; margin-bottom: 10px;
        }
        .input-field:focus { outline: none; border-color: var(--primary); }
        .btn-row { display: flex; gap: 8px; }
        .btn {
            flex: 1; padding: 12px; border-radius: var(--radius-sm); font-weight: 700;
            font-size: 13px; cursor: pointer; border: none; font-family: inherit; transition: var(--transition);
        }
        .btn-cancel { background: var(--surface-light); color: var(--text); border: 1px solid var(--border); }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-primary { background: var(--primary); color: #fff; }

        @keyframes fadeIn { from{opacity:0} to{opacity:1} }
        @keyframes slideUp { from{transform:translateY(100%)} to{transform:translateY(0)} }

        @media (max-width: 400px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
            .stats-grid .stat-card:last-child { grid-column: 1/-1; }
        }
    </style>
</head>
<body>
    <div class="app">
        <!-- ========== الهيدر ========== -->
        <div class="header">
            <a href="../index.php" class="back-btn">←</a>
            <div class="header-info">
                <div class="header-title">👑 لوحة التحكم</div>
                <div class="header-sub">مرحباً، <?php echo htmlspecialchars($userName); ?></div>
            </div>
            <?php if ($stats['pending_users'] > 0): ?>
            <a href="?tab=pending" class="header-badge"><?php echo $stats['pending_users']; ?> طلب</a>
            <?php endif; ?>
        </div>

        <!-- ========== التبويبات ========== -->
        <div class="tabs">
            <a href="?tab=dashboard" class="tab <?php echo $tab === 'dashboard' ? 'active' : ''; ?>">📊 الرئيسية</a>
            <a href="?tab=pending" class="tab <?php echo $tab === 'pending' ? 'active' : ''; ?>">
                ⏳ المعلقة
                <?php if ($stats['pending_users'] > 0): ?>
                <span class="badge"><?php echo $stats['pending_users']; ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=users" class="tab <?php echo $tab === 'users' ? 'active' : ''; ?>">👥 المستخدمين</a>
            <a href="?tab=stats" class="tab <?php echo $tab === 'stats' ? 'active' : ''; ?>">📈 الإحصائيات</a>
            <a href="?tab=activity" class="tab <?php echo $tab === 'activity' ? 'active' : ''; ?>">📋 النشاطات</a>
        </div>

        <!-- ========== المحتوى ========== -->
        <div class="content">
            <?php if ($msg): ?>
                <div class="alert alert-<?php echo $msgType; ?>"><?php echo $msg; ?></div>
            <?php endif; ?>

            <?php if ($tab === 'dashboard'): ?>
                <!-- ====== الإحصائيات ====== -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-val"><?php echo $stats['total_users']; ?></div>
                        <div class="stat-lbl">مستخدم</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⏳</div>
                        <div class="stat-val yellow"><?php echo $stats['pending_users']; ?></div>
                        <div class="stat-lbl">معلق</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">✅</div>
                        <div class="stat-val green"><?php echo $stats['active_users']; ?></div>
                        <div class="stat-lbl">نشط</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">💰</div>
                        <div class="stat-val blue"><?php echo number_format($stats['total_transactions']); ?></div>
                        <div class="stat-lbl">معاملة</div>
                    </div>
                </div>

                <!-- ====== روابط سريعة ====== -->
                <div class="section-title">🔗 روابط سريعة</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px;">
                    <a href="?tab=pending" class="btn-xs btn-warning" style="justify-content:center;padding:12px;">
                        ⏳ طلبات معلقة (<?php echo $stats['pending_users']; ?>)
                    </a>
                    <a href="../index.php" class="btn-xs btn-info" style="justify-content:center;padding:12px;">
                        🏠 الرئيسية
                    </a>
                    <a href="../settings.php" class="btn-xs" style="justify-content:center;padding:12px;">
                        ⚙️ الإعدادات
                    </a>
                    <a href="../logout.php" class="btn-xs btn-reject" style="justify-content:center;padding:12px;">
                        🚪 تسجيل خروج
                    </a>
                </div>

                <!-- ====== المستخدمين الأكثر نشاطاً ====== -->
                <div class="section-title">🔥 المستخدمين الأكثر نشاطاً</div>
                <?php foreach (array_slice($topUsers, 0, 5) as $user): ?>
                <div class="user-card">
                    <div class="user-row">
                        <div class="user-avatar <?php echo $user['role']; ?>">
                            <?php echo mb_substr($user['fullname'], 0, 1); ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name">
                                <?php echo htmlspecialchars($user['fullname']); ?>
                                <span class="user-role <?php echo $user['role']; ?>">
                                    <?php echo $user['role'] === 'admin' ? 'مشرف' : ($user['role'] === 'manager' ? 'مدير' : 'مستخدم'); ?>
                                </span>
                            </div>
                            <div class="user-meta">
                                <span>📊 <?php echo $user['tx_count']; ?> معاملة</span>
                                <span>💰 <?php echo number_format($user['total_amount']); ?></span>
                                <?php if ($user['last_activity']): ?>
                                <span>🕐 <?php echo date('d/m/Y', strtotime($user['last_activity'])); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

            <?php elseif ($tab === 'pending'): ?>
                <!-- ====== الطلبات المعلقة ====== -->
                <div class="section-title">⏳ طلبات التسجيل المعلقة (<?php echo count($pendingUsers); ?>)</div>
                
                <?php if (empty($pendingUsers)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">✅</div>
                        <div>لا توجد طلبات معلقة حالياً</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($pendingUsers as $user): ?>
                    <div class="user-card pending">
                        <div class="user-row">
                            <div class="user-avatar pending">
                                <?php echo mb_substr($user['fullname'], 0, 1); ?>
                            </div>
                            <div class="user-info">
                                <div class="user-name"><?php echo htmlspecialchars($user['fullname']); ?></div>
                                <div class="user-meta">
                                    <span>🔑 @<?php echo htmlspecialchars($user['username']); ?></span>
                                    <span>📱 <?php echo htmlspecialchars($user['phone'] ?? 'لا يوجد'); ?></span>
                                    <span>🗓 <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($user['verification_code'])): ?>
                        <div style="margin-bottom:8px;">
                            <code style="background:rgba(255,217,61,0.15);color:#ffd93d;padding:4px 10px;border-radius:6px;font-size:13px;letter-spacing:2px;font-family:monospace;">
                                🔢 <?php echo htmlspecialchars($user['verification_code']); ?>
                            </code>
                        </div>
                        <?php endif; ?>
                        <div class="user-actions">
                            <a href="?tab=pending&action=approve&user_id=<?php echo $user['id']; ?>" class="btn-xs btn-approve">✅ موافقة</a>
                            <a href="?tab=pending&action=reject&user_id=<?php echo $user['id']; ?>&reason=تم الرفض" class="btn-xs btn-reject">❌ رفض</a>
                            <button class="btn-xs btn-warning" onclick="rejectWithReason(<?php echo $user['id']; ?>)">📝 رفض بسبب</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div style="text-align:center;margin-top:12px;">
                    <a href="https://wa.me/967735981122" target="_blank" class="btn-xs" style="background:#25D366;color:#fff;padding:10px 20px;font-size:12px;">
                        💬 فتح واتساب الإدارة
                    </a>
                </div>

            <?php elseif ($tab === 'users'): ?>
                <!-- ====== جميع المستخدمين ====== -->
                <div class="section-title">👥 جميع المستخدمين (<?php echo $stats['total_users']; ?>)</div>
                
                <?php foreach ($usersStats as $user): ?>
                <div class="user-card <?php echo $user['verification_status'] ?? 'approved'; ?>">
                    <div class="user-row">
                        <div class="user-avatar <?php echo $user['role']; ?>">
                            <?php echo mb_substr($user['fullname'], 0, 1); ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name">
                                <?php echo htmlspecialchars($user['fullname']); ?>
                                <span class="user-role <?php echo $user['role']; ?>">
                                    <?php echo $user['role'] === 'admin' ? '👑 مشرف' : ($user['role'] === 'manager' ? 'مدير' : 'مستخدم'); ?>
                                </span>
                                <?php if ($user['status'] === 'inactive'): ?>
                                    <span style="font-size:9px;color:var(--danger);">(معطل)</span>
                                <?php endif; ?>
                            </div>
                            <div class="user-meta">
                                <span>🔑 @<?php echo htmlspecialchars($user['username']); ?></span>
                                <span>👥 <?php echo $user['partner_count']; ?> عميل</span>
                                <span>💰 <?php echo $user['tx_count']; ?> معاملة</span>
                                <?php if ($user['last_login']): ?>
                                <span>🕐 <?php echo date('d/m/Y', strtotime($user['last_login'])); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($user['id'] != $userId): ?>
                    <div class="user-actions">
                        <a href="?tab=users&action=toggle&user_id=<?php echo $user['id']; ?>" class="btn-xs <?php echo $user['status'] === 'active' ? 'btn-reject' : 'btn-approve'; ?>">
                            <?php echo $user['status'] === 'active' ? '🚫 تعطيل' : '✅ تفعيل'; ?>
                        </a>
                        <?php if ($user['role'] !== 'admin'): ?>
                        <a href="?tab=users&action=role&user_id=<?php echo $user['id']; ?>&role=<?php echo $user['role'] === 'manager' ? 'user' : 'manager'; ?>" class="btn-xs btn-info">
                            🔄 <?php echo $user['role'] === 'manager' ? 'كمستخدم' : 'كمدير'; ?>
                        </a>
                        <?php endif; ?>
                        <a href="?tab=users&action=delete&user_id=<?php echo $user['id']; ?>" class="btn-xs btn-reject" onclick="return confirm('حذف المستخدم وجميع بياناته؟')">
                            🗑️ حذف
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

            <?php elseif ($tab === 'stats'): ?>
                <!-- ====== إحصائيات مفصلة ====== -->
                <div class="section-title">📈 إحصائيات النظام</div>
                
                <div class="stats-grid" style="grid-template-columns: repeat(3,1fr);">
                    <div class="stat-card">
                        <div class="stat-icon">👑</div>
                        <div class="stat-val yellow"><?php echo $stats['admin_count']; ?></div>
                        <div class="stat-lbl">مشرف</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">👨‍💼</div>
                        <div class="stat-val blue"><?php echo $stats['manager_count']; ?></div>
                        <div class="stat-lbl">مدير</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">👤</div>
                        <div class="stat-val"><?php echo $stats['user_count']; ?></div>
                        <div class="stat-lbl">مستخدم</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-val green"><?php echo $stats['total_partners']; ?></div>
                        <div class="stat-lbl">عملاء</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">💰</div>
                        <div class="stat-val"><?php echo number_format($stats['total_transactions']); ?></div>
                        <div class="stat-lbl">معاملات</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">📅</div>
                        <div class="stat-val green"><?php echo $stats['today_transactions']; ?></div>
                        <div class="stat-lbl">اليوم</div>
                    </div>
                </div>

                <!-- ====== توزيع المستخدمين ====== -->
                <div class="section-title">📊 توزيع العملاء لكل مستخدم</div>
                <?php foreach ($usersStats as $user): ?>
                <div class="user-card">
                    <div class="user-row">
                        <div class="user-avatar <?php echo $user['role']; ?>">
                            <?php echo mb_substr($user['fullname'], 0, 1); ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($user['fullname']); ?></div>
                            <div class="user-meta">
                                <span>👥 <?php echo $user['partner_count']; ?> عملاء</span>
                                <span>💰 <?php echo $user['tx_count']; ?> معاملات</span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

            <?php elseif ($tab === 'activity'): ?>
                <!-- ====== سجل النشاطات ====== -->
                <div class="section-title">📋 آخر النشاطات</div>
                
                <?php if (empty($recentActivities)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <div>لا توجد نشاطات مسجلة</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentActivities as $act): ?>
                    <div class="activity-item">
                        <div class="activity-icon">📝</div>
                        <div class="activity-info">
                            <div class="activity-text"><?php echo htmlspecialchars($act['description'] ?? $act['action']); ?></div>
                            <div class="activity-time">
                                <?php echo date('d/m/Y H:i', strtotime($act['created_at'])); ?>
                                <?php if ($act['username']): ?>
                                · <span class="activity-user">@<?php echo htmlspecialchars($act['username']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ========== مودال سبب الرفض ========== -->
    <div class="modal-overlay" id="rejectModal" style="display:none;" onclick="closeRejectModal(event)">
        <div class="modal-sheet" onclick="event.stopPropagation()">
            <div class="modal-handle"></div>
            <div class="modal-title">📝 سبب الرفض</div>
            <input type="hidden" id="rejectUserId">
            <input type="text" class="input-field" id="rejectReason" placeholder="اذكر سبب الرفض...">
            <div class="btn-row">
                <button class="btn btn-cancel" onclick="closeRejectModal()">إلغاء</button>
                <button class="btn btn-danger" onclick="confirmReject()">❌ تأكيد الرفض</button>
            </div>
        </div>
    </div>

    <script>
        function rejectWithReason(userId) {
            document.getElementById('rejectUserId').value = userId;
            document.getElementById('rejectModal').style.display = 'flex';
        }
        
        function closeRejectModal(e) {
            if (e && e.target !== document.getElementById('rejectModal')) return;
            document.getElementById('rejectModal').style.display = 'none';
        }
        
        function confirmReject() {
            var userId = document.getElementById('rejectUserId').value;
            var reason = document.getElementById('rejectReason').value || 'تم الرفض';
            window.location.href = '?tab=pending&action=reject&user_id=' + userId + '&reason=' + encodeURIComponent(reason);
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeRejectModal();
        });
        
        // تحديث تلقائي للإشعارات
        <?php if ($stats['pending_users'] > 0): ?>
        setInterval(function() {
            fetch('../api/check_pending.php')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.count > 0) {
                        document.title = '(' + data.count + ') طلبات معلقة - لوحة التحكم';
                    }
                });
        }, 30000);
        <?php endif; ?>
    </script>
</body>
</html>