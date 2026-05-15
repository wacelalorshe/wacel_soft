<?php
// هذا الملف يتم تضمينه داخل body بعد header.php

// أيقونة الوضع الحالي
$themeIcon = $darkMode ? '☀️' : '🌙';
$themeLabel = $darkMode ? 'نهاري' : 'ليلي';
$themeTip = $darkMode ? 'التبديل إلى الوضع النهاري' : 'التبديل إلى الوضع الليلي';
?>

<style>
    /* ========== هيدر المحتوى ========== */
    .header-content {
        padding: 14px 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        position: sticky;
        top: 0;
        z-index: 50;
        background: <?php echo $darkMode ? 'rgba(15, 15, 26, 0.9)' : 'rgba(240, 242, 245, 0.9)'; ?>;
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-bottom: 1px solid var(--border);
        transition: all 0.4s ease;
    }

    .back-btn {
        width: 38px;
        height: 38px;
        border-radius: var(--radius-sm);
        background: var(--surface);
        border: 1px solid var(--border);
        color: var(--text);
        font-size: 16px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: all 0.3s ease;
        flex-shrink: 0;
    }

    .back-btn:hover {
        background: var(--surface-light);
        transform: translateX(3px);
    }

    .back-btn:active {
        transform: scale(0.92);
        background: var(--primary);
        border-color: var(--primary);
        color: white;
    }

    /* شعار المتجر في الهيدر */
    .header-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
        min-width: 0;
        text-decoration: none;
        color: var(--text);
    }

    .header-logo {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--surface-light);
        font-size: 20px;
        flex-shrink: 0;
        border: 1px solid var(--border);
    }

    .header-logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .header-text {
        flex: 1;
        min-width: 0;
    }

    .header-store-name {
        font-size: 16px;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .header-subtitle {
        font-size: 10px;
        color: var(--text-secondary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* مجموعة الأزرار */
    .header-actions {
        display: flex;
        gap: 6px;
        flex-shrink: 0;
    }

    .hdr-btn {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        border: 1px solid var(--border);
        cursor: pointer;
        font-size: 15px;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        background: var(--surface);
        color: var(--text);
        position: relative;
    }

    .hdr-btn:active {
        transform: scale(0.9);
    }

    /* زر تبديل الوضع */
    .theme-toggle-btn {
        background: <?php echo $darkMode ? '#fdcb6e' : '#2d3436'; ?>;
        border-color: <?php echo $darkMode ? '#fdcb6e' : '#2d3436'; ?>;
        color: <?php echo $darkMode ? '#333' : '#fff'; ?>;
        font-size: 16px;
        animation: themePulse 0.5s ease;
    }

    .theme-toggle-btn:active {
        transform: scale(0.85) rotate(15deg);
    }

    .theme-toggle-btn:hover {
        box-shadow: 0 4px 15px <?php echo $darkMode ? 'rgba(253, 203, 110, 0.3)' : 'rgba(45, 52, 54, 0.3)'; ?>;
    }

    /* أيقونات خاصة */
    .hdr-btn.statement { background: #ff6b35; border-color: #ff6b35; color: white; }
    .hdr-btn.add { background: var(--success); border-color: var(--success); color: white; font-size: 18px; font-weight: 300; }
    .hdr-btn.settings { }
    .hdr-btn.settings:hover { color: var(--primary); border-color: var(--primary); }

    /* شارة الإشعارات */
    .badge-dot {
        position: absolute;
        top: -2px;
        right: -2px;
        width: 10px;
        height: 10px;
        background: var(--danger);
        border-radius: 50%;
        border: 2px solid var(--surface);
        animation: badgePulse 2s ease infinite;
    }

    @keyframes themePulse {
        0% { transform: scale(0.8); }
        50% { transform: scale(1.15); }
        100% { transform: scale(1); }
    }

    @keyframes badgePulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    /* تأثيرات إضافية للهيدر */
    .header-content.scrolled {
        box-shadow: 0 2px 20px var(--shadow);
    }

    /* استجابة */
    @media (max-width: 360px) {
        .header-store-name { font-size: 14px; }
        .hdr-btn { width: 32px; height: 32px; font-size: 13px; }
        .header-logo { width: 32px; height: 32px; font-size: 16px; }
    }
</style>

<script>
    // إضافة تأثير التمرير على الهيدر
    document.addEventListener('DOMContentLoaded', function() {
        const header = document.querySelector('.header-content');
        if (header) {
            window.addEventListener('scroll', function() {
                if (window.scrollY > 10) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            }, { passive: true });
        }
    });

    // دالة تبديل الوضع
    function switchTheme() {
        if (typeof window.toggleTheme === 'function') {
            const isDark = window.toggleTheme();
            // تحديث الصفحة لإعادة تحميل الألوان
            setTimeout(() => {
                location.reload();
            }, 300);
        }
    }
</script>

<!-- ========== الهيدر ========== -->
<header class="header-content" id="mainHeader">
    <?php if (!$isIndex): ?>
    <a href="index.php" class="back-btn" title="الرئيسية">←</a>
    <?php endif; ?>
    
    <a href="index.php" class="header-brand">
        <div class="header-logo">
            <?php if ($storeLogo && !$isIndex): ?>
                <img src="<?php echo htmlspecialchars($storeLogo); ?>" alt="<?php echo htmlspecialchars($storeName); ?>">
            <?php elseif ($storeIcon): ?>
                <img src="<?php echo htmlspecialchars($storeIcon); ?>" alt="<?php echo htmlspecialchars($storeName); ?>">
            <?php else: ?>
                💳
            <?php endif; ?>
        </div>
        <div class="header-text">
            <div class="header-store-name">
                <?php if ($isIndex): ?>
                    # <?php echo htmlspecialchars($storeName); ?>
                <?php elseif ($isClient && isset($clientName)): ?>
                    👤 <?php echo htmlspecialchars($clientName); ?>
                <?php elseif ($isStatement || $isViewStatement): ?>
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
                    <?php echo date('d-m-Y'); ?> | <?php echo getArabicDayName(date('l')); ?>
                <?php elseif ($isClient): ?>
                    المعاملات والرصيد
                <?php elseif ($isStatement): ?>
                    <?php echo htmlspecialchars($storeName); ?>
                <?php else: ?>
                    <?php echo htmlspecialchars($storeName); ?>
                <?php endif; ?>
            </div>
        </div>
    </a>
    
    <div class="header-actions">
        <?php if ($isClient && isset($_GET['id'])): ?>
            <a href="statement.php?id=<?php echo (int)$_GET['id']; ?>" class="hdr-btn statement" title="كشف حساب">📄</a>
        <?php endif; ?>
        
        <?php if ($isIndex): ?>
            <button class="hdr-btn add" onclick="location.href='add_partner.php'" title="إضافة عميل">＋</button>
        <?php endif; ?>
        
        <?php if (!$isSettings): ?>
            <a href="settings.php" class="hdr-btn settings" title="الإعدادات">⚙️</a>
        <?php endif; ?>
        
        <!-- زر تبديل الوضع الليلي/النهاري -->
        <button class="hdr-btn theme-toggle-btn" 
                onclick="switchTheme()" 
                title="<?php echo $themeTip; ?>"
                id="themeToggleBtn">
            <?php echo $themeIcon; ?>
        </button>
    </div>
</header>

<?php
// دالة اسم اليوم بالعربية
function getArabicDayName($englishDay) {
    $days = [
        'Saturday' => 'السبت',
        'Sunday' => 'الأحد',
        'Monday' => 'الإثنين',
        'Tuesday' => 'الثلاثاء',
        'Wednesday' => 'الأربعاء',
        'Thursday' => 'الخميس',
        'Friday' => 'الجمعة'
    ];
    return $days[$englishDay] ?? $englishDay;
}
?>