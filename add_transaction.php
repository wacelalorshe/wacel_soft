<?php
define('APP_RUNNING', true);
require_once 'config.php';
// في header.php أو في كل صفحة
header('Service-Worker-Allowed: /');
require_once 'includes/functions.php';

// ========== معالجة العمليات (AJAX) ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];
    $userId = $_SESSION['user_id'] ?? 0;
    $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
    
    try {
        // ========== إضافة معاملة ==========
        if ($action === 'add') {
            $partnerId = (int)$_POST['partner_id'];
            $amount = abs((float)$_POST['amount']);
            $details = trim($_POST['details'] ?? '');
            $date = $_POST['date'] ?? date('Y-m-d');
            $type = $_POST['type'] ?? 'debit';
            $currency = $_POST['currency'] ?? 'local';
            $quantity = (int)($_POST['quantity'] ?? 0);
            
            if (empty($partnerId)) {
                echo json_encode(['success' => false, 'message' => 'الرجاء اختيار عميل']);
                exit;
            }
            
            if ($amount <= 0) {
                echo json_encode(['success' => false, 'message' => 'الرجاء إدخال المبلغ']);
                exit;
            }
            
            // التحقق من وجود العميل والصلاحية
            if ($isAdmin) {
                $check = $pdo->prepare("SELECT id, name FROM partners WHERE id = ? AND status = 'active'");
                $check->execute([$partnerId]);
            } else {
                $check = $pdo->prepare("SELECT id, name FROM partners WHERE id = ? AND user_id = ? AND status = 'active'");
                $check->execute([$partnerId, $userId]);
            }
            $partner = $check->fetch(PDO::FETCH_ASSOC);
            
            if (!$partner) {
                echo json_encode(['success' => false, 'message' => 'العميل غير موجود أو لا يمكنك الوصول إليه']);
                exit;
            }
            
            $stmt = $pdo->prepare("INSERT INTO transactions (partner_id, date, details, amount, currency_type, transaction_type, quantity, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$partnerId, $date, $details, $amount, $currency, $type, $quantity, $userId]);
            
            echo json_encode([
                'success' => true,
                'message' => "تمت إضافة المعاملة بنجاح ✓",
                'partner_name' => $partner['name'],
                'amount' => $amount,
                'type' => $type
            ]);
            exit;
        }
        
        // ========== إضافة معاملة مع عميل جديد ==========
        if ($action === 'add_with_new_partner') {
            $partnerName = trim($_POST['partner_name'] ?? '');
            $partnerPhone = trim($_POST['partner_phone'] ?? '');
            $amount = abs((float)$_POST['amount']);
            $details = trim($_POST['details'] ?? '');
            $date = $_POST['date'] ?? date('Y-m-d');
            $type = $_POST['type'] ?? 'debit';
            $currency = $_POST['currency'] ?? 'local';
            $quantity = (int)($_POST['quantity'] ?? 0);
            
            if (empty($partnerName)) {
                echo json_encode(['success' => false, 'message' => 'الرجاء إدخال اسم العميل']);
                exit;
            }
            
            if ($amount <= 0) {
                echo json_encode(['success' => false, 'message' => 'الرجاء إدخال المبلغ']);
                exit;
            }
            
            // إضافة العميل أولاً
            $stmt = $pdo->prepare("INSERT INTO partners (name, phone, type, user_id, status) VALUES (?, ?, 'local', ?, 'active')");
            $stmt->execute([$partnerName, $partnerPhone, $userId]);
            $newPartnerId = $pdo->lastInsertId();
            
            // إضافة المعاملة
            $stmt = $pdo->prepare("INSERT INTO transactions (partner_id, date, details, amount, currency_type, transaction_type, quantity, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$newPartnerId, $date, $details, $amount, $currency, $type, $quantity, $userId]);
            
            echo json_encode([
                'success' => true,
                'message' => "تم إضافة العميل والمعاملة بنجاح ✓",
                'partner_name' => $partnerName,
                'partner_id' => $newPartnerId,
                'amount' => $amount,
                'type' => $type
            ]);
            exit;
        }
        
        // ========== البحث عن العملاء ==========
        if ($action === 'search_partners') {
            $query = trim($_POST['query'] ?? '');
            
            if (mb_strlen($query) < 1) {
                if ($isAdmin) {
                    $stmt = $pdo->query("SELECT p.*, (SELECT COUNT(*) FROM transactions t WHERE t.partner_id = p.id) as tx_count FROM partners p WHERE p.status = 'active' ORDER BY p.name LIMIT 10");
                } else {
                    $stmt = $pdo->prepare("SELECT p.*, (SELECT COUNT(*) FROM transactions t WHERE t.partner_id = p.id) as tx_count FROM partners p WHERE p.status = 'active' AND p.user_id = ? ORDER BY p.name LIMIT 10");
                    $stmt->execute([$userId]);
                }
            } else {
                if ($isAdmin) {
                    $stmt = $pdo->prepare("SELECT p.*, (SELECT COUNT(*) FROM transactions t WHERE t.partner_id = p.id) as tx_count FROM partners p WHERE p.status = 'active' AND (p.name LIKE ? OR p.phone LIKE ?) ORDER BY CASE WHEN p.name LIKE ? THEN 0 ELSE 1 END, p.name LIMIT 10");
                    $stmt->execute(['%' . $query . '%', '%' . $query . '%', $query . '%']);
                } else {
                    $stmt = $pdo->prepare("SELECT p.*, (SELECT COUNT(*) FROM transactions t WHERE t.partner_id = p.id) as tx_count FROM partners p WHERE p.status = 'active' AND p.user_id = ? AND (p.name LIKE ? OR p.phone LIKE ?) ORDER BY CASE WHEN p.name LIKE ? THEN 0 ELSE 1 END, p.name LIMIT 10");
                    $stmt->execute([$userId, '%' . $query . '%', '%' . $query . '%', $query . '%']);
                }
            }
            
            $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($partners)) $partners = [];
            
            echo json_encode(['success' => true, 'partners' => $partners]);
            exit;
        }
        
        // ========== جلب آخر المعاملات ==========
        if ($action === 'recent_transactions') {
            if ($isAdmin) {
                $stmt = $pdo->query("SELECT t.*, p.name as partner_name FROM transactions t JOIN partners p ON t.partner_id = p.id ORDER BY t.id DESC LIMIT 20");
            } else {
                $stmt = $pdo->prepare("SELECT t.*, p.name as partner_name FROM transactions t JOIN partners p ON t.partner_id = p.id WHERE t.user_id = ? ORDER BY t.id DESC LIMIT 20");
                $stmt->execute([$userId]);
            }
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($transactions)) $transactions = [];
            
            echo json_encode(['success' => true, 'transactions' => $transactions]);
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'إجراء غير معروف']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
    }
    exit;
}

