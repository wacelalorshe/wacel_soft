<?php
require_once 'config.php';

echo "<h2>🔧 إصلاح المفاتيح الفريدة</h2><pre>";

try {
    // 1. حذف المفتاح الفريد القديم إذا كان موجوداً
    try {
        $pdo->exec("ALTER TABLE settings DROP INDEX setting_key");
        echo "✅ تم حذف المفتاح الفريد القديم\n";
    } catch (Exception $e) {
        echo "⚠️ المفتاح القديم غير موجود\n";
    }
    
    // 2. إضافة مفتاح فريد مركب (setting_key + user_id)
    try {
        $pdo->exec("ALTER TABLE settings ADD UNIQUE INDEX idx_setting_user (setting_key, user_id)");
        echo "✅ تم إضافة المفتاح الفريد المركب (setting_key, user_id)\n";
    } catch (Exception $e) {
        echo "⚠️ " . $e->getMessage() . "\n";
    }
    
    echo "\n🎉 تم الإصلاح!\n";
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage();
}
echo "</pre>";
?>