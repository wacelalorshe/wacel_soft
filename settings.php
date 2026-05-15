<?php
define('APP_RUNNING', true);
require_once 'config.php';
// في header.php أو في كل صفحة
header('Service-Worker-Allowed: /');
require_once 'includes/functions.php';

$currentUserId = $_SESSION['user_id'] ?? 0;
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

// ========== جلب الإعدادات (المستخدم أولاً ثم العامة) ==========
$settings = [];
try {
    // ترتيب: إعدادات المستخدم أولاً (user_id = current)، ثم العامة (user_id IS NULL)
    $stmt = $pdo->prepare("
        SELECT * FROM settings 
        WHERE user_id = ? OR user_id IS NULL 
        ORDER BY CASE WHEN user_id = ? THEN 0 ELSE 1 END, sort_order
    ");
    $stmt->execute([$currentUserId, $currentUserId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // لا تستبدل إعدادات المستخدم بالإعدادات العامة
        if (!isset($settings[$row['setting_key']])) {
            $settings[$row['setting_key']] = $row;
        }
    }
} catch (Exception $e) { $settings = []; }

// ========== معالجة الحفظ ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        // إعدادات نصية
        $textSettings = [
            'store_name', 'store_description', 'store_phone', 'store_address', 'store_email',
            'store_currency_local', 'store_currency_saudi', 'store_currency_dollar',
            'theme_color', 'card_color', 'accent_color', 'text_color',
            'secondary_color', 'success_color', 'danger_color', 'warning_color', 'info_color',
            'border_color', 'surface_color', 'input_bg_color', 'header_bg_color',
            'font_size', 'border_radius', 'card_radius', 'btn_radius',
            'items_per_page', 'session_timeout', 'currency_decimals',
            'decimal_separator', 'thousand_separator', 'shadow_opacity', 'blur_amount',
            'index_bg_color', 'index_card_color', 'index_border_color',
            'client_bg_color', 'client_card_color', 'client_border_color',
            'statement_bg_color', 'statement_card_color', 'statement_border_color',
            'settings_bg_color', 'settings_card_color', 'settings_border_color'
        ];
        
        foreach ($textSettings as $key) {
            if (isset($_POST[$key])) {
                $value = trim($_POST[$key]);
                $checkStmt = $pdo->prepare("SELECT id FROM settings WHERE setting_key = ? AND user_id = ?");
                $checkStmt->execute([$key, $currentUserId]);
                
                if ($checkStmt->fetch()) {
                    $updStmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ? AND user_id = ?");
                    $updStmt->execute([$value, $key, $currentUserId]);
                } else {
                    $insStmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_type, setting_group, user_id) VALUES (?, ?, 'text', 'appearance', ?)");
                    $insStmt->execute([$key, $value, $currentUserId]);
                }
            }
        }
        
        // إعدادات boolean
        $boolSettings = [
            'dark_mode', 'show_balance_in_list', 'show_transaction_count',
            'auto_backup', 'enable_notifications', 'show_logo_in_pdf',
            'show_signature', 'compact_mode', 'show_borders', 'show_shadows',
            'rounded_cards', 'glass_effect', 'animated_cards',
            'index_show_borders', 'client_show_borders',
            'statement_show_borders', 'settings_show_borders'
        ];
        
        foreach ($boolSettings as $key) {
            $value = isset($_POST[$key]) ? 'true' : 'false';
            $checkStmt = $pdo->prepare("SELECT id FROM settings WHERE setting_key = ? AND user_id = ?");
            $checkStmt->execute([$key, $currentUserId]);
            
            if ($checkStmt->fetch()) {
                $updStmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ? AND user_id = ?");
                $updStmt->execute([$value, $key, $currentUserId]);
            } else {
                $insStmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_type, setting_group, user_id) VALUES (?, ?, 'boolean', 'appearance', ?)");
                $insStmt->execute([$key, $value, $currentUserId]);
            }
        }
        
        // إعدادات select
        $selectSettings = ['date_format', 'currency_position', 'default_currency', 'backup_frequency', 'language', 'timezone', 'font_family', 'card_style'];
        
        foreach ($selectSettings as $key) {
            if (isset($_POST[$key])) {
                $checkStmt = $pdo->prepare("SELECT id FROM settings WHERE setting_key = ? AND user_id = ?");
                $checkStmt->execute([$key, $currentUserId]);
                
                if ($checkStmt->fetch()) {
                    $updStmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ? AND user_id = ?");
                    $updStmt->execute([$_POST[$key], $key, $currentUserId]);
                } else {
                    $insStmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_type, setting_group, user_id) VALUES (?, ?, 'select', 'appearance', ?)");
                    $insStmt->execute([$key, $_POST[$key], $currentUserId]);
                }
            }
        }
        
        // رفع الصور
        foreach (['store_icon', 'store_logo'] as $imageKey) {
            if (isset($_FILES[$imageKey]) && $_FILES[$imageKey]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$imageKey];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico'];
                
                if (in_array($ext, $allowed)) {
                    $uploadDir = 'uploads/user_' . $currentUserId . '/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    
                    $fileName = $imageKey . '_' . time() . '.' . $ext;
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($file['tmp_name'], $filePath)) {
                        $checkStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? AND user_id = ?");
                        $checkStmt->execute([$imageKey, $currentUserId]);
                        $oldImage = $checkStmt->fetchColumn();
                        if ($oldImage && file_exists($oldImage)) @unlink($oldImage);
                        
                        $checkStmt2 = $pdo->prepare("SELECT id FROM settings WHERE setting_key = ? AND user_id = ?");
                        $checkStmt2->execute([$imageKey, $currentUserId]);
                        
                        if ($checkStmt2->fetch()) {
                            $updStmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ? AND user_id = ?");
                            $updStmt->execute([$filePath, $imageKey, $currentUserId]);
                        } else {
                            $insStmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_type, setting_group, user_id) VALUES (?, ?, 'image', 'store', ?)");
                            $insStmt->execute([$imageKey, $filePath, $currentUserId]);
                        }
                    }
                }
            }
        }
        
        echo json_encode(['success' => true, 'message' => '✅ تم حفظ الإعدادات بنجاح']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '❌ خطأ: ' . $e->getMessage()]);
    }
    exit;
}