// ========== إحصائيات سريعة ==========
$todayCount = 0;
$todayTotal = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*), COALESCE(SUM(amount), 0) FROM transactions WHERE date = CURDATE()");
    $row = $stmt->fetch(PDO::FETCH_NUM);
    $todayCount = $row[0] ?? 0;
    $todayTotal = $row[1] ?? 0;
} catch (Exception $e) {
    $todayCount = 0;
    $todayTotal = 0;
}
?>

<?php require_once 'includes/header.php'; ?>

<style>
    .main-content { padding: 16px; padding-bottom: 100px; }
    
    .page-title { font-size: 20px; font-weight: 700; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; color: var(--text); }
    .page-subtitle { font-size: 12px; color: var(--text-secondary); margin-bottom: 16px; }
    
    .transaction-form { background: var(--surface); border-radius: var(--radius-lg); padding: 18px; border: 1px solid var(--border); margin-bottom: 16px; }
    .form-group { margin-bottom: 14px; }
    .form-label { display: flex; justify-content: space-between; align-items: center; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; }
    
    .partner-search-wrapper { position: relative; }
    .partner-search-input { width: 100%; padding: 13px 42px 13px 16px; background: var(--surface-light); border: 1.5px solid var(--border); border-radius: var(--radius-sm); color: var(--text); font-size: 14px; font-family: inherit; }
    .partner-search-input:focus { outline: none; border-color: var(--primary); }
    .partner-search-icon { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); font-size: 16px; }
    .search-results { position: absolute; top: 100%; left: 0; right: 0; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm); margin-top: 4px; max-height: 250px; overflow-y: auto; z-index: 50; display: none; }
    .search-results.show { display: block; }
    .search-result-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; cursor: pointer; transition: var(--transition); border-bottom: 1px solid var(--border); color: var(--text); }
    .search-result-item:hover { background: var(--surface-light); }
    .search-result-item:active { background: var(--primary); color: #fff; }
    .result-avatar { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; flex-shrink: 0; }
    .result-info { flex: 1; }
    .result-name { font-size: 13px; font-weight: 600; }
    .result-meta { font-size: 10px; color: var(--text-secondary); }
    .result-add-new { padding: 12px 14px; text-align: center; color: var(--primary); font-weight: 700; cursor: pointer; border-top: 1px solid var(--border); }
    .selected-partner { display: flex; align-items: center; gap: 10px; padding: 12px; background: var(--success-bg); border: 1px solid var(--success); border-radius: var(--radius-sm); margin-top: 8px; }
    .sp-avatar { width: 36px; height: 36px; border-radius: 10px; background: var(--success); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; }
    .sp-info { flex: 1; }
    .sp-name { font-weight: 600; font-size: 13px; }
    .sp-label { font-size: 10px; color: var(--success); }
    .sp-remove { width: 28px; height: 28px; border-radius: 50%; background: var(--danger); border: none; color: #fff; cursor: pointer; font-size: 12px; }
    
    .type-toggle { display: flex; gap: 8px; background: var(--surface-light); border-radius: var(--radius-sm); padding: 4px; }
    .type-btn { flex: 1; padding: 11px; border-radius: 10px; border: none; background: transparent; color: var(--text-secondary); font-weight: 700; font-size: 14px; cursor: pointer; font-family: inherit; }
    .type-btn.debit.active { background: var(--danger); color: #fff; }
    .type-btn.credit.active { background: var(--success); color: #fff; }
    
    .input-row { display: flex; gap: 8px; }
    .input-row .form-group { flex: 1; }
    .form-input { width: 100%; padding: 12px 14px; background: var(--surface-light); border: 1.5px solid var(--border); border-radius: var(--radius-sm); color: var(--text); font-size: 15px; font-family: inherit; }
    .form-input:focus { outline: none; border-color: var(--primary); }
    .amount-input { font-size: 22px; font-weight: 700; text-align: center; }
    
    .currency-options { display: flex; gap: 4px; background: var(--surface-light); border-radius: var(--radius-sm); padding: 3px; }
    .currency-opt { flex: 1; padding: 8px; border-radius: 8px; border: none; background: transparent; color: var(--text-secondary); font-weight: 600; font-size: 11px; cursor: pointer; font-family: inherit; }
    .currency-opt.active { background: var(--primary); color: #fff; }
    
    .submit-btn { width: 100%; padding: 15px; background: var(--primary); color: #fff; border: none; border-radius: var(--radius-sm); font-weight: 700; font-size: 15px; cursor: pointer; font-family: inherit; margin-top: 4px; }
    .submit-btn:active { transform: scale(0.97); }
    
    .recent-section { margin-top: 20px; }
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .section-title { font-size: 13px; font-weight: 700; color: var(--text-secondary); }
    .recent-list { display: flex; flex-direction: column; gap: 6px; }
    .recent-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: var(--surface); border-radius: var(--radius-sm); border: 1px solid var(--border); }
    .recent-icon { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
    .recent-icon.debit { background: var(--danger-bg); color: var(--danger); }
    .recent-icon.credit { background: var(--success-bg); color: var(--success); }
    .recent-info { flex: 1; }
    .recent-partner { font-size: 13px; font-weight: 600; }
    .recent-details { font-size: 11px; color: var(--text-secondary); }
    .recent-amount { font-size: 15px; font-weight: 700; }
    .recent-amount.debit { color: var(--danger); } .recent-amount.credit { color: var(--success); }
    
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 200; display: flex; align-items: flex-end; justify-content: center; animation: fadeIn 0.2s ease; }
    .modal-sheet { background: var(--surface); width: 100%; max-width: 500px; border-radius: var(--radius-xl) var(--radius-xl) 0 0; padding: 8px 20px 24px; animation: slideUp 0.35s ease; }
    .modal-handle { width: 36px; height: 4px; background: var(--border); border-radius: 2px; margin: 0 auto 16px; }
    .modal-title { font-size: 18px; font-weight: 700; text-align: center; margin-bottom: 16px; }
    
    .toast { position: fixed; bottom: 100px; left: 50%; transform: translateX(-50%); padding: 12px 22px; border-radius: 25px; color: #fff; font-weight: 600; font-size: 13px; z-index: 300; animation: toastIn 0.3s ease, toastOut 0.3s ease 2.5s forwards; white-space: nowrap; box-shadow: 0 8px 24px rgba(0,0,0,0.3); }
    .toast.success { background: var(--success); } .toast.error { background: var(--danger); } .toast.warning { background: #ffa502; }
    @keyframes fadeIn { from{opacity:0} to{opacity:1} }
    @keyframes slideUp { from{transform:translateY(100%)} to{transform:translateY(0)} }
    @keyframes toastIn { from{opacity:0;transform:translateX(-50%) translateY(10px)} to{opacity:1;transform:translateX(-50%) translateY(0)} }
    @keyframes toastOut { to{opacity:0;transform:translateX(-50%) translateY(-8px)} }
</style>

<div class="main-content">
    <div class="page-title">💰 إضافة معاملة جديدة</div>
    <div class="page-subtitle">سجل معاملة مالية جديدة لأحد العملاء</div>
    
    <div class="transaction-form">
        <input type="hidden" id="selectedPartnerId" value="">
        
        <!-- البحث عن العميل -->
        <div class="form-group">
            <label class="form-label">العميل *</label>
            <div class="partner-search-wrapper" id="searchWrapper">
                <span class="partner-search-icon">🔍</span>
                <input type="text" class="partner-search-input" id="partnerSearch" placeholder="اكتب اسم العميل للبحث..." autocomplete="off" oninput="searchPartners(this.value)" onfocus="searchPartners(this.value)">
                <div class="search-results" id="searchResults"></div>
            </div>
            <div class="selected-partner" id="selectedPartner" style="display:none;">
                <div class="sp-avatar" id="spAvatar">👤</div>
                <div class="sp-info">
                    <div class="sp-name" id="spName"></div>
                    <div class="sp-label">✓ تم الاختيار</div>
                </div>
                <button class="sp-remove" onclick="clearPartner()">✕</button>
            </div>
        </div>
        
        <!-- نوع المعاملة -->
        <div class="form-group">
            <label class="form-label">نوع المعاملة</label>
            <div class="type-toggle">
                <button class="type-btn debit active" onclick="setType('debit', this)">📤 عليه (دين)</button>
                <button class="type-btn credit" onclick="setType('credit', this)">📥 له (دفعة)</button>
            </div>
            <input type="hidden" id="txType" value="debit">
        </div>
        
        <!-- المبلغ -->
        <div class="form-group">
            <label class="form-label">المبلغ *</label>
            <input type="number" class="form-input amount-input" id="amount" placeholder="0" step="1" min="1" onfocus="this.select()">
        </div>
        
        <!-- التاريخ والكمية -->
        <div class="input-row">
            <div class="form-group">
                <label class="form-label">التاريخ</label>
                <input type="date" class="form-input" id="date" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">الكمية</label>
                <input type="number" class="form-input" id="quantity" placeholder="0" min="0">
            </div>
        </div>
        
        <!-- التفاصيل -->
        <div class="form-group">
            <label class="form-label">التفاصيل</label>
            <input type="text" class="form-input" id="details" placeholder="مثال: حق ماء، بضاعة...">
        </div>
        
        <!-- العملة -->
        <div class="form-group">
            <label class="form-label">العملة</label>
            <div class="currency-options">
                <button class="currency-opt active" onclick="setCurrency('local', this)">💵 محلي</button>
                <button class="currency-opt" onclick="setCurrency('saudi', this)">🇸🇦 سعودي</button>
                <button class="currency-opt" onclick="setCurrency('dollar', this)">💲 دولار</button>
            </div>
            <input type="hidden" id="currency" value="local">
        </div>
        
        <button class="submit-btn" onclick="saveTransaction()">💾 حفظ المعاملة</button>
    </div>
    
    <!-- آخر المعاملات -->
    <div class="recent-section">
        <div class="section-header">
            <div class="section-title">🕐 آخر المعاملات</div>
            <span style="font-size:11px;color:var(--text-muted);">اليوم: <?php echo $todayCount; ?> معاملة</span>
        </div>
        <div class="recent-list" id="recentList">
            <div style="text-align:center;padding:15px;color:var(--text-muted);">جاري التحميل...</div>
        </div>
    </div>
</div>

<!-- Modal عميل جديد -->
<div class="modal-overlay" id="newPartnerModal" style="display:none;" onclick="closeNewPartnerModal(event)">
    <div class="modal-sheet" onclick="event.stopPropagation()">
        <div class="modal-handle"></div>
        <div class="modal-title">👤 إضافة عميل جديد سريع</div>
        <div class="form-group"><label class="form-label">اسم العميل *</label><input type="text" class="form-input" id="newPartnerName" placeholder="أدخل اسم العميل"></div>
        <div class="form-group"><label class="form-label">رقم الهاتف</label><input type="tel" class="form-input" id="newPartnerPhone" placeholder="اختياري" dir="ltr"></div>
        <div style="display:flex;gap:8px;margin-top:8px;">
            <button class="btn" style="flex:1;padding:12px;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer;border:none;font-family:inherit;background:var(--surface-light);color:var(--text);" onclick="closeNewPartnerModal()">إلغاء</button>
            <button class="btn" style="flex:1;padding:12px;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer;border:none;font-family:inherit;background:var(--primary);color:#fff;" onclick="saveWithNewPartner()">✅ متابعة</button>
        </div>
    </div>
</div>

<script>
var txType = 'debit';
var txCurrency = 'local';
var searchTimeout;
var searchController = null;

function setType(type, btn) {
    txType = type;
    document.querySelectorAll('.type-btn').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    document.getElementById('txType').value = type;
}

function setCurrency(currency, btn) {
    txCurrency = currency;
    document.querySelectorAll('.currency-opt').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    document.getElementById('currency').value = currency;
}

function searchPartners(query) {
    var resultsDiv = document.getElementById('searchResults');
    if (searchController) searchController.abort();
    searchController = new AbortController();
    clearTimeout(searchTimeout);
    
    searchTimeout = setTimeout(function() {
        var fd = new FormData();
        fd.append('action', 'search_partners');
        fd.append('query', query);
        
        fetch(window.location.href, { method: 'POST', body: fd, signal: searchController.signal })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var partners = data.partners || [];
                    if (partners.length === 0) {
                        resultsDiv.innerHTML = '<div style="padding:16px;text-align:center;color:var(--text-muted);">😕 لا يوجد عملاء</div><div class="result-add-new" onclick="openNewPartnerModal()">➕ إضافة عميل جديد</div>';
                    } else {
                        var html = '';
                        var colors = [['#fff3e0','#e65100'],['#e8f5e9','#1b5e20'],['#e3f2fd','#0d47a1'],['#f3e5f5','#4a148c']];
                        partners.forEach(function(p, i) {
                            var c = colors[i % 4];
                            html += '<div class="search-result-item" onclick="selectPartner(' + p.id + ', \'' + escapeHtml(p.name) + '\')"><div class="result-avatar" style="background:' + c[0] + ';color:' + c[1] + ';">' + (p.name||'?')[0] + '</div><div class="result-info"><div class="result-name">' + escapeHtml(p.name) + '</div><div class="result-meta">' + (p.phone ? '📱 ' + escapeHtml(p.phone) + ' · ' : '') + (p.tx_count || 0) + ' معاملة</div></div></div>';
                        });
                        html += '<div class="result-add-new" onclick="openNewPartnerModal()">➕ إضافة عميل جديد</div>';
                        resultsDiv.innerHTML = html;
                    }
                    resultsDiv.classList.add('show');
                }
            })
            .catch(function(e) { if (e.name !== 'AbortError') console.log(e); });
    }, 300);
}

function selectPartner(id, name) {
    document.getElementById('selectedPartnerId').value = id;
    document.getElementById('spName').textContent = name;
    document.getElementById('spAvatar').textContent = name[0] || '👤';
    document.getElementById('selectedPartner').style.display = 'flex';
    document.getElementById('partnerSearch').value = name;
    document.getElementById('searchResults').classList.remove('show');
    setTimeout(function() { document.getElementById('amount').focus(); }, 200);
}

function clearPartner() {
    document.getElementById('selectedPartnerId').value = '';
    document.getElementById('selectedPartner').style.display = 'none';
    document.getElementById('partnerSearch').value = '';
    document.getElementById('amount').value = '';
    document.getElementById('details').value = '';
    document.getElementById('searchResults').classList.remove('show');
    setTimeout(function() { document.getElementById('partnerSearch').focus(); }, 200);
}

function openNewPartnerModal() {
    document.getElementById('searchResults').classList.remove('show');
    document.getElementById('newPartnerModal').style.display = 'flex';
    var searchText = document.getElementById('partnerSearch').value.trim();
    if (searchText) document.getElementById('newPartnerName').value = searchText;
    setTimeout(function() { document.getElementById('newPartnerName').focus(); }, 350);
}

function closeNewPartnerModal(e) {
    if (e && e.target !== document.getElementById('newPartnerModal')) return;
    document.getElementById('newPartnerModal').style.display = 'none';
}

function saveWithNewPartner() {
    var name = document.getElementById('newPartnerName').value.trim();
    var amount = document.getElementById('amount').value;
    if (!name) { showToast('الرجاء إدخال اسم العميل', 'error'); return; }
    if (!amount || parseFloat(amount) <= 0) { showToast('الرجاء إدخال المبلغ', 'error'); return; }
    
    var fd = new FormData();
    fd.append('action', 'add_with_new_partner');
    fd.append('partner_name', name);
    fd.append('partner_phone', document.getElementById('newPartnerPhone').value);
    fd.append('amount', amount);
    fd.append('details', document.getElementById('details').value);
    fd.append('date', document.getElementById('date').value);
    fd.append('type', txType);
    fd.append('currency', txCurrency);
    fd.append('quantity', document.getElementById('quantity').value || 0);
    
    fetch(window.location.href, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                showToast(d.message, 'success');
                closeNewPartnerModal();
                selectPartner(d.partner_id, d.partner_name);
                document.getElementById('amount').value = '';
                document.getElementById('details').value = '';
                loadRecentTransactions();
            } else { showToast(d.message, 'error'); }
        });
}

function saveTransaction() {
    var partnerId = document.getElementById('selectedPartnerId').value;
    var amount = document.getElementById('amount').value;
    if (!partnerId) { showToast('الرجاء اختيار عميل', 'error'); return; }
    if (!amount || parseFloat(amount) <= 0) { showToast('الرجاء إدخال المبلغ', 'error'); return; }
    
    var fd = new FormData();
    fd.append('action', 'add');
    fd.append('partner_id', partnerId);
    fd.append('amount', amount);
    fd.append('details', document.getElementById('details').value);
    fd.append('date', document.getElementById('date').value);
    fd.append('type', txType);
    fd.append('currency', txCurrency);
    fd.append('quantity', document.getElementById('quantity').value || 0);
    
    fetch(window.location.href, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                showToast(d.message, 'success');
                document.getElementById('amount').value = '';
                document.getElementById('details').value = '';
                loadRecentTransactions();
                setTimeout(function() { document.getElementById('amount').focus(); }, 300);
            } else { showToast(d.message, 'error'); }
        })
        .catch(function() { showToast('خطأ في الاتصال', 'error'); });
}

