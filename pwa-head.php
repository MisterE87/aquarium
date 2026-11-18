<?php
if (!isset($config)) {
    $config = [];
}
$pwaManifestUrl = htmlspecialchars(urlPath($config, '/manifest.json'), ENT_QUOTES, 'UTF-8');
$pwaIcon192Url = htmlspecialchars(urlPath($config, '/icons/icon-192.png'), ENT_QUOTES, 'UTF-8');
$pwaIconUrl = htmlspecialchars(urlPath($config, '/icons/icon-512.png'), ENT_QUOTES, 'UTF-8');
$pwaSwUrl = htmlspecialchars(urlPath($config, '/sw.js'), ENT_QUOTES, 'UTF-8');
$pwaSwScope = htmlspecialchars(serviceWorkerScope($config), ENT_QUOTES, 'UTF-8');
?>
<link rel="manifest" href="<?= $pwaManifestUrl ?>">
<link rel="icon" type="image/png" sizes="192x192" href="<?= $pwaIcon192Url ?>">
<link rel="icon" type="image/png" sizes="512x512" href="<?= $pwaIconUrl ?>">
<link rel="apple-touch-icon" sizes="512x512" href="<?= $pwaIconUrl ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<style>
    #pwa-install-status {
        display: none;
        margin-top: 10px;
        padding: 10px 12px;
        border-radius: 10px;
        font-size: 13px;
        line-height: 1.4;
        text-align: center;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.18);
        color: inherit;
    }

    #pwa-install-status.is-visible {
        display: block;
    }

    #pwa-install-status.is-error {
        background: rgba(232, 145, 143, 0.25);
        border-color: rgba(232, 145, 143, 0.45);
    }

    #pwa-install-status.is-success {
        background: rgba(126, 201, 160, 0.25);
        border-color: rgba(126, 201, 160, 0.45);
    }
</style>
<script>
    window.deferredInstallPrompt = null;
    var PWA_INSTALL_VERIFY_KEY = 'pwa-install-verify';

    function isStandaloneDisplay() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.matchMedia('(display-mode: fullscreen)').matches
            || window.matchMedia('(display-mode: minimal-ui)').matches
            || window.navigator.standalone === true;
    }

    function ensureInstallStatusElement() {
        var statusEl = document.getElementById('pwa-install-status');
        if (statusEl) {
            return statusEl;
        }

        statusEl = document.createElement('div');
        statusEl.id = 'pwa-install-status';
        statusEl.setAttribute('aria-live', 'polite');

        var anchor = document.querySelector('.pwa-install-btn');
        if (anchor && anchor.parentNode) {
            anchor.parentNode.insertBefore(statusEl, anchor.nextSibling);
        } else if (document.body) {
            document.body.appendChild(statusEl);
        }

        return statusEl;
    }

    function setInstallStatus(message, type) {
        var statusEl = ensureInstallStatusElement();
        if (!statusEl.parentNode) {
            return;
        }

        statusEl.textContent = message;
        statusEl.classList.add('is-visible');
        statusEl.classList.remove('is-error', 'is-success');

        if (type === 'error') {
            statusEl.classList.add('is-error');
        } else if (type === 'success') {
            statusEl.classList.add('is-success');
        }

        if (typeof window.showToast === 'function') {
            window.showToast(message);
        }
    }

    function showPwaInstallButtons() {
        document.querySelectorAll('.pwa-install-btn').forEach(function (btn) {
            btn.hidden = false;
        });
    }

    function hidePwaInstallButtons() {
        document.querySelectorAll('.pwa-install-btn').forEach(function (btn) {
            btn.hidden = true;
        });
    }

    function handleInstallClick(button) {
        var promptEvent = window.deferredInstallPrompt;
        if (!promptEvent) {
            setInstallStatus('Install is not available right now. Reload and try again.', 'error');
            return;
        }

        try {
            promptEvent.prompt();
        } catch (error) {
            console.error('Install prompt failed:', error);
            setInstallStatus('Install prompt failed: ' + error.message, 'error');
            return;
        }

        promptEvent.userChoice.then(function (result) {
            window.deferredInstallPrompt = null;
            hidePwaInstallButtons();

            if (result.outcome === 'accepted') {
                setInstallStatus('Install accepted. Open from your home screen to verify standalone mode.', 'success');
                button.hidden = true;
            } else {
                setInstallStatus('Install dismissed', 'error');
            }
        }).catch(function (error) {
            console.error('Install choice failed:', error);
            setInstallStatus('Install failed: ' + error.message, 'error');
        });
    }

    function bindInstallButtons() {
        document.querySelectorAll('.pwa-install-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                handleInstallClick(button);
            });
        });
    }

    function checkInstallVerification() {
        try {
            if (localStorage.getItem(PWA_INSTALL_VERIFY_KEY) !== '1') {
                return;
            }
            localStorage.removeItem(PWA_INSTALL_VERIFY_KEY);

            if (isStandaloneDisplay()) {
                return;
            }

            setInstallStatus(
                'Installed as a browser shortcut only. Ensure HTTPS is valid, then reinstall using Install app.',
                'error'
            );
        } catch (error) {
            console.warn('Could not verify install mode:', error);
        }
    }

    function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        navigator.serviceWorker.register('<?= $pwaSwUrl ?>', { scope: '<?= $pwaSwScope ?>' })
            .catch(function (error) {
                console.error('Service worker registration failed:', error);
                if (document.body) {
                    setInstallStatus('Service worker registration failed: ' + error.message, 'error');
                }
            });
    }

    registerServiceWorker();

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        window.deferredInstallPrompt = event;
        showPwaInstallButtons();
    });

    window.addEventListener('appinstalled', function () {
        window.deferredInstallPrompt = null;
        hidePwaInstallButtons();

        try {
            localStorage.setItem(PWA_INSTALL_VERIFY_KEY, '1');
        } catch (error) {
            console.warn('Could not store install verify flag:', error);
        }

        setInstallStatus('App installed. Open from your home screen to verify standalone mode.', 'success');
    });

    document.addEventListener('DOMContentLoaded', function () {
        ensureInstallStatusElement();
        bindInstallButtons();
        checkInstallVerification();

        if (isStandaloneDisplay()) {
            hidePwaInstallButtons();
        }
    });
</script>
