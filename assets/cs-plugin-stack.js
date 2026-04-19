/* global csdtOptimizer */
'use strict';

(function () {
    var ajaxUrl = csdtOptimizer.ajaxUrl;
    var nonce   = csdtOptimizer.nonce;
    var baseUrl = csdtOptimizer.baseUrl;

    function post(action, params) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', nonce);
        if (params) {
            Object.keys(params).forEach(function (k) { fd.append(k, params[k]); });
        }
        return fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Simple markdown-ish formatter for AI output
    function formatAiText(text) {
        // Escape HTML first
        var escaped = escHtml(text);
        // Code spans
        escaped = escaped.replace(/`([^`\n]+)`/g,
            '<code style="background:#1e293b;color:#e2e8f0;padding:1px 6px;border-radius:3px;font-family:\'SF Mono\',Consolas,monospace;font-size:.88em;">$1</code>');
        // Bold **text**
        escaped = escaped.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
        // Numbered steps: "1. ", "2. " etc at line start
        escaped = escaped.replace(/(^|\n)(\d+)\.\s+/g, '$1<br><strong style="color:#6366f1;">$2.</strong> ');
        // Paragraph breaks
        escaped = escaped.replace(/\n\n/g, '</p><p style="margin:0 0 10px;line-height:1.75;">');
        // Single line breaks
        escaped = escaped.replace(/\n/g, '<br>');
        return escaped;
    }

    var TAB_LABELS = {
        'security':   'Security Scan',
        'login':      'Login Security',
        'mail':       'Mail / SMTP',
        'migrate':    'Code Migrator',
        'sql':        'SQL Command',
        'logs':       'Server Logs',
        'thumbnails': 'Thumbnails',
    };

    // ── Plugin Stack Scanner ─────────────────────────────────────────────────

    var scanBtn     = document.getElementById('csdt-optimizer-scan-btn');
    var scanningMsg = document.getElementById('csdt-optimizer-scanning');
    var resultsDiv  = document.getElementById('csdt-optimizer-results');

    if (scanBtn) {
        scanBtn.addEventListener('click', function () {
            scanBtn.disabled = true;
            scanBtn.textContent = '⏳ Scanning…';
            if (scanningMsg) scanningMsg.style.display = '';
            if (resultsDiv)  resultsDiv.style.display  = 'none';

            post('csdt_plugin_stack_scan').then(function (res) {
                scanBtn.disabled  = false;
                scanBtn.innerHTML = '🔍 Scan My Plugin Stack';
                if (scanningMsg) scanningMsg.style.display = 'none';

                if (!res.success) {
                    resultsDiv.innerHTML = '<p style="color:#dc2626;font-size:.9em;">Scan failed — please reload and try again.</p>';
                    resultsDiv.style.display = '';
                    return;
                }
                renderScanResults(res.data);
            }).catch(function () {
                scanBtn.disabled  = false;
                scanBtn.innerHTML = '🔍 Scan My Plugin Stack';
                if (scanningMsg) scanningMsg.style.display = 'none';
                if (resultsDiv) {
                    resultsDiv.innerHTML = '<p style="color:#dc2626;font-size:.9em;">Request failed — please reload and try again.</p>';
                    resultsDiv.style.display = '';
                }
            });
        });
    }

    function renderScanResults(data) {
        var matched = data.matched  || [];
        var saving  = data.total_saving || 0;
        var html    = '';

        if (matched.length === 0) {
            html =
                '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:20px 24px;display:flex;gap:14px;align-items:flex-start;">' +
                '<span style="font-size:1.8em;line-height:1;">✅</span>' +
                '<div>' +
                '<p style="margin:0 0 6px;font-weight:700;color:#166534;font-size:1em;">Your plugin stack is already clean.</p>' +
                '<p style="margin:0;color:#374151;font-size:.92em;line-height:1.6;">None of your ' + (data.active_count || 'active') + ' active plugins overlap with CloudScale features. You\'re running a lean stack.</p>' +
                '</div></div>';
        } else {
            // Summary banner
            var savingHtml = saving > 0
                ? ' Removing them could save you <strong>$' + saving + '/year</strong> in premium license fees.'
                : '';
            html +=
                '<div style="background:linear-gradient(135deg,#fff7ed,#fefce8);border:1px solid #fed7aa;border-radius:8px;padding:16px 20px;margin-bottom:20px;display:flex;gap:14px;align-items:flex-start;">' +
                '<span style="font-size:1.8em;line-height:1;">🎯</span>' +
                '<div>' +
                '<p style="margin:0 0 5px;font-weight:700;color:#0f172a;font-size:1.05em;">' +
                matched.length + ' plugin' + (matched.length !== 1 ? 's' : '') + ' found that CloudScale already replaces.' +
                '</p>' +
                '<p style="margin:0;color:#64748b;font-size:.9em;line-height:1.6;">' + savingHtml + ' Safe to deactivate once you\'ve confirmed the CloudScale equivalent is working.</p>' +
                '</div></div>';

            // Results table
            html +=
                '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:16px;">' +
                '<table style="width:100%;border-collapse:collapse;font-size:.88em;">' +
                '<thead>' +
                '<tr style="background:#f8fafc;">' +
                '<th style="padding:10px 14px;text-align:left;font-weight:700;color:#374151;border-bottom:1px solid #e5e7eb;">Plugin</th>' +
                '<th style="padding:10px 14px;text-align:left;font-weight:700;color:#374151;border-bottom:1px solid #e5e7eb;">CloudScale replaces it with</th>' +
                '<th style="padding:10px 14px;text-align:right;font-weight:700;color:#374151;border-bottom:1px solid #e5e7eb;white-space:nowrap;">Saved/yr</th>' +
                '<th style="padding:10px 14px;text-align:center;font-weight:700;color:#374151;border-bottom:1px solid #e5e7eb;">Go to</th>' +
                '</tr>' +
                '</thead><tbody>';

            matched.forEach(function (p, i) {
                var bg      = i % 2 === 0 ? '#fff' : '#f8fafc';
                var tabUrl  = baseUrl + '&tab=' + encodeURIComponent(p.tab || 'home');
                var tabName = TAB_LABELS[p.tab] || p.tab;
                var costStr = p.cost > 0
                    ? '<span style="color:#dc2626;font-weight:600;">$' + p.cost + '</span>'
                    : '<span style="color:#6b7280;">—</span>';
                var vStr    = p.version ? ' <span style="font-weight:400;color:#9ca3af;font-size:.82em;">v' + escHtml(p.version) + '</span>' : '';
                html +=
                    '<tr style="background:' + bg + ';border-bottom:1px solid #f1f5f9;">' +
                    '<td style="padding:10px 14px;font-weight:600;color:#0f172a;">' + escHtml(p.name) + vStr + '</td>' +
                    '<td style="padding:10px 14px;color:#374151;line-height:1.5;">' + escHtml(p.feature) + '</td>' +
                    '<td style="padding:10px 14px;text-align:right;">' + costStr + '</td>' +
                    '<td style="padding:10px 14px;text-align:center;">' +
                    '<a href="' + tabUrl + '" style="color:#6366f1;font-weight:600;font-size:.85em;text-decoration:none;white-space:nowrap;">→ ' + escHtml(tabName) + '</a>' +
                    '</td>' +
                    '</tr>';
            });

            html += '</tbody></table></div>';

            // Safety note
            html +=
                '<div style="background:#f0f9ff;border-left:3px solid #0ea5e9;padding:11px 16px;border-radius:0 6px 6px 0;font-size:.87em;color:#0c4a6e;line-height:1.6;">' +
                '<strong>Before deactivating:</strong> set up and test the CloudScale equivalent first. Take a backup — the free ' +
                '<a href="https://your-wordpress-site.example.com/wordpress-plugin-help/cloudscale-backup-restore-help/" target="_blank" rel="noopener" style="color:#0369a1;">CloudScale Backup plugin</a>' +
                ' does a one-click full-site snapshot.' +
                '</div>';
        }

        if (resultsDiv) {
            resultsDiv.innerHTML = html;
            resultsDiv.style.display = '';
        }
    }

    // ── AI Debugging Assistant ───────────────────────────────────────────────

    var debugBtn     = document.getElementById('csdt-debug-analyze-btn');
    var analyzingMsg = document.getElementById('csdt-debug-analyzing');
    var debugInput   = document.getElementById('csdt-debug-input');
    var debugResult  = document.getElementById('csdt-debug-result');

    if (debugBtn) {
        debugBtn.addEventListener('click', function () {
            var input = debugInput ? debugInput.value.trim() : '';
            if (!input) {
                if (debugResult) {
                    debugResult.innerHTML = '<p style="color:#dc2626;font-size:.9em;">Please enter an error message or description first.</p>';
                    debugResult.style.display = '';
                }
                return;
            }

            debugBtn.disabled  = true;
            debugBtn.textContent = '⏳ Analyzing…';
            if (analyzingMsg) analyzingMsg.style.display = '';
            if (debugResult)  debugResult.style.display  = 'none';

            post('csdt_ai_debug_log', { input: input }).then(function (res) {
                debugBtn.disabled    = false;
                debugBtn.innerHTML   = '🤖 Diagnose with AI';
                if (analyzingMsg) analyzingMsg.style.display = 'none';

                if (!res.success) {
                    var errMsg = (res.data && res.data.message) ? res.data.message : 'Analysis failed.';
                    if (debugResult) {
                        debugResult.innerHTML =
                            '<div style="background:#fff5f5;border-left:3px solid #dc2626;padding:12px 16px;border-radius:0 6px 6px 0;color:#7f1d1d;font-size:.9em;line-height:1.6;">' +
                            '<strong>Error:</strong> ' + escHtml(errMsg) +
                            '</div>';
                        debugResult.style.display = '';
                    }
                    return;
                }

                var text = (res.data && res.data.analysis) ? res.data.analysis : '';
                var html =
                    '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:20px 24px;">' +
                    '<div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #e2e8f0;">' +
                    '<span style="font-size:1.2em;">🤖</span>' +
                    '<span style="font-weight:700;color:#0f172a;font-size:.95em;">AI Diagnosis</span>' +
                    '</div>' +
                    '<div style="color:#374151;font-size:.92em;line-height:1.75;">' +
                    '<p style="margin:0 0 10px;line-height:1.75;">' + formatAiText(text) + '</p>' +
                    '</div>' +
                    '</div>';

                if (debugResult) {
                    debugResult.innerHTML = html;
                    debugResult.style.display = '';
                }
            }).catch(function () {
                debugBtn.disabled    = false;
                debugBtn.innerHTML   = '🤖 Diagnose with AI';
                if (analyzingMsg) analyzingMsg.style.display = 'none';
                if (debugResult) {
                    debugResult.innerHTML = '<p style="color:#dc2626;font-size:.9em;">Request failed — please reload and try again.</p>';
                    debugResult.style.display = '';
                }
            });
        });
    }

}());
