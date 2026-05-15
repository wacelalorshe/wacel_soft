<?php
require_once 'config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$step = 1;
$verificationCode = '';
$whatsappUrl = '';

// معالجة التسجيل
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $fullname = trim($_POST['fullname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // التحقق من البيانات
    if (empty($username) || empty($password) || empty($fullname)) {
        $error = 'الرجاء ملء جميع الحقول المطلوبة';
    } elseif (strlen($username) < 3) {
        $error = 'اسم المستخدم يجب أن يكون 3 أحرف على الأقل';
    } elseif (strlen($password) < 6) {
        $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    } elseif ($password !== $password2) {
        $error = 'كلمتا المرور غير متطابقتين';
    } else {
        // التحقق من عدم وجود المستخدم
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'اسم المستخدم موجود مسبقاً';
        } else {
            // إنشاء كود تحقق عشوائي
            $verificationCode = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, phone, role, status, verification_code, verification_status) VALUES (?, ?, ?, ?, 'user', 'inactive', ?, 'pending')");
                $stmt->execute([$username, $hashedPassword, $fullname, $phone, $verificationCode]);
                
                // إنشاء رابط واتساب مباشر
                $whatsappUrl = createWhatsAppLink($fullname, $username, $phone, $verificationCode);
                
                // حفظ البيانات في الجلسة
                $_SESSION['pending_username'] = $username;
                $_SESSION['pending_code'] = $verificationCode;
                $_SESSION['whatsapp_url'] = $whatsappUrl;
                
                $success = 'تم إنشاء الحساب بنجاح!';
                $step = 2;
                
                // تسجيل النشاط
                logActivity($pdo, null, 'register', "تسجيل مستخدم جديد: {$fullname} (@{$username})");
                
            } catch (Exception $e) {
                $error = 'خطأ في إنشاء الحساب: ' . $e->getMessage();
            }
        }
    }
}

/**
 * إنشاء رابط واتساب مباشر مع رسالة منسقة
 */
function createWhatsAppLink($fullname, $username, $phone, $code) {
    // رقم واتساب الإدارة
    $adminPhone = '967735981122'; // بدون + أو مسافات
    
    // رسالة منسقة للإدارة
    $message = "🔐 *طلب تسجيل جديد*\n\n";
    $message .= "👤 *الاسم:* {$fullname}\n";
    $message .= "🔑 *المستخدم:* `{$username}`\n";
    
    if (!empty($phone)) {
        $message .= "📱 *الهاتف:* `{$phone}`\n";
    }
    
    $message .= "🔢 *كود التحقق:* `{$code}`\n\n";
    $message .= "📅 *التاريخ:* " . date('Y-m-d h:i A') . "\n\n";
    $message .= "———\n";
    $message .= "للموافقة على هذا المستخدم:\n";
    $message .= "✅ اضغط على رابط الموافقة السريعة:\n";
    $message .= "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/admin/index.php?tab=pending\n\n";
    $message .= "او استخدم الأوامر:\n";
    $message .= "للموافقة: `/approve {$code}`\n";
    $message .= "للرفض: `/reject {$code}`\n\n";
    $message .= "———\n";
    $message .= "📱 *نظام دفتر الحسابات*";
    
    // رابط واتساب مباشر
    $whatsappUrl = "https://wa.me/{$adminPhone}?text=" . urlencode($message);
    
    return $whatsappUrl;
}

/**
 * تسجيل النشاط
 */
