<?php
/**
 * تثبيت نظام دفتر الحسابات
 * متوافق مع سيرفر InfinityFree
 */

// منع التثبيت إذا كان config.php موجوداً
if (file_exists('config.php') && filesize('config.php') > 100) {
    die('النظام مثبت مسبقاً. إذا كنت تريد إعادة التثبيت، احذف ملف config.php أولاً.');
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';
$dbTested = false;

// اختبار الاتصال بقاعدة البيانات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_db'])) {
    $db_host = $_POST['db_host'] ?? 'sql107.infinityfree.com';
    $db_name = $_POST['db_name'] ?? '';
    $db_user = $_POST['db_user'] ?? '';
    $db_pass = $_POST['db_pass'] ?? '';
    
    try {
        $dsn = "mysql:host={$db_host};port=3306;charset=utf8mb4";
        $testPdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        
        // محاولة إنشاء قاعدة البيانات إذا لم تكن موجودة
        $testPdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $testPdo->exec("USE `{$db_name}`");
        
        $dbTested = true;
        $success = '✅ تم الاتصال بقاعدة البيانات بنجاح!';
    } catch (PDOException $e) {
        $error = '❌ فشل الاتصال: ' . $e->getMessage();
    }
}

// معالجة التثبيت النهائي
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    $db_host = $_POST['db_host'] ?? 'sql107.infinityfree.com';
    $db_name = $_POST['db_name'] ?? '';
    $db_user = $_POST['db_user'] ?? '';
    $db_pass = $_POST['db_pass'] ?? '';
    $store_name = trim($_POST['store_name'] ?? 'دفتر الحسابات');
    $admin_user = trim($_POST['admin_user'] ?? 'admin');
    $admin_pass = $_POST['admin_pass'] ?? '';
    $admin_email = trim($_POST['admin_email'] ?? '');
    
    if (empty($db_name) || empty($db_user) || empty($admin_user) || empty($admin_pass)) {
        $error = 'جميع الحقول المطلوبة يجب ملؤها';
    } elseif (strlen($admin_pass) < 6) {
        $error = 'كلمة مرور المشرف 6 أحرف على الأقل';
    } else {
        try {
            $dsn = "mysql:host={$db_host};port=3306;charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            // إنشاء قاعدة البيانات
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$db_name}`");
            
            // ========== إنشاء جميع الجداول ==========
            
            // جدول الإعدادات
            $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `setting_key` VARCHAR(100) NOT NULL UNIQUE,
                `setting_value` TEXT,
                `setting_type` VARCHAR(20) DEFAULT 'text',
                `setting_group` VARCHAR(50) DEFAULT 'general',
                `setting_label` VARCHAR(200) DEFAULT NULL,
                `sort_order` INT DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // جدول العملاء
            $pdo->exec("CREATE TABLE IF NOT EXISTS `partners` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `phone` VARCHAR(20) DEFAULT NULL,
                `type` VARCHAR(20) DEFAULT 'local',
                `notes` TEXT,
                `status` VARCHAR(20) DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // جدول المعاملات
            $pdo->exec("CREATE TABLE IF NOT EXISTS `transactions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `partner_id` INT NOT NULL,
                `date` DATE NOT NULL,
                `details` TEXT,
                `amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
                `currency_type` VARCHAR(20) DEFAULT 'local',
                `transaction_type` VARCHAR(20) DEFAULT 'debit',
                `quantity` INT DEFAULT 0,
                `unit` VARCHAR(50) DEFAULT NULL,
                `status` VARCHAR(20) DEFAULT 'completed',
                `created_by` INT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`partner_id`) REFERENCES `partners`(`id`) ON DELETE CASCADE,
                INDEX `idx_date` (`date`),
                INDEX `idx_partner` (`partner_id`),
                INDEX `idx_currency` (`currency_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // جدول المستخدمين
            $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) NOT NULL UNIQUE,
                `password` VARCHAR(255) NOT NULL,
                `fullname` VARCHAR(100) NOT NULL,
                `email` VARCHAR(100) DEFAULT NULL,
                `phone` VARCHAR(20) DEFAULT NULL,
                `role` VARCHAR(20) DEFAULT 'user',
                `status` VARCHAR(20) DEFAULT 'active',
                `avatar` VARCHAR(255) DEFAULT NULL,
                `last_login` DATETIME DEFAULT NULL,
                `last_ip` VARCHAR(45) DEFAULT NULL,
                `login_attempts` INT DEFAULT 0,
                `locked_until` DATETIME DEFAULT NULL,
                `reset_token` VARCHAR(100) DEFAULT NULL,
                `reset_expires` DATETIME DEFAULT NULL,
                `remember_token` VARCHAR(100) DEFAULT NULL,
                `verification_code` VARCHAR(10) DEFAULT NULL,
                `verification_status` VARCHAR(20) DEFAULT 'approved',
                `verified_at` DATETIME DEFAULT NULL,
                `verified_by` INT DEFAULT NULL,
                `rejection_reason` VARCHAR(255) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_username` (`username`),
                INDEX `idx_email` (`email`),
                INDEX `idx_role` (`role`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // جدول النشاطات
            $pdo->exec("CREATE TABLE IF NOT EXISTS `activity_log` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT DEFAULT NULL,
                `action` VARCHAR(50) NOT NULL,
                `description` TEXT,
                `ip_address` VARCHAR(45) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // جدول محاولات الدخول
            $pdo->exec("CREATE TABLE IF NOT EXISTS `login_attempts` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) DEFAULT NULL,
                `ip_address` VARCHAR(45) DEFAULT NULL,
                `success` TINYINT(1) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_username` (`username`),
                INDEX `idx_ip` (`ip_address`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // ========== إضافة الإعدادات الافتراضية ==========
            $defaultSettings = [
                ['store_name', $store_name, 'text', 'store', 'اسم المتجر', 1],
                ['store_description', 'نظام إدارة الحسابات المالية', 'textarea', 'store', 'وصف المتجر', 2],
                ['store_phone', '', 'text', 'store', 'رقم الهاتف', 3],
                ['store_address', '', 'text', 'store', 'العنوان', 4],
                ['store_email', $admin_email, 'text', 'store', 'البريد الإلكتروني', 5],
                ['store_currency_local', 'ريال يمني', 'text', 'store', 'العملة المحلية', 6],
                ['store_currency_saudi', 'ريال سعودي', 'text', 'store', 'العملة السعودية', 7],
                ['store_currency_dollar', 'دولار', 'text', 'store', 'الدولار', 8],
                ['theme_color', '#0f0f1a', 'color', 'appearance', 'لون الخلفية', 9],
                ['card_color', '#1a1a2e', 'color', 'appearance', 'لون البطاقات', 10],
                ['accent_color', '#6c5ce7', 'color', 'appearance', 'اللون المميز', 11],
                ['text_color', '#ffffff', 'color', 'appearance', 'لون النص', 12],
                ['dark_mode', 'true', 'boolean', 'appearance', 'الوضع الليلي', 13],
                ['font_size', '14', 'number', 'appearance', 'حجم الخط', 14],
                ['border_radius', '12', 'number', 'appearance', 'تدوير الزوايا', 15],
                ['date_format', 'd-m-Y', 'select', 'display', 'تنسيق التاريخ', 16],
                ['default_currency', 'local', 'select', 'display', 'العملة الافتراضية', 17],
                ['items_per_page', '20', 'number', 'display', 'عناصر في الصفحة', 18],
                ['currency_decimals', '0', 'number', 'display', 'خانات عشرية', 19],
                ['language', 'ar', 'select', 'system', 'اللغة', 20],
                ['timezone', 'Asia/Riyadh', 'select', 'system', 'المنطقة الزمنية', 21],
            ];
            
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_type, setting_group, setting_label, sort_order) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            foreach ($defaultSettings as $s) {
                $stmt->execute($s);
            }
            
            // ========== إضافة مستخدم المشرف ==========
            $hashedPassword = password_hash($admin_pass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, email, role, status, verification_status, verified_at) VALUES (?, ?, ?, ?, 'admin', 'active', 'approved', NOW()) ON DUPLICATE KEY UPDATE password = VALUES(password), role = 'admin', status = 'active'");
            $stmt->execute([$admin_user, $hashedPassword, 'مدير النظام', $admin_email]);
            
            // ========== إنشاء ملف config.php ==========
            $configContent = "<?php