// ========== تصدير نسخة احتياطية ==========
if (isset($_GET['backup']) && $_GET['backup'] === 'database') { backupDatabase($pdo); exit; }

// ========== استيراد نسخة ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['restore_file'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $file = $_FILES['restore_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) { echo json_encode(['success' => false, 'message' => '❌ خطأ في رفع الملف']); exit; }
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'sql') { echo json_encode(['success' => false, 'message' => '❌ يرجى اختيار ملف SQL']); exit; }
        if ($file['size'] > 10 * 1024 * 1024) { echo json_encode(['success' => false, 'message' => '❌ حجم الملف كبير جداً']); exit; }
        
        $sql = file_get_contents($file['tmp_name']);
        if (empty(trim($sql))) { echo json_encode(['success' => false, 'message' => '❌ الملف فارغ']); exit; }
        
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->beginTransaction();
        
        $queries = parseSQL($sql);
        $executed = 0; $errors = [];
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (empty($query)) continue;
            try { $pdo->exec($query); $executed++; }
            catch (PDOException $e) { if (!in_array($e->getCode(), ['42S01', '42S21', '42000'])) $errors[] = $e->getMessage(); }
        }
        
        $pdo->commit();
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        $msg = "✅ تم استعادة {$executed} من " . count($queries) . " استعلام";
        if (count($errors) > 0) $msg .= " (تخطي " . count($errors) . ")";
        
        echo json_encode(['success' => true, 'message' => $msg, 'executed' => $executed]);
    } catch (Exception $e) {
        if (isset($pdo)) { try { $pdo->rollBack(); } catch(Exception $ex) {} try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch(Exception $ex) {} }
        echo json_encode(['success' => false, 'message' => '❌ خطأ: ' . $e->getMessage()]);
    }
    exit;
}

function updateConfigFile($pdo) {
    $s = []; $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE user_id IS NULL");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $s[$row['setting_key']] = $row['setting_value'];
    $config = "<?php\n";
    $config .= "define('DB_HOST', '" . DB_HOST . "');\n";
    $config .= "define('DB_NAME', '" . DB_NAME . "');\n";
    $config .= "define('DB_USER', '" . DB_USER . "');\n";
    $config .= "define('DB_PASS', '" . DB_PASS . "');\n\n";
    $config .= "session_start();\n";
    $config .= "try {\n    \$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS);\n    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n} catch(PDOException \$e) { die('فشل الاتصال'); }\n";
    $config .= "require_once __DIR__ . '/includes/functions.php';\n";
    file_put_contents('config.php', $config);
}

