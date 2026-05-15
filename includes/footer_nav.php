<?php
/**
 * القائمة السفلية - Footer Navigation
 * تحتوي على: الرئيسية | العملاء | إضافة (نافذة منبثقة) | كشف حساب | الإعدادات
 * تدعم: إضافة معاملة سريعة | إضافة عميل جديد | البحث عن عميل للكشف
 * الوضع الافتراضي: ليلي
 */

// ========== إعدادات الكاش للسماح للمتصفح بتخزين الصفحة (ساعة واحدة) ==========
header("Cache-Control: public, max-age=3600, must-revalidate");
header("Pragma: cache");
header("Expires: " . gmdate("D, d M Y H:i:s", time() + 3600) . " GMT");
// ============================================================================

// في header.php أو في كل صفحة
header('Service-Worker-Allowed: /');
if (!isset($settings) && isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('store_name', 'accent_color', 'dark_mode', 'theme_color', 'card_color', 'text_color')");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        $settings = [];
    }
}

$storeName   = $settings['store_name']   ?? 'دفتر الحسابات';
$accentColor = $settings['accent_color'] ?? '#6c5ce7';
// ========== الوضع الافتراضي: ليلي ==========
$darkMode    = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] === 'true' : true;
// =========================================

$currentPage = basename($_SERVER['PHP_SELF']);
$isIndex          = ($currentPage === 'index.php');
$isClient         = ($currentPage === 'client.php');
$isStatement      = ($currentPage === 'statement.php');
$isSettings       = ($currentPage === 'settings.php');
$isAddTransaction = ($currentPage === 'add_transaction.php');
$isAddPartner     = ($currentPage === 'add_partner.php');

$navItems = [
    ['id' => 'home',      'icon' => '🏠', 'label' => 'الرئيسية',   'url' => 'index.php',        'active' => $isIndex],
    ['id' => 'clients',   'icon' => '👥', 'label' => 'العملاء',    'url' => 'add_partner.php',  'active' => $isAddPartner],
    ['id' => 'add',       'icon' => '＋', 'label' => 'إضافة',      'url' => '#',                'active' => false, 'modal' => true],
    ['id' => 'statement', 'icon' => '📄', 'label' => 'كشف حساب',   'url' => '#',                'active' => $isStatement, 'onclick' => 'openStatementSearch()'],
    ['id' => 'settings',  'icon' => '⚙️', 'label' => 'الإعدادات',  'url' => 'settings.php',     'active' => $isSettings],
];

// ألوان الثيم
if ($darkMode) {
    $navBg = 'rgba(22,22,40,0.95)'; $navBorder = 'rgba(255,255,255,0.08)';
    $modalBg = '#1a1a2e'; $modalSurface = '#252540'; $modalBorder = '#2a2a45';
    $modalText = '#fff'; $modalTextSec = '#8888a0'; $emptyColor = '#888';
} else {
    $navBg = 'rgba(255,255,255,0.95)'; $navBorder = 'rgba(0,0,0,0.08)';
    $modalBg = '#fff'; $modalSurface = '#f8f9fa'; $modalBorder = '#e5e7eb';
    $modalText = '#1a1a2e'; $modalTextSec = '#6b7280'; $emptyColor = '#999';
}
?>

