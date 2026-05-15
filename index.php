<?php
define('APP_RUNNING', true);
require_once 'config.php';
// في header.php أو في كل صفحة
header('Service-Worker-Allowed: /');
require_once 'includes/functions.php';

require_once 'includes/functions.php';

// ========== تخزين user_id في localStorage للاستخدام Offline ==========
$currentUserId = $_SESSION['user_id'] ?? 0;

// ========== معالجة إضافة عميل جديد (AJAX) ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_partner') {
    header('Content-Type: application/json; charset=utf-8');
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $type = $_POST['type'] ?? 'local';
    $userId = $_SESSION['user_id'] ?? 0;
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'الرجاء إدخال اسم العميل']);
        exit;
    }
    
    try {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM partners WHERE name = ? AND user_id = ? AND status = 'active'");
        $checkStmt->execute([$name, $userId]);
        if ($checkStmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'يوجد عميل بهذا الاسم مسبقاً']);
            exit;
        }
        
        $stmt = $pdo->prepare("INSERT INTO partners (name, phone, type, user_id, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->execute([$name, $phone, $type, $userId]);
        $newId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'تم إضافة العميل بنجاح', 
            'id' => $newId, 
            'name' => $name,
            'phone' => $phone,
            'type' => $type,
            'user_id' => $userId,
            'balance' => 0,
            'transaction_count' => 0
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ في الإضافة']);
    }
    exit;
}

$userId = $_SESSION['user_id'] ?? 0;
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

try {
    if ($isAdmin) {
        $query = "SELECT p.*, COALESCE(SUM(CASE WHEN t.transaction_type = 'credit' THEN t.amount ELSE -t.amount END), 0) as balance, COUNT(t.id) as transaction_count, MAX(t.date) as last_transaction FROM partners p LEFT JOIN transactions t ON p.id = t.partner_id AND t.currency_type = 'local' WHERE p.status = 'active' GROUP BY p.id ORDER BY p.name";
        $stmt = $pdo->query($query);
    } else {
        $stmt = $pdo->prepare("SELECT p.*, COALESCE(SUM(CASE WHEN t.transaction_type = 'credit' THEN t.amount ELSE -t.amount END), 0) as balance, COUNT(t.id) as transaction_count, MAX(t.date) as last_transaction FROM partners p LEFT JOIN transactions t ON p.id = t.partner_id AND t.currency_type = 'local' WHERE p.status = 'active' AND p.user_id = ? GROUP BY p.id ORDER BY p.name");
        $stmt->execute([$userId]);
    }
    $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($partners)) $partners = [];
} catch (Exception $e) { $partners = []; }

$totalClients = count($partners);
$totalDebit = 0; $totalCredit = 0;
foreach ($partners as $p) {
    if ($p['balance'] < 0) $totalDebit += abs($p['balance']);
    else $totalCredit += $p['balance'];
}

$avatarColors = [
    ['bg' => '#fff3e0', 'text' => '#e65100'], ['bg' => '#e8f5e9', 'text' => '#1b5e20'],
    ['bg' => '#e3f2fd', 'text' => '#0d47a1'], ['bg' => '#f3e5f5', 'text' => '#4a148c'],
    ['bg' => '#fffde7', 'text' => '#f57f17'], ['bg' => '#fce4ec', 'text' => '#b71c1c'],
    ['bg' => '#e0f7fa', 'text' => '#004d40'], ['bg' => '#efebe9', 'text' => '#3e2723'],
    ['bg' => '#ede7f6', 'text' => '#311b92'], ['bg' => '#fbe9e7', 'text' => '#bf360c'],
];
?>

<?php require_once 'includes/header.php'; ?>

