/**
 * قاعدة البيانات المحلية - IndexedDB
 * تدعم العمل الكامل بدون إنترنت
 */
class LocalDB {
    constructor() {
        this.dbName = 'FinanceAppDB';
        this.dbVersion = 3;
        this.db = null;
    }

    async open() {
        if (this.db) return this.db;
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // جدول العملاء
                if (!db.objectStoreNames.contains('partners')) {
                    const store = db.createObjectStore('partners', { keyPath: 'id', autoIncrement: true });
                    store.createIndex('name', 'name', { unique: false });
                    store.createIndex('synced', 'synced', { unique: false });
                    store.createIndex('user_id', 'user_id', { unique: false });
                }
                
                // جدول المعاملات
                if (!db.objectStoreNames.contains('transactions')) {
                    const store = db.createObjectStore('transactions', { keyPath: 'localId', autoIncrement: true });
                    store.createIndex('partner_id', 'partner_id', { unique: false });
                    store.createIndex('server_id', 'server_id', { unique: false });
                    store.createIndex('synced', 'synced', { unique: false });
                    store.createIndex('user_id', 'user_id', { unique: false });
                }
                
                // جدول طابور المزامنة
                if (!db.objectStoreNames.contains('sync_queue')) {
                    const store = db.createObjectStore('sync_queue', { keyPath: 'id', autoIncrement: true });
                    store.createIndex('action', 'action', { unique: false });
                }
                
                // جدول الإعدادات المحلية
                if (!db.objectStoreNames.contains('local_settings')) {
                    db.createObjectStore('local_settings', { keyPath: 'key' });
                }
            };
            
