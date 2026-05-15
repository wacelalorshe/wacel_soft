<?php
define('APP_RUNNING', true);
require_once 'config.php';
// في header.php أو في كل صفحة
header('Service-Worker-Allowed: /');
require_once 'includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header('Location: index.php'); exit; }

// جلب العميل
$userId = $_SESSION['user_id'] ?? 0;
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

if ($isAdmin) {
    $stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
}
$client = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$client) { header('Location: index.php'); exit; }

// معالجة العمليات (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add') {
            $amount = abs((float)$_POST['amount']);
            if ($amount <= 0) { echo json_encode(['success' => false, 'message' => 'الرجاء إدخال المبلغ']); exit; }
            
            $stmt = $pdo->prepare("INSERT INTO transactions (partner_id, date, details, amount, currency_type, transaction_type, user_id) VALUES (?, ?, ?, ?, 'local', ?, ?)");
            $stmt->execute([$id, $_POST['date'] ?? date('Y-m-d'), trim($_POST['details'] ?? ''), $amount, $_POST['type'] ?? 'debit', $userId]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'تمت الإضافة بنجاح ✓', 
                'id' => $pdo->lastInsertId(),
                'data' => [
                    'id' => $pdo->lastInsertId(),
                    'amount' => $amount,
                    'details' => trim($_POST['details'] ?? ''),
                    'date' => $_POST['date'],
                    'type' => $_POST['type']
                ]
            ]);
            exit;
        }
        
        if ($action === 'delete') {
            $txId = (int)$_POST['tid'];
            $stmt = $pdo->prepare("DELETE FROM transactions WHERE id=? AND partner_id=?");
            $stmt->execute([$txId, $id]);
            echo json_encode(['success' => true, 'message' => 'تم الحذف ✓']);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
    }
    exit;
}

