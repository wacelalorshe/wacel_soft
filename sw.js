/**
 * =============================================================================
 * Service Worker - دفتر الحسابات
 * =============================================================================
 * الإصدار: 3.0
 * 
 * الوظائف:
 * - استراتيجية Network First مع Fallback للكاش
 * - تحديث تلقائي للكاش عند الاتصال
 * - دعم العمل Offline مع عرض offline.html
 * - تحديث فوري عند وجود نسخة جديدة
 * - متوافق مع WebView و PWA
 * =============================================================================
 */

// اسم الكاش مع رقم الإصدار
var CACHE_NAME = 'ledger-app-v3';
var OFFLINE_PAGE = 'offline.html';
var ASSETS_PREFIX = 'file:///android_asset/';

// ========== قائمة الملفات الأساسية للتخزين ==========
var PRECACHE_URLS = [
    '/',
    '/index.php',
    '/client.php',
    '/statement.php',
    '/settings.php',
    '/add_transaction.php',
    '/add_partner.php',
    '/login.php',
    '/offline.html',
    '/manifest.json',
    '/includes/header.php',
    '/includes/footer_nav.php',
    '/includes/functions.php',
    '/js/db.js',
    '/js/sync.js'
];

// ========== حدث التثبيت ==========
self.addEventListener('install', function(event) {
    console.log('🔧 Service Worker: جاري التثبيت...');
    
    // تخطي الانتظار وتفعيل الـ SW الجديد فوراً
    self.skipWaiting();
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(function(cache) {
                console.log('📦 Service Worker: جاري تخزين الملفات الأساسية...');
                return Promise.all(
                    PRECACHE_URLS.map(function(url) {
                        return cache.add(url).catch(function(err) {
                            console.log('⚠️ فشل تخزين:', url, err);
                        });
                    })
                );
            })
            .then(function() {
                console.log('✅ Service Worker: تم التثبيت بنجاح');
            })
    );
});

