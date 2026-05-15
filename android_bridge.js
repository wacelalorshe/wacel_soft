/**
 * android_bridge.js
 * ==========================================
 * أضف هذا الكود في statement.php قبل سكريبتات html2canvas
 * للتعامل مع تحميل الصور والـ PDF في WebView Android
 *
 * ضع هذا الملف في: /js/android_bridge.js
 * واستدعه في statement.php:
 *   <script src="js/android_bridge.js"></script>
 */

(function () {
    "use strict";

    // ======================================================
    //   كشف بيئة Android WebView
    // ======================================================
    var isAndroidWebView = typeof AndroidBridge !== "undefined"
        || typeof AndroidPrint !== "undefined"
        || /wv|WebView/.test(navigator.userAgent)
        || /WaseelSoftApp/.test(navigator.userAgent);

    window.IS_ANDROID_WEBVIEW = isAndroidWebView;

    // ======================================================
    //   تجاوز window.print() في Android
    //   بدلاً من print() نستدعي AndroidPrint.printCurrentPage()
    // ======================================================
    if (isAndroidWebView && typeof AndroidPrint !== "undefined") {
        window._originalPrint = window.print;
        window.print = function () {
            try {
                AndroidPrint.printCurrentPage();
            } catch (e) {
                window._originalPrint();
            }
        };
        console.log("✅ Android Print Bridge مفعّل");
    }

    // ======================================================
    //   تجاوز تحميل الملفات (a[download]) في Android
    //   بدلاً من href=data: نستدعي AndroidBridge.saveBase64File()
    // ======================================================
    if (isAndroidWebView && typeof AndroidBridge !== "undefined") {

        // اعتراض clicks على روابط التحميل
        document.addEventListener("click", function (e) {
            var anchor = e.target.closest("a[download]");
            if (!anchor) return;

            var href = anchor.href || "";
            var fileName = anchor.getAttribute("download") || "file.jpg";

            if (href.startsWith("data:")) {
                e.preventDefault();
                e.stopPropagation();
                try {
                    AndroidBridge.saveBase64File(href, fileName);
                } catch (err) {
                    console.error("Bridge error:", err);
                }
                return;
            }

            // روابط http عادية - DownloadManager يتولاها تلقائياً
        }, true);

        console.log("✅ Android Download Bridge مفعّل");
    }

    // ======================================================
    //   تحميل الصور المُولَّدة بـ html2canvas
    //   بدلاً من downloadSingleImage() المعطوبة في WebView
    // ======================================================
    window.downloadSingleImageAndroid = function (imgData, fileName) {
        if (isAndroidWebView && typeof AndroidBridge !== "undefined") {
            try {
                AndroidBridge.saveBase64File(imgData, fileName);
                return true;
            } catch (e) {
                console.error("saveBase64File error:", e);
                return false;
            }
        }
        return false;
    };

    // ======================================================
    //   تجاوز downloadSingleImage الأصلية
    // ======================================================
    if (isAndroidWebView) {
        window._origDownloadSingleImage = window.downloadSingleImage;
        window.downloadSingleImage = function (imgData, fileName) {
            if (typeof AndroidBridge !== "undefined") {
                AndroidBridge.saveBase64File(imgData, fileName);
            } else if (window._origDownloadSingleImage) {
                window._origDownloadSingleImage(imgData, fileName);
            }
        };
    }

    // ======================================================
    //   مشاركة واتساب من Android
    // ======================================================
    window.shareViaWhatsAppAndroid = function (text) {
        if (typeof AndroidBridge !== "undefined") {
            try {
                AndroidBridge.shareTextViaWhatsApp(text);
                return true;
            } catch (e) {
                return false;
            }
        }
        return false;
    };

    // ======================================================
    //   تحميل html2canvas من الكاش إن أمكن
    // ======================================================
    window.loadHtml2Canvas = function (callback) {
        if (typeof html2canvas !== "undefined") {
            callback();
            return;
        }

        // محاولة تحميل من CDN
        var script = document.createElement("script");
        script.src = "https://html2canvas.hertzen.com/dist/html2canvas.min.js";
        script.onload = function () {
            console.log("✅ html2canvas محمّل من CDN");
            callback();
        };
        script.onerror = function () {
            console.error("❌ فشل تحميل html2canvas من CDN");
            // محاولة تحميل من كاش محلي
            script.src = "/js/html2canvas.min.js";
            script.onload = callback;
            document.head.appendChild(script);
        };
        document.head.appendChild(script);
    };

    // ======================================================
    //   إشعار Toast في Android
    // ======================================================
    window.showAndroidToast = function (message) {
        if (typeof AndroidBridge !== "undefined") {
            try { AndroidBridge.showToast(message); return; } catch (e) {}
        }
        // fallback: Toast JS
        if (typeof showToast === "function") {
            showToast(message, "success");
        }
    };

    console.log("🤖 Android Bridge جاهز | WebView: " + isAndroidWebView);

})();
