<?php
require_once 'config.php';

echo "<h2>🔧 تحديث الإعدادات لكل مستخدم</h2><pre>";

try {
    // 1. إضافة user_id في جدول settings إذا لم يكن موجوداً
    $stmt = $pdo->query("SHOW COLUMNS FROM settings LIKE 'user_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE settings ADD COLUMN user_id INT DEFAULT NULL");
        $pdo->exec("ALTER TABLE settings ADD INDEX idx_user_id (user_id)");
        echo "✅ تم إضافة user_id في settings\n";
        
        // جعل الإعدادات القديمة عامة (user_id = NULL)
        $pdo->exec("UPDATE settings SET user_id = NULL WHERE user_id IS NOT NULL OR user_id = 0");
        echo "✅ تم تعيين الإعدادات القديمة كإعدادات عامة\n";
    } else {
        echo "✓ user_id موجود بالفعل في settings\n";
    }
    
    // 2. إضافة إعدادات افتراضية للمستخدمين الموجودين
    $users = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $defaultSettings = [
        ['store_name', 'دفتر الحسابات', 'text', 'store', 'اسم المتجر', 1],
        ['store_icon', '', 'image', 'store', 'أيقونة المتجر', 2],
        ['store_logo', '', 'image', 'store', 'شعار المتجر', 3],
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, setting_group, setting_label, sort_order, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($users as $uid) {
        foreach ($defaultSettings as $ds) {
            $stmt->execute([...$ds, $uid]);
        }
    }
    
    echo "✅ تم إضافة إعدادات افتراضية لـ " . count($users) . " مستخدم\n";
    echo "\n🎉 تم التحديث بنجاح!\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage();
}
echo "</pre>";
?>