(function () {
    'use strict';
    var config = window.ai0rView;
    if (!config || navigator.doNotTrack === '1' || window.doNotTrack === '1' || navigator.globalPrivacyControl) return;
    var sent = false;
    function count() {
        if (sent || document.visibilityState !== 'visible') return;
        sent = true;
        // No cookies, persistent identifiers, page URLs or referrers are transmitted.
        fetch(config.url, {
            method: 'POST', credentials: 'omit', keepalive: true, referrerPolicy: 'no-referrer',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: config.id, token: config.token })
        }).catch(function () {});
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', count, { once: true });
    else count();
    document.addEventListener('visibilitychange', count);
}());