function backupDatabase($pdo) {
    $tables = []; $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) $tables[] = $row[0];
    $sql = "-- نسخة احتياطية - " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`"); $row = $stmt->fetch(PDO::FETCH_NUM); $sql .= $row[1] . ";\n\n";
        $stmt = $pdo->query("SELECT * FROM `$table`");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $values = array_map(function($v) use ($pdo) { return $v === null ? 'NULL' : $pdo->quote($v); }, array_values($row));
            $sql .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
        }
        $sql .= "\n";
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="backup_' . date('Y-m-d_H-i-s') . '.sql"');
    echo $sql; exit;
}

function parseSQL($sql) {
    $queries = []; $current = ''; $inString = false; $stringChar = '';
    foreach (explode("\n", $sql) as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '--') === 0 || strpos($line, '#') === 0) continue;
        for ($i = 0; $i < strlen($line); $i++) {
            $c = $line[$i];
            if (!$inString && ($c === "'" || $c === '"')) { $inString = true; $stringChar = $c; }
            elseif ($inString && $c === $stringChar && ($i === 0 || $line[$i-1] !== '\\')) { $inString = false; }
        }
        $current .= $line . "\n";
        if (!$inString && strpos($line, ';') !== false) { $queries[] = trim($current); $current = ''; }
    }
    if (!empty(trim($current))) $queries[] = trim($current);
    return $queries;
}

$val = function($key, $default = '') use ($settings) { return htmlspecialchars($settings[$key]['setting_value'] ?? $default); };
$checked = function($key) use ($settings) { return ($settings[$key]['setting_value'] ?? 'true') === 'true' ? 'checked' : ''; };
$selected = function($key, $value) use ($settings) { return ($settings[$key]['setting_value'] ?? '') === $value ? 'selected' : ''; };

$currentUsername = $_SESSION['username'] ?? '—';
$currentFullname = $_SESSION['user_fullname'] ?? '—';
$currentRole = $_SESSION['user_role'] ?? 'user';
$currentEmail = $_SESSION['user_email'] ?? '—';
$roleLabel = $currentRole === 'admin' ? '👑 مشرف' : ($currentRole === 'manager' ? '👨‍💼 مدير' : '👤 مستخدم');
?>

<?php require_once 'includes/header.php'; ?>

<style>
    .main-content { padding: 16px; padding-bottom: 100px; color: var(--text); max-width: 600px; margin: 0 auto; }
    .page-title { font-size: 20px; font-weight: 800; margin-bottom: 4px; color: var(--text); }
    .page-subtitle { font-size: 12px; color: var(--text-secondary); margin-bottom: 14px; }
    
    .tabs { display: flex; gap: 4px; background: var(--surface); border-radius: var(--radius); padding: 4px; margin-bottom: 14px; border: 1px solid var(--border); overflow-x: auto; scrollbar-width: none; }
    .tabs::-webkit-scrollbar { display: none; }
    .tab { flex: 1; padding: 10px 7px; border-radius: 10px; border: none; background: transparent; color: var(--text-secondary); font-weight: 700; font-size: 11px; cursor: pointer; font-family: inherit; white-space: nowrap; transition: var(--transition); text-align: center; min-width: 55px; }
    .tab.active { background: var(--primary); color: #fff; }
    
    .section-title { font-size: 14px; font-weight: 700; margin-bottom: 6px; color: var(--text); display: flex; align-items: center; gap: 6px; }
    .section-subtitle { font-size: 11px; color: var(--text-secondary); margin-bottom: 10px; }
    
    .setting-card { background: var(--surface); border-radius: var(--radius); padding: 13px 15px; margin-bottom: 7px; border: 1px solid var(--border); transition: var(--transition); }
    .setting-card:hover { border-color: var(--primary); }
    .setting-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
    .setting-info { flex: 1; min-width: 0; }
    .setting-label { font-size: 13px; font-weight: 600; color: var(--text); }
    .setting-desc { font-size: 10px; color: var(--text-secondary); margin-top: 2px; }
    
    .input-field { width: 100%; padding: 10px 12px; background: var(--surface-light); border: 1.5px solid var(--border); border-radius: 10px; color: var(--text); font-size: 13px; font-family: inherit; transition: var(--transition); margin-top: 6px; }
    .input-field:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(108,92,231,0.1); }
    .input-field::placeholder { color: var(--text-muted); }
    .input-field[readonly] { opacity: 0.65; cursor: default; border-style: dashed; }
    textarea.input-field { resize: vertical; min-height: 60px; }
    .input-sm { max-width: 120px; text-align: center; }
    select.input-field { appearance: none; -webkit-appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath d='M6 8L1 3h10z' fill='%23888'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: left 12px center; padding-left: 35px; }
    select.input-field option { background: var(--surface); color: var(--text); }
    
    .toggle { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
    .toggle input { opacity: 0; width: 0; height: 0; }
    .toggle .slider { position: absolute; cursor: pointer; inset: 0; background: var(--surface-light); border: 1px solid var(--border); border-radius: 24px; transition: 0.3s; }
    .toggle .slider:before { content: ""; position: absolute; height: 16px; width: 16px; left: 3px; bottom: 3px; background: var(--text-secondary); border-radius: 50%; transition: 0.3s; }
    .toggle input:checked + .slider { background: var(--primary); border-color: var(--primary); }
    .toggle input:checked + .slider:before { transform: translateX(20px); background: #fff; }
    
    .color-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px; margin-top: 4px; }
    .color-item { display: flex; align-items: center; gap: 8px; padding: 7px 10px; background: var(--surface-light); border-radius: 8px; border: 1px solid var(--border); }
    .color-input { width: 34px; height: 30px; border: 2px solid var(--border); border-radius: 6px; cursor: pointer; padding: 1px; background: transparent; flex-shrink: 0; }
    .color-label { font-size: 10px; color: var(--text-secondary); flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    
    .upload-row { display: flex; align-items: center; gap: 10px; margin-top: 6px; }
    .preview-box { width: 48px; height: 48px; border-radius: 10px; background: var(--surface-light); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; overflow: hidden; font-size: 20px; flex-shrink: 0; }
    .preview-box img { width: 100%; height: 100%; object-fit: cover; }
    .upload-btn { padding: 7px 12px; background: var(--primary); color: #fff; border: none; border-radius: 16px; font-weight: 600; font-size: 11px; cursor: pointer; font-family: inherit; }
    .upload-btn:active { transform: scale(0.95); }
    
    .btn-row { display: flex; gap: 8px; margin-top: 14px; }
    .btn { flex: 1; padding: 13px; border-radius: 10px; font-weight: 700; font-size: 13px; cursor: pointer; border: none; font-family: inherit; transition: var(--transition); text-align: center; }
    .btn:active { transform: scale(0.96); } .btn:disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
    .btn-save { background: var(--success); color: #fff; }
    .btn-reset { background: var(--surface-light); color: var(--text); border: 1px solid var(--border); }
    
    .danger-zone { margin-top: 14px; }
    .danger-btn { width: 100%; padding: 12px; border: 1px solid var(--danger); border-radius: 10px; background: transparent; color: var(--danger); font-weight: 600; font-size: 12px; cursor: pointer; font-family: inherit; margin-bottom: 6px; transition: var(--transition); display: flex; align-items: center; justify-content: center; gap: 6px; }
    .danger-btn:active { background: var(--danger); color: #fff; }
    .app-footer { text-align: center; padding: 12px; color: var(--text-muted); font-size: 10px; }
    
    .toast { position: fixed; bottom: 100px; left: 50%; transform: translateX(-50%); padding: 10px 20px; border-radius: 20px; color: #fff; font-weight: 600; font-size: 12px; z-index: 300; animation: toastIn 0.3s ease, toastOut 0.3s ease 2.5s forwards; white-space: nowrap; }
    .toast.success { background: var(--success); } .toast.error { background: var(--danger); } .toast.warning { background: #ffa502; color: #333; }
    @keyframes toastIn { from{opacity:0;transform:translateX(-50%) translateY(10px)} to{opacity:1;transform:translateX(-50%) translateY(0)} }
    @keyframes toastOut { to{opacity:0;transform:translateX(-50%) translateY(-8px)} }
    
    @media (max-width: 400px) { .main-content { padding: 10px; } .tab { font-size: 9px; padding: 7px 4px; min-width: 45px; } .color-grid { grid-template-columns: 1fr 1fr; } }
</style>

<div class="main-content">
    <div class="page-title">⚙️ الإعدادات</div>
    <div class="page-subtitle">🟢 إعدادات خاصة بحسابك (User ID: <?php echo $currentUserId; ?>)</div>

    <div class="tabs">
        <button class="tab active" onclick="showTab('store')">🏪 المتجر</button>
        <button class="tab" onclick="showTab('appearance')">🎨 المظهر</button>
        <button class="tab" onclick="showTab('colors')">🌈 الألوان</button>
        <button class="tab" onclick="showTab('pages')">📄 الصفحات</button>
        <button class="tab" onclick="showTab('preferences')">📋 تفضيلات</button>
        <button class="tab" onclick="showTab('account')">👤 حسابي</button>
        <?php if ($isAdmin): ?><button class="tab" onclick="showTab('system')">⚙️ النظام</button><?php endif; ?>
    </div>

    <form id="settingsForm" method="POST" enctype="multipart/form-data" onsubmit="return false;">
        
        <!-- ========== 1. المتجر ========== -->
        <div class="tab-content" id="tab-store">
            <div class="section-title">🏪 معلومات المتجر</div>
            <div class="setting-card"><div class="setting-label">اسم المتجر</div><div class="setting-desc">يظهر في الهيدر وعناوين الصفحات والتقارير</div><input type="text" class="input-field" name="store_name" value="<?php echo $val('store_name', 'دفتر الحسابات'); ?>" maxlength="100"></div>
            <div class="setting-card"><div class="setting-label">البريد الإلكتروني</div><input type="email" class="input-field" name="store_email" value="<?php echo $val('store_email'); ?>" placeholder="example@email.com" dir="ltr"></div>
            <div class="setting-card"><div class="setting-label">رقم الهاتف</div><input type="text" class="input-field" name="store_phone" value="<?php echo $val('store_phone'); ?>" placeholder="+967XXXXXXXXX" dir="ltr"></div>
            <div class="setting-card"><div class="setting-label">العنوان</div><input type="text" class="input-field" name="store_address" value="<?php echo $val('store_address'); ?>" placeholder="العنوان الكامل"></div>
            <div class="setting-card"><div class="setting-label">وصف المتجر</div><textarea class="input-field" name="store_description" rows="2"><?php echo $val('store_description'); ?></textarea></div>
            <div class="setting-card"><div class="setting-label">أيقونة المتجر</div><div class="upload-row"><div class="preview-box" id="preview-icon"><?php if (!empty($settings['store_icon']['setting_value']) && file_exists($settings['store_icon']['setting_value'])): ?><img src="<?php echo $val('store_icon'); ?>"><?php else: ?>🏪<?php endif; ?></div><input type="file" name="store_icon" accept="image/*" style="display:none" id="iconInput" onchange="previewImg(this,'preview-icon')"><button type="button" class="upload-btn" onclick="document.getElementById('iconInput').click()">📁 رفع</button></div></div>
            <div class="setting-card"><div class="setting-label">شعار المتجر</div><div class="setting-desc">يظهر في كشوف الحساب والتقارير</div><div class="upload-row"><div class="preview-box" id="preview-logo"><?php if (!empty($settings['store_logo']['setting_value']) && file_exists($settings['store_logo']['setting_value'])): ?><img src="<?php echo $val('store_logo'); ?>"><?php else: ?>🖼️<?php endif; ?></div><input type="file" name="store_logo" accept="image/*" style="display:none" id="logoInput" onchange="previewImg(this,'preview-logo')"><button type="button" class="upload-btn" onclick="document.getElementById('logoInput').click()">📁 رفع</button></div></div>
            <div class="section-title" style="margin-top:12px;">💰 أسماء العملات</div>
            <div class="setting-card"><div class="setting-label">العملة المحلية</div><input type="text" class="input-field" name="store_currency_local" value="<?php echo $val('store_currency_local', 'ريال يمني'); ?>"></div>
            <div class="setting-card"><div class="setting-label">العملة السعودية</div><input type="text" class="input-field" name="store_currency_saudi" value="<?php echo $val('store_currency_saudi', 'ريال سعودي'); ?>"></div>
            <div class="setting-card"><div class="setting-label">الدولار</div><input type="text" class="input-field" name="store_currency_dollar" value="<?php echo $val('store_currency_dollar', 'دولار'); ?>"></div>
        </div>

        <!-- ========== 2. المظهر العام ========== -->
        <div class="tab-content" id="tab-appearance" style="display:none;">
            <div class="section-title">🎨 تخصيص المظهر العام</div>
            <div class="setting-card"><div class="setting-row"><div class="setting-info"><div class="setting-label">الوضع الليلي الافتراضي</div></div><label class="toggle"><input type="checkbox" name="dark_mode" <?php echo $checked('dark_mode'); ?>><span class="slider"></span></label></div></div>
            <div class="setting-card"><div class="setting-row"><div class="setting-info"><div class="setting-label">تأثير الزجاج (Glass)</div></div><label class="toggle"><input type="checkbox" name="glass_effect" <?php echo $checked('glass_effect'); ?>><span class="slider"></span></label></div></div>
            <div class="setting-card"><div class="setting-row"><div class="setting-info"><div class="setting-label">بطاقات مستديرة</div></div><label class="toggle"><input type="checkbox" name="rounded_cards" <?php echo $checked('rounded_cards'); ?>><span class="slider"></span></label></div></div>
            <div class="setting-card"><div class="setting-row"><div class="setting-info"><div class="setting-label">إظهار الظلال</div></div><label class="toggle"><input type="checkbox" name="show_shadows" <?php echo $checked('show_shadows'); ?>><span class="slider"></span></label></div></div>
            <div class="setting-card"><div class="setting-row"><div class="setting-info"><div class="setting-label">إظهار الحدود</div></div><label class="toggle"><input type="checkbox" name="show_borders" <?php echo $checked('show_borders'); ?>><span class="slider"></span></label></div></div>
            <div class="setting-card"><div class="setting-row"><div class="setting-info"><div class="setting-label">حركات متحركة</div></div><label class="toggle"><input type="checkbox" name="animated_cards" <?php echo $checked('animated_cards'); ?>><span class="slider"></span></label></div></div>
            <div class="setting-card"><div class="setting-row"><div class="setting-info"><div class="setting-label">الوضع المدمج</div><div class="setting-desc">مسافات أقل</div></div><label class="toggle"><input type="checkbox" name="compact_mode" <?php echo $checked('compact_mode'); ?>><span class="slider"></span></label></div></div>
            <div class="setting-card"><div class="setting-label">نمط البطاقات</div><select class="input-field" name="card_style"><option value="default" <?php echo $selected('card_style', 'default'); ?>>افتراضي</option><option value="elevated" <?php echo $selected('card_style', 'elevated'); ?>>مرتفع</option><option value="outlined" <?php echo $selected('card_style', 'outlined'); ?>>محدد</option><option value="flat" <?php echo $selected('card_style', 'flat'); ?>>مسطح</option></select></div>
            <div class="setting-card"><div class="setting-label">نوع الخط</div><select class="input-field" name="font_family"><option value="default" <?php echo $selected('font_family', 'default'); ?>>افتراضي</option><option value="cairo" <?php echo $selected('font_family', 'cairo'); ?>>Cairo</option><option value="tajawal" <?php echo $selected('font_family', 'tajawal'); ?>>Tajawal</option></select></div>
            <div class="setting-card"><div class="setting-label">حجم الخط (px)</div><input type="number" class="input-field input-sm" name="font_size" value="<?php echo $val('font_size', '14'); ?>" min="10" max="24"></div>
            <div class="setting-card"><div class="setting-label">تدوير الزوايا</div><input type="number" class="input-field input-sm" name="border_radius" value="<?php echo $val('border_radius', '12'); ?>" min="4" max="30"></div>
            <div class="setting-card"><div class="setting-label">تدوير البطاقات</div><input type="number" class="input-field input-sm" name="card_radius" value="<?php echo $val('card_radius', '16'); ?>" min="4" max="30"></div>
            <div class="setting-card"><div class="setting-label">تدوير الأزرار</div><input type="number" class="input-field input-sm" name="btn_radius" value="<?php echo $val('btn_radius', '10'); ?>" min="4" max="30"></div>
        </div>

        <!-- ========== 3. الألوان ========== -->
        <div class="tab-content" id="tab-colors" style="display:none;">
            <div class="section-title">🌈 تخصيص الألوان العامة</div>
            <?php 
            $colors = [
                ['theme_color', 'لون الخلفية', '#0f0f1a'],
                ['surface_color', 'لون البطاقات', '#1a1a2e'],
                ['accent_color', 'اللون المميز', '#6c5ce7'],
                ['secondary_color', 'اللون الثانوي', '#a29bfe'],
                ['text_color', 'لون النص', '#ffffff'],
                ['success_color', 'لون النجاح', '#00d68f'],
                ['danger_color', 'لون الخطأ', '#ff6b6b'],
                ['warning_color', 'لون التحذير', '#ffd93d'],
                ['info_color', 'لون المعلومات', '#74b9ff'],
                ['border_color', 'لون الحدود', '#2a2a45'],
                ['input_bg_color', 'خلفية الإدخال', '#0f1629'],
                ['header_bg_color', 'خلفية الهيدر', 'rgba(15,15,26,0.9)'],
            ];
            ?>
            <div class="color-grid">
                <?php foreach ($colors as $c): ?>
                <div class="color-item">
                    <input type="color" class="color-input" name="<?php echo $c[0]; ?>" value="<?php echo $val($c[0], $c[2]); ?>">
                    <span class="color-label"><?php echo $c[1]; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="setting-card" style="margin-top:8px;"><div class="setting-label">شفافية الظلال (0-100)</div><input type="number" class="input-field input-sm" name="shadow_opacity" value="<?php echo $val('shadow_opacity', '30'); ?>" min="0" max="100"></div>
            <div class="setting-card"><div class="setting-label">مقدار التمويه (px)</div><input type="number" class="input-field input-sm" name="blur_amount" value="<?php echo $val('blur_amount', '20'); ?>" min="0" max="50"></div>
        </div>

        <!-- ========== 4. الصفحات ========== -->
        <div class="tab-content" id="tab-pages" style="display:none;">
            <div class="section-title">📄 تخصيص كل صفحة على حدة</div>
            
            <!-- الرئيسية -->
            <div class="section-subtitle" style="font-weight:700;color:var(--primary);margin-top:8px;">🏠 الصفحة الرئيسية</div>
            <div class="setting-card"><div class="setting-label">لون الخلفية</div><div class="color-item" style="margin-top:4px;"><input type="color" class="color-input" name="index_bg_color" value="<?php echo $val('index_bg_color', '#0f0f1a'); ?>"><span class="color-label">خلفية الرئيسية</span></div></div>
            <div class="setting-card"><div class="setting-label">لون البطاقات</div><div class="color-item" style="margin-top:4px;"><input type="color" class="color-input" name="index_card_color" value="<?php echo $val('index_card_color', '#1a1a2e'); ?>"><span class="color-label">بطاقات الرئيسية</span></div></div>
            <div class="setting-card"><div class="setting-label">لون الحدود</div><div class="color-item" style="margin-top:4px;"><input type="color" class="color-input" name="index_border_color" value="<?php echo $val('index_border_color', '#2a2a45'); ?>"><span class="color-label">حدود الرئيسية</span></div></div>
            <div class="setting-card"><div class="setting-row"><div class="setting-info"><div class="setting-label">إظهار الحدود</div></div><label class="toggle"><input type="checkbox" name="index_show_borders" <?php echo $checked('index_show_borders'); ?>><span class="slider"></span></label></div></div>

            <!-- العميل -->
            <div class="section-subtitle" style="font-weight:700;color:var(--primary);margin-top:8px;">👤 صفحة العميل</div>
            <div class="setting-card"><div class="setting-label">لون الخلفية</div><div class="color-item" style="margin-top:4px;"><input type="color" class="color-input" name="client_bg_color" value="<?php echo $val('client_bg_color', '#0f0f1a'); ?>"><span class="color-label">خلفية العميل</span></div></div>
            <div class="setting-card"><div class="setting-label">لون البطاقات</div><div class="color-item" style="margin-top:4px;"><input type="color" class="color-input" name="client_card_color" value="<?php echo $val('client_card_color', '#1a1a2e'); ?>"><span class="color-label">بطاقات العميل</span></div></div>
            <div class="setting-card"><div class="setting-label">لون الحدود</div><div class="color-item" style="margin-top:4px;"><input type="color" class="color-input" name="client_border_color" value="<?php echo $val('client_border_color', '#2a2a45'); ?>"><span class="color-label">حدود العميل</span></div></div>
            <div class="setting-card"><div class="setting-row"><div class="setting-info"><div class="setting-label">إظهار الحدود</div></div><label class="toggle"><input type="checkbox" name="client_show_borders" <?php echo $checked('client_show_borders'); ?>><span class="slider"></span></label></div></div>

            <!-- الكشف -->
            <div class="section-subtitle" style="font-weight:700;color:var(--primary);margin-top:8px;">📄 كشف الحساب</div>
            <div class="setting-card"><div class="setting-label">لون الخلفية</div><div class="color-item" style="margin-top:4px;"><input type="color" class="color-input" name="statement_bg_color" value="<?php echo $val('statement_bg_color', '#ffffff'); ?>"><span class="color-label">خلفية الكشف</span></div></div>
            <div class="setting-card"><div class="setting-label">لون البطاقات</div><div class="color-item" style="margin-top:4px;"><input type="color" class="color-input" name="statement_card_color" value="<?php echo $val('statement_card_color', '#ffffff'); ?>"><span class="color-label">بطاقات الكشف</span></div></div>
            <div class="setting-card"><div class="setting-label">لون الحدود</div><div class="color-item" style="margin-top:4px;"><input type="color" class="color-input" name="statement_border_color" value="<?php echo $val('statement_border_color', '#e0e0e0'); ?>"><span class="color-label">حدود الكشف</span></div></div>
            <div class="setting-card"><div class="setting-row"><div class="setting-info"><div class="setting-label">إظهار الحدود</div></div><label class="toggle"><input type="checkbox" name="statement_show_borders" <?php echo $checked('statement_show_borders'); ?>><span class="slider"></span></label></div></div>

            <!-- الإعدادات -->
            <div class="section-subtitle" style="font-weight:700;color:var(--primary);margin-top:8px;">⚙️ صفحة الإعدادات</div>
            <div class="setting-card"><div class="setting-label">لون الخلفية</div><div class="color-item" style="margin-top:4px;"><input type="color" class="color-input" name="settings_bg_color" value="<?php echo $val('settings_bg_color', '#0f0f1a'); ?>"><span class="color-label">خلفية الإعدادات</span></div></div>
            <div class="setting-card"><div class="setting-label">لون البطاقات</div><div class="color-item" style="margin-top:4px;"><input type="color" class="color-input" name="settings_card_color" value="<?php echo $val('settings_card_color', '#1a1a2e'); ?>"><span class="color-label">بطاقات الإعدادات</span></div></div>
            <div class="setting-card"><div class="setting-label">لون الحدود</div><div class="color-item" style="margin-top:4px;"><input type="color" class="color-input" name="settings_border_color" value="<?php echo $val('settings_border_color', '#2a2a45'); ?>"><span class="color-label">حدود الإعدادات</span></div></div>
            <div class="setting-card"><div class="setting-row"><div class="setting-info"><div class="setting-label">إظهار الحدود</div></div><label class="toggle"><input type="checkbox" name="settings_show_borders" <?php echo $checked('settings_show_borders'); ?>><span class="slider"></span></label></div></div>
        </div>

        <!-- ========== 5. تفضيلات ========== -->
        <div class="tab-content" id="tab-preferences" style="display:none;">
            <div class="section-title">📋 تفضيلات العرض</div>
            <div class="setting-card"><div class="setting-row"><div class="setting-info"><div class="setting-label">إظهار الرصيد في القائمة</div></div><label class="toggle"><input type="checkbox" name="show_balance_in_list" <?php echo $checked('show_balance_in_list'); ?>><span class="slider"></span></label></div></div>
            <div class="setting-card"><div class="setting-row"><div class="setting-info"><div class="setting-label">إظهار عدد العمليات</div></div><label class="toggle"><input type="checkbox" name="show_transaction_count" <?php echo $checked('show_transaction_count'); ?>><span class="slider"></span></label></div></div>
            <div class="setting-card"><div class="setting-row"><div class="setting-info"><div class="setting-label">إظهار الشعار في PDF</div></div><label class="toggle"><input type="checkbox" name="show_logo_in_pdf" <?php echo $checked('show_logo_in_pdf'); ?>><span class="slider"></span></label></div></div>
            <div class="setting-card"><div class="setting-row"><div class="setting-info"><div class="setting-label">إظهار التوقيع في الكشف</div></div><label class="toggle"><input type="checkbox" name="show_signature" <?php echo $checked('show_signature'); ?>><span class="slider"></span></label></div></div>
            <div class="setting-card"><div class="setting-label">عدد العناصر في الصفحة</div><input type="number" class="input-field input-sm" name="items_per_page" value="<?php echo $val('items_per_page', '20'); ?>" min="5" max="100"></div>
            <div class="setting-card"><div class="setting-label">تنسيق التاريخ</div><select class="input-field" name="date_format"><option value="d-m-Y" <?php echo $selected('date_format', 'd-m-Y'); ?>>يوم-شهر-سنة</option><option value="d/m/Y" <?php echo $selected('date_format', 'd/m/Y'); ?>>يوم/شهر/سنة</option><option value="Y-m-d" <?php echo $selected('date_format', 'Y-m-d'); ?>>سنة-شهر-يوم</option></select></div>
            <div class="setting-card"><div class="setting-label">العملة الافتراضية</div><select class="input-field" name="default_currency"><option value="local" <?php echo $selected('default_currency', 'local'); ?>>محلي</option><option value="saudi" <?php echo $selected('default_currency', 'saudi'); ?>>سعودي</option><option value="dollar" <?php echo $selected('default_currency', 'dollar'); ?>>دولار</option></select></div>
            <div class="setting-card"><div class="setting-label">الخانات العشرية</div><input type="number" class="input-field input-sm" name="currency_decimals" value="<?php echo $val('currency_decimals', '0'); ?>" min="0" max="3"></div>
        </div>

        <!-- ========== 6. حسابي ========== -->
        <div class="tab-content" id="tab-account" style="display:none;">
            <div class="section-title">👤 معلومات حسابي</div>
            <div class="setting-card"><div class="setting-label">اسم المستخدم</div><input type="text" class="input-field" value="<?php echo htmlspecialchars($currentUsername); ?>" readonly></div>
            <div class="setting-card"><div class="setting-label">الاسم الكامل</div><input type="text" class="input-field" value="<?php echo htmlspecialchars($currentFullname); ?>" readonly></div>
            <div class="setting-card"><div class="setting-label">معرف المستخدم</div><input type="text" class="input-field" value="<?php echo $currentUserId; ?>" readonly></div>
            <div class="setting-card"><div class="setting-label">الدور</div><input type="text" class="input-field" value="<?php echo $roleLabel; ?>" readonly></div>
            <div class="setting-card"><div class="setting-label">البريد الإلكتروني</div><input type="text" class="input-field" value="<?php echo htmlspecialchars($currentEmail); ?>" readonly></div>
        </div>

        <!-- ========== 7. النظام ========== -->
        <?php if ($isAdmin): ?>
        <div class="tab-content" id="tab-system" style="display:none;">
            <div class="section-title">⚙️ إعدادات النظام</div>
            <div class="setting-card"><div class="setting-row"><div class="setting-info"><div class="setting-label">نسخ احتياطي تلقائي</div></div><label class="toggle"><input type="checkbox" name="auto_backup" <?php echo $checked('auto_backup'); ?>><span class="slider"></span></label></div></div>
            <div class="setting-card"><div class="setting-row"><div class="setting-info"><div class="setting-label">تفعيل الإشعارات</div></div><label class="toggle"><input type="checkbox" name="enable_notifications" <?php echo $checked('enable_notifications'); ?>><span class="slider"></span></label></div></div>
            <div class="setting-card"><div class="setting-label">لغة النظام</div><select class="input-field" name="language"><option value="ar" <?php echo $selected('language', 'ar'); ?>>العربية</option><option value="en" <?php echo $selected('language', 'en'); ?>>English</option></select></div>
            <div class="setting-card"><div class="setting-label">المنطقة الزمنية</div><select class="input-field" name="timezone"><option value="Asia/Riyadh" <?php echo $selected('timezone', 'Asia/Riyadh'); ?>>الرياض (+3)</option><option value="Asia/Dubai" <?php echo $selected('timezone', 'Asia/Dubai'); ?>>دبي (+4)</option></select></div>
            <div class="danger-zone">
                <div class="section-title" style="color:var(--danger);">⚠️ منطقة الخطر</div>
                <button type="button" class="danger-btn" onclick="backupDatabase()">📥 تصدير نسخة احتياطية</button>
                <button type="button" class="danger-btn" id="restoreBtn" onclick="document.getElementById('restoreInput').click()">📤 استيراد نسخة احتياطية</button>
                <input type="file" id="restoreInput" accept=".sql" style="display:none" onchange="restoreDatabase(this)">
                <button type="button" class="danger-btn" onclick="resetSettings()">🔄 إعادة ضبط الإعدادات</button>
                <button type="button" class="danger-btn" onclick="clearAllData()">🗑️ مسح جميع البيانات</button>
            </div>
            <div class="app-footer">الإصدار 1.0.0 • دفتر الحسابات © <?php echo date('Y'); ?></div>
        </div>
        <?php endif; ?>

        <div class="btn-row">
            <button type="button" class="btn btn-reset" onclick="window.location.href='index.php'">← رجوع</button>
            <button type="button" class="btn btn-save" id="saveBtn" onclick="saveSettings()">💾 حفظ جميع الإعدادات</button>
        </div>
    </form>
</div>

<script>
    function showTab(name) {
        document.querySelectorAll('.tab').forEach(function(t){t.classList.remove('active')});
        document.querySelectorAll('.tab-content').forEach(function(c){c.style.display='none'});
        var tb=document.querySelector('[onclick="showTab(\''+name+'\')"]');
        if(tb)tb.classList.add('active');
        var tc=document.getElementById('tab-'+name);
        if(tc)tc.style.display='block';
    }
    
    function previewImg(input, previewId) {
        var p=document.getElementById(previewId);
        if(input.files&&input.files[0]){
            var r=new FileReader();
            r.onload=function(e){p.innerHTML='<img src="'+e.target.result+'" alt="معاينة">'};
            r.readAsDataURL(input.files[0]);
        }
    }
    
    function saveSettings() {
        var btn=document.getElementById('saveBtn');
        if(!btn||btn.disabled)return;
        btn.textContent='⏳ جاري الحفظ...';
        btn.disabled=true;
        btn.style.opacity='0.6';
        
        var form=document.getElementById('settingsForm');
        var formData=new FormData(form);
        formData.append('save_settings','1');
        
        fetch(window.location.href,{method:'POST',body:formData})
        .then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json();})
        .then(function(data){
            btn.textContent='💾 حفظ جميع الإعدادات';
            btn.disabled=false;
            btn.style.opacity='1';
            if(data.success){
                showToast(data.message,'success');
                setTimeout(function(){location.reload();},1200);
            }else{
                showToast(data.message||'خطأ في الحفظ','error');
            }
        })
        .catch(function(err){
            console.error('Save error:',err);
            btn.textContent='💾 حفظ جميع الإعدادات';
            btn.disabled=false;
            btn.style.opacity='1';
            showToast('❌ فشل الاتصال - حاول مرة أخرى','error');
        });
    }
    
    function backupDatabase(){window.location.href='?backup=database';}
    
    function restoreDatabase(input){
        if(!input.files[0])return;
        var n=input.files[0].name,s=(input.files[0].size/1024).toFixed(1);
        if(!confirm('⚠️ سيتم استبدال جميع البيانات!\n\nالملف: '+n+' ('+s+' KB)\n\nمتأكد؟')){input.value='';return;}
        if(!confirm('تأكيد نهائي:')){input.value='';return;}
        var btn=document.getElementById('restoreBtn');
        if(btn){btn.textContent='⏳ جاري...';btn.disabled=true;}
        var fd=new FormData();fd.append('restore_file',input.files[0]);
        fetch(window.location.href,{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(data){
            if(btn){btn.textContent='📤 استيراد نسخة احتياطية';btn.disabled=false;}
            showToast(data.message,data.success?'success':'error');
            if(data.success)setTimeout(function(){location.reload();},1500);
        })
        .catch(function(){if(btn){btn.textContent='📤 استيراد نسخة احتياطية';btn.disabled=false;}showToast('❌ خطأ','error');});
        input.value='';
    }
    
    function resetSettings(){if(confirm('إعادة ضبط جميع الإعدادات؟')){document.getElementById('settingsForm').reset();showToast('تم إعادة الضبط','warning');}}
    
    function clearAllData(){
        if(confirm('⚠️ سيتم مسح جميع المعاملات والعملاء!\n\nمتأكد؟')){
            if(confirm('تأكيد نهائي:')){
                fetch('api/clear_data.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'confirm=yes'})
                .then(function(r){return r.json();})
                .then(function(data){showToast(data.message,data.success?'success':'error');if(data.success)setTimeout(function(){location.href='index.php'},1500);});
            }
        }
    }
    
    function showToast(m,t){
        var x=document.querySelector('.toast');if(x)x.remove();
        var d=document.createElement('div');d.className='toast '+t;d.textContent=m;
        document.body.appendChild(d);setTimeout(function(){d.remove();},3000);
    }
    
    document.addEventListener('keydown',function(e){if((e.ctrlKey||e.metaKey)&&e.key==='s'){e.preventDefault();saveSettings();}});
</script>

<?php require_once 'includes/footer_nav.php'; ?>