// ========== حدث التفعيل ==========
self.addEventListener('activate', function(event) {
    console.log('🔄 Service Worker: جاري التفعيل...');
    
    // حذف الكاش القديم
    event.waitUntil(
        caches.keys().then(function(cacheNames) {
            return Promise.all(
                cacheNames.map(function(cacheName) {
                    if (cacheName !== CACHE_NAME) {
                        console.log('🗑️ Service Worker: حذف الكاش القديم:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(function() {
            // السيطرة على جميع العملاء فوراً
            return clients.claim();
        }).then(function() {
            console.log('✅ Service Worker: تم التفعيل بنجاح');
            // إشعار جميع النوافذ المفتوحة بالتحديث
            self.clients.matchAll().then(function(clients) {
                clients.forEach(function(client) {
                    client.postMessage({
                        type: 'SW_ACTIVATED',
                        version: CACHE_NAME
                    });
                });
            });
        })
    );
});

// ========== استراتيجية Network First (الشبكة أولاً) ==========
self.addEventListener('fetch', function(event) {
    var requestURL = new URL(event.request.url);
    
    // تجاهل طلبات API والمزامنة
    if (requestURL.pathname.includes('/api/') || 
        requestURL.pathname.includes('/sync') ||
        event.request.method !== 'GET') {
        return;
    }
    
    // تجاهل طلبات chrome-extension
    if (event.request.url.startsWith('chrome-extension://')) {
        return;
    }
    
    // تجاهل طلبات android_asset
    if (event.request.url.includes('android_asset')) {
        return;
    }
    
    event.respondWith(
        fetch(event.request, { cache: 'no-store' })
            .then(function(networkResponse) {
                // تحديث الكاش بالنسخة الجديدة
                var responseClone = networkResponse.clone();
                
                caches.open(CACHE_NAME).then(function(cache) {
                    // تخزين فقط الصفحات والملفات الثابتة
                    var contentType = networkResponse.headers.get('content-type') || '';
                    if (contentType.includes('text/html') || 
                        contentType.includes('text/css') || 
                        contentType.includes('application/javascript') ||
                        contentType.includes('application/json') ||
                        requestURL.pathname.match(/\.(js|css|json|html|php|ico|png|jpg|svg)$/)) {
                        cache.put(event.request, responseClone);
                    }
                });
                
                return networkResponse;
            })
            .catch(function() {
                console.log('📡 Offline - استخدام الكاش لـ:', event.request.url);
                
                // محاولة استخدام الكاش
                return caches.match(event.request)
                    .then(function(cachedResponse) {
                        if (cachedResponse) {
                            return cachedResponse;
                        }
                        
                        // إذا كانت الصفحة HTML، عرض صفحة offline
                        if (event.request.headers.get('accept') && 
                            event.request.headers.get('accept').includes('text/html')) {
                            return caches.match(OFFLINE_PAGE).then(function(offlineResponse) {
                                if (offlineResponse) {
                                    return offlineResponse;
                                }
                                // إنشاء صفحة Offline مخصصة
                                return new Response(
                                    '<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>غير متصل</title><style>body{font-family:sans-serif;background:#0f0f1a;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center;padding:20px;box-sizing:border-box}h1{font-size:60px;margin:0} h2{font-size:24px;margin:16px 0} p{color:#888;margin:8px 0} button{background:#6c5ce7;color:#fff;border:none;padding:14px 28px;border-radius:12px;font-size:16px;font-weight:700;margin-top:20px;cursor:pointer;font-family:inherit} button:active{transform:scale(0.95)}</style></head><body><div><h1>📡</h1><h2>أنت غير متصل بالإنترنت</h2><p>يتم عرض البيانات من الذاكرة المحلية</p><p style="font-size:12px;color:#555;">سيتم المزامنة تلقائياً عند عودة الاتصال</p><button onclick="location.reload()">🔄 إعادة المحاولة</button></div></body></html>',
                                    { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                                );
                            });
                        }
                        
                        return new Response('', { status: 503, statusText: 'Service Unavailable' });
                    });
            })
    );
});

// ========== استقبال الرسائل من الصفحات ==========
self.addEventListener('message', function(event) {
    console.log('📨 Service Worker: رسالة مستلمة:', event.data);
    
    if (event.data && event.data.type === 'SKIP_WAITING') {
        // تخطي الانتظار
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'CLEAR_CACHE') {
        // مسح الكاش
        event.waitUntil(
            caches.keys().then(function(cacheNames) {
                return Promise.all(
                    cacheNames.map(function(cacheName) {
                        return caches.delete(cacheName);
                    })
                );
            }).then(function() {
                console.log('🗑️ Service Worker: تم مسح جميع الكاش');
                // إعادة تحميل جميع العملاء
                self.clients.matchAll().then(function(clients) {
                    clients.forEach(function(client) {
                        client.navigate(client.url);
                    });
                });
            })
        );
    }
    
    if (event.data && event.data.type === 'UPDATE_NOW') {
        // تحديث فوري
        self.skipWaiting();
        self.clients.matchAll().then(function(clients) {
            clients.forEach(function(client) {
                client.postMessage({
                    type: 'RELOAD_PAGE'
                });
            });
        });
    }
});

// ========== مراقبة التحديثات ==========
self.addEventListener('sync', function(event) {
    console.log('🔄 Service Worker: حدث sync:', event.tag);
    
    if (event.tag === 'sync-data') {
        event.waitUntil(
            // محاولة مزامنة البيانات
            fetch('/api/sync.php', { method: 'POST' })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    console.log('✅ تمت المزامنة:', data);
                    // إشعار العملاء
                    self.clients.matchAll().then(function(clients) {
                        clients.forEach(function(client) {
                            client.postMessage({
                                type: 'SYNC_COMPLETE',
                                data: data
                            });
                        });
                    });
                })
                .catch(function(err) {
                    console.log('❌ فشلت المزامنة:', err);
                })
        );
    }
});

// ========== إشعارات Push ==========
self.addEventListener('push', function(event) {
    console.log('📬 Service Worker: إشعار push مستلم');
    
    var title = 'دفتر الحسابات';
    var options = {
        body: event.data ? event.data.text() : 'يوجد تحديث جديد',
        icon: '/icon-192x192.png',
        badge: '/icon-192x192.png',
        vibrate: [200, 100, 200],
        tag: 'ledger-notification',
        renotify: true,
        data: {
            url: '/index.php'
        }
    };
    
    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// ========== النقر على الإشعار ==========
self.addEventListener('notificationclick', function(event) {
    console.log('👆 Service Worker: النقر على الإشعار');
    
    event.notification.close();
    
    event.waitUntil(
        clients.matchAll({ type: 'window' })
            .then(function(clientList) {
                // فتح نافذة موجودة أو إنشاء جديدة
                for (var i = 0; i < clientList.length; i++) {
                    var client = clientList[i];
                    if (client.url.includes('/index.php') && 'focus' in client) {
                        return client.focus();
                    }
                }
                if (clients.openWindow) {
                    return clients.openWindow('/index.php');
                }
            })
    );
});

console.log('✅ Service Worker: تم التحميل والإعداد - ' + CACHE_NAME);