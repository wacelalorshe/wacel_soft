<?php
define('DB_HOST', 'sql107.infinityfree.com');
define('DB_NAME', 'if0_41815788_df_wacel');
define('DB_USER', 'if0_41815788');
define('DB_PASS', 'jwV1rYbI0qIjMb');

define('APP_NAME', 'البيب');
define('CURRENCY_LOCAL', 'ريال يمني');
define('CURRENCY_SAUDI', 'ريال سعودي');
define('CURRENCY_DOLLAR', 'دولار');

session_start();
try {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) { die('فشل الاتصال'); }
require_once __DIR__ . '/includes/functions.php';