function logActivity($pdo, $userId, $action, $description) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $description, $ip]);
    } catch (Exception $e) {
        // تجاهل أخطاء السجل
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>إنشاء حساب - دفتر الحسابات</title>
    <meta name="theme-color" content="#0f0f1a">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 50%, #16213e 100%);
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #fff;
        }
        .container { width: 100%; max-width: 420px; }
        
        .header { text-align: center; margin-bottom: 24px; }
        .header .logo { font-size: 55px; display: block; margin-bottom: 8px; animation: float 3s ease infinite; }
        @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-15px)} }
        .header h1 { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
        .header p { font-size: 13px; color: #8888a0; }
        
        .card {
            background: rgba(26,26,46,0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 28px 22px;
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }
        
        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.6;
        }
        .alert-error { background: rgba(255,71,87,0.15); color: #ff6b6b; border: 1px solid rgba(255,71,87,0.3); }
        .alert-success { background: rgba(0,214,143,0.15); color: #00d68f; border: 1px solid rgba(0,214,143,0.3); }
        .alert-info { background: rgba(55,66,250,0.15); color: #a29bfe; border: 1px solid rgba(55,66,250,0.3); }
        .alert-warning { background: rgba(255,165,2,0.15); color: #ffd93d; border: 1px solid rgba(255,165,2,0.3); }
        
        .form-group { margin-bottom: 14px; }
        .form-label { display: block; font-size: 12px; font-weight: 600; color: #8888a0; margin-bottom: 5px; }
        .form-input {
            width: 100%;
            padding: 13px 14px;
            background: rgba(15,22,41,0.6);
            border: 1.5px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            color: #fff;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.3s;
        }
        .form-input:focus { outline: none; border-color: #6c5ce7; box-shadow: 0 0 0 3px rgba(108,92,231,0.15); }
        .form-input::placeholder { color: #5a5a7a; }
        
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.3s;
        }
        .btn:active { transform: scale(0.96); }
        .btn-primary { background: linear-gradient(135deg, #6c5ce7, #a29bfe); color: #fff; box-shadow: 0 8px 25px rgba(108,92,231,0.3); }
        .btn-whatsapp { background: #25D366; color: #fff; font-size: 16px; box-shadow: 0 8px 25px rgba(37,211,102,0.3); animation: pulse-wa 2s infinite; }
        @keyframes pulse-wa { 0%,100%{box-shadow:0 8px 25px rgba(37,211,102,0.3)} 50%{box-shadow:0 12px 35px rgba(37,211,102,0.5)} }
        .btn-check { background: rgba(255,255,255,0.05); color: #8888a0; border: 1px solid rgba(255,255,255,0.1); }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-bottom: 20px;
            align-items: center;
        }
        .step-dot {
            width: 30px; height: 30px; border-radius: 50%; background: #2a2a45;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; color: #8888a0; transition: all 0.3s;
        }
        .step-dot.active { background: #6c5ce7; color: #fff; box-shadow: 0 0 15px rgba(108,92,231,0.5); }
        .step-dot.done { background: #00d68f; color: #fff; }
        .step-line { flex: 1; height: 2px; background: #2a2a45; max-width: 40px; }
        .step-line.done { background: #00d68f; }
        
        .code-box {
            text-align: center;
            padding: 16px;
            background: rgba(15,22,41,0.6);
            border-radius: 12px;
            margin: 14px 0;
            border: 2px dashed #6c5ce7;
        }
        .code-text {
            font-size: 34px;
            font-weight: 900;
            letter-spacing: 8px;
            color: #ffd93d;
            font-family: 'Courier New', monospace;
        }
        .code-label { font-size: 11px; color: #8888a0; margin-bottom: 8px; }
        .code-sub { font-size: 10px; color: #ff6b6b; margin-top: 6px; }
        
        .waiting-box { text-align: center; padding: 16px 0; }
        .spinner {
            width: 50px; height: 50px; border: 3px solid rgba(108,92,231,0.2);
            border-top-color: #6c5ce7; border-radius: 50%;
            animation: spin 1s linear infinite; margin: 0 auto 14px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .admin-info {
            text-align: center; padding: 12px; background: rgba(255,255,255,0.03);
            border-radius: 10px; margin: 12px 0; font-size: 12px; color: #8888a0;
        }
        .admin-info strong { color: #25D366; }
        
        .link { text-align: center; margin-top: 16px; font-size: 13px; color: #8888a0; }
        .link a { color: #6c5ce7; text-decoration: none; font-weight: 700; }
        .link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span class="logo">💳</span>
            <h1>إنشاء حساب جديد</h1>
            <p>سجل حسابك وانتظر موافقة الإدارة</p>
        </div>
        
        <!-- مؤشر الخطوات -->
        <div class="step-indicator">
            <span class="step-dot <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'done' : ''; ?>">
                <?php echo $step > 1 ? '✓' : '1'; ?>
            </span>
            <span class="step-line <?php echo $step > 1 ? 'done' : ''; ?>"></span>
            <span class="step-dot <?php echo $step >= 2 ? 'active' : ''; ?>">
                2
            </span>
            <span class="step-line"></span>
            <span class="step-dot">3</span>
        </div>
        
        <div class="card">
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($step === 1): ?>
                <!-- ========== الخطوة 1: نموذج التسجيل ========== -->
                <form method="POST" id="registerForm">
                    <div class="form-group">
                        <label class="form-label">الاسم الكامل *</label>
                        <input type="text" class="form-input" name="fullname" 
                               placeholder="أدخل اسمك الكامل" required autofocus maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">اسم المستخدم *</label>
                        <input type="text" class="form-input" name="username" 
                               placeholder="اختر اسم مستخدم (3 أحرف على الأقل)" required minlength="3" maxlength="50">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">رقم الهاتف</label>
                        <input type="tel" class="form-input" name="phone" 
                               placeholder="مثال: 777123456" dir="ltr" maxlength="20">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">كلمة المرور * (6 أحرف على الأقل)</label>
                        <input type="password" class="form-input" name="password" 
                               placeholder="أدخل كلمة المرور" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">تأكيد كلمة المرور *</label>
                        <input type="password" class="form-input" name="password2" 
                               placeholder="أعد إدخال كلمة المرور" required>
                    </div>
                    
                    <div class="alert alert-info">
                        📝 بعد التسجيل، سيتم إرسال كود التحقق مباشرة إلى إدارة النظام عبر واتساب للموافقة على حسابك.
                    </div>
                    
                    <button type="submit" class="btn btn-primary">✅ إنشاء الحساب</button>
                </form>
                
            <?php else: ?>
                <!-- ========== الخطوة 2: تم التسجيل - انتظار الموافقة ========== -->
                <?php if ($success): ?>
                    <div class="alert alert-success">✅ <?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="waiting-box">
                    <div class="spinner"></div>
                    <h3 style="margin-bottom:4px;">🕐 بانتظار موافقة الإدارة</h3>
                    <p style="font-size:12px;color:#8888a0;">تم إنشاء حسابك وإرسال الطلب للإدارة</p>
                </div>
                
                <!-- كود التحقق -->
                <div class="code-box">
                    <div class="code-label">🔢 كود التحقق الخاص بك</div>
                    <div class="code-text"><?php echo $_SESSION['pending_code'] ?? $verificationCode; ?></div>
                    <div class="code-sub">⚠️ احفظ هذا الكود - ستحتاجه عند المراجعة</div>
                </div>
                
                <!-- معلومات الإدارة -->
                <div class="admin-info">
                    📱 تم إرسال طلبك إلى الإدارة عبر واتساب<br>
                    رقم الإدارة: <strong>+967 735 981 122</strong>
                </div>
                
                <!-- زر إرسال للإدارة -->
                <a href="<?php echo $_SESSION['whatsapp_url'] ?? $whatsappUrl; ?>" 
                   target="_blank" 
                   class="btn btn-whatsapp" 
                   style="display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none;">
                    <span style="font-size:22px;">💬</span>
                    إرسال كود التحقق للإدارة عبر واتساب
                </a>
                
                <p style="text-align:center;font-size:11px;color:#8888a0;margin-top:8px;">
                    اضغط على الزر أعلاه لإرسال الكود مباشرة للإدارة
                </p>
                
                <!-- زر التحقق من الموافقة -->
                <button class="btn btn-check" onclick="checkApproval()" id="checkBtn" style="margin-top:10px;">
                    🔄 التحقق من حالة الموافقة
                </button>
                
                <p style="text-align:center;font-size:11px;color:#8888a0;margin-top:8px;">
                    بعد الموافقة، يمكنك <a href="login.php" style="color:#6c5ce7;">تسجيل الدخول</a>
                </p>
            <?php endif; ?>
        </div>
        
        <div class="link">
            لديك حساب بالفعل؟ <a href="login.php">تسجيل الدخول</a>
        </div>
    </div>
    
    <script>
        // محاولة فتح واتساب تلقائياً بعد التسجيل
        <?php if ($step === 2 && !empty($whatsappUrl)): ?>
        // فتح واتساب في نافذة جديدة بعد 3 ثواني
        setTimeout(function() {
            window.open('<?php echo $whatsappUrl; ?>', '_blank');
        }, 2000);
        <?php endif; ?>
        
        function checkApproval() {
            const btn = document.getElementById('checkBtn');
            btn.textContent = '⏳ جاري التحقق...';
            btn.disabled = true;
            
            fetch('api/check_approval.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'username=<?php echo $_SESSION['pending_username'] ?? ''; ?>'
            })
            .then(r => r.json())
            .then(data => {
                btn.textContent = '🔄 التحقق من حالة الموافقة';
                btn.disabled = false;
                
                if (data.approved) {
                    alert('✅ تمت الموافقة على حسابك! سيتم تحويلك لصفحة تسجيل الدخول.');
                    window.location.href = 'login.php?approved=1';
                } else if (data.rejected) {
                    alert('❌ تم رفض طلبك.\nالسبب: ' + (data.reason || 'غير محدد'));
                } else {
                    alert('⏳ حسابك لا يزال قيد المراجعة.\nيرجى الانتظار أو التواصل مع الإدارة.\n\n📱 واتساب: +967 735 981 122');
                }
            })
            .catch(() => {
                btn.textContent = '🔄 التحقق من حالة الموافقة';
                btn.disabled = false;
                alert('⚠️ خطأ في الاتصال. تأكد من اتصالك بالإنترنت.');
            });
        }
        
        // تحديث تلقائي كل 10 ثواني
        <?php if ($step === 2): ?>
        setInterval(checkApproval, 10000);
        <?php endif; ?>
    </script>
</body>
</html>