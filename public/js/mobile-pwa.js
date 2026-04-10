(function () {
    var config = window.MobilePwaConfig || {};
    var installButtons = Array.from(document.querySelectorAll('[data-pwa-install]'));
    var iosHint = document.querySelector('[data-ios-install-hint]');
    var dismissIosHint = document.querySelector('[data-dismiss-ios-install]');
    var deferredPrompt = null;
    var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    var isIos = /iphone|ipad|ipod/i.test(window.navigator.userAgent);
    var isSafari = /^((?!chrome|android).)*safari/i.test(window.navigator.userAgent);
    var iosHintDismissed = window.localStorage.getItem('mobile-pwa-ios-hint-dismissed') === '1';

    function toggleInstallButtons(show) {
        installButtons.forEach(function (button) {
            button.hidden = !show;
        });
    }

    if ('serviceWorker' in navigator && config.serviceWorkerUrl) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(config.serviceWorkerUrl, {
                scope: config.scope || '/app/',
            }).catch(function () {
                return null;
            });
        });
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredPrompt = event;
        if (!isStandalone) {
            toggleInstallButtons(true);
        }
    });

    installButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (!deferredPrompt) {
                return;
            }

            deferredPrompt.prompt();

            deferredPrompt.userChoice.finally(function () {
                deferredPrompt = null;
                toggleInstallButtons(false);
            });
        });
    });

    if (isStandalone) {
        toggleInstallButtons(false);
    }

    if (iosHint && dismissIosHint && !isStandalone && isIos && isSafari && !iosHintDismissed) {
        iosHint.hidden = false;

        dismissIosHint.addEventListener('click', function () {
            iosHint.hidden = true;
            window.localStorage.setItem('mobile-pwa-ios-hint-dismissed', '1');
        });
    }
})();