<style>
    .main-content { padding: 16px; padding-bottom: 90px; }
    .status-bar { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 10px 14px; border-radius: var(--radius); margin-bottom: 12px; font-size: 12px; font-weight: 700; transition: all 0.3s ease; }
    .status-bar.online { background: rgba(0,214,143,0.1); border: 1px solid rgba(0,214,143,0.3); color: #00d68f; }
    .status-bar.offline { background: rgba(255,107,107,0.1); border: 1px solid rgba(255,107,107,0.3); color: #ff6b6b; }
    .status-bar.syncing { background: rgba(255,165,2,0.1); border: 1px solid rgba(255,165,2,0.3); color: #ffa502; }
    .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-left: 6px; }
    .status-dot.online { background: #00d68f; box-shadow: 0 0 8px rgba(0,214,143,0.5); animation: pulse-dot 2s infinite; }
    .status-dot.offline { background: #ff6b6b; }
    .status-dot.syncing { background: #ffa502; animation: spin 1s linear infinite; }
    @keyframes pulse-dot { 0%,100%{opacity:1} 50%{opacity:0.4} }
    @keyframes spin { to{transform:rotate(360deg)} }
    .sync-btn { padding: 7px 14px; border-radius: 20px; border: none; font-weight: 700; font-size: 11px; cursor: pointer; font-family: inherit; white-space: nowrap; transition: all 0.3s; background: var(--primary); color: #fff; display: flex; align-items: center; gap: 5px; }
    .sync-btn:active { transform: scale(0.95); } .sync-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .search-bar { position: relative; margin-bottom: 14px; }
    .search-input { width: 100%; padding: 13px 44px 13px 44px; background: var(--surface); border: 1.5px solid var(--border); border-radius: var(--radius); color: var(--text); font-size: 14px; font-family: inherit; transition: var(--transition); }
    .search-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(108,92,231,0.12); }
    .search-input::placeholder { color: var(--text-muted); }
    .search-icon-left { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 16px; pointer-events: none; }
    .search-clear-btn { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); width: 28px; height: 28px; border-radius: 50%; background: var(--border); border: none; color: var(--text-secondary); cursor: pointer; font-size: 12px; display: none; align-items: center; justify-content: center; transition: var(--transition); }
    .search-clear-btn.show { display: flex; } .search-clear-btn:active { background: var(--danger); color: white; }
    .filter-chips { display: flex; gap: 8px; margin-bottom: 14px; overflow-x: auto; scrollbar-width: none; -webkit-overflow-scrolling: touch; align-items: center; }
    .filter-chips::-webkit-scrollbar { display: none; }
    .chip { padding: 8px 16px; border-radius: 20px; border: 1px solid var(--border); background: var(--surface); color: var(--text-secondary); font-size: 12px; font-weight: 600; cursor: pointer; white-space: nowrap; transition: var(--transition); font-family: inherit; }
    .chip.active { background: var(--primary); border-color: var(--primary); color: white; } .chip:active { transform: scale(0.95); }
    .chip-add-btn { display: inline-flex; align-items: center; gap: 4px; padding: 8px 14px; background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: #fff; border: none; border-radius: 20px; font-family: inherit; font-size: 11px; font-weight: 700; cursor: pointer; white-space: nowrap; box-shadow: 0 4px 12px rgba(108,92,231,0.3); transition: all 0.3s; }
    .chip-add-btn:active { transform: scale(0.95); }
    .stats-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 16px; }
    .stat-mini { background: var(--surface); border-radius: var(--radius); padding: 14px 10px; text-align: center; border: 1px solid var(--border); transition: var(--transition); }
    .stat-mini:active { transform: scale(0.96); background: var(--surface-light); }
    .stat-mini .stat-icon { font-size: 22px; margin-bottom: 6px; }
    .stat-mini .stat-value { font-size: 18px; font-weight: 700; transition: all 0.3s; }
    .stat-mini .stat-label { font-size: 10px; color: var(--text-secondary); margin-top: 3px; }
    .stat-value.red { color: var(--danger); } .stat-value.green { color: var(--success); }
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .section-title { font-size: 13px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; }
    .section-count { font-size: 11px; background: var(--surface); padding: 5px 12px; border-radius: 20px; color: var(--text-secondary); border: 1px solid var(--border); transition: all 0.3s; }
    .clients-list { display: flex; flex-direction: column; gap: 8px; }
    .client-card { background: var(--surface); border-radius: var(--radius-lg); padding: 14px 16px; border: 1px solid var(--border); display: flex; align-items: center; gap: 12px; cursor: pointer; transition: var(--transition); animation: cardIn 0.4s ease forwards; opacity: 0; position: relative; overflow: hidden; }
    .client-card.new-card { animation: cardPopIn 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards; border-color: var(--primary); box-shadow: 0 0 20px rgba(108,92,231,0.3); }
    @keyframes cardIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes cardPopIn { 0% { transform: scale(0.8); opacity: 0; } 60% { transform: scale(1.03); opacity: 1; } 100% { transform: scale(1); opacity: 1; } }
    .client-card:active { transform: scale(0.985); background: var(--surface-light); }
    .client-card::before { content: ''; position: absolute; right: 0; top: 0; bottom: 0; width: 3px; background: var(--border); }
    .client-card.has-debt::before { background: var(--danger); } .client-card.has-credit::before { background: var(--success); }
    .client-avatar { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 700; flex-shrink: 0; }
    .client-info { flex: 1; min-width: 0; }
    .client-name { font-size: 15px; font-weight: 600; margin-bottom: 4px; }
    .client-meta { display: flex; gap: 6px; flex-wrap: wrap; }
    .meta-tag { font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600; }
    .meta-tag.count { background: var(--info-bg); color: var(--info); } .meta-tag.date { background: var(--warning-bg); color: var(--warning); }
    .meta-tag.local { background: rgba(255,165,2,0.15); color: #ffa502; }
    .client-balance { text-align: left; flex-shrink: 0; }
    .balance-amount { font-size: 18px; font-weight: 700; } .balance-amount.positive { color: var(--success); } .balance-amount.negative { color: var(--danger); } .balance-amount.zero { color: var(--text-muted); }
    .balance-label { font-size: 10px; color: var(--text-secondary); text-align: left; margin-top: 2px; }

    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); z-index: 200; display: none; overflow-y: auto; animation: fadeIn 0.2s ease; }
    .modal-sheet { background: var(--surface); margin: 60px auto 20px auto; width: 92%; max-width: 500px; border-radius: 18px; padding: 22px 18px 24px; box-shadow: 0 15px 50px rgba(0,0,0,0.5); animation: modalIn 0.3s ease; }
    .modal-title { font-size: 18px; font-weight: 700; text-align: center; margin-bottom: 18px; color: var(--text); }
    .form-group { margin-bottom: 14px; }
    .form-label { display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; }
    .form-input { width: 100%; padding: 13px 15px; background: var(--surface-light); border: 1.5px solid var(--border); border-radius: var(--radius-sm); color: var(--text); font-size: 14px; font-family: inherit; transition: var(--transition); }
    .form-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(108,92,231,0.1); }
    .form-select { appearance: none; -webkit-appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath d='M6 8L1 3h10z' fill='%238888a0'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: left 14px center; padding-left: 35px; }
    .btn-row { display: flex; gap: 10px; margin-top: 18px; }
    .btn { flex: 1; padding: 14px; border-radius: var(--radius-sm); font-weight: 700; font-size: 14px; cursor: pointer; border: none; font-family: inherit; transition: var(--transition); }
    .btn:active { transform: scale(0.96); } .btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .btn-cancel { background: var(--surface-light); color: var(--text); border: 1px solid var(--border); }
    .btn-save { background: var(--primary); color: white; }
    
    .toast { position: fixed; bottom: 110px; left: 50%; transform: translateX(-50%); padding: 12px 22px; border-radius: 25px; color: white; font-weight: 600; font-size: 13px; z-index: 300; animation: toastIn 0.3s ease, toastOut 0.3s ease 2.2s forwards; white-space: nowrap; box-shadow: 0 8px 24px rgba(0,0,0,0.3); }
    .toast.success { background: var(--success); } .toast.error { background: var(--danger); } .toast.warning { background: #ffa502; }
    @keyframes toastIn { from { opacity: 0; transform: translateX(-50%) translateY(10px); } to { opacity: 1; transform: translateX(-50%) translateY(0); } }
    @keyframes toastOut { to { opacity: 0; transform: translateX(-50%) translateY(-8px); } }
    @keyframes fadeIn { from{opacity:0} to{opacity:1} }
    @keyframes modalIn { from{transform:translateY(-40px);opacity:0} to{transform:translateY(0);opacity:1} }
    .empty-state { text-align: center; padding: 50px 20px; }
    .empty-icon { font-size: 70px; margin-bottom: 16px; animation: float 3s ease infinite; }
    @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-18px)} }
    .empty-title { font-size: 18px; font-weight: 700; margin-bottom: 8px; color: var(--text); }
    .empty-desc { font-size: 13px; color: var(--text-secondary); margin-bottom: 16px; }
    .empty-btn { display: inline-flex; align-items: center; gap: 6px; padding: 12px 24px; background: var(--primary); color: white; border: none; border-radius: var(--radius); font-weight: 700; font-size: 14px; cursor: pointer; font-family: inherit; }
    @media (max-width: 360px) { .main-content { padding: 12px; padding-bottom: 85px; } .stats-grid { gap: 6px; } .stat-mini { padding: 10px 6px; } .client-avatar { width: 40px; height: 40px; font-size: 16px; } .client-name { font-size: 14px; } .balance-amount { font-size: 15px; } }
