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
        // مزامنة العملاء المحليين
        if ($action === 'sync') {
            $data = json_decode($_POST['data'] ?? '[]', true);
            if (!is_array($data) || empty($data)) {
                echo json_encode(['success' => false, 'message' => 'لا توجد بيانات للمزامنة']);
                exit;
            }
            $synced = 0;
            foreach ($data as $p) {
                $checkStmt = $pdo->prepare("SELECT id FROM partners WHERE name = ? AND user_id = ? AND status = 'active'");
                $checkStmt->execute([$p['name'] ?? '', $userId]);
                if ($checkStmt->fetch()) continue;
                
                $stmt = $pdo->prepare("INSERT INTO partners (name, phone, type, user_id, status) VALUES (?, ?, ?, ?, 'active')");
                $stmt->execute([$p['name'] ?? '', $p['phone'] ?? '', $p['type'] ?? 'local', $userId]);
                $synced++;
            }
            echo json_encode(['success' => true, 'message' => "تمت مزامنة {$synced} عميل", 'synced' => $synced]);
            exit;
        }
        
        // إضافة عميل
        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $type = $_POST['type'] ?? 'local';
            
            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'الرجاء إدخال اسم العميل']);
                exit;
            }
            
            $checkStmt = $pdo->prepare("SELECT id FROM partners WHERE name = ? AND user_id = ? AND status = 'active'");
            $checkStmt->execute([$name, $userId]);
            if ($checkStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'يوجد عميل بهذا الاسم مسبقاً']);
                exit;
            }
            
            $stmt = $pdo->prepare("INSERT INTO partners (name, phone, type, user_id, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->execute([$name, $phone, $type, $userId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'تم إضافة العميل بنجاح',
                'id' => $pdo->lastInsertId(),
                'name' => $name,
                'phone' => $phone,
                'type' => $type,
                'balance' => 0,
                'transaction_count' => 0
            ]);
            exit;
        }
        
        // تعديل عميل
        if ($action === 'edit') {
            $id = (int)$_POST['id'];
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $type = $_POST['type'] ?? 'local';
            
            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'الرجاء إدخال اسم العميل']);
                exit;
            }
            
            if (!$isAdmin) {
                $ownerStmt = $pdo->prepare("SELECT id FROM partners WHERE id = ? AND user_id = ?");
                $ownerStmt->execute([$id, $userId]);
                if (!$ownerStmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'لا يمكنك تعديل هذا العميل']);
                    exit;
                }
            }
            
            $stmt = $pdo->prepare("UPDATE partners SET name=?, phone=?, type=? WHERE id=?");
            $stmt->execute([$name, $phone, $type, $id]);
            echo json_encode(['success' => true, 'message' => 'تم تحديث العميل', 'id' => $id, 'name' => $name, 'phone' => $phone, 'type' => $type]);
            exit;
        }
        
        // حذف عميل مع جميع معاملاته
        if ($action === 'delete') {
            $id = (int)$_POST['id'];
            
            if (!$isAdmin) {
                $ownerStmt = $pdo->prepare("SELECT id, name FROM partners WHERE id = ? AND user_id = ?");
                $ownerStmt->execute([$id, $userId]);
                $partner = $ownerStmt->fetch(PDO::FETCH_ASSOC);
                if (!$partner) {
                    echo json_encode(['success' => false, 'message' => 'لا يمكنك حذف هذا العميل']);
                    exit;
                }
            }
            
            $txCountStmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE partner_id = ?");
            $txCountStmt->execute([$id]);
            $txCount = $txCountStmt->fetchColumn();
            
            try {
                $pdo->beginTransaction();
                $deleteTxStmt = $pdo->prepare("DELETE FROM transactions WHERE partner_id = ?");
                $deleteTxStmt->execute([$id]);
                $deletePartnerStmt = $pdo->prepare("DELETE FROM partners WHERE id = ?");
                $deletePartnerStmt->execute([$id]);
                $pdo->commit();
                
                $msg = 'تم حذف العميل بنجاح';
                if ($txCount > 0) {
                    $msg .= " مع {$txCount} معاملة";
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => $msg . ' ✓',
                    'id' => $id,
                    'deleted_transactions' => $txCount
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'فشل الحذف']);
            }
            exit;
        }
        
        // جلب عميل للتعديل
        if ($action === 'get') {
            $id = (int)$_POST['id'];
            
            if ($isAdmin) {
                $stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
                $stmt->execute([$id]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $userId]);
            }
            $partner = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($partner) {
                echo json_encode(['success' => true, 'data' => $partner]);
            } else {
                echo json_encode(['success' => false, 'message' => 'العميل غير موجود']);
            }
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'إجراء غير معروف']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
    }
    exit;
}

