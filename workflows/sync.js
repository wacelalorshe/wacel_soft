/**
 * نظام المزامنة المحسن - يدعم Android WebView
 */
class SyncManager {
    constructor() {
        this.isSyncing = false;
        this.syncInterval = 10000; // كل 10 ثواني
        this.apiBase = 'api/sync.php';
        this.maxRetries = 3;
        this.isWebView = this.detectWebView();
    }

    detectWebView() {
        var ua = navigator.userAgent;
        return /wv|WebView|Android.*Version\/.*Chrome/.test(ua) || 
               typeof AndroidBridge !== 'undefined' ||
               typeof AndroidPrint !== 'undefined';
    }

    startAutoSync() {
        // عند عودة الاتصال
        window.addEventListener('online', () => {
            console.log('🟢 متصل - بدء المزامنة...');
            updateSyncStatus('🟢 متصل - جاري المزامنة...', 'syncing');
            setTimeout(() => this.sync(), 1000);
        });

        // عند فقدان الاتصال
        window.addEventListener('offline', () => {
            console.log('🔴 غير متصل');
            updateSyncStatus('🔴 غير متصل - حفظ محلي', 'offline');
            
            // إشعار في Android
            if (this.isWebView && typeof AndroidBridge !== 'undefined') {
                AndroidBridge.showToast('📡 غير متصل - البيانات تحفظ محلياً');
            }
        });

        // مزامنة دورية فقط عند الاتصال
        setInterval(() => {
            if (navigator.onLine && !this.isSyncing) {
                this.sync();
            }
        }, this.syncInterval);

        // مزامنة أولية
        if (navigator.onLine) {
            setTimeout(() => this.sync(), 2000);
        }

        // تحديث حالة الاتصال
        this.updateConnectionStatus();
    }

    updateConnectionStatus() {
        var statusBar = document.getElementById('statusBar');
        var statusDot = document.getElementById('statusDot');
        var statusText = document.getElementById('statusText');
        var syncBtn = document.getElementById('syncBtn');
        
        if (statusBar && statusDot && statusText) {
            if (navigator.onLine) {
                statusBar.className = 'status-bar online';
                statusDot.className = 'status-dot online';
                statusText.textContent = '🟢 متصل';
                if (syncBtn) {
                    syncBtn.disabled = false;
                    syncBtn.textContent = '🔄 مزامنة';
                }
            } else {
                statusBar.className = 'status-bar offline';
                statusDot.className = 'status-dot offline';
                statusText.textContent = '🔴 غير متصل';
                if (syncBtn) {
                    syncBtn.disabled = true;
                    syncBtn.textContent = '📡';
                }
            }
        }
    }

    async sync() {
        if (this.isSyncing) return;
        if (!navigator.onLine) return;
        
        this.isSyncing = true;

        try {
            updateSyncStatus('جاري المزامنة...', 'syncing');

            // 1. رفع البيانات المحلية للسيرفر
            var queue = [];
            try {
                queue = await localDB.getSyncQueue();
            } catch (e) {
                console.log('لا يوجد طابور مزامنة');
            }
            
            if (queue && queue.length > 0) {
                updateSyncStatus('جاري رفع ' + queue.length + ' عنصر...', 'syncing');
                
                for (var i = 0; i < queue.length; i++) {
                    var item = queue[i];
                    try {
                        await this.uploadItem(item);
                        await localDB.removeFromSyncQueue(item.id);
                    } catch (error) {
                        console.error('فشل رفع:', item.id, error);
                    }
                }
            }

            // 2. تنزيل بيانات السيرفر
            await this.downloadFromServer();

            updateSyncStatus('✅ محدثة', 'synced');
            
            // إخفاء رسالة المزامنة بعد 3 ثواني
            setTimeout(() => {
                var indicator = document.getElementById('syncIndicator');
                if (indicator) {
                    indicator.style.display = 'none';
                }
            }, 3000);

            // إعادة تحميل الصفحة إذا كانت هناك مزامنة
            if (queue && queue.length > 0) {
                setTimeout(() => {
                    if (confirm('تمت المزامنة. تحديث الصفحة؟')) {
                        window.location.reload();
                    }
                }, 1500);
            }

        } catch (error) {
            console.error('فشل المزامنة:', error);
            updateSyncStatus('⚠️ فشل المزامنة', 'error');
        }

        this.isSyncing = false;
    }