</style>

<div class="main-content">
    <div class="status-bar online" id="statusBar">
        <span><span class="status-dot online" id="statusDot"></span><span id="statusText">🟢 متصل بالإنترنت</span></span>
        <button class="sync-btn" id="syncBtn" onclick="manualSync()">🔄 مزامنة</button>
    </div>
    <div class="search-bar">
        <span class="search-icon-left">🔍</span>
        <input type="text" class="search-input" id="searchInput" placeholder="ابحث عن عميل بالاسم..." oninput="filterClients()" autocomplete="off">
        <button class="search-clear-btn" id="searchClearBtn" onclick="clearSearch()">✕</button>
    </div>
    <div class="filter-chips">
        <button class="chip active" data-filter="all" onclick="setFilter('all', this)">📋 الكل</button>
        <button class="chip" data-filter="debit" onclick="setFilter('debit', this)">📤 عليهم دين</button>
        <button class="chip" data-filter="credit" onclick="setFilter('credit', this)">📥 لهم دين</button>
        <button class="chip-add-btn" onclick="openAddModal()">＋ عميل</button>
    </div>
    <div class="stats-grid">
        <div class="stat-mini"><div class="stat-icon">👥</div><div class="stat-value" id="totalClientsVal"><?php echo $totalClients; ?></div><div class="stat-label">عميل</div></div>
        <div class="stat-mini"><div class="stat-icon">📤</div><div class="stat-value red" id="totalDebitVal"><?php echo number_format($totalDebit); ?></div><div class="stat-label">عليك</div></div>
        <div class="stat-mini"><div class="stat-icon">📥</div><div class="stat-value green" id="totalCreditVal"><?php echo number_format($totalCredit); ?></div><div class="stat-label">لك</div></div>
    </div>
    <div class="section-header"><div class="section-title">👥 العملاء</div><div class="section-count" id="clientCount"><?php echo $totalClients; ?> عميل</div></div>
    <div class="clients-list" id="clientsList">
        <?php if (empty($partners)): ?>
        <div class="empty-state" id="emptyState"><div class="empty-icon">📭</div><div class="empty-title">لا يوجد عملاء بعد</div><div class="empty-desc">ابدأ بإضافة أول عميل لك</div><button class="empty-btn" onclick="openAddModal()">➕ إضافة أول عميل</button></div>
        <?php else: ?>
            <?php foreach ($partners as $i => $p): $color = $avatarColors[$i % count($avatarColors)]; $cardClass = $p['balance'] < 0 ? 'has-debt' : ($p['balance'] > 0 ? 'has-credit' : ''); $balanceClass = $p['balance'] > 0 ? 'positive' : ($p['balance'] < 0 ? 'negative' : 'zero'); ?>
            <div class="client-card <?php echo $cardClass; ?>" style="animation-delay:<?php echo $i*0.04; ?>s" data-name="<?php echo htmlspecialchars(strtolower($p['name'])); ?>" data-balance="<?php echo $p['balance']; ?>" data-filter="<?php echo $p['balance'] < 0 ? 'debit' : ($p['balance'] > 0 ? 'credit' : 'zero'); ?>" data-server="1" onclick="window.location.href='client.php?id=<?php echo $p['id']; ?>'">
                <div class="client-avatar" style="background:<?php echo $color['bg']; ?>;color:<?php echo $color['text']; ?>;"><?php echo mb_substr($p['name'],0,1); ?></div>
                <div class="client-info"><div class="client-name"><?php echo htmlspecialchars($p['name']); ?></div><div class="client-meta"><?php if ($p['transaction_count'] > 0): ?><span class="meta-tag count">📊 <?php echo $p['transaction_count']; ?> عملية</span><?php endif; ?><?php if ($p['last_transaction']): ?><span class="meta-tag date">🕐 <?php echo date('d/m/Y', strtotime($p['last_transaction'])); ?></span><?php endif; ?></div></div>
                <div class="client-balance"><div class="balance-amount <?php echo $balanceClass; ?>"><?php echo number_format(abs($p['balance'])); ?></div><div class="balance-label"><?php echo $p['balance'] > 0 ? 'له' : ($p['balance'] < 0 ? 'عليه' : '—'); ?></div></div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="modal-overlay" id="addPartnerModal" style="display:none;" onclick="closeAddModal(event)">
    <div class="modal-sheet" onclick="event.stopPropagation()">
        <div class="modal-title">👤 إضافة عميل جديد</div>
        <div class="form-group"><label class="form-label">الاسم *</label><input type="text" class="form-input" id="partnerName" placeholder="أدخل اسم العميل" maxlength="100" autocomplete="off"></div>
        <div class="form-group"><label class="form-label">رقم الهاتف</label><input type="tel" class="form-input" id="partnerPhone" placeholder="777123456" dir="ltr" maxlength="20"></div>
        <div class="form-group"><label class="form-label">نوع العملة</label><select class="form-input form-select" id="partnerType"><option value="local">💵 محلي</option><option value="dollar">💲 دولار</option></select></div>
        <div class="btn-row">
            <button class="btn btn-cancel" onclick="closeAddModal()">إلغاء</button>
            <button class="btn btn-save" id="savePartnerBtn" onclick="addPartner()">✅ إضافة</button>
        </div>
    </div>
