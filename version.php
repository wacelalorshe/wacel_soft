<?php
/**
 * ملف تتبع الإصدار
 */
define('APP_VERSION', '1.0.1');
define('APP_BUILD', '<?php echo time(); ?>');
define('LAST_UPDATE', '2025-05-07 12:00:00');

// تحديث manifest.json تلقائياً
$manifestFile = __DIR__ . '/manifest.json';
if (file_exists($manifestFile)) {
    $manifest = json_decode(file_get_contents($manifestFile), true);
    $manifest['version'] = APP_VERSION;
    $manifest['build'] = APP_BUILD;
    file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
?>