<style>
    .bottom-nav { position:fixed; bottom:0; left:0; right:0; max-width:500px; margin:0 auto; background:<?php echo $navBg; ?>; backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px); border-top:1px solid <?php echo $navBorder; ?>; display:flex; justify-content:space-around; align-items:center; padding:6px 4px; padding-bottom:max(6px,env(safe-area-inset-bottom,6px)); z-index:99; }
    .nav-item { display:flex; flex-direction:column; align-items:center; gap:2px; padding:5px 8px; min-width:52px; cursor:pointer; text-decoration:none; color:<?php echo $darkMode?'#8888a0':'#6b7280'; ?>; transition:all .25s; position:relative; border-radius:12px; -webkit-tap-highlight-color:transparent; user-select:none; font-family:inherit; border:none; background:none; }
    .nav-item:active { transform:scale(.9); }
    .nav-item.active { color:<?php echo $accentColor; ?>; }
    .nav-item.active::before { content:''; position:absolute; top:-7px; left:50%; transform:translateX(-50%); width:20px; height:3px; background:<?php echo $accentColor; ?>; border-radius:0 0 4px 4px; }
    .nav-icon { font-size:19px; line-height:1; }
    .nav-label { font-size:10px; font-weight:600; }
    .nav-item.add-btn-nav { position:relative; top:-18px; }
    .nav-item.add-btn-nav .nav-icon-wrap { width:50px; height:50px; background:linear-gradient(135deg,<?php echo $accentColor; ?>,#a29bfe); border-radius:15px; display:flex; align-items:center; justify-content:center; box-shadow:0 8px 25px rgba(108,92,231,.4); animation:pulse-add 2.5s infinite; }
    .nav-item.add-btn-nav .nav-icon { font-size:24px; color:#fff; font-weight:300; }
    .nav-item.add-btn-nav .nav-label { margin-top:4px; color:<?php echo $accentColor; ?>; }
    @keyframes pulse-add { 0%,100%{box-shadow:0 8px 25px rgba(108,92,231,.4)} 50%{box-shadow:0 14px 35px rgba(108,92,231,.6)} }

    /* ========== المودال بنمط index.php ========== */
    .modal-overlay { 
        position:fixed; 
        top:0; 
        left:0; 
        width:100%; 
        height:100%; 
        background:rgba(0,0,0,0.6); 
        backdrop-filter:blur(6px); 
        -webkit-backdrop-filter:blur(6px); 
        z-index:250; 
        display:none; 
        overflow-y:auto; 
        animation:fadeIn .2s ease; 
    }
    .modal-sheet { 
        background:<?php echo $modalBg; ?>; 
        margin:60px auto 20px auto; 
        width:92%; 
        max-width:500px; 
        border-radius:18px; 
        padding:22px 18px 24px; 
        box-shadow:0 15px 50px rgba(0,0,0,0.5); 
        animation:modalIn 0.3s ease; 
    }
    .modal-handle { 
        width:36px; 
        height:5px; 
        background:rgba(255,255,255,.2); 
        border-radius:3px; 
        margin:0 auto 14px; 
    }
    .modal-title { 
        font-size:18px; 
        font-weight:700; 
        text-align:center; 
        margin-bottom:18px; 
        color:<?php echo $modalText; ?>; 
    }
    .modal-cancel { 
        display:block; 
        width:100%; 
        padding:14px; 
        margin-top:18px; 
        background:<?php echo $modalSurface; ?>; 
        border:1px solid <?php echo $modalBorder; ?>; 
        border-radius:12px; 
        color:<?php echo $modalTextSec; ?>; 
        font-size:14px; 
        font-weight:700; 
        cursor:pointer; 
        text-align:center; 
        font-family:inherit; 
        transition:all 0.3s; 
    }
    .modal-cancel:active { transform:scale(0.96); background:rgba(255,107,107,0.1); color:#ff6b6b; border-color:#ff6b6b; }

    .fg { margin-bottom:14px; }
    .fl { display:block; font-size:12px; font-weight:600; color:<?php echo $modalTextSec; ?>; margin-bottom:6px; }
    .fi { 
        width:100%; 
        padding:13px 15px; 
        background:<?php echo $modalSurface; ?>; 
        border:1.5px solid <?php echo $modalBorder; ?>; 
        border-radius:12px; 
        color:<?php echo $modalText; ?>; 
        font-size:14px; 
        font-family:inherit; 
        outline:none; 
        transition:all 0.3s; 
    }
    .fi:focus { border-color:<?php echo $accentColor; ?>; box-shadow:0 0 0 3px rgba(108,92,231,0.1); }
    .fi.big { font-size:20px; font-weight:700; text-align:center; }
    .ft { display:flex; gap:8px; }
    .ftb { 
        flex:1; 
        padding:12px; 
        border-radius:12px; 
        border:2px solid <?php echo $modalBorder; ?>; 
        background:transparent; 
        color:<?php echo $modalTextSec; ?>; 
        font-weight:700; 
        font-size:14px; 
        cursor:pointer; 
        font-family:inherit; 
        transition:all 0.3s; 
    }
    .ftb:active { transform:scale(0.95); }
    .ftb.active.debit { background:#ff6b6b; border-color:#ff6b6b; color:#fff; }
    .ftb.active.credit { background:#00d68f; border-color:#00d68f; color:#fff; }
    .fsub { 
        width:100%; 
        padding:14px; 
        background:<?php echo $accentColor; ?>; 
        color:#fff; 
        border:none; 
        border-radius:12px; 
        font-weight:700; 
        font-size:14px; 
        cursor:pointer; 
        font-family:inherit; 
        margin-top:6px; 
        transition:all 0.3s; 
    }
    .fsub:active { transform:scale(0.96); }
    .fsub:disabled { opacity:0.5; cursor:not-allowed; }

    .sr { max-height:280px; overflow-y:auto; }
    .si { 
        display:flex; 
        align-items:center; 
        gap:12px; 
        padding:12px 14px; 
        border-radius:12px; 
        cursor:pointer; 
        text-decoration:none; 
        color:<?php echo $modalText; ?>; 
        transition:all .2s; 
    }
    .si:hover, .si:active { background:<?php echo $darkMode?'rgba(255,255,255,.05)':'rgba(0,0,0,.03)'; ?>; }
    .sa { 
        width:42px; 
        height:42px; 
        border-radius:12px; 
        display:flex; 
        align-items:center; 
        justify-content:center; 
        font-weight:700; 
        font-size:16px; 
        flex-shrink:0; 
    }
    .sn { font-weight:600; font-size:14px; }
    .sm { font-size:11px; color:<?php echo $modalTextSec; ?>; margin-top:2px; }
    .sb { font-size:15px; font-weight:700; } .sb.neg { color:#ff6b6b; } .sb.pos { color:#00d68f; }
    .se { text-align:center; padding:20px; color:<?php echo $emptyColor; ?>; font-size:13px; }

    .quick-tabs { 
        display:flex; 
        gap:4px; 
        margin-bottom:14px; 
        background:<?php echo $modalSurface; ?>; 
        border-radius:12px; 
        padding:4px; 
    }
    .quick-tab { 
        flex:1; 
        padding:11px; 
        border-radius:10px; 
        border:none; 
        background:transparent; 
        color:<?php echo $modalTextSec; ?>; 
        font-weight:700; 
        font-size:13px; 
        cursor:pointer; 
        font-family:inherit; 
        text-align:center; 
        transition:all 0.3s; 
    }
    .quick-tab.active { background:<?php echo $accentColor; ?>; color:#fff; }

    .sp { 
        display:flex; 
        align-items:center; 
        gap:10px; 
        padding:12px; 
        background:rgba(0,214,143,.1); 
        border:1px solid #00d68f; 
        border-radius:12px; 
        margin-top:8px; 
    }
    .spn { font-weight:600; font-size:13px; }
    .spr { 
        margin-right:auto; 
        background:#ff6b6b; 
        color:#fff; 
        border:none; 
        border-radius:50%; 
        width:26px; 
        height:26px; 
        cursor:pointer; 
        font-size:13px; 
        transition:all 0.3s; 
    }
    .spr:active { transform:scale(0.9); }

    /* ========== شريط التحديث ========== */
    .update-banner {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 9999;
        background: <?php echo $accentColor; ?>;
        color: #fff;
        padding: 12px 16px;
        text-align: center;
        font-weight: 700;
        font-size: 13px;
        animation: slideDown 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .update-banner button {
        background: #fff;
        color: <?php echo $accentColor; ?>;
        border: none;
        padding: 6px 14px;
        border-radius: 20px;
        font-weight: 700;
        cursor: pointer;
        font-family: inherit;
        font-size: 12px;
    }
    .update-banner button:active {
        transform: scale(0.95);
    }

    @keyframes fadeIn { from{opacity:0} to{opacity:1} }
    @keyframes modalIn { from{transform:translateY(-40px);opacity:0} to{transform:translateY(0);opacity:1} }
    @keyframes slideDown { from{transform:translateY(-100%);opacity:0} to{transform:translateY(0);opacity:1} }
    @keyframes slideUp { from{transform:translateY(0);opacity:1} to{transform:translateY(-100%);opacity:0} }
    @media print { .bottom-nav,.modal-overlay { display:none!important; } }
    body.has-bottom-nav { padding-bottom:85px!important; }
    @media (min-width:501px) { .bottom-nav { border-left:1px solid <?php echo $navBorder; ?>; border-right:1px solid <?php echo $navBorder; ?>; } }
</style>

<!-- ==================== القائمة السفلية ==================== -->
<nav class="bottom-nav" id="bottomNav">
    <?php foreach ($navItems as $item): ?>
        <?php if (isset($item['modal']) && $item['modal']): ?>
            <div class="nav-item add-btn-nav" onclick="openQuickAdd()">
                <div class="nav-icon-wrap"><span class="nav-icon">＋</span></div>
                <span class="nav-label">إضافة</span>
            </div>
        <?php elseif (isset($item['onclick'])): ?>
            <div class="nav-item <?php echo $item['active']?'active':''; ?>" onclick="<?php echo $item['onclick']; ?>">
                <span class="nav-icon"><?php echo $item['icon']; ?></span>
                <span class="nav-label"><?php echo $item['label']; ?></span>
            </div>
        <?php else: ?>
            <a href="<?php echo $item['url']; ?>" class="nav-item <?php echo $item['active']?'active':''; ?>">
                <span class="nav-icon"><?php echo $item['icon']; ?></span>
                <span class="nav-label"><?php echo $item['label']; ?></span>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>

<!-- ==================== Modal الإضافة السريعة ==================== -->
<div class="modal-overlay" id="quickAddModal" onclick="closeQuickAdd(event)">
    <div class="modal-sheet" onclick="event.stopPropagation()">
        <div class="modal-handle"></div>
        <div class="modal-title">➕ إضافة سريعة</div>
        
        <!-- تبويبات: معاملة | عميل -->
        <div class="quick-tabs">
            <button class="quick-tab active" onclick="switchQuickTab('transaction', this)">💰 معاملة</button>
            <button class="quick-tab" onclick="switchQuickTab('partner', this)">👤 عميل جديد</button>
        </div>
        
        <!-- ====== تبويب المعاملة ====== -->
        <div id="quickTabTransaction">
            <div class="fg">
                <label class="fl">العميل *</label>
                <input type="text" class="fi" id="quickPartnerSearch" placeholder="🔍 اكتب اسم العميل..." autocomplete="off" oninput="quickSearchPartners(this.value)">
                <div class="sr" id="quickSearchResults"></div>
                <div class="sp" id="quickSelectedPartner" style="display:none;">
                    <span class="spn" id="quickSpName"></span>
                    <button class="spr" onclick="quickClearPartner()">✕</button>
                </div>
                <input type="hidden" id="quickPartnerId" value="">
            </div>
            <div class="fg">
                <label class="fl">النوع</label>
                <div class="ft">
                    <button class="ftb debit active" onclick="quickSetType('debit',this)">📤 عليه</button>
                    <button class="ftb credit" onclick="quickSetType('credit',this)">📥 له</button>
                </div>
            </div>
            <div class="fg"><label class="fl">المبلغ *</label><input type="number" class="fi big" id="quickAmount" placeholder="0" step="1" min="1"></div>
            <div class="fg"><label class="fl">التفاصيل</label><input type="text" class="fi" id="quickDetails" placeholder="مثال: حق ماء..."></div>
            <div class="fg"><label class="fl">التاريخ</label><input type="date" class="fi" id="quickDate" value="<?php echo date('Y-m-d'); ?>"></div>
            <button class="fsub" onclick="quickSaveTransaction()">💾 حفظ المعاملة</button>
        </div>
        
        <!-- ====== تبويب العميل ====== -->
        <div id="quickTabPartner" style="display:none;">
            <div class="fg"><label class="fl">اسم العميل *</label><input type="text" class="fi" id="quickPartnerName" placeholder="أدخل اسم العميل"></div>
            <div class="fg"><label class="fl">رقم الهاتف</label><input type="tel" class="fi" id="quickPartnerPhone" placeholder="777123456" dir="ltr"></div>
            <div class="fg"><label class="fl">نوع العملة</label><select class="fi" id="quickPartnerType"><option value="local">💵 محلي</option><option value="dollar">💲 دولار</option></select></div>
            <button class="fsub" onclick="quickSavePartner()" style="background:#00d68f;">👤 حفظ العميل</button>
        </div>
        
        <button class="modal-cancel" onclick="closeQuickAdd()">✕ إلغاء</button>
    </div>
</div>

<!-- ==================== Modal كشف حساب ==================== -->
<div class="modal-overlay" id="statementSearchModal" onclick="closeStatementSearch(event)">
    <div class="modal-sheet" onclick="event.stopPropagation()">
        <div class="modal-handle"></div>
        <div class="modal-title">📄 كشف حساب عميل</div>
        <div class="fg">
            <input type="text" class="fi" id="statementClientSearch" placeholder="🔍 اكتب اسم العميل..." oninput="searchClientsForStatement()" autocomplete="off">
        </div>
        <div class="sr" id="statementSearchResults"></div>
        <button class="modal-cancel" onclick="closeStatementSearch()">✕ إلغاء</button>
    </div>
</div>

<script>
var quickTxType = 'debit';
var quickSearchTimeout;
var stSearchTimeout;

// ========== إصدار التطبيق ==========
var APP_VERSION = '1.0.<?php echo time(); ?>';
var APP_BUILD_TIME = '<?php echo date('Y-m-d H:i:s'); ?>';

// ========== كشف WebView ==========
function isWebView() {
    var ua = navigator.userAgent;
    return /wv|WebView|Android.*Version\/.*Chrome/.test(ua);
}

// ========== تبويبات الإضافة السريعة ==========
function switchQuickTab(tab, btn) {
    document.querySelectorAll('.quick-tab').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
    document.getElementById('quickTabTransaction').style.display = (tab === 'transaction') ? '' : 'none';
    document.getElementById('quickTabPartner').style.display = (tab === 'partner') ? '' : 'none';
}

// ========== فتح/إغلاق المودال ==========
function openQuickAdd() {
    document.getElementById('quickAddModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
    document.body.style.position = 'fixed';
    document.body.style.width = '100%';
    resetQuickForm();
    setTimeout(function(){ document.getElementById('quickPartnerSearch').focus(); }, 350);
}

function closeQuickAdd(e) {
    if (e && e.target !== document.getElementById('quickAddModal')) return;
    document.getElementById('quickAddModal').style.display = 'none';
    document.body.style.overflow = '';
    document.body.style.position = '';
    document.body.style.width = '';
}

function resetQuickForm() {
    document.getElementById('quickPartnerId').value = '';
    document.getElementById('quickPartnerSearch').value = '';
    document.getElementById('quickAmount').value = '';
    document.getElementById('quickDetails').value = '';
    document.getElementById('quickSelectedPartner').style.display = 'none';
    document.getElementById('quickSearchResults').innerHTML = '';
    document.getElementById('quickPartnerName').value = '';
    document.getElementById('quickPartnerPhone').value = '';
    document.querySelectorAll('.ftb').forEach(function(b){ b.classList.remove('active','debit','credit'); });
    document.querySelector('.ftb.debit').classList.add('active','debit');
    quickTxType = 'debit';
    switchQuickTab('transaction', document.querySelector('.quick-tab'));
}

// ========== البحث عن العملاء ==========
function quickSearchPartners(query) {
    clearTimeout(quickSearchTimeout);
    var resultsDiv = document.getElementById('quickSearchResults');
    if (query.length < 1) { resultsDiv.innerHTML = ''; return; }
    
    quickSearchTimeout = setTimeout(function(){
        fetch('api/search.php?q=' + encodeURIComponent(query) + '&type=partners&limit=6')
            .then(function(r){ return r.json(); })
            .then(function(data){
                if (data.success && data.results && data.results.length > 0) {
                    var html = '';
                    var colors = [['#fff3e0','#e65100'],['#e8f5e9','#1b5e20'],['#e3f2fd','#0d47a1'],['#f3e5f5','#4a148c']];
                    data.results.forEach(function(c, i){
                        var clr = colors[i % 4];
                        html += '<div class="si" onclick="quickSelectPartner(' + c.id + ',\'' + escHtml(c.name) + '\')"><div class="sa" style="background:' + clr[0] + ';color:' + clr[1] + ';">' + (c.initial||'?') + '</div><div style="flex:1;"><div class="sn">' + escHtml(c.name) + '</div><div class="sm">' + (c.phone?'📱 '+escHtml(c.phone)+' · ':'') + (c.details||'') + '</div></div><div class="sb ' + (c.balance<0?'neg':'pos') + '">' + Math.abs(c.balance||0).toLocaleString('ar-SA') + '</div></div>';
                    });
                    resultsDiv.innerHTML = html;
                } else {
                    resultsDiv.innerHTML = '<div class="se">😕 لا يوجد عملاء</div>';
                }
            });
    }, 300);
}

function quickSelectPartner(id, name) {
    document.getElementById('quickPartnerId').value = id;
    document.getElementById('quickPartnerSearch').value = name;
    document.getElementById('quickSpName').textContent = '👤 ' + name;
    document.getElementById('quickSelectedPartner').style.display = 'flex';
    document.getElementById('quickSearchResults').innerHTML = '';
    setTimeout(function(){ document.getElementById('quickAmount').focus(); }, 200);
}

function quickClearPartner() {
    document.getElementById('quickPartnerId').value = '';
    document.getElementById('quickPartnerSearch').value = '';
    document.getElementById('quickSelectedPartner').style.display = 'none';
    document.getElementById('quickPartnerSearch').focus();
}

function quickSetType(type, btn) {
    quickTxType = type;
    document.querySelectorAll('#quickTabTransaction .ftb').forEach(function(b){ b.classList.remove('active','debit','credit'); });
    btn.classList.add('active', type);
}

// ========== حفظ معاملة سريعة والتوجيه لصفحة العميل ==========
function quickSaveTransaction() {
    var partnerId = document.getElementById('quickPartnerId').value;
    var amount = document.getElementById('quickAmount').value;
    var partnerName = document.getElementById('quickSpName').textContent.replace('👤 ', '');
    
    if (!partnerId) { alert('الرجاء اختيار عميل'); return; }
    if (!amount || parseFloat(amount) <= 0) { alert('الرجاء إدخال المبلغ'); return; }
    
    var fd = new FormData();
    fd.append('action', 'add');
    fd.append('partner_id', partnerId);
    fd.append('amount', amount);
    fd.append('details', document.getElementById('quickDetails').value);
    fd.append('date', document.getElementById('quickDate').value);
    fd.append('type', quickTxType);
    fd.append('currency', 'local');
    fd.append('quantity', 0);
    
    var submitBtn = document.querySelector('#quickTabTransaction .fsub');
    submitBtn.textContent = '⏳ جاري الحفظ...';
    submitBtn.disabled = true;
    
    fetch('add_transaction.php', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                closeQuickAdd();
                alert('✅ ' + d.message + '\n\nسيتم تحويلك إلى صفحة ' + partnerName);
                window.location.href = 'client.php?id=' + partnerId;
            } else {
                alert('❌ ' + d.message);
                submitBtn.textContent = '💾 حفظ المعاملة';
                submitBtn.disabled = false;
            }
        })
        .catch(function(){
            alert('❌ خطأ في الاتصال');
            submitBtn.textContent = '💾 حفظ المعاملة';
            submitBtn.disabled = false;
        });
}

// ========== حفظ عميل سريع ==========
function quickSavePartner() {
    var name = document.getElementById('quickPartnerName').value.trim();
    var phone = document.getElementById('quickPartnerPhone').value.trim();
    var type = document.getElementById('quickPartnerType').value;
    
    if (!name) { alert('الرجاء إدخال اسم العميل'); return; }
    
    var fd = new FormData();
    fd.append('action', 'add');
    fd.append('name', name);
    fd.append('phone', phone);
    fd.append('type', type);
    
    var submitBtn = document.querySelector('#quickTabPartner .fsub');
    submitBtn.textContent = '⏳ جاري الحفظ...';
    submitBtn.disabled = true;
    
    fetch('add_partner.php', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                closeQuickAdd();
                alert('✅ ' + d.message + '\n\nسيتم تحويلك إلى صفحة ' + name);
                window.location.href = 'client.php?id=' + d.id;
            } else {
                alert('❌ ' + d.message);
                submitBtn.textContent = '👤 حفظ العميل';
                submitBtn.disabled = false;
            }
        })
        .catch(function(){
            alert('❌ خطأ في الاتصال');
            submitBtn.textContent = '👤 حفظ العميل';
            submitBtn.disabled = false;
        });
}

// ========== كشف حساب ==========
function openStatementSearch() {
    document.getElementById('statementSearchModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
    document.body.style.position = 'fixed';
    document.body.style.width = '100%';
    document.getElementById('statementClientSearch').value = '';
    document.getElementById('statementSearchResults').innerHTML = '';
    setTimeout(function(){ document.getElementById('statementClientSearch').focus(); }, 400);
}

function closeStatementSearch(e) {
    if (e && e.target !== document.getElementById('statementSearchModal')) return;
    document.getElementById('statementSearchModal').style.display = 'none';
    document.body.style.overflow = '';
    document.body.style.position = '';
    document.body.style.width = '';
}

function searchClientsForStatement() {
    clearTimeout(stSearchTimeout);
    var query = document.getElementById('statementClientSearch').value.trim();
    var resultsDiv = document.getElementById('statementSearchResults');
    
    if (query.length < 1) { resultsDiv.innerHTML = '<div class="se">🔍 اكتب اسم العميل</div>'; return; }
    resultsDiv.innerHTML = '<div style="text-align:center;padding:15px;color:<?php echo $emptyColor; ?>;">⏳ جاري البحث...</div>';
    
    stSearchTimeout = setTimeout(function(){
        fetch('api/search.php?q=' + encodeURIComponent(query) + '&type=partners&limit=10')
            .then(function(r){ return r.json(); })
            .then(function(data){
                if (data.success && data.results && data.results.length > 0) {
                    var html = '';
                    var colors = [['#fff3e0','#e65100'],['#e8f5e9','#1b5e20'],['#e3f2fd','#0d47a1'],['#f3e5f5','#4a148c'],['#fffde7','#f57f17'],['#fce4ec','#b71c1c']];
                    data.results.forEach(function(c, i){
                        var clr = colors[i % 6];
                        var balClass = c.balance < 0 ? 'neg' : 'pos';
                        html += '<a href="statement.php?id=' + c.id + '" class="si"><div class="sa" style="background:' + clr[0] + ';color:' + clr[1] + ';">' + (c.initial||'?') + '</div><div style="flex:1;"><div class="sn">' + escHtml(c.name) + '</div><div class="sm">' + (c.phone?'📱 '+escHtml(c.phone)+' · ':'') + (c.details||'') + '</div></div><div class="sb ' + balClass + '">' + Math.abs(c.balance||0).toLocaleString('ar-SA') + '</div></a>';
                    });
                    resultsDiv.innerHTML = html;
                } else {
                    resultsDiv.innerHTML = '<div class="se">😕 لا توجد نتائج</div>';
                }
            })
            .catch(function(){ resultsDiv.innerHTML = '<div class="se">⚠️ خطأ في البحث</div>'; });
    }, 400);
}

function escHtml(text) { var d = document.createElement('div'); d.textContent = text; return d.innerHTML; }

// ========== إخفاء القائمة عند التمرير ==========
var lastScrollY = window.scrollY, navVisible = true, ticking = false;
var bottomNav = document.getElementById('bottomNav');
if (bottomNav) {
    window.addEventListener('scroll', function(){
        if (!ticking) {
            requestAnimationFrame(function(){
                var s = window.scrollY, diff = s - lastScrollY;
                if (diff > 3 && s > 120 && navVisible) { bottomNav.style.transform = 'translateY(120%)'; bottomNav.style.opacity = '0'; navVisible = false; }
                else if (diff < -3 && !navVisible) { bottomNav.style.transform = 'translateY(0)'; bottomNav.style.opacity = '1'; navVisible = true; }
                lastScrollY = s; ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });
}

// ========== التواصل مع Service Worker ==========
if ('serviceWorker' in navigator) {
    // الاستماع للرسائل من Service Worker
    navigator.serviceWorker.addEventListener('message', function(event) {
        if (event.data && event.data.type === 'RELOAD_PAGE') {
            console.log('🔄 تحديث الصفحة من Service Worker');
            window.location.reload(true);
        }
        
        if (event.data && event.data.type === 'SW_ACTIVATED') {
            console.log('✅ Service Worker مفعل - الإصدار:', event.data.version);
        }
        
        if (event.data && event.data.type === 'SYNC_COMPLETE') {
            console.log('✅ تمت المزامنة في الخلفية');
        }
    });
    
    // دالة لمسح الكاش والتحديث
    window.clearCacheAndReload = function() {
        if (navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({
                type: 'CLEAR_CACHE'
            });
        }
        if ('caches' in window) {
            caches.keys().then(function(names) {
                return Promise.all(names.map(function(name) {
                    return caches.delete(name);
                }));
            }).then(function() {
                console.log('🗑️ تم مسح جميع الكاش');
                window.location.reload(true);
            });
        } else {
            window.location.reload(true);
        }
    };
    
    // دالة لطلب التحديث الفوري
    window.updateAppNow = function() {
        if (navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({
                type: 'UPDATE_NOW'
            });
        }
    };
    
    // دالة لتخطي الانتظار
    window.skipWaiting = function() {
        if (navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({
                type: 'SKIP_WAITING'
            });
        }
    };
}

// ========== فحص التحديثات تلقائياً ==========
function checkForUpdates() {
    if (!navigator.onLine) return;
    
    fetch('manifest.json?t=' + Date.now(), { cache: 'no-store' })
        .then(function(r) { return r.json(); })
        .then(function(manifest) {
            var currentVersion = localStorage.getItem('app_version');
            var newVersion = manifest.version || '1.0';
            
            if (currentVersion && currentVersion !== newVersion) {
                showUpdatePrompt(newVersion);
            }
            
            localStorage.setItem('app_version', newVersion);
        })
        .catch(function() {});
}

function showUpdatePrompt(version) {
    // إزالة أي شريط تحديث موجود
    var existingBanner = document.querySelector('.update-banner');
    if (existingBanner) existingBanner.remove();
    
    var banner = document.createElement('div');
    banner.className = 'update-banner';
    banner.innerHTML = '🔄 يتوفر تحديث جديد (v' + version + ') <button onclick="clearCacheAndReload()">تحديث الآن</button>';
    document.body.prepend(banner);
    
    // إخفاء الشريط تلقائياً بعد 10 ثواني
    setTimeout(function() {
        banner.style.animation = 'slideUp 0.3s ease forwards';
        setTimeout(function() { 
            if (banner.parentNode) banner.remove(); 
        }, 300);
    }, 10000);
}

function forceReload() {
    clearCacheAndReload();
}

// ========== أحداث لوحة المفاتيح ==========
document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') { closeQuickAdd(); closeStatementSearch(); }
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') { e.preventDefault(); openQuickAdd(); }
    // Ctrl+Shift+R لمسح الكاش والتحديث
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'R') { 
        e.preventDefault(); 
        clearCacheAndReload(); 
    }
});

// ========== عند تحميل الصفحة ==========
document.addEventListener('DOMContentLoaded', function(){ 
    document.body.classList.add('has-bottom-nav'); 
    
    // فحص التحديثات
    checkForUpdates();
    
    // فحص دوري كل 5 دقائق
    setInterval(checkForUpdates, 300000);
});

// ========== مراقبة الاتصال ==========
window.addEventListener('online', function() {
    console.log('🟢 تم استعادة الاتصال');
    checkForUpdates();
});

window.addEventListener('offline', function() {
    console.log('🔴 تم فقدان الاتصال');
});
</script>