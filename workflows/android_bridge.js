// android_bridge.js - جسر التواصل مع تطبيق Android

class AndroidBridge {
    static isNativeAvailable() {
        try {
            return typeof AppBridge !== 'undefined' && 
                   AppBridge && 
                   typeof AppBridge.hasNative === 'function' && 
                   AppBridge.hasNative();
        } catch (e) {
            return false;
        }
    }

    static printDocument() {
        if (this.isNativeAvailable()) {
            try {
                AppBridge.printDocument();
            } catch (e) {
                console.error('خطأ في الطباعة:', e);
                window.print();
            }
        } else {
            window.print();
        }
    }

    static saveImage(base64Data, fileName) {
        if (this.isNativeAvailable()) {
            try {
                AppBridge.saveImage(base64Data, fileName);
                return true;
            } catch (e) {
                console.error('خطأ في حفظ الصورة:', e);
                return false;
            }
        }
        return false;
    }

    static downloadPdf(base64Data, fileName) {
        if (this.isNativeAvailable()) {
            try {
                AppBridge.downloadPdf(base64Data, fileName);
                return true;
            } catch (e) {
                console.error('خطأ في تحميل PDF:', e);
                return false;
            }
        }
        return false;
    }

    static shareText(text) {
        if (this.isNativeAvailable()) {
            try {
                AppBridge.shareText(text);
                return true;
            } catch (e) {
                console.error('خطأ في المشاركة:', e);
                return false;
            }
        }
        return false;
    }

    static showToast(message) {
        if (this.isNativeAvailable()) {
            try {
                AppBridge.showToast(message);
            } catch (e) {
                this.showWebToast(message);
            }
        } else {
            this.showWebToast(message);
        }
    }

    static showWebToast(message) {
        const existingToast = document.querySelector('.web-toast');
        if (existingToast) {
            existingToast.remove();
        }

        const toast = document.createElement('div');
        toast.className = 'web-toast';
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.85);
            color: white;
            padding: 12px 24px;
            border-radius: 25px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 14px;
            z-index: 999999;
            animation: toastIn 0.3s ease, toastOut 0.3s ease 2.7s;
            white-space: nowrap;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        `;

        const style = document.createElement('style');
        style.textContent = `
            @keyframes toastIn {
                from { 
                    opacity: 0; 
                    transform: translate(-50%, 20px); 
                }
                to { 
                    opacity: 1; 
                    transform: translate(-50%, 0); 
                }
            }
            @keyframes toastOut {
                from { 
                    opacity: 1; 
                    transform: translate(-50%, 0); 
                }
                to { 
                    opacity: 0; 
                    transform: translate(-50%, -20px); 
                }
            }
        `;
        
        if (!document.querySelector('#toast-styles')) {
            style.id = 'toast-styles';
            document.head.appendChild(style);
        }

        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    static downloadFile(url, fileName) {
        if (this.isNativeAvailable()) {
            try {
                AppBridge.downloadFileFromUrl(url);
                return true;
            } catch (e) {
                console.error('خطأ في التحميل:', e);
                return false;
            }
        }
        return false;
    }
}

// تصدير الكلاس للاستخدام العام
window.AndroidBridge = AndroidBridge;