// ========== جلب العملاء ==========
$userId = $_SESSION['user_id'] ?? 0;
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

if ($isAdmin) {
    $stmt = $pdo->query("SELECT p.*, COUNT(t.id) as transaction_count, COALESCE(SUM(CASE WHEN t.transaction_type = 'credit' THEN t.amount ELSE -t.amount END), 0) as balance, MAX(t.date) as last_transaction FROM partners p LEFT JOIN transactions t ON p.id = t.partner_id AND t.currency_type = 'local' WHERE p.status = 'active' GROUP BY p.id ORDER BY p.name");
} else {
    $stmt = $pdo->prepare("SELECT p.*, COUNT(t.id) as transaction_count, COALESCE(SUM(CASE WHEN t.transaction_type = 'credit' THEN t.amount ELSE -t.amount END), 0) as balance, MAX(t.date) as last_transaction FROM partners p LEFT JOIN transactions t ON p.id = t.partner_id AND t.currency_type = 'local' WHERE p.status = 'active' AND p.user_id = ? GROUP BY p.id ORDER BY p.name");
    $stmt->execute([$userId]);
}
$partners = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$totalPartners = count($partners);
$partnersWithDebt = 0;
$partnersWithCredit = 0;
foreach ($partners as $p) {
    if ($p['balance'] < 0) $partnersWithDebt++;
    if ($p['balance'] > 0) $partnersWithCredit++;
}

$avatarColors = [
    ['bg' => '#fff3e0', 'text' => '#e65100'],
    ['bg' => '#e8f5e9', 'text' => '#1b5e20'],
    ['bg' => '#e3f2fd', 'text' => '#0d47a1'],
    ['bg' => '#f3e5f5', 'text' => '#4a148c'],
    ['bg' => '#fffde7', 'text' => '#f57f17'],
    ['bg' => '#fce4ec', 'text' => '#b71c1c'],
    ['bg' => '#e0f7fa', 'text' => '#004d40'],
    ['bg' => '#efebe9', 'text' => '#3e2723'],
    ['bg' => '#ede7f6', 'text' => '#311b92'],
    ['bg' => '#fbe9e7', 'text' => '#bf360c'],
];
?>

<?php require_once 'includes/header.php'; ?>