function loadRecentTransactions() {
    var list = document.getElementById('recentList');
    var fd = new FormData(); fd.append('action', 'recent_transactions');
    fetch(window.location.href, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.transactions && data.transactions.length > 0) {
                var html = '';
                data.transactions.forEach(function(t) {
                    var isDebit = t.transaction_type === 'debit';
                    html += '<div class="recent-item"><div class="recent-icon ' + t.transaction_type + '">' + (isDebit ? '📤' : '📥') + '</div><div class="recent-info"><div class="recent-partner">' + escapeHtml(t.partner_name) + '</div><div class="recent-details">' + (t.details || 'بدون تفاصيل') + ' · ' + t.date + '</div></div><div class="recent-amount ' + t.transaction_type + '">' + (isDebit ? '-' : '+') + Number(t.amount).toLocaleString('ar-SA') + '</div></div>';
                });
                list.innerHTML = html;
            } else {
                list.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);">📭 لا توجد معاملات</div>';
            }
        })
        .catch(function() { list.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);">خطأ في التحميل</div>'; });
}

function escapeHtml(text) { var d = document.createElement('div'); d.textContent = text; return d.innerHTML; }
function showToast(msg, type) { var t = document.createElement('div'); t.className = 'toast ' + type; t.textContent = msg; document.body.appendChild(t); setTimeout(function() { t.remove(); }, 2800); }

