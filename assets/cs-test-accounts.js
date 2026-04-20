/* global csdtTestAccounts */
(function () {
    'use strict';

    var cfg = (typeof csdtTestAccounts !== 'undefined') ? csdtTestAccounts : {};
    var ajaxUrl = cfg.ajaxUrl || '';
    var nonce   = cfg.nonce   || '';

    /* ── cached last credentials for copy helpers ── */
    var lastCreds = null;

    /* ── helpers ── */
    function post(action, data, cb) {
        var body = new FormData();
        body.append('action', action);
        body.append('nonce',  nonce);
        Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
        fetch(ajaxUrl, { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(cb)
            .catch(function (e) { console.error('[csdt-test-accounts]', e); });
    }

    function el(id) { return document.getElementById(id); }

    /* ── render accounts list ── */
    function renderAccounts(accounts) {
        var list = el('cs-ta-list');
        if (!list) return;

        if (!accounts || !accounts.length) {
            list.innerHTML = '<p style="color:#888;font-size:13px;margin:0;">No active test accounts.</p>';
            return;
        }

        list.innerHTML = accounts.map(function (a) {
            var mins = Math.max(0, Math.ceil(a.expires_in / 60));
            var loginInfo = '';
            if (a.max_logins > 0) {
                loginInfo = ' · ' + a.login_count + '/' + a.max_logins + ' logins';
            }
            return '<div class="cs-ta-account-row" style="display:flex;align-items:center;gap:12px;padding:8px 12px;margin-bottom:4px;background:#f9fafb;border-radius:6px;border:1px solid #e5e7eb;">' +
                '<div style="flex:1;font-family:monospace;font-size:13px;">' + escHtml(a.username) + '</div>' +
                '<div style="font-size:12px;color:#6b7280;">expires in ' + mins + 'm' + escHtml(loginInfo) + '</div>' +
                '<button type="button" class="cs-btn-secondary cs-btn-sm cs-ta-revoke" data-user-id="' + a.user_id + '" style="color:#dc2626;border-color:#fca5a5;">Revoke</button>' +
                '</div>';
        }).join('');

        wireRevoke();
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function wireRevoke() {
        document.querySelectorAll('.cs-ta-revoke').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var userId = btn.getAttribute('data-user-id');
                btn.disabled = true;
                btn.textContent = '…';
                post('csdt_test_account_revoke', { user_id: userId }, function (res) {
                    if (res.success) {
                        renderAccounts(res.data.accounts);
                    } else {
                        btn.disabled = false;
                        btn.textContent = 'Revoke';
                        alert('Error: ' + (res.data || 'unknown'));
                    }
                });
            });
        });
    }

    /* ── show credentials box ── */
    function showCreds(data) {
        lastCreds = data;
        var box = el('cs-ta-creds');
        if (!box) return;

        var expires = new Date(data.expires_at * 1000).toLocaleString();
        el('cs-ta-cred-user').textContent    = data.username;
        el('cs-ta-cred-pw').textContent      = data.app_password;
        el('cs-ta-cred-url').textContent     = data.rest_url;
        el('cs-ta-cred-expires').textContent = expires;
        box.style.display = 'block';
    }

    /* ── init ── */
    document.addEventListener('DOMContentLoaded', function () {
        var panel = el('cs-panel-test-accounts');
        if (!panel) return;

        /* Enable toggle */
        var chkEnabled = el('cs-ta-enabled');
        var options    = el('cs-ta-options');
        if (chkEnabled && options) {
            chkEnabled.addEventListener('change', function () {
                options.style.display = chkEnabled.checked ? 'flex' : 'none';
            });
        }

        /* Single-use toggle */
        var chkSingleUse  = el('cs-ta-single-use');
        var maxLoginsEl   = el('cs-ta-max-logins');
        if (chkSingleUse && maxLoginsEl) {
            chkSingleUse.addEventListener('change', function () {
                if (chkSingleUse.checked) {
                    maxLoginsEl.value    = '1';
                    maxLoginsEl.disabled = true;
                } else {
                    maxLoginsEl.disabled = false;
                }
            });
        }

        /* Save settings */
        var btnSave  = el('cs-ta-save');
        var savedMsg = el('cs-ta-saved');
        if (btnSave) {
            btnSave.addEventListener('click', function () {
                btnSave.disabled = true;
                var payload = {
                    enabled:    chkEnabled && chkEnabled.checked ? '1' : '0',
                    ttl:        (el('cs-ta-ttl') || {}).value || '1800',
                    single_use: chkSingleUse && chkSingleUse.checked ? '1' : '0',
                    max_logins: maxLoginsEl ? (parseInt(maxLoginsEl.value, 10) || 0) : 0,
                };
                post('csdt_test_account_settings_save', payload, function (res) {
                    btnSave.disabled = false;
                    if (res.success && savedMsg) {
                        savedMsg.classList.add('visible');
                        setTimeout(function () { savedMsg.classList.remove('visible'); }, 3000);
                    }
                });
            });
        }

        /* Create account */
        var btnCreate = el('cs-ta-create');
        if (btnCreate) {
            btnCreate.addEventListener('click', function () {
                btnCreate.disabled = true;
                btnCreate.textContent = '…';
                post('csdt_test_account_create', {}, function (res) {
                    btnCreate.disabled = false;
                    btnCreate.textContent = '+ Create Test Account';
                    if (res.success) {
                        showCreds(res.data);
                        renderAccounts(res.data.accounts);
                    } else {
                        alert('Error: ' + (res.data || 'unknown'));
                    }
                });
            });
        }

        /* Copy as JSON */
        var btnJson = el('cs-ta-copy-json');
        if (btnJson) {
            btnJson.addEventListener('click', function () {
                if (!lastCreds) return;
                var obj = {
                    username:     lastCreds.username,
                    app_password: lastCreds.app_password,
                    rest_url:     lastCreds.rest_url,
                    expires_at:   lastCreds.expires_at,
                };
                navigator.clipboard.writeText(JSON.stringify(obj, null, 2)).then(function () {
                    btnJson.textContent = '✓ Copied';
                    setTimeout(function () { btnJson.textContent = '⎘ Copy as JSON'; }, 1500);
                });
            });
        }

        /* Copy curl example */
        var btnCurl = el('cs-ta-copy-curl');
        if (btnCurl) {
            btnCurl.addEventListener('click', function () {
                if (!lastCreds) return;
                var curl = 'curl -u "' + lastCreds.username + ':' + lastCreds.app_password + '" ' + lastCreds.rest_url;
                navigator.clipboard.writeText(curl).then(function () {
                    btnCurl.textContent = '✓ Copied';
                    setTimeout(function () { btnCurl.textContent = '⎘ Copy curl example'; }, 1500);
                });
            });
        }

        /* Render initial list (from PHP-localised data) */
        renderAccounts(cfg.accounts || []);
        wireRevoke();
    });
}());
