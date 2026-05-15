<?php
require_once 'config.php';
require_once 'includes/auth.php';
// في header.php أو في كل صفحة
header('Service-Worker-Allowed: /');
// إذا كان المستخدم مسجل دخوله بالفعل
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// التحقق من remember token
checkRememberToken($pdo);
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// معالجة تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'الرجاء إدخال اسم المستخدم وكلمة المرور';
    } else {
        $result = attemptLogin($pdo, $username, $password);
        if ($result['success']) {
            $redirect = $_SESSION['redirect_url'] ?? 'index.php';
            unset($_SESSION['redirect_url']);
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>تسجيل الدخول - دفتر الحسابات</title>
    <meta name="theme-color" content="#0f0f1a">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 50%, #16213e 100%);
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #fff;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header .logo {
            font-size: 60px;
            margin-bottom: 10px;
            display: block;
            animation: float 3s ease infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }
        .login-header h1 {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 6px;
        }
        .login-header p {
            font-size: 13px;
            color: #8888a0;
        }
        .login-card {
            background: rgba(26, 26, 46, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px 24px;
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 13px;
            font-weight: 600;
        }
        .alert-error {
            background: rgba(255, 71, 87, 0.15);
            color: #ff6b6b;
            border: 1px solid rgba(255, 71, 87, 0.3);
        }
        .alert-success {
            background: rgba(0, 214, 143, 0.15);
            color: #00d68f;
            border: 1px solid rgba(0, 214, 143, 0.3);
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #8888a0;
            margin-bottom: 6px;
        }
        .form-input {
            width: 100%;
            padding: 14px 16px;
            background: rgba(15, 22, 41, 0.6);
            border: 1.5px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            color: #fff;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.3s;
        }
        .form-input:focus {
            outline: none;
            border-color: #6c5ce7;
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.15);
        }
        .form-input::placeholder { color: #5a5a7a; }
        .input-icon {
            position: relative;
        }
        .input-icon .icon {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
        }
        .input-icon .form-input {
            padding-right: 44px;
        }
        .checkbox-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #8888a0;
            cursor: pointer;
        }
        .checkbox-label input {
            width: 18px;
            height: 18px;
            accent-color: #6c5ce7;
        }
        .forgot-link {
            font-size: 13px;
            color: #6c5ce7;
            text-decoration: none;
            font-weight: 600;
        }
        .forgot-link:hover { text-decoration: underline; }
        .login-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #6c5ce7, #a29bfe);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.3s;
            box-shadow: 0 8px 25px rgba(108, 92, 231, 0.3);
        }
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(108, 92, 231, 0.4);
        }
        .login-btn:active { transform: scale(0.97); }
        .login-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .register-link {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #8888a0;
        }
        .register-link a {
            color: #6c5ce7;
            text-decoration: none;
            font-weight: 700;
        }
        .register-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <span class="logo">💳</span>
            <h1>دفتر الحسابات</h1>
            <p>قم بتسجيل الدخول للمتابعة</p>
        </div>
        
        <div class="login-card">
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" onsubmit="return handleSubmit(this)">
                <div class="form-group">
                    <label class="form-label">اسم المستخدم</label>
                    <div class="input-icon">
                        <span class="icon">👤</span>
                        <input type="text" class="form-input" name="username" 
                               placeholder="أدخل اسم المستخدم" required autocomplete="username" autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">كلمة المرور</label>
                    <div class="input-icon">
                        <span class="icon">🔒</span>
                        <input type="password" class="form-input" name="password" 
                               placeholder="أدخل كلمة المرور" required autocomplete="current-password">
                    </div>
                </div>
                
                <div class="checkbox-row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember">
                        <span>تذكرني</span>
                    </label>
                    <a href="forgot_password.php" class="forgot-link">نسيت كلمة المرور؟</a>
                </div>
                
                <button type="submit" class="login-btn" id="loginBtn">🚀 تسجيل الدخول</button>
            </form>
        </div>
        
        <div class="register-link">
            ليس لديك حساب؟ <a href="register.php">إنشاء حساب جديد</a>
        </div>
    </div>
    
    <script>
        function handleSubmit(form) {
            const btn = document.getElementById('loginBtn');
            btn.textContent = '⏳ جاري تسجيل الدخول...';
            btn.disabled = true;
            return true;
        }
    </script>
</body>
</html>