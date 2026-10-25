(function () {
    'use strict';
    var config = window.ai0rView;
    if (!config || !config.ajax || navigator.doNotTrack === '1' || window.doNotTrack === '1' || navigator.globalPrivacyControl) return;
    var sent = false;
    function count() {
        if (sent || document.visibilityState !== 'visible') return;
        sent = true;
        // Existing WordPress login cookies let the server exclude signed-in users.
        // No analytics cookies or persistent visitor identifiers are created.
        fetch(config.ajax, {
            method: 'POST', credentials: 'include', keepalive: true, referrerPolicy: 'no-referrer',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'ai0r_view', id: config.id, token: config.token }).toString()
        }).catch(function () {});
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', count, { once: true });
    else count();
    document.addEventListener('visibilitychange', count);
}());