</div>

<script>
// ========== تخزين user_id للاستخدام Offline ==========
<?php if ($currentUserId > 0): ?>
localStorage.setItem('offline_user_id', '<?php echo $currentUserId; ?>');
console.log('✅ user_id مخزن:', '<?php echo $currentUserId; ?>');
<?php endif; ?>
</script>

<script src="js/db.js"></script>
<script>
var currentFilter = 'all';
var avatarColors = [['#fff3e0','#e65100'],['#e8f5e9','#1b5e20'],['#e3f2fd','#0d47a1'],['#f3e5f5','#4a148c'],['#fffde7','#f57f17'],['#fce4ec','#b71c1c'],['#e0f7fa','#004d40'],['#efebe9','#3e2723'],['#ede7f6','#311b92'],['#fbe9e7','#bf360c']];

function filterClients() {
    var q = document.getElementById('searchInput').value.toLowerCase();
    var cb = document.getElementById('searchClearBtn');
    var cards = document.querySelectorAll('.client-card');
    var v = 0;
    cb.classList.toggle('show', q.length > 0);
    cards.forEach(function(c) {
        var n = (c.dataset.name || '').toLowerCase();
        var f = c.dataset.filter || 'all';
        var ms = n.includes(q);
        var mf = currentFilter === 'all' || (currentFilter === 'debit' && f === 'debit') || (currentFilter === 'credit' && f === 'credit');
        if (ms && mf) { c.style.display = ''; v++; } else { c.style.display = 'none'; }
    });
    document.getElementById('clientCount').textContent = v + ' عميل';
}
function clearSearch() { document.getElementById('searchInput').value = ''; document.getElementById('searchClearBtn').classList.remove('show'); filterClients(); document.getElementById('searchInput').focus(); }
function setFilter(f, b) { currentFilter = f; document.querySelectorAll('.chip').forEach(function(c){c.classList.remove('active')}); b.classList.add('active'); filterClients(); }