// جلب المعاملات للعرض الأولي
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE partner_id = ? AND currency_type = 'local' ORDER BY date DESC, id DESC");
$stmt->execute([$id]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalDebit = 0; $totalCredit = 0;
foreach ($transactions as $t) {
    $t['transaction_type'] === 'debit' ? $totalDebit += $t['amount'] : $totalCredit += $t['amount'];
}
$balance = $totalCredit - $totalDebit;
?>

<?php require_once 'includes/header.php'; ?>

<style>
    .main-content { padding: 16px; padding-bottom: 100px; }
    .balance-card { background: var(--surface); border-radius: var(--radius-lg); padding: 5px; text-align: center; border: 1px solid var(--border); margin-bottom: 12px; }
    .balance-big { font-size: 18px; font-weight: 800; margin: 0px 0; transition: all 0.3s ease; }
    .balance-big.positive { color: var(--success); } .balance-big.negative { color: var(--danger); }
    .summary-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-bottom: 12px; }
    .summary-item { background: var(--surface); border-radius: var(--radius); padding: 12px; text-align: center; border: 1px solid var(--border); }
    .summary-item .val { font-size: 18px; font-weight: 700; } .summary-item .lbl { font-size: 10px; color: var(--text-secondary); }
    .val.red { color: var(--danger); } .val.green { color: var(--success); }
    
    .add-tx-btn { width: 100%; padding: 14px; background: var(--primary); color: #fff; border: none; border-radius: var(--radius); font-weight: 700; margin-bottom: 14px; cursor: pointer; }
    
    .transaction-item { display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--surface); border-radius: 14px; margin-bottom: 6px; border: 1px solid var(--border); animation: slideIn 0.3s ease-out; }
    @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

    .tx-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .tx-icon.debit { background: var(--danger-bg); color: var(--danger); }
    .tx-icon.credit { background: var(--success-bg); color: var(--success); }
    .tx-info { flex: 1; }
    .tx-amount { font-size: 16px; font-weight: 700; }
    .tx-amount.debit { color: var(--danger); } .tx-amount.credit { color: var(--success); }

    /* ========== تم تعديل المودال ليطابق نمط index.php ========== */
    .modal-overlay { 
        position: fixed; 
        top: 0; 
        left: 0; 
        width: 100%; 
        height: 100%; 
        background: rgba(0,0,0,0.6); 
        backdrop-filter: blur(6px); 
        -webkit-backdrop-filter: blur(6px); 
        z-index: 200; 
        display: none; 
        overflow-y: auto; 
        animation: fadeIn 0.2s ease; 
    }
    .modal-sheet { 
        background: var(--surface); 
        margin: 60px auto 20px auto; 
        width: 92%; 
        max-width: 500px; 
        border-radius: 18px; 
        padding: 22px 18px 24px; 
        box-shadow: 0 15px 50px rgba(0,0,0,0.5); 
        animation: modalIn 0.3s ease; 
    }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes modalIn { from { transform: translateY(-40px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    
    .modal-title { 
        font-size: 18px; 
        font-weight: 700; 
        text-align: center; 
        margin-bottom: 18px; 
        color: var(--text); 
    }
    
    .fi { 
        width: 100%; 
        padding: 13px 15px; 
        background: var(--surface-light); 
        border: 1.5px solid var(--border); 
        border-radius: 12px; 
        margin-bottom: 14px; 
        color: var(--text); 
        font-size: 14px; 
        font-family: inherit; 
        outline: none; 
        transition: all 0.3s; 
    }
    .fi:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(108,92,231,0.1); }
    
    .btn-save { 
        background: var(--primary); 
        color: #fff; 
        width: 100%; 
        padding: 14px; 
        border-radius: 12px; 
        border: none; 
        font-weight: 700; 
        font-size: 14px; 
        cursor: pointer; 
        font-family: inherit; 
        transition: all 0.3s; 
    }
    .btn-save:active { transform: scale(0.96); }
    .btn-save:disabled { opacity: 0.5; cursor: not-allowed; }
    
    .modal-cancel { 
        display: block; 
        width: 100%; 
        padding: 14px; 
        margin-top: 14px; 
        background: var(--surface-light); 
        border: 1px solid var(--border); 
        border-radius: 12px; 
        color: var(--text-secondary); 
        font-size: 14px; 
        font-weight: 700; 
        cursor: pointer; 
        text-align: center; 
        font-family: inherit; 
        transition: all 0.3s; 
    }
    .modal-cancel:active { transform: scale(0.96); background: rgba(255,107,107,0.1); color: #ff6b6b; border-color: #ff6b6b; }
    
    .type-toggle { 
        display: flex; 
        gap: 8px; 
        margin-bottom: 14px; 
    }
    .type-btn { 
        flex: 1; 
        padding: 12px; 
        border-radius: 12px; 
        border: 2px solid var(--border); 
        background: transparent; 
        color: var(--text-secondary); 
        font-weight: 700; 
        font-size: 14px; 
        cursor: pointer; 
        font-family: inherit; 
        transition: all 0.3s; 
    }
    .type-btn:active { transform: scale(0.95); }
    .type-btn.active.debit { background: #ff6b6b; border-color: #ff6b6b; color: #fff; }
    .type-btn.active.credit { background: #00d68f; border-color: #00d68f; color: #fff; }
    
    .toast { position: fixed; bottom: 110px; left: 50%; transform: translateX(-50%); padding: 12px 22px; border-radius: 25px; color: white; font-weight: 600; font-size: 13px; z-index: 300; animation: toastIn 0.3s ease, toastOut 0.3s ease 2.2s forwards; white-space: nowrap; box-shadow: 0 8px 24px rgba(0,0,0,0.3); }
    .toast.success { background: var(--success); } .toast.error { background: var(--danger); } .toast.warning { background: #ffa502; }
    @keyframes toastIn { from { opacity: 0; transform: translateX(-50%) translateY(10px); } to { opacity: 1; transform: translateX(-50%) translateY(0); } }
    @keyframes toastOut { to { opacity: 0; transform: translateX(-50%) translateY(-8px); } }
</style>

<div class="main-content">
     
    
    <div class="summary-row">
        <div class="summary-item"><div id="sumDebit" class="val red"><?php echo number_format($totalDebit); ?></div><div class="lbl">عليه</div></div>
        <div class="summary-item"><div id="sumCredit" class="val green"><?php echo number_format($totalCredit); ?></div><div class="lbl">له</div></div>
        <div class="summary-item"><div id="sumCount" class="val"><?php echo count($transactions); ?></div><div class="lbl">عملية</div></div>
    </div>
    
    <button class="add-tx-btn" onclick="openAddTxModal()">➕ إضافة معاملة جديدة</button>
    
    <div id="txList">
        <?php foreach ($transactions as $t): ?>
            <div class="transaction-item" id="tx-<?php echo $t['id']; ?>">
                <div class="tx-icon <?php echo $t['transaction_type']; ?>"><?php echo $t['transaction_type'] === 'debit' ? '📤' : '📥'; ?></div>
                <div class="tx-info">
                    <div class="tx-details"><?php echo htmlspecialchars($t['details'] ?: 'بدون تفاصيل'); ?></div>
                    <div class="tx-date"><?php echo date('d/m/Y', strtotime($t['date'])); ?></div>
                </div>
                <div class="tx-amount <?php echo $t['transaction_type']; ?>"><?php echo ($t['transaction_type'] === 'debit' ? '-' : '+') . number_format($t['amount']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ==================== Modal إضافة معاملة ==================== -->
<div class="modal-overlay" id="addTxModal" onclick="closeAddTxModal(event)">
    <div class="modal-sheet" onclick="event.stopPropagation()">
        <div class="modal-title">💰 إضافة عملية جديدة</div>
        
        <div class="type-toggle">
            <button id="btnDebit" class="type-btn active debit" onclick="setTxType('debit')">📤 عليه</button>
            <button id="btnCredit" class="type-btn credit" onclick="setTxType('credit')">📥 له</button>
        </div>
        
        <input type="text" id="txDetails" class="fi" placeholder="التفاصيل (اختياري)">
        <input type="number" id="txAmount" class="fi" placeholder="المبلغ *" style="font-size:20px; font-weight:700; text-align:center;" step="1" min="1">
        <input type="date" id="txDate" class="fi" value="<?php echo date('Y-m-d'); ?>">
        
        <button class="btn-save" id="saveBtn" onclick="saveNewTransaction()">💾 حفظ العملية</button>
        <button class="modal-cancel" onclick="closeAddTxModal()">✕ إلغاء</button>
    </div>
</div>

<div class="balance-card">
        <div style="font-size:12px;color:var(--text-secondary);">الرصيد الحالي</div>
        <div id="mainBalance" class="balance-big <?php echo $balance >= 0 ? 'positive' : 'negative'; ?>"><?php echo number_format(abs($balance)); ?></div>
        <div id="balanceStatus" style="font-size:12px;color:var(--text-secondary);"><?php echo $balance >= 0 ? 'له' : 'عليه'; ?></div>
    </div>
<script>
let currentType = 'debit';
let totalDebit = <?php echo $totalDebit; ?>;
let totalCredit = <?php echo $totalCredit; ?>;
let count = <?php echo count($transactions); ?>;

function isWebView() {
    var ua = navigator.userAgent;
    return /wv|WebView|Android.*Version\/.*Chrome/.test(ua);
}

function setTxType(type) {
    currentType = type;
    document.getElementById('btnDebit').className = 'type-btn debit' + (type === 'debit' ? ' active' : '');
    document.getElementById('btnCredit').className = 'type-btn credit' + (type === 'credit' ? ' active' : '');
}

function openAddTxModal() {
    document.getElementById('addTxModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
    document.body.style.position = 'fixed';
    document.body.style.width = '100%';
    setTimeout(function(){ document.getElementById('txDetails').focus(); }, 350);
}

function closeAddTxModal(e) {
    if (e && e.target !== document.getElementById('addTxModal')) return;
    document.getElementById('addTxModal').style.display = 'none';
    document.body.style.overflow = '';
    document.body.style.position = '';
    document.body.style.width = '';
}

// ========== دعم Offline ==========
var currentClientId = <?php echo $id; ?>;
var currentClientName = '<?php echo addslashes($client['name']); ?>';

async function saveNewTransaction() {
    const amount = parseFloat(document.getElementById('txAmount').value);
    const details = document.getElementById('txDetails').value.trim();
    const date = document.getElementById('txDate').value;
    const saveBtn = document.getElementById('saveBtn');

    if (!amount || amount <= 0) return alert('أدخل المبلغ');

    saveBtn.disabled = true;
    saveBtn.innerText = '⏳ جاري الحفظ...';

    // حفظ محلي إذا كان Offline
    if (!navigator.onLine) {
        try {
            await localDB.addTransaction({
                partner_id: currentClientId,
                amount: amount,
                details: details,
                date: date,
                transaction_type: currentType,
                currency_type: 'local'
            });
            
            // تحديث الواجهة مباشرة
            var txData = {
                id: Date.now(),
                amount: amount,
                details: details,
                date: date,
                type: currentType
            };
            addTxToDOM(txData);
            updateTotals(amount, currentType);
            closeAddTxModal();
            document.getElementById('txAmount').value = '';
            document.getElementById('txDetails').value = '';
            showToast('✅ تم الحفظ محلياً - سيتزامن لاحقاً', 'success');
        } catch (e) {
            alert('خطأ في الحفظ المحلي');
        }
        saveBtn.disabled = false;
        saveBtn.innerText = '💾 حفظ العملية';
        return;
    }

    // حفظ على السيرفر إذا كان Online
    const fd = new FormData();
    fd.append('action', 'add');
    fd.append('amount', amount);
    fd.append('details', details);
    fd.append('date', date);
    fd.append('type', currentType);

    try {
        const response = await fetch(window.location.href, { method: 'POST', body: fd });
        const result = await response.json();

        if (result.success) {
            addTxToDOM(result.data);
            updateTotals(amount, currentType);
            closeAddTxModal();
            document.getElementById('txAmount').value = '';
            document.getElementById('txDetails').value = '';
            showToast('✅ تم الحفظ بنجاح', 'success');
        }
    } catch (e) {
        // فشل الاتصال - حفظ محلي
        try {
            await localDB.addTransaction({
                partner_id: currentClientId,
                amount: amount,
                details: details,
                date: date,
                transaction_type: currentType,
                currency_type: 'local'
            });
            var txData = {
                id: Date.now(),
                amount: amount,
                details: details,
                date: date,
                type: currentType
            };
            addTxToDOM(txData);
            updateTotals(amount, currentType);
            closeAddTxModal();
            document.getElementById('txAmount').value = '';
            document.getElementById('txDetails').value = '';
            showToast('⚠️ فشل الاتصال - تم الحفظ محلياً', 'warning');
        } catch (e2) {
            alert('خطأ في الحفظ');
        }
    }
    saveBtn.disabled = false;
    saveBtn.innerText = '💾 حفظ العملية';
}

function addTxToDOM(tx) {
    const list = document.getElementById('txList');
    const div = document.createElement('div');
    div.className = 'transaction-item';
    div.id = 'tx-' + tx.id;
    
    const formattedDate = tx.date.split('-').reverse().join('/');
    const icon = tx.type === 'debit' ? '📤' : '📥';
    const sign = tx.type === 'debit' ? '-' : '+';
    
    div.innerHTML = `
        <div class="tx-icon ${tx.type}">${icon}</div>
        <div class="tx-info">
            <div class="tx-details">${tx.details || 'بدون تفاصيل'}</div>
            <div class="tx-date">${formattedDate}</div>
        </div>
        <div class="tx-amount ${tx.type}">${sign}${Number(tx.amount).toLocaleString()}</div>
    `;
    list.prepend(div);
}

function updateTotals(amount, type) {
    if (type === 'debit') totalDebit += amount;
    else totalCredit += amount;
    count++;

    const balance = totalCredit - totalDebit;
    
    document.getElementById('sumDebit').innerText = totalDebit.toLocaleString();
    document.getElementById('sumCredit').innerText = totalCredit.toLocaleString();
    document.getElementById('sumCount').innerText = count;
    
    const balanceEl = document.getElementById('mainBalance');
    balanceEl.innerText = Math.abs(balance).toLocaleString();
    balanceEl.className = 'balance-big ' + (balance >= 0 ? 'positive' : 'negative');
    document.getElementById('balanceStatus').innerText = balance >= 0 ? 'له' : 'عليه';
}

function showToast(msg, type) {
    var existing = document.querySelector('.toast');
    if (existing) existing.remove();
    
    const toast = document.createElement('div');
    toast.className = 'toast ' + (type || 'success');
    toast.innerText = msg;
    document.body.appendChild(toast);
    setTimeout(function() { toast.remove(); }, 2500);
}

// تحميل المعاملات المحلية عند Offline
async function loadLocalTransactions() {
    if (navigator.onLine) return;
    try {
        var transactions = await localDB.getTransactions(currentClientId);
        if (transactions && transactions.length > 0) {
            // إضافة المعاملات المحلية للواجهة
            transactions.forEach(function(t) {
                var txData = {
                    id: t.localId || t.id,
                    amount: parseFloat(t.amount),
                    details: t.details || '',
                    date: t.date,
                    type: t.transaction_type
                };
                
                // فحص إن كانت موجودة مسبقاً
                var existingEl = document.getElementById('tx-' + txData.id);
                if (!existingEl) {
                    addTxToDOM(txData);
                }
            });
            
            // إعادة حساب الإجماليات
            recalculateTotals();
        }
    } catch (e) {
        console.log('لا توجد معاملات محلية');
    }
}

// إعادة حساب الإجماليات
function recalculateTotals() {
    var allTx = document.querySelectorAll('#txList .transaction-item');
    var debit = 0, credit = 0;
    allTx.forEach(function(tx) {
        var amountEl = tx.querySelector('.tx-amount');
        if (amountEl) {
            var text = amountEl.textContent.replace(/[^0-9]/g, '');
            var amount = parseInt(text) || 0;
            if (amountEl.classList.contains('debit')) {
                debit += amount;
            } else if (amountEl.classList.contains('credit')) {
                credit += amount;
            }
        }
    });
    
    totalDebit = debit;
    totalCredit = credit;
    count = allTx.length;
    var balance = credit - debit;
    
    document.getElementById('sumDebit').innerText = debit.toLocaleString();
    document.getElementById('sumCredit').innerText = credit.toLocaleString();
    document.getElementById('sumCount').innerText = count;
    
    var balanceEl = document.getElementById('mainBalance');
    balanceEl.innerText = Math.abs(balance).toLocaleString();
    balanceEl.className = 'balance-big ' + (balance >= 0 ? 'positive' : 'negative');
    document.getElementById('balanceStatus').innerText = balance >= 0 ? 'له' : 'عليه';
}

// تشغيل التحميل المحلي
document.addEventListener('DOMContentLoaded', function() {
    loadLocalTransactions();
});

// تحديث عند العودة للاتصال
window.addEventListener('online', function() {
    showToast('🟢 تم استعادة الاتصال - جاري المزامنة...', 'warning');
    if (typeof syncManager !== 'undefined') {
        syncManager.sync().then(function() {
            setTimeout(function() { location.reload(); }, 1500);
        });
    }
});

// ========== تحميل البيانات من IndexedDB عند Offline ==========
async function loadOfflineClientData() {
    var clientId = <?php echo $id; ?>;
    
    try {
        // محاولة تحميل العميل من IndexedDB
        var client = await localDB.getPartnerById(clientId);
        var transactions = await localDB.getTransactions(clientId);
        
        if (client) {
            // تحديث واجهة العميل
            document.querySelector('.page-title').textContent = '👤 ' + (client.name || 'العميل');
            document.querySelector('.page-subtitle').textContent = 'المعاملات والرصيد (وضع Offline)';
            
            // تحديث الرصيد
            var balance = 0;
            var totalDebitOff = 0;
            var totalCreditOff = 0;
            
            transactions.forEach(function(t) {
                if (t.transaction_type === 'debit') {
                    totalDebitOff += parseFloat(t.amount);
                    balance -= parseFloat(t.amount);
                } else {
                    totalCreditOff += parseFloat(t.amount);
                    balance += parseFloat(t.amount);
                }
            });
            
            document.getElementById('mainBalance').textContent = Math.abs(balance).toLocaleString('ar-SA');
            document.getElementById('mainBalance').className = 'balance-big ' + (balance >= 0 ? 'positive' : 'negative');
            document.getElementById('balanceStatus').textContent = balance >= 0 ? 'له' : 'عليه';
            
            document.getElementById('sumDebit').textContent = totalDebitOff.toLocaleString('ar-SA');
            document.getElementById('sumCredit').textContent = totalCreditOff.toLocaleString('ar-SA');
            document.getElementById('sumCount').textContent = transactions.length;
            
            // عرض المعاملات المحلية
            var txList = document.getElementById('txList');
            txList.innerHTML = '';
            
            if (transactions.length === 0) {
                txList.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-secondary);">لا توجد معاملات مخزنة محلياً</div>';
            } else {
                transactions.sort(function(a, b) {
                    return new Date(b.date) - new Date(a.date);
                }).forEach(function(t) {
                    var isDebit = t.transaction_type === 'debit';
                    var div = document.createElement('div');
                    div.className = 'transaction-item';
                    div.id = 'tx-' + (t.localId || t.id);
                    div.innerHTML = 
                        '<div class="tx-icon ' + t.transaction_type + '">' + (isDebit ? '📤' : '📥') + '</div>' +
                        '<div class="tx-info">' +
                            '<div class="tx-details">' + (t.details || 'بدون تفاصيل') + ' 💾 محلي</div>' +
                            '<div class="tx-date">' + t.date + '</div>' +
                        '</div>' +
                        '<div class="tx-amount ' + t.transaction_type + '">' + (isDebit ? '-' : '+') + parseFloat(t.amount).toLocaleString('ar-SA') + '</div>';
                    txList.appendChild(div);
                });
            }
            
            // إظهار شريط Offline
            var statusBar = document.createElement('div');
            statusBar.style.cssText = 'background:rgba(255,165,2,0.1);border:1px solid rgba(255,165,2,0.3);color:#ffa502;padding:8px;text-align:center;border-radius:10px;margin-bottom:10px;font-size:12px;font-weight:700;';
            statusBar.textContent = '📡 وضع Offline - بيانات محلية';
            document.querySelector('.main-content').insertBefore(statusBar, document.querySelector('.main-content').firstChild);
        }
    } catch (e) {
        console.log('لا توجد بيانات محلية للعميل:', e);
    }
}

// تحميل البيانات المحلية عند Offline
if (!navigator.onLine) {
    document.addEventListener('DOMContentLoaded', function() {
        loadOfflineClientData();
    });
}

// إغلاق المودال عند الضغط على Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeAddTxModal(); }
});
</script>
<?php require_once 'includes/footer_nav.php'; ?>