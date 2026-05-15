<?php
/**
 * =============================================================================
 * الهيدر الموحد - Header
 * =============================================================================
 * التاريخ: 2025
 * الإصدار: 3.1 (+ زر تحديث ذكي)
 * =============================================================================
 */

if (!defined('APP_RUNNING')) {
    require_once __DIR__ . '/../config.php';
}

require_once __DIR__ . '/auth.php';

$publicPages = [
    'login.php', 'register.php', 'forgot_password.php', 'reset_password.php',
    'install_auth.php', 'install.php', 'logout.php', 'api.php', 'offline.html',
    'fix_auth.php', 'fix_config.php', 'check.php', 'create_icon.php',
    'manifest.json', 'sw.js'
];

$currentPage = basename($_SERVER['PHP_SELF']);

if (!in_array($currentPage, $publicPages)) {
    if (!isLoggedIn() && isset($pdo)) {
        checkRememberToken($pdo);
    }
    
    if (!isLoggedIn()) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول', 'redirect' => 'login.php']);
            exit;
        }
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit;
    }
    
    $_SESSION['last_activity'] = time();
    
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        $sessionTimeout = 7200;
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > $sessionTimeout)) {
            logout();
            header('Location: login.php?expired=1');
            exit;
        }
    }
}

$headerSettings = [];
try {
    if (isset($pdo)) {
        $currentUserId = $_SESSION['user_id'] ?? 0;
        $stmt = $pdo->prepare("
            SELECT setting_key, setting_value 
            FROM settings 
            WHERE user_id = ? OR user_id IS NULL 
            ORDER BY CASE WHEN user_id = ? THEN 0 ELSE 1 END
        ");
        $stmt->execute([$currentUserId, $currentUserId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($headerSettings[$row['setting_key']])) {
                $headerSettings[$row['setting_key']] = $row['setting_value'];
            }
        }
    }
} catch (Exception $e) {
    $headerSettings = [];
}

$storeName    = $headerSettings['store_name']        ?? 'دفتر الحسابات';
$storeIcon    = $headerSettings['store_icon']        ?? '';
$storeLogo    = $headerSettings['store_logo']        ?? '';
$storePhone   = $headerSettings['store_phone']       ?? '';
$storeAddress = $headerSettings['store_address']     ?? '';
$storeDesc    = $headerSettings['store_description'] ?? '';
$storeEmail   = $headerSettings['store_email']       ?? '';

$darkMode = true;
if (isset($_COOKIE['dark_mode'])) {
    $darkMode = $_COOKIE['dark_mode'] === 'true';
} elseif (isset($headerSettings['dark_mode'])) {
    $darkMode = $headerSettings['dark_mode'] === 'true';
}

if ($darkMode) {
    $themeBg           = $headerSettings['theme_color'] ?? '#0f0f1a';
    $themeSurface      = $headerSettings['card_color'] ?? '#1a1a2e';
    $themeSurfaceLight = '#252540';
    $themePrimary      = $headerSettings['accent_color'] ?? '#6c5ce7';
    $themeText         = $headerSettings['text_color'] ?? '#ffffff';
    $themeTextSec      = '#8888a0';
    $themeTextMuted    = '#5a5a7a';
    $themeBorder       = '#2a2a45';
    $themeInputBg      = '#0f1629';
    $themeSuccess      = '#00d68f';
    $themeDanger       = '#ff6b6b';
    $themeWarning      = '#ffd93d';
    $themeInfo         = '#74b9ff';
    $themeShadow       = 'rgba(0,0,0,0.3)';
    $themeHeaderBg     = 'rgba(15, 15, 26, 0.9)';
    $themeBtnBg        = '#fdcb6e';
    $themeBtnColor     = '#333';
} else {
    $themeBg           = '#f0f2f5';
    $themeSurface      = '#ffffff';
    $themeSurfaceLight = '#f8f9fa';
    $themePrimary      = $headerSettings['accent_color'] ?? '#6c5ce7';
    $themeText         = '#1a1a2e';
    $themeTextSec      = '#6b7280';
    $themeTextMuted    = '#9ca3af';
    $themeBorder       = '#e5e7eb';
    $themeInputBg      = '#f9fafb';
    $themeSuccess      = '#059669';
    $themeDanger       = '#dc2626';
    $themeWarning      = '#d97706';
    $themeInfo         = '#3b82f6';
    $themeShadow       = 'rgba(0,0,0,0.08)';
    $themeHeaderBg     = 'rgba(240, 242, 245, 0.9)';
    $themeBtnBg        = '#2d3436';
    $themeBtnColor     = '#fff';
}

$fontSize     = $headerSettings['font_size']     ?? '14';
$borderRadius = $headerSettings['border_radius'] ?? '12';
$accentColor  = $themePrimary;

$isIndex          = ($currentPage === 'admin.php' || $currentPage === '');
$isClient         = ($currentPage === 'client.php');
$isStatement      = ($currentPage === 'statement.php');
$isSettings       = ($currentPage === 'settings.php');
$isAddTransaction = ($currentPage === 'add_transaction.php');
$isAddPartner     = ($currentPage === 'add_partner.php');

$clientName = '';
$clientId   = 0;
if ($isClient && isset($_GET['id'])) {
    $clientId = (int)$_GET['id'];
    try {
        if (isset($pdo)) {
            $stmt = $pdo->prepare("SELECT name FROM partners WHERE id = ?");
            $stmt->execute([$clientId]);
            $clientName = $stmt->fetchColumn();
        }
    } catch (Exception $e) {}
}

$pageTitle = $storeName;
if ($isClient && $clientName) {
    $pageTitle = $clientName . ' | ' . $storeName;
} elseif ($isStatement) {
    $pageTitle = 'كشف حساب | ' . $storeName;
} elseif ($isSettings) {
    $pageTitle = 'الإعدادات | ' . $storeName;
} elseif ($isAddTransaction) {
    $pageTitle = 'إضافة معاملة | ' . $storeName;
} elseif ($isAddPartner) {
    $pageTitle = 'إضافة عميل | ' . $storeName;
}

$themeIcon = $darkMode ? '☀️' : '🌙';
$themeTip  = $darkMode ? 'التبديل إلى الوضع النهاري' : 'التبديل إلى الوضع الليلي';

function getArabicDayName($day) {
    $days = [
        'Saturday'  => 'السبت',
        'Sunday'    => 'الأحد',
        'Monday'    => 'الإثنين',
        'Tuesday'   => 'الثلاثاء',
        'Wednesday' => 'الأربعاء',
        'Thursday'  => 'الخميس',
        'Friday'    => 'الجمعة'
    ];
    return $days[$day] ?? $day;
}

$currentUserName = $_SESSION['user_fullname'] ?? '';
$currentUserRole = $_SESSION['user_role'] ?? '';

$swPath = 'sw.js';
if (dirname($_SERVER['PHP_SELF']) != '/') {
    $depth = substr_count(dirname($_SERVER['PHP_SELF']), '/');
    $swPath = str_repeat('../', $depth) . 'sw.js';
}

header("Cache-Control: public, max-age=604800, stale-while-revalidate=86400");
header("Pragma: cache");
header("Expires: " . gmdate("D, d M Y H:i:s", time() + 604800) . " GMT");
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="<?php echo $darkMode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    
    <?php if ($storeIcon && file_exists($storeIcon)): ?>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($storeIcon); ?>">
    <link rel="shortcut icon" type="image/png" href="<?php echo htmlspecialchars($storeIcon); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($storeIcon); ?>">
    <?php endif; ?>
    
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="<?php echo $themeBg; ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="<?php echo $darkMode ? 'black-translucent' : 'default'; ?>">
    <meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars($storeName); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($storeDesc ?: $storeName); ?>">
    
    <style>
        :root {
            --bg: <?php echo $themeBg; ?>;
            --surface: <?php echo $themeSurface; ?>;
            --surface-light: <?php echo $themeSurfaceLight; ?>;
            --primary: <?php echo $themePrimary; ?>;
            --primary-light: #a29bfe;
            --primary-dark: #5a4bd1;
            --success: <?php echo $themeSuccess; ?>;
            --success-bg: <?php echo $darkMode ? 'rgba(0, 214, 143, 0.1)' : 'rgba(5, 150, 105, 0.1)'; ?>;
            --danger: <?php echo $themeDanger; ?>;
            --danger-bg: <?php echo $darkMode ? 'rgba(255, 107, 107, 0.1)' : 'rgba(220, 38, 38, 0.08)'; ?>;
            --warning: <?php echo $themeWarning; ?>;
            --warning-bg: <?php echo $darkMode ? 'rgba(255, 217, 61, 0.08)' : 'rgba(217, 119, 6, 0.08)'; ?>;
            --info: <?php echo $themeInfo; ?>;
            --info-bg: <?php echo $darkMode ? 'rgba(116, 185, 255, 0.1)' : 'rgba(59, 130, 246, 0.1)'; ?>;
            --text: <?php echo $themeText; ?>;
            --text-secondary: <?php echo $themeTextSec; ?>;
            --text-muted: <?php echo $themeTextMuted; ?>;
            --border: <?php echo $themeBorder; ?>;
            --input-bg: <?php echo $themeInputBg; ?>;
            --shadow: <?php echo $themeShadow; ?>;
            --header-bg: <?php echo $themeHeaderBg; ?>;
            --font-size: <?php echo $fontSize; ?>px;
            --radius-xs: 8px;
            --radius-sm: 12px;
            --radius: 16px;
            --radius-lg: 20px;
            --radius-xl: 24px;
            --transition: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            font-size: var(--font-size);
            min-height: 100vh;
            min-height: 100dvh;
            -webkit-tap-highlight-color: transparent;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            transition: background var(--transition-slow), color var(--transition-slow);
            overflow-x: hidden;
        }

        .app { max-width: 500px; margin: 0 auto; min-height: 100dvh; position: relative; }

        @media (min-width: 501px) {
            .app {
                border-left: 1px solid var(--border);
                border-right: 1px solid var(--border);
                box-shadow: 0 0 40px var(--shadow);
            }
        }

        .connection-dot { display: none; }

        .sync-indicator {
            display: none;
            text-align: center;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 700;
            position: sticky;
            top: 0;
            z-index: 102;
            transition: all 0.3s ease;
        }
        .sync-indicator.synced  { background: rgba(0,214,143,0.1); color: #00d68f; }
        .sync-indicator.syncing { background: rgba(255,165,2,0.1);  color: #ffa502; animation: pulse 1.5s infinite; }
        .sync-indicator.offline { background: rgba(255,107,107,0.1); color: #ff6b6b; }
        .sync-indicator.error   { background: rgba(255,107,107,0.1); color: #ff6b6b; }

        @keyframes pulse { 
            0%, 100% { opacity: 1; } 
            50%       { opacity: 0.5; } 
        }

        /* ======================================================
           الهيدر الرئيسي
        ====================================================== */
        .main-header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: var(--header-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            transition: all var(--transition-slow);
        }
        .main-header.scrolled { box-shadow: 0 2px 20px var(--shadow); }

        .header-inner {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            max-width: 500px;
            margin: 0 auto;
        }

        .back-btn {
            width: 38px; height: 38px; min-width: 38px;
            border-radius: var(--radius-sm); background: var(--surface);
            border: 1px solid var(--border); color: var(--text);
            font-size: 17px; cursor: pointer; display: flex;
            align-items: center; justify-content: center; text-decoration: none;
            transition: all 0.3s ease; flex-shrink: 0;
        }
        .back-btn:hover { transform: translateX(3px); border-color: var(--primary); }
        .back-btn:active { transform: scale(0.92); }
        .back-btn.hidden { opacity: 0; pointer-events: none; width: 0; min-width: 0; padding: 0; border: none; margin: 0; }

        .header-brand {
            display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0;
            text-decoration: none; color: var(--text); cursor: pointer;
        }
        .header-logo {
            width: 38px; height: 38px; min-width: 38px; border-radius: 10px;
            overflow: hidden; display: flex; align-items: center; justify-content: center;
            background: var(--surface-light); font-size: 20px; border: 1px solid var(--border);
        }
        .header-logo img { width: 100%; height: 100%; object-fit: contain; }
        .header-text { flex: 1; min-width: 0; }
        .header-title    { font-size: 16px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .header-subtitle { font-size: 10px; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 1px; }

        .header-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }

        .hdr-btn {
            width: 36px; height: 36px; border-radius: 10px; border: 1px solid var(--border);
            cursor: pointer; font-size: 15px; transition: all 0.3s ease;
            display: flex; align-items: center; justify-content: center;
            text-decoration: none; background: var(--surface); color: var(--text); position: relative;
        }
        .hdr-btn:hover  { border-color: var(--primary); }
        .hdr-btn:active { transform: scale(0.9); }
        .hdr-btn.statement-btn { background: #ff6b35; border-color: #ff6b35; color: white; }
        .hdr-btn.add-btn       { background: var(--success); border-color: var(--success); color: white; font-size: 18px; font-weight: 300; }
        .hdr-btn.settings-btn:hover { color: var(--primary); border-color: var(--primary); }

        /* ======================================================
           زر التحديث الذكي  🔄
        ====================================================== */
        .refresh-btn {
            width: 36px; height: 36px; border-radius: 10px;
            border: 1.5px solid var(--info);
            background: var(--info-bg);
            color: var(--info);
            cursor: pointer; font-size: 16px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            transition: all 0.3s ease;
            position: relative;
        }
        .refresh-btn:hover  { background: var(--info); color: #fff; transform: scale(1.05); }
        .refresh-btn:active { transform: scale(0.88) rotate(90deg); }

        /* حالة الدوران أثناء التحديث */
        .refresh-btn.spinning .refresh-icon {
            animation: spin-icon 0.7s linear infinite;
        }
        @keyframes spin-icon {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }

        /* شريط الإشعار الخفيف أسفل الهيدر */
        .refresh-toast {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 5px 12px;
            font-size: 11px;
            font-weight: 600;
            border-bottom: 1px solid var(--border);
            transition: all 0.3s ease;
        }
        .refresh-toast.show        { display: flex; }
        .refresh-toast.refreshing  { background: rgba(116,185,255,0.1); color: var(--info); }
        .refresh-toast.done        { background: rgba(0,214,143,0.1);   color: var(--success); }
        /* ====================================================== */

        .conn-status-dot {
            width: 10px; height: 10px; border-radius: 50%;
            display: inline-block; flex-shrink: 0;
            transition: background 0.3s ease;
            box-shadow: 0 0 6px rgba(0,0,0,0.3);
        }
        .conn-status-dot.online  { background: #00d68f; box-shadow: 0 0 8px rgba(0,214,143,0.5); }
        .conn-status-dot.offline { background: #ff6b6b; box-shadow: 0 0 8px rgba(255,107,107,0.5); animation: pulse-dot 1.5s infinite; }
        .conn-status-dot.syncing { background: #ffa502; box-shadow: 0 0 8px rgba(255,165,2,0.5);  animation: pulse-dot 1s infinite; }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: 0.4; transform: scale(0.7); }
        }

        .theme-toggle {
            width: 36px; height: 36px; border-radius: 10px; border: 1px solid var(--border);
            cursor: pointer; font-size: 15px; transition: all 0.3s ease;
            display: flex; align-items: center; justify-content: center;
            background: <?php echo $themeBtnBg; ?>; border-color: <?php echo $themeBtnBg; ?>;
            color: <?php echo $themeBtnColor; ?>; flex-shrink: 0;
        }
        .theme-toggle:hover  { transform: rotate(15deg) scale(1.05); }
        .theme-toggle:active { transform: scale(0.85) rotate(30deg); }
        .theme-toggle .theme-icon { font-size: 16px; transition: transform 0.3s ease; }

        .logout-btn { background: var(--danger-bg); border-color: var(--danger); color: var(--danger); }
        .logout-btn:hover { background: var(--danger); color: #fff; }

        .user-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--success); display: inline-block; }

        .sync-btn-header { background: var(--warning-bg); border-color: var(--warning); color: var(--warning); position: relative; }
        .sync-btn-header.has-pending { animation: pulse-sync 2s infinite; }
        .sync-badge {
            position: absolute; top: -4px; right: -4px;
            background: #ff6b6b; color: white; font-size: 9px;
            width: 16px; height: 16px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; font-weight: 700;
        }
        @keyframes pulse-sync { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }

        @keyframes slideDown { 
            from { opacity: 0; transform: translateY(-10px); } 
            to   { opacity: 1; transform: translateY(0); } 
        }
        .main-header { animation: slideDown 0.4s ease; }

        @media (max-width: 360px) {
            .header-inner  { padding: 8px 10px; gap: 6px; }
            .header-title  { font-size: 14px; }
            .header-logo   { width: 32px; height: 32px; min-width: 32px; font-size: 16px; }
            .back-btn      { width: 32px; height: 32px; min-width: 32px; font-size: 14px; }
            .hdr-btn, .theme-toggle, .refresh-btn { width: 32px; height: 32px; font-size: 13px; }
            .conn-status-dot { width: 8px; height: 8px; }
        }
    </style>
</head>
<body>

    <div class="app">
        <!-- الهيدر الرئيسي -->
        <header class="main-header" id="mainHeader">
            <div class="header-inner">
                
                <!-- زر الرجوع -->
                <a href="index.php" class="back-btn <?php echo $isIndex ? 'hidden' : ''; ?>" title="الرئيسية">←</a>
                
                <!-- شعار واسم المتجر -->
                <a href="index.php" class="header-brand">
                    <div class="header-logo">
                        <?php if ($storeIcon && file_exists($storeIcon)): ?>
                            <img src="<?php echo htmlspecialchars($storeIcon); ?>" alt="<?php echo htmlspecialchars($storeName); ?>" onerror="this.innerHTML='💳'">
                        <?php elseif ($storeLogo && file_exists($storeLogo) && !$isIndex): ?>
                            <img src="<?php echo htmlspecialchars($storeLogo); ?>" alt="<?php echo htmlspecialchars($storeName); ?>" onerror="this.innerHTML='💳'">
                        <?php else: ?>
                            💳
                        <?php endif; ?>
                    </div>
                    
                    <div class="header-text">
                        <div class="header-title">
                            <?php if ($isIndex): ?>
                                # <?php echo htmlspecialchars($storeName); ?>
                            <?php elseif ($isClient && $clientName): ?>
                                👤 <?php echo htmlspecialchars($clientName); ?>
                            <?php elseif ($isStatement): ?>
                                📄 كشف حساب
                            <?php elseif ($isSettings): ?>
                                ⚙️ الإعدادات
                            <?php elseif ($isAddTransaction): ?>
                                💰 إضافة معاملة
                            <?php elseif ($isAddPartner): ?>
                                👥 إضافة عميل
                            <?php else: ?>
                                <?php echo htmlspecialchars($storeName); ?>
                            <?php endif; ?>
                        </div>
                        <div class="header-subtitle">
                            <?php if ($isIndex): ?>
                                <span class="user-dot"></span> <?php echo htmlspecialchars($currentUserName ?: 'مستخدم'); ?>
                            <?php elseif ($isClient): ?>
                                المعاملات والرصيد
                            <?php else: ?>
                                <?php echo htmlspecialchars($storeDesc ?: $storeName); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                
                <!-- أزرار الإجراءات -->
                <div class="header-actions">

                    <!-- نقطة حالة الاتصال -->
                    <span class="conn-status-dot online" id="connStatusDot" title="حالة الاتصال"></span>

                    <!-- زر كشف حساب (يظهر فقط في صفحة العميل) -->
                    <?php if ($isClient && $clientId > 0): ?>
                    <a href="statement.php?id=<?php echo $clientId; ?>" class="hdr-btn statement-btn" title="كشف حساب">📄</a>
                    <?php endif; ?>

                    <!-- زر إضافة عميل (يظهر فقط في الصفحة الرئيسية) -->
                    <?php if ($isIndex): ?>
                    <button class="hdr-btn add-btn" onclick="location.href='add_partner.php'" title="إضافة عميل">＋</button>
                    <?php endif; ?>

                    <!-- ============================================
                         🔄 زر التحديث الذكي (جديد)
                         يمسح كاش الصفحة الحالية ويعيد تحميلها
                         مع مزامنة IndexedDB إن وُجدت
                    ============================================ -->
                    <button class="refresh-btn" id="smartRefreshBtn"
                            onclick="smartRefresh()"
                            title="تحديث البيانات وعرض أحدث المعاملات">
                        <span class="refresh-icon" id="refreshIcon">🔄</span>
                    </button>
                    <!-- ============================================ -->

                    <!-- زر لوحة التحكم / الإعدادات -->
                    <?php if ($currentUserRole === 'admin'): ?>
                        <?php if (!$isSettings): ?>
                        <a href="admin/index.php" class="hdr-btn settings-btn" title="لوحة التحكم">👑</a>
                        <?php endif; ?>
                    <?php elseif ($currentUserRole === 'manager'): ?>
                        <?php if (!$isSettings): ?>
                        <a href="settings.php" class="hdr-btn settings-btn" title="الإعدادات">⚙️</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if (!$isSettings): ?>
                        <a href="settings.php" class="hdr-btn settings-btn" title="الإعدادات">⚙️</a>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- زر تبديل الوضع الليلي/النهاري -->
                    <button class="theme-toggle" onclick="toggleTheme()" title="<?php echo $themeTip; ?>" id="themeToggleBtn">
                        <span class="theme-icon" id="themeIcon"><?php echo $themeIcon; ?></span>
                    </button>

                    <!-- زر تسجيل الخروج -->
                    <a href="logout.php" class="hdr-btn logout-btn" title="تسجيل الخروج"
                       onclick="return confirm('هل أنت متأكد من تسجيل الخروج؟')">🚪</a>
                </div>
            </div>

            <!-- شريط إشعار التحديث -->
            <div class="refresh-toast" id="refreshToast"></div>
        </header>
        <!-- نهاية الهيدر -->

<!-- مكتبات JavaScript الأساسية -->
<script src="js/db.js"></script>
<script src="js/sync.js"></script>

<script>
/* ============================================================
   دالة التحديث الذكي
   1. تُظهر شريط إشعار
   2. تُشغّل المزامنة إن كانت متاحة
   3. تمسح كاش Service Worker للصفحة الحالية
   4. تعيد تحميل الصفحة متجاوزةً الكاش (hard reload)
============================================================ */
async function smartRefresh() {
    var btn  = document.getElementById('smartRefreshBtn');
    var icon = document.getElementById('refreshIcon');
    var toast = document.getElementById('refreshToast');

    // منع الضغط المزدوج
    if (btn.classList.contains('spinning')) return;

    // 1. تفعيل حالة الدوران
    btn.classList.add('spinning');
    btn.disabled = true;

    // 2. إظهار شريط الإشعار
    if (toast) {
        toast.textContent = '⏳ جاري تحديث البيانات...';
        toast.className = 'refresh-toast show refreshing';
    }

    try {
        // 3. مزامنة IndexedDB مع السيرفر إن كان هناك بيانات معلقة
        if (navigator.onLine) {
            if (typeof syncManager !== 'undefined' && syncManager.syncAll) {
                await syncManager.syncAll();
            } else if (typeof localDB !== 'undefined' && localDB.syncQueue) {
                await localDB.syncQueue();
            }
        }

        // 4. مسح كاش Service Worker للصفحة الحالية فقط
        if ('caches' in window) {
            var cacheNames = await caches.keys();
            var currentUrl = location.href.split('?')[0]; // بدون query string

            for (var cacheName of cacheNames) {
                var cache = await caches.open(cacheName);
                // احذف الصفحة الحالية ومواردها الشائعة من الكاش
                await cache.delete(currentUrl);
                await cache.delete(location.href);
                // امسح كذلك طلبات API المرتبطة
                var requests = await cache.keys();
                for (var req of requests) {
                    if (req.url.includes('api.php') || req.url.includes('get_') || req.url.includes('fetch_')) {
                        await cache.delete(req);
                    }
                }
            }
        }

        // 5. إخبار Service Worker بتخطي الكاش عند الطلب القادم
        if (navigator.serviceWorker && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({
                type: 'SKIP_CACHE',
                url: location.href
            });
        }

        // 6. إظهار إشعار النجاح لثانية ثم إعادة التحميل
        if (toast) {
            toast.textContent = '✅ تم التحديث، جاري التحميل...';
            toast.className = 'refresh-toast show done';
        }

        setTimeout(function() {
            // location.reload(true) = hard reload يتجاوز الكاش في المتصفح
            location.reload(true);
        }, 600);

    } catch (err) {
        console.error('خطأ في التحديث الذكي:', err);
        // حتى لو فشلت المزامنة - أعد التحميل على أي حال
        location.reload(true);
    }
}

/* ============================================================
   تبديل الوضع الليلي/النهاري
============================================================ */
function toggleTheme() {
    var html = document.documentElement;
    var isDark = html.classList.contains('dark');
    var newMode = !isDark;
    
    html.classList.toggle('dark', newMode);
    html.classList.toggle('light', !newMode);
    
    var metaTheme = document.querySelector('meta[name="theme-color"]');
    if (metaTheme) metaTheme.content = newMode ? '#0f0f1a' : '#f0f2f5';
    
    var metaApple = document.querySelector('meta[name="apple-mobile-web-app-status-bar-style"]');
    if (metaApple) metaApple.content = newMode ? 'black-translucent' : 'default';
    
    var iconEl = document.getElementById('themeIcon');
    if (iconEl) iconEl.textContent = newMode ? '☀️' : '🌙';
    
    var btn = document.getElementById('themeToggleBtn');
    if (btn) btn.title = newMode ? 'التبديل إلى الوضع النهاري' : 'التبديل إلى الوضع الليلي';
    
    if (newMode) {
        document.documentElement.style.setProperty('--bg', '#0f0f1a');
        document.documentElement.style.setProperty('--surface', '#1a1a2e');
        document.documentElement.style.setProperty('--surface-light', '#252540');
        document.documentElement.style.setProperty('--text', '#ffffff');
        document.documentElement.style.setProperty('--text-secondary', '#8888a0');
        document.documentElement.style.setProperty('--text-muted', '#5a5a7a');
        document.documentElement.style.setProperty('--border', '#2a2a45');
        document.documentElement.style.setProperty('--input-bg', '#0f1629');
        document.documentElement.style.setProperty('--shadow', 'rgba(0,0,0,0.3)');
        document.documentElement.style.setProperty('--header-bg', 'rgba(15, 15, 26, 0.9)');
    } else {
        document.documentElement.style.setProperty('--bg', '#f0f2f5');
        document.documentElement.style.setProperty('--surface', '#ffffff');
        document.documentElement.style.setProperty('--surface-light', '#f8f9fa');
        document.documentElement.style.setProperty('--text', '#1a1a2e');
        document.documentElement.style.setProperty('--text-secondary', '#6b7280');
        document.documentElement.style.setProperty('--text-muted', '#9ca3af');
        document.documentElement.style.setProperty('--border', '#e5e7eb');
        document.documentElement.style.setProperty('--input-bg', '#f9fafb');
        document.documentElement.style.setProperty('--shadow', 'rgba(0,0,0,0.08)');
        document.documentElement.style.setProperty('--header-bg', 'rgba(240, 242, 245, 0.9)');
    }
    
    document.cookie = 'dark_mode=' + newMode + '; path=/; max-age=31536000; SameSite=Lax';
}

window.isWebView = function() {
    var ua = navigator.userAgent;
    return /wv|WebView|Android.*Version\/.*Chrome/.test(ua);
};

function updateConnectionStatus() {
    var dot = document.getElementById('connStatusDot');
    if (!dot) return;
    dot.classList.remove('online', 'offline', 'syncing');
    if (!navigator.onLine) {
        dot.classList.add('offline');
        dot.title = '🔴 وضع الأوفلاين (يتم العرض من الذاكرة)';
    } else {
        dot.classList.add('online');
        dot.title = 'متصل بالإنترنت';
    }
}

async function checkPendingSync() {
    try {
        var btn   = document.getElementById('manualSyncBtn');
        var badge = document.getElementById('syncBadge');
        if (typeof localDB !== 'undefined') {
            var count = await localDB.getSyncQueueCount();
            if (badge && count > 0) {
                badge.style.display = 'flex';
                badge.textContent = count;
                if (btn) btn.classList.add('has-pending');
            } else if (badge) {
                badge.style.display = 'none';
                if (btn) btn.classList.remove('has-pending');
            }
        }
    } catch (e) {
        console.log('خطأ في فحص المزامنة:', e);
    }
}

async function manualSync() {
    var btn = document.getElementById('manualSyncBtn');
    var dot = document.getElementById('connStatusDot');
    if (!navigator.onLine) {
        alert('لا يوجد اتصال بالإنترنت. سيتم المزامنة تلقائياً عند عودة الاتصال.');
        return;
    }
    if (btn) { btn.disabled = true; btn.style.opacity = '0.5'; }
    if (dot)  { dot.classList.remove('online', 'offline'); dot.classList.add('syncing'); dot.title = 'جاري المزامنة...'; }
    try {
        if (typeof syncManager !== 'undefined' && syncManager.syncAll) {
            await syncManager.syncAll();
        } else if (typeof localDB !== 'undefined' && localDB.syncQueue) {
            await localDB.syncQueue();
        }
    } catch (e) { console.error('❌ فشلت المزامنة:', e); }
    if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
    updateConnectionStatus();
    checkPendingSync();
}

document.addEventListener('DOMContentLoaded', function() {
    var header  = document.getElementById('mainHeader');
    if (header) {
        var ticking = false;
        window.addEventListener('scroll', function() {
            if (!ticking) {
                requestAnimationFrame(function() {
                    header.classList.toggle('scrolled', window.scrollY > 10);
                    ticking = false;
                });
                ticking = true;
            }
        }, { passive: true });
    }
    if (document.querySelector('.bottom-nav')) {
        document.body.classList.add('has-bottom-nav');
    }
    updateConnectionStatus();
    checkPendingSync();
});

var prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
prefersDark.addEventListener('change', function(e) {
    if (!document.cookie.match(/dark_mode=([^;]+)/)) { toggleTheme(); }
});

window.addEventListener('online',  function() { updateConnectionStatus(); setTimeout(checkPendingSync, 1000); });
window.addEventListener('offline', function() { updateConnectionStatus(); });

setInterval(checkPendingSync, 10000);

if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('<?php echo $swPath; ?>')
            .then(function(registration) {
                console.log('Service Worker مسجل:', registration.scope);
                registration.addEventListener('updatefound', function() {
                    var newWorker = registration.installing;
                    newWorker.addEventListener('statechange', function() {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            if (confirm('نسخة جديدة من التطبيق متاحة. تحديث الآن؟')) {
                                newWorker.postMessage('skipWaiting');
                                window.location.reload();
                            }
                        }
                    });
                });
            })
            .catch(function(error) { console.log('Service Worker:', error); });
    });
}
</script>