function openAddModal() { document.getElementById('addPartnerModal').style.display = 'block'; document.body.style.overflow = 'hidden'; document.body.style.position = 'fixed'; document.body.style.width = '100%'; document.getElementById('partnerName').value = ''; document.getElementById('partnerPhone').value = ''; document.getElementById('partnerType').value = 'local'; setTimeout(function(){ document.getElementById('partnerName').focus(); }, 350); }
function closeAddModal(e) { if (e && e.target !== document.getElementById('addPartnerModal')) return; document.getElementById('addPartnerModal').style.display = 'none'; document.body.style.overflow = ''; document.body.style.position = ''; document.body.style.width = ''; }

async function addPartner() {
    var name = document.getElementById('partnerName').value.trim();
    var phone = document.getElementById('partnerPhone').value.trim();
    var type = document.getElementById('partnerType').value;
    if (!name) { showToast('الرجاء إدخال اسم العميل', 'error'); return; }
    
    var btn = document.getElementById('savePartnerBtn');
    btn.textContent = '⏳ جاري الحفظ...'; btn.disabled = true;
    
    var uid = localStorage.getItem('offline_user_id') || '0';
    
    // Offline
    if (!navigator.onLine) {
        try {
            await localDB.addPartner({ name: name, phone: phone, type: type, user_id: parseInt(uid) });
            showToast('✅ تم حفظ العميل محلياً', 'success');
            closeAddModal();
            addClientToUI({ name: name, phone: phone, type: type, balance: 0, transaction_count: 0 }, true);
            updateStats(1, 0, 0);
        } catch (e) { showToast('❌ خطأ في الحفظ المحلي', 'error'); }
        btn.textContent = '✅ إضافة'; btn.disabled = false;
        return;
    }
    
    // Online
    var fd = new FormData(); fd.append('action', 'add_partner'); fd.append('name', name); fd.append('phone', phone); fd.append('type', type);
    try {
        var r = await fetch(window.location.href, { method: 'POST', body: fd });
        var d = await r.json();
        if (d.success) {
            showToast(d.message, 'success');
            closeAddModal();
            addClientToUI(d, false);
            updateStats(1, 0, 0);
        } else { showToast(d.message, 'error'); }
    } catch (e) {
        try {
            await localDB.addPartner({ name: name, phone: phone, type: type, user_id: parseInt(uid) });
            showToast('⚠️ فشل الاتصال - تم الحفظ محلياً', 'warning');
            closeAddModal();
            addClientToUI({ name: name, phone: phone, type: type, balance: 0, transaction_count: 0 }, true);
            updateStats(1, 0, 0);
        } catch (e2) { showToast('❌ خطأ في الحفظ', 'error'); }
    }
    btn.textContent = '✅ إضافة'; btn.disabled = false;
}