<style>
    .main-content { padding: 16px; padding-bottom: 100px; }
    .status-ribbon { display: none; padding: 10px 14px; border-radius: var(--radius); margin-bottom: 10px; font-size: 12px; font-weight: 700; text-align: center; }
    .status-ribbon.show { display: block; } .status-ribbon.offline { background: rgba(255,107,107,0.1); border: 1px solid rgba(255,107,107,0.3); color: #ff6b6b; }
    .status-ribbon.online { background: rgba(0,214,143,0.1); border: 1px solid rgba(0,214,143,0.3); color: #00d68f; } .status-ribbon.warning { background: rgba(255,165,2,0.1); border: 1px solid rgba(255,165,2,0.3); color: #ffa502; }
    .sync-bar { display: none; align-items: center; justify-content: space-between; gap: 8px; padding: 10px 14px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); margin-bottom: 10px; font-size: 11px; }
    .sync-bar.show { display: flex; } .sync-btn { padding: 7px 16px; background: var(--primary); color: #fff; border: none; border-radius: 20px; font-weight: 700; font-size: 11px; cursor: pointer; font-family: inherit; } .sync-btn:active { transform: scale(0.95); } .sync-btn:disabled { opacity: 0.5; }
    .stats-grid { display: grid; grid-template-columns: repeat(3,1fr); gap:8px; margin-bottom:14px; }
    .stat-card { background:var(--surface); border-radius:var(--radius); padding:12px 8px; text-align:center; border:1px solid var(--border); }
    .stat-card .v { font-size:18px; font-weight:700; transition: all 0.3s; } .stat-card .l { font-size:10px; color:var(--text-secondary); margin-top:3px; } .v.red { color:var(--danger); } .v.green { color:var(--success); }
    .action-bar { display:flex; gap:8px; margin-bottom:14px; align-items:center; }
    .search-bar { position:relative; flex:1; } .search-input { width:100%; padding:13px 44px 13px 44px; background:var(--surface); border:1.5px solid var(--border); border-radius:var(--radius); color:var(--text); font-size:14px; font-family:inherit; } .search-input:focus { outline:none; border-color:var(--primary); }
    .search-icon { position:absolute; left:14px; top:50%; transform:translateY(-50%); font-size:16px; pointer-events:none; }
    .add-partner-btn { padding: 13px 18px; background: var(--primary); color: #fff; border: none; border-radius: var(--radius); font-weight: 700; font-size: 14px; cursor: pointer; font-family: inherit; white-space: nowrap; display: flex; align-items: center; gap: 6px; transition: all 0.2s; } .add-partner-btn:active { transform: scale(0.95); }
    .section-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; } .section-title { font-size:13px; font-weight:700; color:var(--text-secondary); }
    .section-count { font-size:11px; background:var(--surface); padding:5px 12px; border-radius:20px; color:var(--text-secondary); border:1px solid var(--border); transition: all 0.3s; }
    .partners-list { display:flex; flex-direction:column; gap:8px; }
    .partner-card { background:var(--surface); border-radius:var(--radius-lg); padding:14px 16px; border:1px solid var(--border); display:flex; align-items:center; gap:12px; cursor:pointer; transition:var(--transition); animation:cardIn 0.4s ease forwards; opacity:0; position:relative; overflow:hidden; }
    .partner-card.new-card { animation: cardPopIn 0.5s cubic-bezier(0.68,-0.55,0.265,1.55) forwards; border-color: var(--primary); box-shadow: 0 0 20px rgba(108,92,231,0.3); }
    @keyframes cardIn { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
    @keyframes cardPopIn { 0%{transform:scale(0.8);opacity:0} 60%{transform:scale(1.03);opacity:1} 100%{transform:scale(1);opacity:1} }
    .partner-card:active { transform:scale(0.985); background:var(--surface-light); }
    .partner-card::before { content:''; position:absolute; right:0; top:0; bottom:0; width:3px; background:var(--border); } .partner-card.has-debt::before { background:var(--danger); } .partner-card.has-credit::before { background:var(--success); }
    .partner-avatar { width:48px; height:48px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:20px; font-weight:700; flex-shrink:0; }
    .partner-info { flex:1; min-width:0; } .partner-name { font-size:15px; font-weight:600; margin-bottom:4px; display:flex; align-items:center; gap:6px; }
    .partner-meta { display:flex; gap:6px; flex-wrap:wrap; } .meta-tag { font-size:10px; padding:3px 8px; border-radius:6px; font-weight:600; }
    .meta-tag.count { background:var(--info-bg); color:var(--info); } .meta-tag.date { background:var(--warning-bg); color:var(--warning); } .meta-tag.local { background:rgba(255,165,2,0.2); color:#ffa502; }
    .partner-balance { text-align:left; flex-shrink:0; } .balance-amount { font-size:18px; font-weight:700; } .balance-amount.positive { color:var(--success); } .balance-amount.negative { color:var(--danger); } .balance-amount.zero { color:var(--text-muted); }
    .balance-label { font-size:10px; color:var(--text-secondary); text-align:left; margin-top:2px; }
    .partner-actions { display:flex; gap:4px; margin-top:6px; justify-content:flex-end; } .action-icon { width:30px; height:30px; border-radius:8px; border:1px solid var(--border); background:var(--surface-light); color:var(--text-secondary); cursor:pointer; font-size:12px; display:flex; align-items:center; justify-content:center; } .action-icon:active { transform:scale(0.9); } .action-icon.edit:active { background:var(--primary); color:#fff; border-color:var(--primary); } .action-icon.delete:active { background:var(--danger); color:#fff; border-color:var(--danger); }
    
    /* ========== المودال بنمط index.php ========== */
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); z-index: 200; display: none; overflow-y: auto; animation: fadeIn 0.2s ease; }
    .modal-sheet { background: var(--surface); margin: 60px auto 20px auto; width: 92%; max-width: 500px; border-radius: 18px; padding: 22px 18px 24px; box-shadow: 0 15px 50px rgba(0,0,0,0.5); animation: modalIn 0.3s ease; }
    .modal-title { font-size: 18px; font-weight: 700; text-align: center; margin-bottom: 18px; color: var(--text); }
    .form-group { margin-bottom: 14px; } .form-label { display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; }
    .form-input { width: 100%; padding: 13px 15px; background: var(--surface-light); border: 1.5px solid var(--border); border-radius: var(--radius-sm); color: var(--text); font-size: 14px; font-family: inherit; transition: var(--transition); }
    .form-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(108,92,231,0.1); }
    .btn-row { display: flex; gap: 10px; margin-top: 18px; } .btn { flex: 1; padding: 14px; border-radius: var(--radius-sm); font-weight: 700; font-size: 14px; cursor: pointer; border: none; font-family: inherit; transition: var(--transition); }
    .btn:active { transform: scale(0.96); } .btn:disabled { opacity: 0.5; cursor: not-allowed; } .btn-cancel { background: var(--surface-light); color: var(--text); border: 1px solid var(--border); } .btn-save { background: var(--primary); color: #fff; }
    
    .toast { position: fixed; bottom: 110px; left: 50%; transform: translateX(-50%); padding: 12px 22px; border-radius: 25px; color: #fff; font-weight: 600; font-size: 13px; z-index: 300; animation: toastIn 0.3s ease, toastOut 0.3s ease 2.5s forwards; white-space: nowrap; box-shadow: 0 8px 24px rgba(0,0,0,0.3); }
    .toast.success { background: var(--success); } .toast.error { background: var(--danger); } .toast.warning { background: #ffa502; }
    @keyframes fadeIn { from{opacity:0} to{opacity:1} } @keyframes modalIn { from{transform:translateY(-40px);opacity:0} to{transform:translateY(0);opacity:1} }
    @keyframes toastIn { from{opacity:0;transform:translateX(-50%) translateY(10px)} to{opacity:1;transform:translateX(-50%) translateY(0)} } @keyframes toastOut { to{opacity:0;transform:translateX(-50%) translateY(-8px)} }
    
    @media (max-width: 360px) { .main-content { padding: 12px; } .partner-avatar { width: 40px; height: 40px; font-size: 16px; } .partner-name { font-size: 14px; } .balance-amount { font-size: 15px; } }
</style>

<div class="main-content">
    <div class="status-ribbon" id="statusRibbon"></div>
    <div class="sync-bar" id="syncBar"><span>💾 عملاء محليين: <strong id="localCount">0</strong></span><button class="sync-btn" onclick="syncNow()">🔄 مزامنة الآن</button></div>
    
    <div class="stats-grid">
        <div class="stat-card"><div class="v" id="totalPartnersVal"><?php echo $totalPartners; ?></div><div class="l">العملاء</div></div>
        <div class="stat-card"><div class="v red" id="debtVal"><?php echo $partnersWithDebt; ?></div><div class="l">مدين</div></div>
        <div class="stat-card"><div class="v green" id="creditVal"><?php echo $partnersWithCredit; ?></div><div class="l">دائن</div></div>
    </div>
    
    <div class="action-bar">
        <div class="search-bar"><span class="search-icon">🔍</span><input type="text" class="search-input" id="searchInput" placeholder="ابحث عن عميل..." oninput="filterPartners()" autocomplete="off"></div>
        <button class="add-partner-btn" onclick="openAddModal()">➕ جديد</button>
    </div>
    
    <div class="section-header"><div class="section-title">👥 العملاء</div><div class="section-count" id="clientCount"><?php echo $totalPartners; ?> عميل</div></div>
    
    <div class="partners-list" id="partnersList">
        <?php if (empty($partners)): ?>
        <div id="emptyState" style="text-align:center;padding:30px;color:var(--text-secondary);"><div style="font-size:50px;">📭</div><div>لا يوجد عملاء</div><button class="add-partner-btn" style="margin-top:12px;" onclick="openAddModal()">➕ إضافة أول عميل</button></div>
        <?php else: ?>
            <?php foreach ($partners as $i => $p): $color = $avatarColors[$i % count($avatarColors)]; $cardClass = $p['balance'] < 0 ? 'has-debt' : ($p['balance'] > 0 ? 'has-credit' : ''); $balanceClass = $p['balance'] > 0 ? 'positive' : ($p['balance'] < 0 ? 'negative' : 'zero'); ?>
            <div class="partner-card <?php echo $cardClass; ?>" style="animation-delay:<?php echo $i*0.04; ?>s" data-name="<?php echo htmlspecialchars(strtolower($p['name'])); ?>" data-server="1" data-id="<?php echo $p['id']; ?>" data-balance="<?php echo $p['balance']; ?>" onclick="window.location.href='client.php?id=<?php echo $p['id']; ?>'">
                <div class="partner-avatar" style="background:<?php echo $color['bg']; ?>;color:<?php echo $color['text']; ?>;"><?php echo mb_substr($p['name'],0,1); ?></div>
                <div class="partner-info">
                    <div class="partner-name"><?php echo htmlspecialchars($p['name']); ?></div>
                    <div class="partner-meta">
                        <?php if ($p['transaction_count'] > 0): ?><span class="meta-tag count">📊 <?php echo $p['transaction_count']; ?> عملية</span><?php endif; ?>
                        <?php if ($p['last_transaction']): ?><span class="meta-tag date">🕐 <?php echo date('d/m/Y', strtotime($p['last_transaction'])); ?></span><?php endif; ?>
                    </div>
                    <div class="partner-actions" onclick="event.stopPropagation()">
                        <button class="action-icon edit" onclick="openEditModal(<?php echo $p['id']; ?>)">✏️</button>
                        <button class="action-icon delete" onclick="confirmDelete(<?php echo $p['id']; ?>)">🗑️</button>
                    </div>
                </div>
                <div class="partner-balance">
                    <div class="balance-amount <?php echo $balanceClass; ?>"><?php echo number_format(abs($p['balance'])); ?></div>
                    <div class="balance-label"><?php echo $p['balance'] > 0 ? 'له' : ($p['balance'] < 0 ? 'عليه' : '—'); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal إضافة/تعديل -->
<div class="modal-overlay" id="partnerModal" style="display:none;" onclick="closeModal(event)">
    <div class="modal-sheet" onclick="event.stopPropagation()">
        <div class="modal-title" id="modalTitle">👤 إضافة عميل جديد</div>
        <input type="hidden" id="partnerId"><input type="hidden" id="formAction" value="add">
        <div class="form-group"><label class="form-label">الاسم *</label><input type="text" class="form-input" id="partnerName" placeholder="أدخل اسم العميل" autocomplete="off"></div>
        <div class="form-group"><label class="form-label">رقم الهاتف</label><input type="tel" class="form-input" id="partnerPhone" placeholder="777123456" dir="ltr"></div>
        <div class="form-group"><label class="form-label">نوع العملة</label><select class="form-input" id="partnerType"><option value="local">💵 محلي</option><option value="dollar">💲 دولار</option></select></div>
        <div class="btn-row">
            <button class="btn btn-cancel" onclick="closeModal()">إلغاء</button>
            <button class="btn btn-save" id="saveBtn" onclick="savePartner()">💾 حفظ</button>
        </div>
    </div>
</div>

<script src="js/db.js"></script>
<script>
var statusRibbon = document.getElementById('statusRibbon'), syncBar = document.getElementById('syncBar');
var avatarColors = [['#fff3e0','#e65100'],['#e8f5e9','#1b5e20'],['#e3f2fd','#0d47a1'],['#f3e5f5','#4a148c'],['#fffde7','#f57f17'],['#fce4ec','#b71c1c'],['#e0f7fa','#004d40'],['#efebe9','#3e2723'],['#ede7f6','#311b92'],['#fbe9e7','#bf360c']];

function getLocalPartners() { try { return JSON.parse(localStorage.getItem('offline_partners') || '[]'); } catch(e) { return []; } }
function saveLocalPartner(data) { var list = getLocalPartners(); data.localId = Date.now(); list.push(data); localStorage.setItem('offline_partners', JSON.stringify(list)); updateUI(); }
function clearLocalPartners() { localStorage.removeItem('offline_partners'); updateUI(); }
function updateUI() { var c = getLocalPartners().length; document.getElementById('localCount').textContent = c; syncBar.classList.toggle('show', c > 0); }

function setStatus(m, t) { statusRibbon.className = 'status-ribbon show ' + t; statusRibbon.textContent = m; if (t === 'online') setTimeout(function(){ statusRibbon.classList.remove('show'); }, 3000); }
window.addEventListener('online', function(){ setStatus('🟢 متصل - تحميل أحدث نسخة...', 'online'); setTimeout(function(){ window.location.reload(true); }, 1500); });
window.addEventListener('offline', function(){ setStatus('📡 غير متصل', 'offline'); });
document.addEventListener('DOMContentLoaded', function(){ if(!navigator.onLine) setStatus('📡 غير متصل', 'offline'); loadLocalPartners(); updateUI(); });

function loadLocalPartners() { var list = getLocalPartners(); if (list.length > 0) { list.forEach(function(p){ addPartnerToUI(p, true); }); setStatus('💾 ' + list.length + ' عملاء محليين', 'warning'); } }

function addPartnerToUI(data, isLocal) {
    var emptyState = document.getElementById('emptyState'); if (emptyState) emptyState.remove();
    var list = document.getElementById('partnersList');
    var idx = list.querySelectorAll('.partner-card').length;
    var color = avatarColors[idx % avatarColors.length];
    var card = document.createElement('div');
    card.className = 'partner-card new-card';
    card.setAttribute('data-name', (data.name || '').toLowerCase());
    card.setAttribute('data-balance', data.balance || 0);
    if (isLocal) { card.setAttribute('data-local', '1'); }
    else { card.setAttribute('data-server', '1'); card.setAttribute('data-id', data.id || ''); }
    if (data.id && !isLocal) { card.onclick = function(){ window.location.href = 'client.php?id=' + data.id; }; }
    card.innerHTML = '<div class="partner-avatar" style="background:' + color[0] + ';color:' + color[1] + ';">' + (data.name || '?')[0] + '</div>' +
        '<div class="partner-info"><div class="partner-name">' + (data.name || '') + (isLocal ? ' <span class="meta-tag local">محلي</span>' : '') + '</div>' +
        '<div class="partner-meta">' + (data.phone ? '<span class="meta-tag count">📱 ' + (data.phone || '') + '</span>' : '<span class="meta-tag count">📊 0 عملية</span>') + '</div>' +
        (data.id && !isLocal ? '<div class="partner-actions" onclick="event.stopPropagation()"><button class="action-icon edit" onclick="openEditModal(' + data.id + ')">✏️</button><button class="action-icon delete" onclick="confirmDelete(' + data.id + ')">🗑️</button></div>' : '') +
        '</div><div class="partner-balance"><div class="balance-amount zero">0</div><div class="balance-label">—</div></div>';
    var firstServer = list.querySelector('[data-server]');
    if (firstServer) { list.insertBefore(card, firstServer); } else { list.appendChild(card); }
    updateClientCount();
    updateStats(1, 0, 0);
    setTimeout(function(){ card.classList.remove('new-card'); }, 600);
}

function updateStats(clients, debit, credit) {
    var tp = document.getElementById('totalPartnersVal'); if (tp) tp.textContent = parseInt(tp.textContent) + clients;
    var td = document.getElementById('debtVal'); if (td && debit > 0) td.textContent = parseInt(td.textContent) + debit;
    var tc = document.getElementById('creditVal'); if (tc && credit > 0) tc.textContent = parseInt(tc.textContent) + credit;
}
function updateClientCount() {
    var c = document.querySelectorAll('.partner-card').length;
    var cc = document.getElementById('clientCount'); if (cc) cc.textContent = c + ' عميل';
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = '👤 إضافة عميل جديد';
    document.getElementById('formAction').value = 'add'; document.getElementById('partnerId').value = '';
    document.getElementById('partnerName').value = ''; document.getElementById('partnerPhone').value = ''; document.getElementById('partnerType').value = 'local';
    document.getElementById('partnerModal').style.display = 'block'; document.body.style.overflow = 'hidden'; document.body.style.position = 'fixed'; document.body.style.width = '100%';
    setTimeout(function(){ document.getElementById('partnerName').focus(); }, 350);
}
function closeModal(e) { if (e && e.target !== document.getElementById('partnerModal')) return; document.getElementById('partnerModal').style.display = 'none'; document.body.style.overflow = ''; document.body.style.position = ''; document.body.style.width = ''; }

async function savePartner() {
    var name = document.getElementById('partnerName').value.trim(), phone = document.getElementById('partnerPhone').value.trim(), type = document.getElementById('partnerType').value;
    if (!name) { showToast('الرجاء إدخال اسم العميل', 'error'); return; }
    var data = { name: name, phone: phone, type: type, balance: 0, transaction_count: 0 };
    var action = document.getElementById('formAction').value;
    var btn = document.getElementById('saveBtn'); btn.textContent = '⏳ جاري...'; btn.disabled = true;
    
    if (!navigator.onLine && action === 'add') { saveLocalPartner(data); showToast('✅ تم حفظ العميل محلياً', 'success'); closeModal(); addPartnerToUI(data, true); btn.textContent = '💾 حفظ'; btn.disabled = false; return; }
    
    var fd = new FormData(); fd.append('action', action); fd.append('name', name); fd.append('phone', phone); fd.append('type', type);
    if (action === 'edit') fd.append('id', document.getElementById('partnerId').value);
    
    try {
        var r = await fetch(window.location.href, { method: 'POST', body: fd }); var d = await r.json();
        if (d.success) {
            showToast(d.message, 'success'); closeModal();
            if (action === 'add') { data.id = d.id; addPartnerToUI(data, false); }
            else { updatePartnerInUI(d.id, d); }
        } else { showToast(d.message, 'error'); }
    } catch(e) {
        if (action === 'add') { saveLocalPartner(data); showToast('⚠️ فشل الاتصال - تم الحفظ محلياً', 'warning'); closeModal(); addPartnerToUI(data, true); }
        else { showToast('❌ خطأ في الاتصال', 'error'); }
    }
    btn.textContent = '💾 حفظ'; btn.disabled = false;
}

function updatePartnerInUI(id, data) {
    var card = document.querySelector('[data-id="' + id + '"]');
    if (card) { var nameEl = card.querySelector('.partner-name'); if (nameEl) nameEl.textContent = data.name; var avatarEl = card.querySelector('.partner-avatar'); if (avatarEl) avatarEl.textContent = data.name[0]; }
}

async function syncNow() {
    if (!navigator.onLine) { showToast('⚠️ لا يوجد اتصال', 'error'); return; }
    var list = getLocalPartners(); if (list.length === 0) { showToast('✅ لا توجد بيانات', 'success'); return; }
    var btn = document.querySelector('.sync-btn'); btn.textContent = '⏳ جاري...'; btn.disabled = true;
    try {
        var fd = new FormData(); fd.append('action', 'sync'); fd.append('data', JSON.stringify(list));
        var r = await fetch(window.location.href, { method: 'POST', body: fd }); var d = await r.json();
        if (d.success) { clearLocalPartners(); showToast('✅ ' + d.message, 'success'); setTimeout(function(){ window.location.reload(true); }, 1000); }
        else { showToast('❌ ' + d.message, 'error'); btn.textContent = '🔄 مزامنة'; btn.disabled = false; }
    } catch(e) { showToast('❌ فشلت المزامنة', 'error'); btn.textContent = '🔄 مزامنة'; btn.disabled = false; }
}

function openEditModal(id) {
    document.getElementById('modalTitle').textContent = '✏️ تعديل عميل'; document.getElementById('formAction').value = 'edit'; document.getElementById('partnerId').value = id;
    document.getElementById('partnerName').value = 'جاري التحميل...'; document.getElementById('partnerPhone').value = '';
    document.getElementById('partnerModal').style.display = 'block'; document.body.style.overflow = 'hidden'; document.body.style.position = 'fixed'; document.body.style.width = '100%';
    var fd = new FormData(); fd.append('action','get'); fd.append('id',id);
    fetch(window.location.href, {method:'POST',body:fd}).then(function(r){return r.json()}).then(function(d){
        if(d.success && d.data){ document.getElementById('partnerName').value = d.data.name||''; document.getElementById('partnerPhone').value = d.data.phone||''; document.getElementById('partnerType').value = d.data.type||'local'; }
        else { showToast('العميل غير موجود', 'error'); closeModal(); }
    }).catch(function(){ showToast('خطأ في التحميل', 'error'); closeModal(); });
}

function confirmDelete(id) {
    var card = document.querySelector('[data-id="' + id + '"]');
    var partnerName = card ? (card.querySelector('.partner-name')?.textContent || 'العميل') : 'العميل';
    var msg = '⚠️ تحذير!\n\nسيتم حذف "' + partnerName + '" وجميع المعاملات المرتبطة به.\n\nلا يمكن التراجع عن هذا الإجراء.\n\nهل أنت متأكد؟';
    if (confirm(msg)) {
        if (confirm('تأكيد نهائي: هل تريد حذف ' + partnerName + ' وجميع معاملاته؟')) {
            var fd = new FormData(); fd.append('action', 'delete'); fd.append('id', id);
            fetch(window.location.href, { method: 'POST', body: fd }).then(function(r){ return r.json(); }).then(function(d){
                showToast(d.message, d.success ? 'success' : 'error');
                if (d.success && card) { card.style.transform = 'scale(0.8)'; card.style.opacity = '0'; card.style.transition = 'all 0.3s ease'; setTimeout(function(){ card.remove(); updateClientCount(); updateStats(-1, 0, 0); }, 300); }
            }).catch(function(){ showToast('❌ خطأ في الاتصال', 'error'); });
        }
    }
}

function filterPartners() { var q = document.getElementById('searchInput').value.toLowerCase(); document.querySelectorAll('.partner-card').forEach(function(c){ var n = (c.querySelector('.partner-name')?.textContent || '').toLowerCase(); c.style.display = n.includes(q) ? '' : 'none'; }); }
function showToast(m, t) { var x = document.querySelector('.toast'); if (x) x.remove(); var d = document.createElement('div'); d.className = 'toast ' + t; d.textContent = m; document.body.appendChild(d); setTimeout(function(){ d.remove(); }, 2800); }
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeModal(); });
document.getElementById('partnerName')?.addEventListener('keypress', function(e){ if(e.key==='Enter') document.getElementById('partnerPhone').focus(); });
document.getElementById('partnerPhone')?.addEventListener('keypress', function(e){ if(e.key==='Enter') savePartner(); });

// ========== فحص الاتصال وتحديث الصفحات تلقائياً ==========
// إذا كان هناك إنترنت، قم بتحميل أحدث نسخة من السيرفر
if (navigator.onLine) {
    var isFromCache = false;
    
    if (window.performance && window.performance.navigation) {
        if (window.performance.navigation.type === 2) {
            isFromCache = true;
        }
    }
    
    var lastLoad = localStorage.getItem('last_page_load_' + window.location.pathname);
    var now = Date.now();
    var needsRefresh = false;
    
    if (lastLoad) {
        var diff = now - parseInt(lastLoad);
        if (diff > 60000) {
            needsRefresh = true;
        }
    } else {
        needsRefresh = true;
    }
    
    localStorage.setItem('last_page_load_' + window.location.pathname, now);
    
    if (isFromCache || needsRefresh) {
        console.log('🔄 تحميل أحدث نسخة من السيرفر...');
        window.location.reload(true);
    }
}

// عند عودة الاتصال، أعد تحميل الصفحة
window.addEventListener('online', function() {
    console.log('🟢 تم استعادة الاتصال - تحميل أحدث نسخة...');
    setTimeout(function() {
        window.location.reload(true);
    }, 1000);
});

// عند ظهور الصفحة (العودة من صفحة أخرى)
document.addEventListener('visibilitychange', function() {
    if (!document.hidden && navigator.onLine) {
        var lastLoad = localStorage.getItem('last_page_load_' + window.location.pathname);
        if (lastLoad && (Date.now() - parseInt(lastLoad)) > 30000) {
            console.log('🔄 تحديث الصفحة بعد العودة...');
            window.location.reload(true);
        }
    }
});
</script>

<?php require_once 'includes/footer_nav.php'; ?>