    async uploadItem(item) {
        var formData = new FormData();
        formData.append('action', item.action);
        formData.append('data', typeof item.data === 'string' ? item.data : JSON.stringify(item.data));

        var response = await fetch(this.apiBase, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        if (!response.ok) throw new Error('فشل الرفع');
        
        var result = await response.json();
        if (!result.success) throw new Error(result.message || 'فشل');
        
        return result;
    }

    async downloadFromServer() {
        try {
            var response = await fetch(this.apiBase + '?action=fetch_all', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            if (!response.ok) return;
            
            var data = await response.json();
            
            if (data.success) {
                // تحديث العملاء
                if (data.partners && data.partners.length > 0) {
                    try { await localDB.bulkUpdatePartners(data.partners); } catch (e) {}
                }
                
                // تحديث المعاملات
                if (data.transactions && data.transactions.length > 0) {
                    try { await localDB.bulkUpdateTransactions(data.transactions); } catch (e) {}
                }
                
                // حفظ وقت آخر مزامنة
                try {
                    await localDB.setSetting('last_sync', new Date().toISOString());
                    await localDB.setSetting('total_partners', data.total_partners || 0);
                    await localDB.setSetting('total_transactions', data.total_transactions || 0);
                } catch (e) {}
            }
        } catch (error) {
            console.error('فشل التنزيل:', error);
        }
    }
}

// المتغيرات العامة
var syncManager;

// تحديث مؤشر المزامنة
function updateSyncStatus(message, status) {
    var indicator = document.getElementById('syncIndicator');
    if (indicator) {
        indicator.textContent = message;
        indicator.className = 'sync-indicator ' + status;
        indicator.style.display = 'block';
    }
    
    var statusBar = document.getElementById('statusBar');
    var statusDot = document.getElementById('statusDot');
    var statusText = document.getElementById('statusText');
    
    if (statusBar && statusDot && statusText) {
        if (status === 'synced') {
            statusBar.className = 'status-bar online';
            statusDot.className = 'status-dot online';
            statusText.textContent = '🟢 متصل';
        } else if (status === 'offline') {
            statusBar.className = 'status-bar offline';
            statusDot.className = 'status-dot offline';
            statusText.textContent = '🔴 غير متصل';
        } else if (status === 'syncing') {
            statusBar.className = 'status-bar syncing';
            statusDot.className = 'status-dot syncing';
            statusText.textContent = message;
        }
    }
}

// بدء المزامنة عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    if (typeof localDB !== 'undefined') {
        syncManager = new SyncManager();
        syncManager.startAutoSync();
    }
});

// دالة المزامنة اليدوية
async function manualSync() {
    if (!navigator.onLine) {
        showToast('⚠️ لا يوجد اتصال بالإنترنت', 'error');
        return { success: false, message: 'لا يوجد اتصال' };
    }
    
    if (typeof syncManager === 'undefined') {
        syncManager = new SyncManager();
        syncManager.startAutoSync();
    }
    
    await syncManager.sync();
    
    var queueCount = 0;
    try {
        queueCount = await localDB.getSyncQueueCount();
    } catch (e) {}
    
    return {
        success: true,
        message: queueCount === 0 ? '✅ جميع البيانات محدثة' : '✅ تمت المزامنة'
    };
}

// عرض رسالة
function showToast(msg, type) {
    // محاولة استخدام Android bridge
    if (typeof AndroidBridge !== 'undefined' && AndroidBridge.showToast) {
        AndroidBridge.showToast(msg);
        return;
    }
    
    // إنشاء toast في الصفحة
    var existing = document.querySelector('.toast');
    if (existing) existing.remove();
    
    var toast = document.createElement('div');
    toast.className = 'toast ' + (type || 'success');
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(function() { toast.remove(); }, 3000);
}