document.addEventListener('click', function(e) { if (!document.getElementById('searchWrapper').contains(e.target)) document.getElementById('searchResults').classList.remove('show'); });
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') { closeNewPartnerModal(); document.getElementById('searchResults').classList.remove('show'); } });
document.addEventListener('DOMContentLoaded', function() { searchPartners(''); loadRecentTransactions(); });

// ========== دعم Offline ==========
var offlineSaveBtn = document.querySelector('.submit-btn');
var originalSaveText = offlineSaveBtn.textContent;

async function saveTransaction() {
    var partnerId = document.getElementById('selectedPartnerId').value;
    var amount = document.getElementById('amount').value;
    
    if (!partnerId) { showToast('الرجاء اختيار عميل', 'error'); return; }
    if (!amount || parseFloat(amount) <= 0) { showToast('الرجاء إدخال المبلغ', 'error'); return; }
    
    offlineSaveBtn.textContent = '⏳ جاري الحفظ...';
    offlineSaveBtn.disabled = true;
    
    // حفظ محلي إذا كان Offline
    if (!navigator.onLine) {
        try {
            await localDB.addTransaction({
                partner_id: parseInt(partnerId),
                amount: parseFloat(amount),
                details: document.getElementById('details').value,
                date: document.getElementById('date').value,
                transaction_type: txType,
                currency_type: txCurrency
            });
            
            showToast('✅ تم الحفظ محلياً - سيتزامن لاحقاً', 'success');
            document.getElementById('amount').value = '';
            document.getElementById('details').value = '';
            loadRecentTransactions();
            setTimeout(function() { document.getElementById('amount').focus(); }, 300);
        } catch (e) {
            showToast('❌ خطأ في الحفظ المحلي', 'error');
        }
        offlineSaveBtn.textContent = originalSaveText;
        offlineSaveBtn.disabled = false;
        return;
    }
    
    // حفظ على السيرفر
    var fd = new FormData();
    fd.append('action', 'add');
    fd.append('partner_id', partnerId);
    fd.append('amount', amount);
    fd.append('details', document.getElementById('details').value);
    fd.append('date', document.getElementById('date').value);
    fd.append('type', txType);
    fd.append('currency', txCurrency);
    fd.append('quantity', document.getElementById('quantity').value || 0);
    
    try {
        var r = await fetch(window.location.href, { method: 'POST', body: fd });
        var d = await r.json();
        if (d.success) {
            showToast(d.message, 'success');
            document.getElementById('amount').value = '';
            document.getElementById('details').value = '';
            loadRecentTransactions();
            setTimeout(function() { document.getElementById('amount').focus(); }, 300);
        } else {
            showToast(d.message, 'error');
        }
    } catch (e) {
        // فشل الاتصال - حفظ محلي
        try {
            await localDB.addTransaction({
                partner_id: parseInt(partnerId),
                amount: parseFloat(amount),
                details: document.getElementById('details').value,
                date: document.getElementById('date').value,
                transaction_type: txType,
                currency_type: txCurrency
            });
            showToast('⚠️ فشل الاتصال - تم الحفظ محلياً', 'warning');
            document.getElementById('amount').value = '';
            document.getElementById('details').value = '';
            loadRecentTransactions();
        } catch (e2) {
            showToast('❌ خطأ في الحفظ', 'error');
        }
    }
    offlineSaveBtn.textContent = originalSaveText;
    offlineSaveBtn.disabled = false;
}
</script>

<?php require_once 'includes/footer_nav.php'; ?>