/**
 * ملف الإعدادات - دفتر الحسابات
 * تم إنشاؤه تلقائياً بواسطة معالج التثبيت
 * تاريخ الإنشاء: " . date('Y-m-d H:i:s') . "
 */

// إعدادات قاعدة البيانات - InfinityFree
define('DB_HOST', '{$db_host}');
define('DB_NAME', '{$db_name}');
define('DB_USER', '{$db_user}');
define('DB_PASS', '{$db_pass}');
define('DB_PORT', '3306');

// إعدادات التطبيق
define('APP_NAME', '" . addslashes($store_name) . "');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'https://' . \$_SERVER['HTTP_HOST'] . dirname(\$_SERVER['PHP_SELF']) . ');

// المنطقة الزمنية
date_default_timezone_set('Asia/Riyadh');

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// الاتصال بقاعدة البيانات
try {
    \$pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
        ]
    );
} catch (PDOException \$e) {
    die('فشل الاتصال بقاعدة البيانات. يرجى التحقق من الإعدادات.');
}

// تحميل الدوال المساعدة
require_once __DIR__ . '/includes/functions.php';
";
            
            if (file_put_contents('config.php', $configContent)) {
                // إنشاء مجلد uploads
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0755, true);
                }
                
                // إنشاء مجلد admin
                if (!is_dir('admin')) {
                    mkdir('admin', 0755, true);
                }
                
                // إنشاء ملف .htaccess للحماية
                $htaccess = "# حماية المجلدات\nOptions -Indexes\n\n# منع الوصول للملفات الحساسة\n<FilesMatch \"^(config\.php|\.env|\.sql)$\">\n  Order deny,allow\n  Deny from all\n</FilesMatch>\n\n# إعادة توجيه الأخطاء\nErrorDocument 404 /404.php\n";
                file_put_contents('.htaccess', $htaccess);
                
                $success = '✅ تم تثبيت النظام بنجاح! يمكنك الآن تسجيل الدخول.';
                $step = 3;
            } else {
                $error = '❌ لا يمكن كتابة ملف config.php. تحقق من صلاحيات المجلد (يجب أن يكون 755 أو 777).';
            }
            
        } catch (PDOException $e) {
            $error = '❌ خطأ في التثبيت: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>تثبيت دفتر الحسابات</title>
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
        .container { width: 100%; max-width: 550px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header .icon { font-size: 60px; display: block; margin-bottom: 10px; }
        .header h1 { font-size: 24px; font-weight: 800; margin-bottom: 6px; }
        .header p { font-size: 13px; color: #8888a0; }
        .card {
            background: rgba(26,26,46,0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 28px 24px;
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }
        .alert {
            padding: 14px 16px; border-radius: 12px; margin-bottom: 16px;
            font-size: 13px; font-weight: 600; line-height: 1.6;
        }
        .alert-error { background: rgba(255,71,87,0.15); color: #ff6b6b; border: 1px solid rgba(255,71,87,0.3); }
        .alert-success { background: rgba(0,214,143,0.15); color: #00d68f; border: 1px solid rgba(0,214,143,0.3); }
        .alert-info { background: rgba(55,66,250,0.15); color: #a29bfe; border: 1px solid rgba(55,66,250,0.3); }
        
        .form-group { margin-bottom: 14px; }
        .form-label { display: block; font-size: 12px; font-weight: 600; color: #8888a0; margin-bottom: 5px; }
        .form-input {
            width: 100%; padding: 13px 14px; background: rgba(15,22,41,0.6);
            border: 1.5px solid rgba(255,255,255,0.1); border-radius: 10px;
            color: #fff; font-size: 14px; font-family: inherit; transition: all 0.3s;
        }
        .form-input:focus { outline: none; border-color: #6c5ce7; }
        .form-input::placeholder { color: #5a5a7a; }
        .form-input:read-only { opacity: 0.7; }
        
        .btn {
            width: 100%; padding: 14px; border: none; border-radius: 12px;
            font-size: 15px; font-weight: 700; cursor: pointer; font-family: inherit;
            transition: all 0.3s; text-align: center; text-decoration: none; display: block;
        }
        .btn:active { transform: scale(0.97); }
        .btn-primary { background: linear-gradient(135deg, #6c5ce7, #a29bfe); color: #fff; box-shadow: 0 8px 25px rgba(108,92,231,0.3); }
        .btn-success { background: linear-gradient(135deg, #00d68f, #34d399); color: #fff; box-shadow: 0 8px 25px rgba(0,214,143,0.3); }
        .btn-test { background: rgba(255,165,2,0.15); color: #ffa502; border: 1px solid rgba(255,165,2,0.3); }
        
        .steps { display: flex; justify-content: center; gap: 6px; margin-bottom: 20px; }
        .step { width: 30px; height: 30px; border-radius: 50%; background: #2a2a45; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #8888a0; }
        .step.active { background: #6c5ce7; color: #fff; }
        .step.done { background: #00d68f; color: #fff; }
        
        .info-box { background: rgba(15,22,41,0.6); border-radius: 10px; padding: 12px; margin: 12px 0; font-size: 12px; color: #8888a0; }
        .info-box strong { color: #ffd93d; }
        .info-box code { background: rgba(108,92,231,0.2); padding: 2px 6px; border-radius: 4px; color: #a29bfe; }
        
        .success-icon { font-size: 80px; text-align: center; display: block; margin: 10px 0; animation: bounce 0.6s ease; }
        @keyframes bounce { 0%,100%{transform:scale(1)} 50%{transform:scale(1.1)} }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span class="icon">💳</span>
            <h1>تثبيت دفتر الحسابات</h1>
            <p>InfinityFree Server</p>
        </div>
        
        <div class="card">
            <!-- خطوات التثبيت -->
            <div class="steps">
                <span class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'done' : ''; ?>"><?php echo $step > 1 ? '✓' : '1'; ?></span>
                <span class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'done' : ''; ?>"><?php echo $step > 2 ? '✓' : '2'; ?></span>
                <span class="step <?php echo $step >= 3 ? 'active' : ''; ?>">3</span>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($step === 1): ?>
                <!-- ====== الخطوة 1: إعدادات قاعدة البيانات ====== -->
                <form method="POST">
                    <div class="alert alert-info">
                        📝 أدخل بيانات قاعدة البيانات من لوحة تحكم InfinityFree
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">مضيف MySQL *</label>
                        <input type="text" class="form-input" name="db_host" value="sql107.infinityfree.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">اسم قاعدة البيانات *</label>
                        <input type="text" class="form-input" name="db_name" placeholder="مثال: if0_41815788_db" required>
                        <small style="color:#8888a0;font-size:10px;">يجب أن يبدأ بـ if0_41815788_</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">اسم المستخدم *</label>
                        <input type="text" class="form-input" name="db_user" value="if0_41815788" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">كلمة المرور *</label>
                        <input type="password" class="form-input" name="db_pass" value="jwV1rYbI0qIjMb" required>
                    </div>
                    
                    <button type="submit" name="test_db" class="btn btn-test">🔍 اختبار الاتصال</button>
                    
                    <?php if ($dbTested): ?>
                    <div class="alert alert-success" style="margin-top:12px;">✅ تم الاتصال بنجاح! أكمل البيانات أدناه.</div>
                    
                    <hr style="border-color:rgba(255,255,255,0.1);margin:16px 0;">
                    
                    <h3 style="margin-bottom:12px;">⚙️ إعدادات المشرف</h3>
                    
                    <div class="form-group">
                        <label class="form-label">اسم المتجر</label>
                        <input type="text" class="form-input" name="store_name" value="دفتر الحسابات" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">اسم مستخدم المشرف *</label>
                        <input type="text" class="form-input" name="admin_user" value="admin" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">كلمة مرور المشرف * (6 أحرف على الأقل)</label>
                        <input type="password" class="form-input" name="admin_pass" placeholder="اختر كلمة مرور قوية" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">البريد الإلكتروني للمشرف</label>
                        <input type="email" class="form-input" name="admin_email" placeholder="admin@example.com">
                    </div>
                    
                    <button type="submit" name="install" class="btn btn-success">🚀 تثبيت النظام</button>
                    <?php endif; ?>
                </form>
                
            <?php elseif ($step === 2): ?>
                <!-- ====== الخطوة 2: جاري التثبيت ====== -->
                <form method="POST">
                    <input type="hidden" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'sql107.infinityfree.com'); ?>">
                    <input type="hidden" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? ''); ?>">
                    <input type="hidden" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'if0_41815788'); ?>">
                    <input type="hidden" name="db_pass" value="<?php echo htmlspecialchars($_POST['db_pass'] ?? ''); ?>">
                    <input type="hidden" name="store_name" value="<?php echo htmlspecialchars($_POST['store_name'] ?? 'دفتر الحسابات'); ?>">
                    <input type="hidden" name="admin_user" value="<?php echo htmlspecialchars($_POST['admin_user'] ?? 'admin'); ?>">
                    <input type="hidden" name="admin_pass" value="<?php echo htmlspecialchars($_POST['admin_pass'] ?? ''); ?>">
                    <input type="hidden" name="admin_email" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>">
                    
                    <div style="text-align:center;padding:20px;">
                        <div style="font-size:50px;margin-bottom:12px;">⏳</div>
                        <p>جاري التحضير للتثبيت...</p>
                    </div>
                    
                    <button type="submit" name="install" class="btn btn-success">🚀 بدء التثبيت</button>
                </form>
                
            <?php elseif ($step === 3): ?>
                <!-- ====== الخطوة 3: اكتمل التثبيت ====== -->
                <span class="success-icon">✅</span>
                <h2 style="text-align:center;margin-bottom:10px;">🎉 تم التثبيت بنجاح!</h2>
                <p style="text-align:center;color:#8888a0;font-size:14px;margin-bottom:16px;">
                    تم تثبيت نظام دفتر الحسابات على سيرفر InfinityFree
                </p>
                
                <div class="info-box">
                    <strong>🔐 معلومات تسجيل الدخول:</strong><br>
                    الرابط: <code><?php echo 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/login.php'; ?></code><br>
                    المستخدم: <code><?php echo htmlspecialchars($_POST['admin_user'] ?? 'admin'); ?></code><br>
                    كلمة المرور: <code>********</code> (التي أدخلتها)
                </div>
                
                <div class="info-box">
                    <strong>⚠️ تنبيهات مهمة:</strong><br>
                    • احذف ملف <code>install.php</code> فوراً للأمان<br>
                    • تأكد من صلاحية المجلدات <code>755</code><br>
                    • يمكنك إدارة النظام من لوحة التحكم
                </div>
                
                <a href="login.php" class="btn btn-success">🚀 تسجيل الدخول</a>
                <a href="index.php" class="btn btn-primary" style="margin-top:8px;">🏠 الذهاب للرئيسية</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>