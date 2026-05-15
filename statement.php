<?php
define('APP_RUNNING', true);
require_once 'config.php';
header('Service-Worker-Allowed: /');
require_once 'includes/functions.php';

// ========== إعدادات المتجر ==========
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) { $settings = []; }

$storeName    = $settings['store_name']           ?? 'وسيل سوفت';
$storeIcon    = $settings['store_icon']           ?? '';
$storeLogo    = $settings['store_logo']           ?? '';
$storePhone   = $settings['store_phone']          ?? '735981222';
$storeAddress = $settings['store_address']        ?? 'تعز دمنة خدير';
$accentColor  = $settings['accent_color']         ?? '#d32f2f';
$currencyName = $settings['store_currency_local'] ?? 'ريال يمني';

// ========== بيانات العميل ==========
$client_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$client_id) { header('Location: index.php'); exit; }

$userId  = $_SESSION['user_id'] ?? 0;
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

if ($isAdmin) {
    $stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
    $stmt->execute([$client_id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ? AND user_id = ?");
    $stmt->execute([$client_id, $userId]);
}
$client = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$client) { header('Location: index.php'); exit; }

// ========== فلترة ==========
$filterFrom = $_GET['from'] ?? '';
$filterTo   = $_GET['to']   ?? '';

$sql = "SELECT * FROM transactions WHERE partner_id = ? AND currency_type = 'local'";
$params = [$client_id];
if ($filterFrom) { $sql .= " AND date >= ?"; $params[] = $filterFrom; }
if ($filterTo)   { $sql .= " AND date <= ?"; $params[] = $filterTo; }
if (!$isAdmin)   { $sql .= " AND user_id = ?"; $params[] = $userId; }
$sql .= " ORDER BY date ASC, id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$totalDebit = 0; $totalCredit = 0;
foreach ($transactions as $t) {
    $t['transaction_type'] === 'debit' ? $totalDebit += $t['amount'] : $totalCredit += $t['amount'];
}
$finalBalance = $totalCredit - $totalDebit;
$txCount      = count($transactions);
$todayDate    = date('d-m-Y');
$clientPhone  = $client['phone'] ?? '';

function fmtNum($num) { return number_format((float)$num, 0, '.', ','); }

$pdfBaseName = 'كشف_حساب_' . preg_replace('/\s+/', '_', $client['name']) . '_' . date('Ymd');

// ========== حفظ PDF على السيرفر (AJAX) ==========
if (isset($_POST['save_pdf']) && $_POST['save_pdf'] === '1') {
    header('Content-Type: application/json; charset=utf-8');

    // التحقق من الجلسة
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'غير مصرح']);
        exit;
    }

    $pdfData = $_POST['pdf_data'] ?? '';
    if (empty($pdfData)) {
        echo json_encode(['success' => false, 'message' => 'لا توجد بيانات']);
        exit;
    }

    // التحقق من الحجم (10MB max)
    if (strlen($pdfData) > 14000000) {
        echo json_encode(['success' => false, 'message' => 'الملف كبير جداً']);
        exit;
    }

    $pdfData = str_replace('data:application/pdf;base64,', '', $pdfData);
    $pdfData = str_replace(' ', '+', $pdfData);
    $decoded = base64_decode($pdfData, true);

    if (!$decoded || substr($decoded, 0, 4) !== '%PDF') {
        // بعض مكتبات jsPDF تولد PDFs بدون %PDF header واضح، نتحقق بطريقة أخرى
        if (!$decoded) {
            echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة']);
            exit;
        }
    }

    $pdfDir = __DIR__ . '/statements/';
    if (!is_dir($pdfDir)) mkdir($pdfDir, 0755, true);

    // اسم ملف آمن مع معرف العميل والوقت
    $safeClientName = preg_replace('/[^a-zA-Z0-9\x{0600}-\x{06FF}_-]/u', '_', $client['name']);
    $pdfFileName    = 'stmt_' . $client_id . '_' . time() . '.pdf';
    $pdfPath        = $pdfDir . $pdfFileName;

    if (file_put_contents($pdfPath, $decoded)) {
        echo json_encode([
            'success'  => true,
            'url'      => 'statements/' . $pdfFileName,
            'filename' => $pdfBaseName . '.pdf'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل الحفظ على السيرفر']);
    }
    exit;
}
?>
<?php require_once 'includes/header.php'; ?>

<style>
    :root { --primary-red: <?= htmlspecialchars($accentColor) ?>; }
    .main-content {
        padding: 10px; max-width: 800px; margin: 0 auto;
        background: var(--surface); border-radius: var(--radius-lg); color: var(--text);
    }
    .image-header {
        display: flex; justify-content: space-between; align-items: center;
        padding: 15px 0; border-bottom: 2px solid var(--primary-red); margin-bottom: 15px; gap: 10px;
    }
    .header-right  { text-align: right; flex: 1; }
    .header-center { text-align: center; flex: 1; }
    .header-left   { text-align: left; flex: 1; }
    .header-right .store-name-red  { font-size: 20px; font-weight: 800; color: var(--primary-red); }
    .header-right .phone-number    { font-size: 11px; color: var(--text-secondary); font-weight: 700; }
    .header-left  .title-label     { font-size: 20px; font-weight: 800; color: var(--text); }
    .header-left  .address-label   { font-size: 11px; color: var(--text-secondary); font-weight: 600; }
    .header-center img { height: 70px; max-width: 100%; object-fit: contain; }
    .statement-header-info { text-align: center; margin-bottom: 15px; }
    .statement-header-info h2 { font-size: 18px; color: var(--text); }
    .statement-header-info .meta { font-size: 14px; color: var(--text-secondary); display: flex; justify-content: center; gap: 15px; flex-wrap: wrap; }
    .table-container { border: 2px solid var(--border); border-radius: 8px; overflow: hidden; background: var(--surface); }
    table.data-table { width: 100%; border-collapse: collapse; }
    table.data-table thead th {
        background: var(--primary-red); color: #fff; padding: 11px 8px;
        font-size: 13px; font-weight: 700; border-right: 1px solid rgba(255,255,255,0.2);
    }
    table.data-table thead th:last-child { border-right: none; }
    table.data-table tbody td {
        padding: 11px 8px; text-align: center; border: 1.5px solid var(--border);
        font-size: 13px; color: var(--text);
    }
    table.data-table tbody tr:nth-child(even) td { background: var(--surface-light); }
    .td-la    { color: #16a34a !important; font-weight: 700; }
    .td-alayh { color: #dc2626 !important; font-weight: 700; }
    .td-total { font-weight: 800; }
    table.data-table tbody td:nth-child(2) { text-align: right; }
    .final-result { text-align: center; padding: 25px 0; }
    .final-result .currency { color: var(--primary-red); font-size: 18px; font-weight: 700; }
    .final-result .amount   { font-size: 42px; font-weight: 900; }
    .final-result .label    { font-size: 15px; color: var(--text-secondary); font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 6px; }
    .balance-status { font-weight: 700; padding: 4px 12px; border-radius: 20px; }
    .balance-status.positive { color: #16a34a; background: rgba(22,163,74,0.1); border: 1px solid rgba(22,163,74,0.3); }
    .balance-status.negative { color: #dc2626; background: rgba(220,38,38,0.1); border: 1px solid rgba(220,38,38,0.3); }
    .signatures { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 30px; padding: 0 20px; flex-wrap: wrap; }
    .sig-box    { text-align: center; width: 140px; border-top: 1px dashed var(--border); padding-top: 10px; font-size: 14px; color: var(--text-secondary); }
    .stamp-box  { width: 85px; height: 85px; border: 2px dashed var(--primary-red); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary-red); font-weight: bold; transform: rotate(-10deg); font-size: 12px; text-align: center; line-height: 1.3; }
    .page-num-footer { display: flex; justify-content: space-between; border-top: 1px solid var(--border); margin-top: 20px; padding-top: 5px; font-size: 12px; color: var(--text-secondary); }

    /* ===== Toolbar ===== */
    .toolbar {
        margin-bottom: 15px; display: flex; gap: 8px; flex-wrap: wrap;
        padding: 8px; position: sticky; top: 65px; z-index: 50;
        background: var(--bg); border-radius: var(--radius); border: 1px solid var(--border);
    }
    .btn {
        padding: 10px 14px; border-radius: 10px; text-decoration: none;
        font-size: 11px; cursor: pointer; border: none; font-family: inherit;
        font-weight: 700; white-space: nowrap; transition: all 0.2s;
        display: inline-flex; align-items: center; gap: 5px; color: #fff;
    }
    .btn:active { transform: scale(0.95); opacity: 0.8; }
    .btn-print       { background: #2d3436; }
    .btn-pdf         { background: #d32f2f; }
    .btn-save-server { background: #e67e22; }
    .btn-image       { background: #6c5ce7; }
    .btn-whatsapp    { background: #25D366; }
    .btn-share       { background: #8e44ad; }
    .btn-back        { background: var(--surface-light); color: var(--text); border: 1px solid var(--border); }

    /* Toast */
    .toast {
        position: fixed; bottom: 100px; left: 50%; transform: translateX(-50%);
        padding: 12px 24px; border-radius: 25px; color: #fff; font-weight: 600;
        font-size: 12px; z-index: 9999; box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        animation: toastIn .3s ease; white-space: nowrap;
    }
    .toast.success { background: #00b894; }
    .toast.error   { background: #d32f2f; }
    .toast.warning { background: #f39c12; }
    @keyframes toastIn {
        from { opacity: 0; transform: translateX(-50%) translateY(10px); }
        to   { opacity: 1; transform: translateX(-50%) translateY(0); }
    }

    @media print {
        body { background: #fff !important; }
        .toolbar, .bottom-nav, .no-print { display: none !important; }
    }
    @media (max-width: 500px) {
        .toolbar { gap: 5px; }
        .btn { padding: 8px 10px; font-size: 10px; }
        .signatures { flex-direction: column; align-items: center; gap: 20px; }
        .sig-box { width: 100%; }
    }
</style>

<!-- ===== Toolbar ===== -->
<div class="toolbar no-print">
    <button class="btn btn-print"       onclick="StatementHandler.doPrint()">🖨️ طباعة</button>
    <button class="btn btn-pdf"         onclick="StatementHandler.generateAndDownload('pdf')">📥 PDF</button>
    <button class="btn btn-save-server" onclick="StatementHandler.saveToServer()">💾 حفظ</button>
    <button class="btn btn-image"       onclick="StatementHandler.generateAndDownload('image')">🖼️ صورة</button>
    <?php if ($clientPhone): ?>
    <button class="btn btn-whatsapp"    onclick="StatementHandler.shareWhatsApp()">💬 واتساب</button>
    <?php endif; ?>
    <button class="btn btn-share"       onclick="StatementHandler.shareFile()">📤 مشاركة</button>
    <a      class="btn btn-back"        href="client.php?id=<?= $client_id ?>">← رجوع</a>
</div>

<!-- ===== محتوى الكشف ===== -->
<div class="main-content" id="statementContent">
    <div class="image-header">
        <div class="header-right">
            <span class="store-name-red"><?= htmlspecialchars($storeName) ?></span>
            <div class="phone-number">📱 <?= htmlspecialchars($storePhone) ?></div>
        </div>
        <div class="header-center">
            <?php if ($storeLogo && file_exists($storeLogo)): ?>
                <img src="<?= htmlspecialchars($storeLogo) ?>" alt="logo" crossorigin="anonymous" id="storeLogoImg">
            <?php else: ?>
                <div style="font-size:40px;">💳</div>
            <?php endif; ?>
        </div>
        <div class="header-left">
            <span class="title-label"><?= htmlspecialchars($client['name']) ?></span>
            <div class="address-label">📍 <?= htmlspecialchars($storeAddress) ?></div>
        </div>
    </div>

    <div class="statement-header-info">
        <h2>📄 كشف حساب - <?= htmlspecialchars($client['name']) ?></h2>
        <div class="meta">
            <span>🗓 <?= $todayDate ?></span>
            <?php if ($clientPhone): ?><span>📱 <?= htmlspecialchars($clientPhone) ?></span><?php endif; ?>
            <span>📢 <?= $txCount ?> معاملة</span>
        </div>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>التاريخ</th>
                    <th>التفاصيل</th>
                    <th>له</th>
                    <th>عليه</th>
                    <th>الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($txCount > 0): $running = 0; ?>
                    <?php foreach ($transactions as $t): ?>
                        <?php
                            $isCredit = $t['transaction_type'] === 'credit';
                            $running += $isCredit ? $t['amount'] : -$t['amount'];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars(date('d-m-Y', strtotime($t['date']))) ?></td>
                            <td><?= htmlspecialchars($t['details'] ?? '-') ?></td>
                            <td class="td-la">   <?= $isCredit  ? fmtNum($t['amount']) : '-' ?></td>
                            <td class="td-alayh"><?= !$isCredit ? fmtNum($t['amount']) : '-' ?></td>
                            <td class="td-total"><?= fmtNum($running) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="padding:30px;text-align:center;">لا توجد معاملات</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="final-result">
        <div class="currency"><?= htmlspecialchars($currencyName) ?></div>
        <div class="amount" style="color: <?= $finalBalance >= 0 ? '#16a34a' : '#dc2626' ?>;">
            <?= fmtNum(abs($finalBalance)) ?>
        </div>
        <div class="label">الرصيد الإجمالي
            <span class="balance-status <?= $finalBalance >= 0 ? 'positive' : 'negative' ?>">
                <?= $finalBalance >= 0 ? '✅ له' : '❌ عليه' ?>
            </span>
        </div>
    </div>

    <?php if ($txCount > 0): ?>
    <div class="signatures">
        <div class="sig-box">توقيع العميل</div>
        <div class="stamp-box"><?= htmlspecialchars(mb_substr($storeName, 0, 15)) ?></div>
        <div class="sig-box">توقيع المحاسب</div>
    </div>
    <?php endif; ?>

    <div class="page-num-footer">
        <span><?= htmlspecialchars($storeName) ?></span>
        <span>صفحة 1 / 1</span>
    </div>
</div>

<!-- المكتبات -->
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>

<script>
// =====================================================
//  StatementHandler — متوافق مع Android WebView
//  يدعم: AndroidPrint.saveBase64File / AppBridge.saveImage
//  والمتصفح العادي
// =====================================================
var StatementHandler = (function () {

    // ---- بيانات من PHP ----
    var FILE_BASE    = <?= json_encode($pdfBaseName) ?>;
    var CLIENT_PHONE = <?= json_encode($clientPhone) ?>;
    var STORE_NAME   = <?= json_encode($storeName) ?>;
    var CLIENT_ID    = <?= $client_id ?>;
    var TODAY        = <?= json_encode($todayDate) ?>;
    var BALANCE_TEXT = <?= json_encode(fmtNum(abs($finalBalance)) . ' ' . $currencyName) ?>;

    // ---- حالة داخلية ----
    var _pdfBlob    = null;
    var _pdfDataUrl = null;
    var _imgBlob    = null;
    var _imgDataUrl = null;
    var _savedUrl   = '';

    // =========== Toast ===========
    function toast(msg, type) {
        type = type || 'success';
        var old = document.querySelector('.toast');
        if (old) old.remove();
        var el = document.createElement('div');
        el.className = 'toast ' + type;
        el.textContent = msg;
        document.body.appendChild(el);
        setTimeout(function () { if (el.parentNode) el.remove(); }, 3000);
    }

    // =========== اكتشاف بيئة التشغيل ===========
    function isAndroidWebView() {
        return !!(window.AndroidPrint || window.AppBridge);
    }

    // =========== حفظ ملف (يدعم WebView + متصفح) ===========
    // dataUrl = 'data:application/pdf;base64,...'  أو  'data:image/png;base64,...'
    function saveToDevice(dataUrl, fileName) {
        // Android WebView — AndroidPrint
        if (window.AndroidPrint && typeof window.AndroidPrint.saveBase64File === 'function') {
            try {
                window.AndroidPrint.saveBase64File(dataUrl, fileName);
                return true;
            } catch (e) {}
        }
        // Android WebView — AppBridge
        if (window.AppBridge && typeof window.AppBridge.saveImage === 'function') {
            try {
                window.AppBridge.saveImage(dataUrl, fileName);
                return true;
            } catch (e) {}
        }
        // متصفح عادي — تحميل مباشر
        try {
            var parts = dataUrl.split(',');
            var mime  = parts[0].match(/:(.*?);/)[1];
            var bytes = atob(parts[1]);
            var arr   = new Uint8Array(bytes.length);
            for (var i = 0; i < bytes.length; i++) arr[i] = bytes.charCodeAt(i);
            var blob = new Blob([arr], { type: mime });
            var url  = URL.createObjectURL(blob);
            var a    = document.createElement('a');
            a.href = url; a.download = fileName;
            document.body.appendChild(a); a.click();
            document.body.removeChild(a);
            setTimeout(function () { URL.revokeObjectURL(url); }, 2000);
            return true;
        } catch (e) {
            console.error('saveToDevice error:', e);
            return false;
        }
    }

    // =========== مشاركة ملف عبر Android Intent ===========
    // يستخدم shareText كـ fallback إن لم تتوفر دالة مشاركة ملف
    function shareFileNative(dataUrl, fileName, mimeType) {
        // أفضل حل: نحفظ الملف أولاً ثم نشاركه
        // AndroidPrint.saveBase64File تحفظ وتفتح نافذة المشاركة في بعض الإصدارات
        if (window.AndroidPrint && typeof window.AndroidPrint.saveBase64File === 'function') {
            try {
                window.AndroidPrint.saveBase64File(dataUrl, fileName);
                toast('✅ تم الحفظ، افتح الملف من التنزيلات للمشاركة', 'success');
                return true;
            } catch (e) {}
        }
        if (window.AppBridge && typeof window.AppBridge.saveImage === 'function') {
            try {
                window.AppBridge.saveImage(dataUrl, fileName);
                toast('✅ تم الحفظ، افتح الملف من المعرض للمشاركة', 'success');
                return true;
            } catch (e) {}
        }
        return false;
    }

    // =========== بناء clone للتصوير ===========
    function buildClone() {
        var original = document.getElementById('statementContent');
        if (!original) throw new Error('لم يتم العثور على محتوى الكشف');

        // تحويل الشعار لـ data URL لتجنب مشكلة CORS مع html2canvas
        var logoImg = document.getElementById('storeLogoImg');
        if (logoImg && logoImg.complete && logoImg.naturalWidth > 0) {
            try {
                var cvs = document.createElement('canvas');
                cvs.width  = logoImg.naturalWidth;
                cvs.height = logoImg.naturalHeight;
                cvs.getContext('2d').drawImage(logoImg, 0, 0);
                logoImg.src = cvs.toDataURL('image/png');
            } catch (e) { logoImg.style.display = 'none'; }
        }

        var wrap = document.createElement('div');
        wrap.style.cssText = [
            'position:fixed', 'left:-9999px', 'top:0',
            'width:750px', 'background:#ffffff',
            'padding:20px', 'direction:rtl',
            'font-family:system-ui,Arial,sans-serif',
            'z-index:-1'
        ].join(';');

        var clone = original.cloneNode(true);

        // استبدال CSS variables بألوان ثابتة (html2canvas لا يفهمها)
        var accentColor = getComputedStyle(document.documentElement)
                            .getPropertyValue('--primary-red').trim() || '#d32f2f';

        clone.style.cssText = 'background:#ffffff !important; color:#111111 !important;';

        // إصلاح الجدول
        clone.querySelectorAll('table').forEach(function (t) {
            t.style.cssText = 'border-collapse:collapse;width:100%;';
        });
        clone.querySelectorAll('td, th').forEach(function (c) {
            c.style.border      = '1px solid #cccccc';
            c.style.padding     = '8px';
            c.style.textAlign   = 'center';
            c.style.color       = '#111111';
            c.style.background  = '#ffffff';
        });
        clone.querySelectorAll('thead th').forEach(function (th) {
            th.style.background = accentColor;
            th.style.color      = '#ffffff';
        });
        clone.querySelectorAll('tr:nth-child(even) td').forEach(function (td) {
            td.style.background = '#f9f9f9';
        });
        clone.querySelectorAll('.td-la').forEach(function (el) {
            el.style.color = '#16a34a';
        });
        clone.querySelectorAll('.td-alayh').forEach(function (el) {
            el.style.color = '#dc2626';
        });
        clone.querySelectorAll('.store-name-red').forEach(function (el) {
            el.style.color = accentColor;
        });
        clone.querySelectorAll('.stamp-box').forEach(function (el) {
            el.style.borderColor = accentColor;
            el.style.color       = accentColor;
        });
        clone.querySelectorAll('img').forEach(function (img) {
            img.setAttribute('crossorigin', 'anonymous');
        });

        wrap.appendChild(clone);
        document.body.appendChild(wrap);
        return { wrap: wrap, clone: clone };
    }

    // =========== html2canvas ===========
    function captureCanvas(node) {
        return html2canvas(node, {
            scale:           2,
            useCORS:         true,
            allowTaint:      false,
            backgroundColor: '#ffffff',
            logging:         false,
            imageTimeout:    5000
        });
    }

    // =========== توليد PDF ===========
    function generatePDF() {
        return new Promise(function (resolve, reject) {
            if (typeof window.jspdf === 'undefined') {
                return reject(new Error('مكتبة jsPDF غير محملة'));
            }
            var jsPDF  = window.jspdf.jsPDF;
            var built  = buildClone();
            var wrap   = built.wrap;
            var clone  = built.clone;

            setTimeout(function () {
                captureCanvas(clone).then(function (canvas) {
                    try {
                        var imgData    = canvas.toDataURL('image/jpeg', 0.92);
                        var pdf        = new jsPDF('p', 'mm', 'a4');
                        var pageW      = pdf.internal.pageSize.getWidth();
                        var pageH      = pdf.internal.pageSize.getHeight();
                        var margin     = 5;
                        var imgW       = pageW - margin * 2;
                        var imgH       = canvas.height * imgW / canvas.width;
                        var heightLeft = imgH;
                        var position   = margin;
                        var page       = 1;

                        pdf.addImage(imgData, 'JPEG', margin, position, imgW, imgH);
                        heightLeft -= (pageH - margin * 2);

                        while (heightLeft > 0) {
                            position = -(pageH * page) + margin;
                            pdf.addPage();
                            pdf.addImage(imgData, 'JPEG', margin, position, imgW, imgH);
                            heightLeft -= (pageH - margin * 2);
                            page++;
                        }

                        var dataUrl  = pdf.output('datauristring');
                        var b64      = dataUrl.split(',')[1];
                        var bytes    = atob(b64);
                        var arr      = new Uint8Array(bytes.length);
                        for (var i = 0; i < bytes.length; i++) arr[i] = bytes.charCodeAt(i);

                        _pdfBlob    = new Blob([arr], { type: 'application/pdf' });
                        _pdfDataUrl = dataUrl;

                        wrap.remove();
                        resolve({ blob: _pdfBlob, b64: b64, dataUrl: dataUrl });
                    } catch (e) {
                        wrap.remove();
                        reject(e);
                    }
                }).catch(function (e) {
                    wrap.remove();
                    reject(e);
                });
            }, 400);
        });
    }

    // =========== توليد صورة PNG ===========
    function generateImage() {
        return new Promise(function (resolve, reject) {
            var built = buildClone();
            var wrap  = built.wrap;
            var clone = built.clone;

            setTimeout(function () {
                captureCanvas(clone).then(function (canvas) {
                    try {
                        var dataUrl = canvas.toDataURL('image/png', 1.0);
                        var b64     = dataUrl.split(',')[1];
                        var bytes   = atob(b64);
                        var arr     = new Uint8Array(bytes.length);
                        for (var i = 0; i < bytes.length; i++) arr[i] = bytes.charCodeAt(i);

                        _imgBlob    = new Blob([arr], { type: 'image/png' });
                        _imgDataUrl = dataUrl;

                        wrap.remove();
                        resolve({ blob: _imgBlob, b64: b64, dataUrl: dataUrl });
                    } catch (e) {
                        wrap.remove();
                        reject(e);
                    }
                }).catch(function (e) {
                    wrap.remove();
                    reject(e);
                });
            }, 400);
        });
    }

    // =========== API العامة ===========
    return {

        // ---- طباعة ----
        // في Android WebView نستخدم JavascriptInterface بدل window.print()
        doPrint: function () {
            // محاولة 1: JavascriptInterface (AndroidPrint / AppBridge)
            if (window.AndroidPrint && typeof window.AndroidPrint.printDocument === 'function') {
                try { window.AndroidPrint.printDocument(); return; } catch (e) {}
            }
            if (window.AppBridge && typeof window.AppBridge.printDocument === 'function') {
                try { window.AppBridge.printDocument(); return; } catch (e) {}
            }
            // محاولة 2: متصفح عادي
            window.print();
        },

        // ---- توليد وتحميل PDF أو صورة ----
        generateAndDownload: function (type) {
            var self = this;
            toast('جاري الإنشاء...', 'warning');

            if (type === 'pdf') {
                generatePDF().then(function (r) {
                    var ok = saveToDevice(r.dataUrl, FILE_BASE + '.pdf');
                    toast(ok ? '✅ تم تحميل PDF' : '❌ فشل التحميل', ok ? 'success' : 'error');
                }).catch(function (e) {
                    console.error(e);
                    toast('❌ فشل إنشاء PDF: ' + e.message, 'error');
                });
            } else {
                generateImage().then(function (r) {
                    var ok = saveToDevice(r.dataUrl, FILE_BASE + '.png');
                    toast(ok ? '✅ تم تحميل الصورة' : '❌ فشل التحميل', ok ? 'success' : 'error');
                }).catch(function (e) {
                    console.error(e);
                    toast('❌ فشل إنشاء الصورة: ' + e.message, 'error');
                });
            }
        },

        // ---- حفظ على السيرفر ----
        saveToServer: function () {
            var self = this;
            toast('جاري الحفظ...', 'warning');

            var doSave = function (b64) {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.href, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.timeout = 30000;
                xhr.onload = function () {
                    if (xhr.status === 200) {
                        try {
                            var res = JSON.parse(xhr.responseText);
                            if (res.success) {
                                _savedUrl = res.url;
                                toast('✅ تم الحفظ على السيرفر', 'success');
                            } else {
                                toast('❌ ' + (res.message || 'فشل'), 'error');
                            }
                        } catch (e) {
                            toast('❌ خطأ في الرد', 'error');
                        }
                    } else {
                        toast('❌ خطأ HTTP: ' + xhr.status, 'error');
                    }
                };
                xhr.onerror   = function () { toast('❌ فشل الاتصال', 'error'); };
                xhr.ontimeout = function () { toast('❌ انتهت مهلة الاتصال', 'error'); };
                xhr.send('save_pdf=1&pdf_data=' + encodeURIComponent('data:application/pdf;base64,' + b64));
            };

            if (_pdfBlob) {
                doSave(_pdfDataUrl.split(',')[1]);
            } else {
                generatePDF().then(function (r) {
                    doSave(r.b64);
                }).catch(function (e) {
                    toast('❌ فشل إنشاء PDF: ' + e.message, 'error');
                });
            }
        },

        // ---- مشاركة الكشف (PDF) ----
        shareFile: function () {
            var self = this;

            var doShare = function () {
                // Android WebView — أفضل طريقة: حفظ ثم إشعار المستخدم
                if (isAndroidWebView()) {
                    shareFileNative(_pdfDataUrl, FILE_BASE + '.pdf', 'application/pdf');
                    return;
                }
                // متصفح حديث يدعم Web Share API مع الملفات
                var file = new File([_pdfBlob], FILE_BASE + '.pdf', { type: 'application/pdf' });
                if (navigator.canShare && navigator.canShare({ files: [file] })) {
                    navigator.share({
                        title: FILE_BASE,
                        files: [file]
                    }).then(function () {
                        toast('✅ تمت المشاركة', 'success');
                    }).catch(function (e) {
                        if (e.name !== 'AbortError') {
                            // Fallback: تحميل مباشر
                            saveToDevice(_pdfDataUrl, FILE_BASE + '.pdf');
                            toast('✅ تم التحميل بدلاً عن المشاركة', 'success');
                        }
                    });
                } else {
                    // Fallback: تحميل مباشر
                    saveToDevice(_pdfDataUrl, FILE_BASE + '.pdf');
                    toast('✅ تم التحميل', 'success');
                }
            };

            if (_pdfBlob) {
                doShare();
            } else {
                toast('جاري إنشاء PDF...', 'warning');
                generatePDF().then(function () {
                    doShare();
                }).catch(function (e) {
                    toast('❌ فشل إنشاء PDF: ' + e.message, 'error');
                });
            }
        },

        // ---- مشاركة واتساب ----
        shareWhatsApp: function () {
            if (!CLIENT_PHONE) { toast('لا يوجد رقم هاتف', 'error'); return; }

            // تنسيق الرقم: إضافة 967 إن لم يكن موجوداً
            var phone = CLIENT_PHONE.replace(/[^0-9]/g, '');
            if (phone.length <= 9) phone = '967' + phone;
            else if (phone.charAt(0) === '0') phone = '967' + phone.substring(1);

            var msg = encodeURIComponent(
                '📄 كشف حساب - ' + STORE_NAME + '\n' +
                '📅 ' + TODAY + '\n' +
                '💰 الرصيد: ' + BALANCE_TEXT
            );
            var waUrl = 'https://wa.me/' + phone + '?text=' + msg;

            // Android WebView: استخدم shareText
            if (window.AndroidPrint && typeof window.AndroidPrint.shareText === 'function') {
                try {
                    window.AndroidPrint.shareText(
                        '📄 كشف حساب - ' + STORE_NAME + '\n📅 ' + TODAY + '\n💰 الرصيد: ' + BALANCE_TEXT
                    );
                    toast('✅ تم فتح خيارات المشاركة', 'success');
                    return;
                } catch (e) {}
            }
            if (window.AppBridge && typeof window.AppBridge.shareText === 'function') {
                try {
                    window.AppBridge.shareText(
                        '📄 كشف حساب - ' + STORE_NAME + '\n📅 ' + TODAY + '\n💰 الرصيد: ' + BALANCE_TEXT
                    );
                    toast('✅ تم فتح خيارات المشاركة', 'success');
                    return;
                } catch (e) {}
            }
            // متصفح عادي
            window.open(waUrl, '_blank');
            toast('✅ تم فتح واتساب', 'success');
        }

    }; // end return
}()); // end IIFE
</script>

<?php require_once 'includes/footer.php'; ?>