            request.onsuccess = (e) => {
                this.db = e.target.result;
                resolve(this.db);
            };
            request.onerror = (e) => reject(e.target.error);
        });
    }

    // ==================== العملاء ====================
    
    async addPartner(data) {
        await this.open();
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction('partners', 'readwrite');
            const store = tx.objectStore('partners');
            const record = {
                name: data.name,
                phone: data.phone || '',
                type: data.type || 'local',
                notes: data.notes || '',
                status: 'active',
                balance: 0,
                transaction_count: 0,
                synced: 0,
                user_id: data.user_id || 0,
                createdAt: new Date().toISOString()
            };
            const req = store.add(record);
            req.onsuccess = () => {
                const localId = req.result;
                this.addToSyncQueue('partner_add', { ...record, id: localId });
                resolve({ ...record, id: localId });
            };
            req.onerror = () => reject(req.error);
        });
    }

    async updatePartner(id, data) {
        await this.open();
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction('partners', 'readwrite');
            const store = tx.objectStore('partners');
            const req = store.get(id);
            req.onsuccess = () => {
                const partner = req.result;
                if (!partner) { reject(new Error('غير موجود')); return; }
                const updated = { ...partner, ...data, updatedAt: new Date().toISOString() };
                store.put(updated);
                
                if (partner.synced === 1) {
                    this.addToSyncQueue('partner_update', { id: partner.server_id || id, ...data });
                }
                
                resolve(updated);
            };
            req.onerror = () => reject(req.error);
        });
    }

    async deletePartner(id) {
        await this.open();
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction('partners', 'readwrite');
            const store = tx.objectStore('partners');
            const req = store.get(id);
            req.onsuccess = () => {
                const partner = req.result;
                if (partner && partner.synced === 1) {
                    this.addToSyncQueue('partner_delete', { id: partner.server_id || id });
                }
                store.delete(id);
                
                // حذف المعاملات المرتبطة
                const txStore = this.db.transaction('transactions', 'readwrite').objectStore('transactions');
                const index = txStore.index('partner_id');
                const cursorReq = index.openCursor(IDBKeyRange.only(id));
                cursorReq.onsuccess = (e) => {
                    const cursor = e.target.result;
                    if (cursor) {
                        cursor.delete();
                        cursor.continue();
                    }
                };
                
                resolve();
            };
            req.onerror = () => reject(req.error);
        });
    }

    async getPartners() {
        await this.open();
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction('partners', 'readonly');
            const req = tx.objectStore('partners').getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = () => reject(req.error);
        });
    }

    async getPartnerById(id) {
        await this.open();
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction('partners', 'readonly');
            const req = tx.objectStore('partners').get(id);
            req.onsuccess = () => resolve(req.result || null);
            req.onerror = () => reject(req.error);
        });
    }

    // ==================== المعاملات ====================
    
    async addTransaction(data) {
        await this.open();
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction('transactions', 'readwrite');
            const store = tx.objectStore('transactions');
            const record = {
                partner_id: data.partner_id,
                date: data.date || new Date().toISOString().split('T')[0],
                details: data.details || '',
                amount: parseFloat(data.amount) || 0,
                currency_type: data.currency_type || 'local',
                transaction_type: data.transaction_type || data.type || 'debit',
                quantity: data.quantity || 0,
                user_id: data.user_id || 0,
                synced: 0,
                createdAt: new Date().toISOString()
            };
            const req = store.add(record);
            req.onsuccess = () => {
                const localId = req.result;
                this.addToSyncQueue('transaction_add', { ...record, localId });
                
                // تحديث رصيد العميل محلياً
                this.updatePartnerBalance(data.partner_id);
                
                resolve({ ...record, localId });
            };
            req.onerror = () => reject(req.error);
        });
    }

    async updatePartnerBalance(partnerId) {
        await this.open();
        const transactions = await this.getTransactions(partnerId);
        let balance = 0;
        transactions.forEach(t => {
            balance += t.transaction_type === 'credit' ? t.amount : -t.amount;
        });
        
        const partner = await this.getPartnerById(partnerId);
        if (partner) {
            partner.balance = balance;
            partner.transaction_count = transactions.length;
            const tx = this.db.transaction('partners', 'readwrite');
            tx.objectStore('partners').put(partner);
        }
    }

    async getTransactions(partnerId) {
        await this.open();
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction('transactions', 'readonly');
            const index = tx.objectStore('transactions').index('partner_id');
            const req = index.getAll(partnerId);
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = () => reject(req.error);
        });
    }

    async getAllTransactions() {
        await this.open();
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction('transactions', 'readonly');
            const req = tx.objectStore('transactions').getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = () => reject(req.error);
        });
    }

    // ==================== طابور المزامنة ====================
    
    async addToSyncQueue(action, data) {
        await this.open();
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction('sync_queue', 'readwrite');
            const req = tx.objectStore('sync_queue').add({
                action,
                data,
                createdAt: new Date().toISOString(),
                retries: 0
            });
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
    }

    async getSyncQueue() {
        await this.open();
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction('sync_queue', 'readonly');
            const req = tx.objectStore('sync_queue').getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = () => reject(req.error);
        });
    }

    async removeFromSyncQueue(id) {
        await this.open();
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction('sync_queue', 'readwrite');
            const req = tx.objectStore('sync_queue').delete(id);
            req.onsuccess = () => resolve();
            req.onerror = () => reject(req.error);
        });
    }

    async clearSyncQueue() {
        await this.open();
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction('sync_queue', 'readwrite');
            tx.objectStore('sync_queue').clear();
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        });
    }

    async getSyncQueueCount() {
        await this.open();
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction('sync_queue', 'readonly');
            const req = tx.objectStore('sync_queue').count();
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
    }

    // ==================== الإعدادات المحلية ====================
    
    async setSetting(key, value) {
        await this.open();
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction('local_settings', 'readwrite');
            tx.objectStore('local_settings').put({ key, value });
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        });
    }

    async getSetting(key) {
        await this.open();
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction('local_settings', 'readonly');
            const req = tx.objectStore('local_settings').get(key);
            req.onsuccess = () => resolve(req.result?.value || null);
            req.onerror = () => reject(req.error);
        });
    }

    // ==================== مسح البيانات ====================
    
    async clearAll() {
        await this.open();
        const stores = ['partners', 'transactions', 'sync_queue', 'local_settings'];
        for (const name of stores) {
            const tx = this.db.transaction(name, 'readwrite');
            tx.objectStore(name).clear();
            await new Promise(r => { tx.oncomplete = r; });
        }
    }

    // ==================== تحديث مجمع ====================
    
    async bulkUpdatePartners(partners) {
        await this.open();
        const tx = this.db.transaction('partners', 'readwrite');
        const store = tx.objectStore('partners');
        
        // مسح العملاء الحاليين المتزامنين
        const all = await this.getPartners();
        for (const p of all) {
            if (p.synced === 1) store.delete(p.id);
        }
        
        // إضافة العملاء الجدد من السيرفر
        for (const p of partners) {
            store.add({
                id: p.id,
                name: p.name,
                phone: p.phone || '',
                type: p.type || 'local',
                status: p.status || 'active',
                balance: p.balance || 0,
                transaction_count: p.transaction_count || 0,
                synced: 1,
                server_id: p.id,
                user_id: p.user_id || 0,
                createdAt: p.created_at || new Date().toISOString()
            });
        }
        
        return new Promise((resolve, reject) => {
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        });
    }

    async bulkUpdateTransactions(transactions) {
        await this.open();
        const tx = this.db.transaction('transactions', 'readwrite');
        const store = tx.objectStore('transactions');
        
        const all = await this.getAllTransactions();
        for (const t of all) {
            if (t.synced === 1) store.delete(t.localId);
        }
        
        for (const t of transactions) {
            store.add({
                partner_id: t.partner_id,
                date: t.date,
                details: t.details || '',
                amount: parseFloat(t.amount) || 0,
                currency_type: t.currency_type || 'local',
                transaction_type: t.transaction_type,
                quantity: t.quantity || 0,
                synced: 1,
                server_id: t.id,
                user_id: t.user_id || 0,
                createdAt: t.created_at || new Date().toISOString()
            });
        }
        
        return new Promise((resolve, reject) => {
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        });
    }
}

const localDB = new LocalDB();