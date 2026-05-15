<?php
/**
 * نظام المصادقة - Authentication System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * التحقق من تسجيل الدخول
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * التحقق من صلاحية المشرف
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * طلب تسجيل الدخول
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit;
    }
}

/**
 * طلب صلاحية مشرف
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: index.php?error=access_denied');
        exit;
    }
}

/**
 * محاولة تسجيل الدخول
 */
function attemptLogin($pdo, $username, $password) {
    // التحقق من محاولات القفل
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return ['success' => false, 'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة'];
    }
    
    // التحقق من الحالة
    if ($user['status'] === 'inactive') {
        return ['success' => false, 'message' => 'الحساب معطل. راجع المسؤول'];
    }
    
    // التحقق من القفل
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        $minutes = ceil((strtotime($user['locked_until']) - time()) / 60);
        return ['success' => false, 'message' => "الحساب مقفل. حاول مرة أخرى بعد {$minutes} دقيقة"];
    }
    
    // التحقق من كلمة المرور
    if (!password_verify($password, $user['password'])) {
        // زيادة محاولات الفشل
        $attempts = $user['login_attempts'] + 1;
        $lockedUntil = null;
        
        if ($attempts >= 5) {
            $lockedUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        }
        
        $stmt = $pdo->prepare("UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?");
        $stmt->execute([$attempts, $lockedUntil, $user['id']]);
        
        $remaining = 5 - $attempts;
        return ['success' => false, 'message' => "كلمة المرور غير صحيحة. متبقي {$remaining} محاولات"];
    }
    
    // تسجيل الدخول ناجح
    $stmt = $pdo->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // إعداد الجلسة
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_fullname'] = $user['fullname'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['login_time'] = time();
    
    // توليد remember token إذا طلب
    if (isset($_POST['remember']) && $_POST['remember'] === 'on') {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
        $stmt->execute([$token, $expires, $user['id']]);
        
        setcookie('remember_token', $token, strtotime('+30 days'), '/', '', false, true);
        setcookie('remember_user', $user['id'], strtotime('+30 days'), '/', '', false, true);
    }
    
    return ['success' => true, 'message' => 'تم تسجيل الدخول بنجاح'];
}

/**
 * تسجيل مستخدم جديد
 */
function registerUser($pdo, $username, $password, $fullname, $email = '') {
    // التحقق من وجود المستخدم
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        return ['success' => false, 'message' => 'اسم المستخدم موجود مسبقاً'];
    }
    
    if ($email) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'البريد الإلكتروني مستخدم مسبقاً'];
        }
    }
    
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, email, role) VALUES (?, ?, ?, ?, 'user')");
    $stmt->execute([$username, $hashedPassword, $fullname, $email]);
    
    return ['success' => true, 'message' => 'تم إنشاء الحساب بنجاح'];
}

/**
 * إنشاء رمز استعادة كلمة المرور
 */
function createResetToken($pdo, $email) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return ['success' => false, 'message' => 'البريد الإلكتروني غير مسجل'];
    }
    
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
    $stmt->execute([$token, $expires, $user['id']]);
    
    return ['success' => true, 'token' => $token, 'email' => $email];
}

/**
 * إعادة تعيين كلمة المرور
 */
function resetPassword($pdo, $token, $newPassword) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return ['success' => false, 'message' => 'رابط إعادة التعيين غير صالح أو منتهي الصلاحية'];
    }
    
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL, login_attempts = 0, locked_until = NULL WHERE id = ?");
    $stmt->execute([$hashedPassword, $user['id']]);
    
    return ['success' => true, 'message' => 'تم تغيير كلمة المرور بنجاح'];
}

/**
 * تسجيل الخروج
 */
function logout() {
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
        setcookie('remember_user', '', time() - 3600, '/');
    }
}

/**
 * التحقق من remember me token
 */
function checkRememberToken($pdo) {
    if (isset($_COOKIE['remember_token']) && isset($_COOKIE['remember_user'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND reset_token = ? AND reset_expires > NOW()");
        $stmt->execute([$_COOKIE['remember_user'], $_COOKIE['remember_token']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_fullname'] = $user['fullname'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['login_time'] = time();
            return true;
        }
    }
    return false;
}


/**
 * الحصول على user_id الحالي
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? 0;
}

/**
 * التحقق مما إذا كان المستخدم مشرفاً ويمكنه رؤية جميع البيانات
 */
function canViewAllData() {
    return isAdmin();
}