function addClientToUI(data, isLocal) {
    var emptyState = document.getElementById('emptyState'); if (emptyState) emptyState.remove();
    var list = document.getElementById('clientsList');
    var idx = list.querySelectorAll('.client-card').length;
    var color = avatarColors[idx % avatarColors.length];
    var card = document.createElement('div');
    card.className = 'client-card new-card';
    if (isLocal) card.setAttribute('data-local', '1'); else card.setAttribute('data-server', '1');
    card.setAttribute('data-name', (data.name || '').toLowerCase());
    card.setAttribute('data-balance', data.balance || 0);
    card.setAttribute('data-filter', (data.balance || 0) < 0 ? 'debit' : ((data.balance || 0) > 0 ? 'credit' : 'zero'));
    if (data.id) card.onclick = function(){ window.location.href = 'client.php?id=' + data.id; };
    card.innerHTML = '<div class="client-avatar" style="background:' + color[0] + ';color:' + color[1] + ';">' + (data.name || '?')[0] + '</div>' +
        '<div class="client-info"><div class="client-name">' + (data.name || '') + '</div><div class="client-meta">' + (isLocal ? '<span class="meta-tag local">💾 محلي</span>' : '<span class="meta-tag count">📊 0 عملية</span>') + '</div></div>' +
        '<div class="client-balance"><div class="balance-amount zero">0</div><div class="balance-label">—</div></div>';
    var firstServer = list.querySelector('[data-server]');
    if (firstServer) list.insertBefore(card, firstServer); else list.appendChild(card);
    updateClientCount();
    setTimeout(function(){ card.classList.remove('new-card'); }, 600);
}

function updateStats(clients, debit, credit) {
    var tc = document.getElementById('totalClientsVal'); if (tc) tc.textContent = parseInt(tc.textContent) + clients;
    var td = document.getElementById('totalDebitVal'); if (td) td.textContent = (parseInt(td.textContent.replace(/,/g,'')) + debit).toLocaleString('ar-SA');
    var tcr = document.getElementById('totalCreditVal'); if (tcr) tcr.textContent = (parseInt(tcr.textContent.replace(/,/g,'')) + credit).toLocaleString('ar-SA');
}
function updateClientCount() {
    var count = document.querySelectorAll('.client-card').length;
    var cc = document.getElementById('clientCount'); if (cc) cc.textContent = count + ' عميل';
}

async function loadLocalPartners() {
    if (navigator.onLine) return;
    try {
        var lp = await localDB.getPartners();
        if (lp && lp.length > 0) {
            var existing = new Set();
            document.querySelectorAll('.client-card').forEach(function(c){ var n = c.querySelector('.client-name'); if (n) existing.add(n.textContent.trim()); });
            lp.forEach(function(p){ if (!existing.has(p.name)) addClientToUI(p, true); });
        }
    } catch (e) {}
}

window.manualSync = async function() {
    if (!navigator.onLine) { 
        showToast('⚠️ لا يوجد اتصال', 'error'); 
        return; 
    }
    
    var sb = document.getElementById('statusBar'), 
        sd = document.getElementById('statusDot'), 
        st = document.getElementById('statusText'), 
        sbtn = document.getElementById('syncBtn');
    
    sb.className = 'status-bar syncing'; 
    sd.className = 'status-dot syncing'; 
    st.textContent = '🟡 جاري المزامنة...'; 
    sbtn.textContent = '⏳'; 
    sbtn.disabled = true;
    
    try {
        var queue = await localDB.getSyncQueue();
        
        if (!queue || queue.length === 0) { 
            updateOnlineStatus(); 
            showToast('✅ جميع البيانات محدثة', 'success'); 
            return; 
        }
        
        var synced = 0;
        for (var i = 0; i < queue.length; i++) {
            try { 
                var fd = new FormData(); 
                fd.append('action', queue[i].action); 
                fd.append('data', JSON.stringify(queue[i].data)); 
                var resp = await fetch('api/sync.php', { method: 'POST', body: fd });
                var result = await resp.json();
                if (result.success) {
                    await localDB.removeFromSyncQueue(queue[i].id); 
                    synced++; 
                }
            } catch (e) {}
        }
        
        // ========== تم التعديل هنا: إعادة تشغيل الموقع بعد المزامنة ==========
        showToast('✅ تمت مزامنة ' + synced + ' عنصر - جاري إعادة التشغيل...', 'success');
        
        // انتظار 1.5 ثانية ثم إعادة تشغيل الصفحة بقوة
        setTimeout(function() {
            // محاولة إغلاق التطبيق إذا كان في WebView
            if (window.isWebView && window.isWebView()) {
                if (navigator.app && navigator.app.exitApp) {
                    navigator.app.exitApp();
                }
            }
            
            // إعادة تحميل الصفحة مع تجاهل الكاش
            window.location.reload(true);
            
            // خطة بديلة: إعادة التوجيه مع متغير لمنع الكاش
            setTimeout(function() {
                var currentUrl = window.location.href.split('?')[0];
                window.location.href = currentUrl + '?synced=' + Date.now();
            }, 500);
        }, 1500);
        
    } catch (e) { 
        updateOnlineStatus();
        showToast('❌ فشلت المزامنة', 'error'); 
        sbtn.textContent = '🔄 مزامنة'; 
        sbtn.disabled = false;
    }
};

function updateOnlineStatus() {
    var sb = document.getElementById('statusBar'), sd = document.getElementById('statusDot'), st = document.getElementById('statusText'), sbtn = document.getElementById('syncBtn');
    if (navigator.onLine) { 
        sb.className = 'status-bar online'; 
        sd.className = 'status-dot online'; 
        st.textContent = '🟢 متصل'; 
        if (sbtn) { sbtn.textContent = '🔄 مزامنة'; sbtn.disabled = false; }
    } else { 
        sb.className = 'status-bar offline'; 
        sd.className = 'status-dot offline'; 
        st.textContent = '🔴 غير متصل'; 
        if (sbtn) { sbtn.textContent = '📡'; sbtn.disabled = true; }
    }
}

function showToast(m, t) { var x = document.querySelector('.toast'); if (x) x.remove(); var d = document.createElement('div'); d.className = 'toast ' + t; d.textContent = m; document.body.appendChild(d); setTimeout(function(){ d.remove(); }, 2500); }

// ========== عند تحميل الصفحة، تحقق من وجود مزامنة سابقة ==========
document.addEventListener('DOMContentLoaded', function() {
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('synced')) {
        showToast('✅ تم تحديث البيانات بنجاح', 'success');
        // تنظيف الـ URL من المتغير
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }
});

window.addEventListener('online', function(){ updateOnlineStatus(); setTimeout(manualSync, 1500); });
window.addEventListener('offline', function(){ updateOnlineStatus(); loadLocalPartners(); });
document.addEventListener('DOMContentLoaded', function(){ 
    updateOnlineStatus(); 
    loadLocalPartners(); 
});
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeAddModal(); if ((e.ctrlKey || e.metaKey) && e.key === 'f') { e.preventDefault(); document.getElementById('searchInput').focus(); } if ((e.ctrlKey || e.metaKey) && e.key === 'n') { e.preventDefault(); openAddModal(); } });
document.getElementById('partnerName')?.addEventListener('keypress', function(e){ if (e.key === 'Enter') document.getElementById('partnerPhone').focus(); });
document.getElementById('partnerPhone')?.addEventListener('keypress', function(e){ if (e.key === 'Enter') addPartner(); });
</script>

<?php require_once 'includes/footer_nav.php'; ?>