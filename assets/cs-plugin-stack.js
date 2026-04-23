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
        var matched  = data.matched  || [];
        var saving   = data.total_saving || 0;
        var html     = '';

        var activePlugins   = matched.filter(function (p) { return p.active !== false; });
        var inactivePlugins = matched.filter(function (p) { return p.active === false; });

        if (activePlugins.length === 0 && inactivePlugins.length === 0) {
            html =
                '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:20px 24px;display:flex;gap:14px;align-items:flex-start;">' +
                '<span style="font-size:1.8em;line-height:1;">✅</span>' +
                '<div>' +
                '<p style="margin:0 0 6px;font-weight:700;color:#166534;font-size:1em;">Your plugin stack is already clean.</p>' +
                '<p style="margin:0;color:#374151;font-size:.92em;line-height:1.6;">None of your ' + (data.active_count || 'active') + ' active plugins overlap with CloudScale features. You\'re running a lean stack.</p>' +
                '</div></div>';
        } else {
            if (activePlugins.length > 0) {
                var savingHtml = saving > 0
                    ? ' Removing them could save you <strong>$' + saving + '/year</strong> in premium license fees.'
                    : '';
                html +=
                    '<div style="background:linear-gradient(135deg,#fff7ed,#fefce8);border:1px solid #fed7aa;border-radius:8px;padding:16px 20px;margin-bottom:20px;display:flex;gap:14px;align-items:flex-start;">' +
                    '<span style="font-size:1.8em;line-height:1;">🎯</span>' +
                    '<div>' +
                    '<p style="margin:0 0 5px;font-weight:700;color:#0f172a;font-size:1.05em;">' +
                    activePlugins.length + ' active plugin' + (activePlugins.length !== 1 ? 's' : '') + ' found that CloudScale already replaces.' +
                    '</p>' +
                    '<p style="margin:0;color:#64748b;font-size:.9em;line-height:1.6;">' + savingHtml + ' Safe to deactivate once you\'ve confirmed the CloudScale equivalent is working.</p>' +
                    '</div></div>';

                html += renderPluginTable(activePlugins);

                html +=
                    '<div style="background:#f0f9ff;border-left:3px solid #0ea5e9;padding:11px 16px;border-radius:0 6px 6px 0;font-size:.87em;color:#0c4a6e;line-height:1.6;margin-bottom:' + (inactivePlugins.length > 0 ? '24' : '0') + 'px;">' +
                    '<strong>Before deactivating:</strong> set up and test the CloudScale equivalent first. Take a backup — the free ' +
                    '<a href="https://your-wordpress-site.example.com/wordpress-plugin-help/cloudscale-backup-restore-help/" target="_blank" rel="noopener" style="color:#0369a1;">CloudScale Backup plugin</a>' +
                    ' does a one-click full-site snapshot.' +
                    '</div>';
            }

            if (inactivePlugins.length > 0) {
                html +=
                    '<div style="background:#fafafa;border:1px solid #e5e7eb;border-radius:8px;padding:16px 20px;margin-bottom:16px;">' +
                    '<p style="margin:0 0 12px;font-weight:700;color:#374151;font-size:.95em;">🗑️ Also installed but inactive — safe to delete</p>' +
                    '<p style="margin:0 0 14px;color:#6b7280;font-size:.87em;line-height:1.5;">These plugins are deactivated and covered by CloudScale. You can delete them to reduce attack surface and keep your dashboard tidy.</p>';
                html += renderPluginTable(inactivePlugins, true);
                html += '</div>';
            }
        }

        if (resultsDiv) {
            resultsDiv.innerHTML = html;
            resultsDiv.style.display = '';
        }
    }

    function renderPluginTable(plugins, muted) {
        var html =
            '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:16px;">' +
            '<table style="width:100%;border-collapse:collapse;font-size:.88em;">' +
            '<thead>' +
            '<tr style="background:#f8fafc;">' +
            '<th style="padding:10px 14px;text-align:left;font-weight:700;color:#374151;border-bottom:1px solid #e5e7eb;">Plugin</th>' +
            '<th style="padding:10px 14px;text-align:left;font-weight:700;color:#374151;border-bottom:1px solid #e5e7eb;">CloudScale replaces it with</th>' +
            ( !muted ? '<th style="padding:10px 14px;text-align:right;font-weight:700;color:#374151;border-bottom:1px solid #e5e7eb;white-space:nowrap;">Saved/yr</th>' : '' ) +
            '<th style="padding:10px 14px;text-align:center;font-weight:700;color:#374151;border-bottom:1px solid #e5e7eb;">Go to</th>' +
            '</tr>' +
            '</thead><tbody>';

        plugins.forEach(function (p, i) {
            var bg      = i % 2 === 0 ? '#fff' : '#f8fafc';
            var tabUrl  = baseUrl + '&tab=' + encodeURIComponent(p.tab || 'home');
            var tabName = TAB_LABELS[p.tab] || p.tab;
            var vStr    = p.version ? ' <span style="font-weight:400;color:#9ca3af;font-size:.82em;">v' + escHtml(p.version) + '</span>' : '';
            html +=
                '<tr style="background:' + bg + ';border-bottom:1px solid #f1f5f9;">' +
                '<td style="padding:10px 14px;font-weight:600;color:' + (muted ? '#6b7280' : '#0f172a') + ';">' + escHtml(p.name) + vStr + '</td>' +
                '<td style="padding:10px 14px;color:#374151;line-height:1.5;">' + escHtml(p.feature) + '</td>';
            if (!muted) {
                var costStr = p.cost > 0
                    ? '<span style="color:#dc2626;font-weight:600;">$' + p.cost + '</span>'
                    : '<span style="color:#6b7280;">—</span>';
                html += '<td style="padding:10px 14px;text-align:right;">' + costStr + '</td>';
            }
            html +=
                '<td style="padding:10px 14px;text-align:center;">' +
                '<a href="' + tabUrl + '" style="color:#6366f1;font-weight:600;font-size:.85em;text-decoration:none;white-space:nowrap;">→ ' + escHtml(tabName) + '</a>' +
                '</td>' +
                '</tr>';
        });

        html += '</tbody></table></div>';
        return html;
    }

    // ── Uptime Monitor ───────────────────────────────────────────────────────

    var uptimeSetupWrap   = document.getElementById('csdt-uptime-setup-wrap');
    var uptimeStatusWrap  = document.getElementById('csdt-uptime-status-wrap');
    var uptimeStatusInner = document.getElementById('csdt-uptime-status-inner');
    var uptimeDeployBtn   = document.getElementById('csdt-uptime-deploy-btn');
    var uptimeDeploying   = document.getElementById('csdt-uptime-deploying');
    var uptimeDeployRes   = document.getElementById('csdt-uptime-deploy-result');
    var uptimeGenBtn      = document.getElementById('csdt-uptime-generate-token-btn');
    var uptimeTokenWrap   = document.getElementById('csdt-uptime-token-wrap');
    var uptimeTokenDisplay= document.getElementById('csdt-uptime-token-display');
    var uptimeManualWrap  = document.getElementById('csdt-uptime-manual-wrap');
    var uptimeNtfyInput   = document.getElementById('csdt-uptime-ntfy-url');
    var uptimeSlugInput   = document.getElementById('csdt-uptime-ready-slug');
    var uptimeUrlDisplay  = document.getElementById('csdt-ready-url-display');
    var uptimeRefreshBtn  = document.getElementById('csdt-uptime-refresh-btn');
    var uptimeSaveBtn     = document.getElementById('csdt-uptime-save-btn');
    var uptimeSaveStatus  = document.getElementById('csdt-uptime-save-status');
    var uptimeTestBtn     = document.getElementById('csdt-uptime-test-btn');

    if (uptimeGenBtn) {
        uptimeGenBtn.addEventListener('click', function () {
            uptimeGenBtn.disabled = true;
            uptimeGenBtn.textContent = '⏳ Generating…';
            post('csdt_uptime_setup').then(function (res) {
                uptimeGenBtn.disabled = false;
                uptimeGenBtn.textContent = '🔄 Regenerate Token';
                if (!res.success) return;
                var d = res.data;
                if (uptimeTokenWrap)   { uptimeTokenWrap.style.display = 'flex'; }
                if (uptimeTokenDisplay){ uptimeTokenDisplay.value = d.token; }
                renderManualDeploy(d.worker_js, d.wrangler_toml);
            }).catch(function () {
                uptimeGenBtn.disabled = false;
                uptimeGenBtn.textContent = '🔑 Generate Token';
            });
        });
    }

    function renderManualDeploy(workerJs, wranglerToml) {
        if (!uptimeManualWrap) return;
        uptimeManualWrap.innerHTML =
            '<p style="font-size:.85em;color:#374151;margin:0 0 10px;">1. Go to <a href="https://dash.cloudflare.com/?to=/:account/workers-and-pages/create" target="_blank" rel="noopener" style="color:#6366f1;">dash.cloudflare.com → Workers → Create</a>. Choose "Hello World" then replace the entire script with the code below.</p>' +
            '<p style="font-size:.85em;color:#374151;margin:0 0 6px;">2. Click <strong>Deploy</strong>, then go to <strong>Settings → Variables</strong> and add: <code style="background:#f1f5f9;padding:1px 5px;border-radius:3px;">SITE_URL</code>, <code style="background:#f1f5f9;padding:1px 5px;border-radius:3px;">PING_URL</code>, <code style="background:#f1f5f9;padding:1px 5px;border-radius:3px;">READY_URL</code>, <code style="background:#f1f5f9;padding:1px 5px;border-radius:3px;">PING_TOKEN</code>, <code style="background:#f1f5f9;padding:1px 5px;border-radius:3px;">NTFY_URL</code> from the wrangler.toml below.</p>' +
            '<p style="font-size:.85em;color:#374151;margin:0 0 6px;">3. Go to <strong>Triggers → Cron Triggers → Add Cron</strong> and enter <code style="background:#f1f5f9;padding:1px 5px;border-radius:3px;">* * * * *</code> (every minute).</p>' +
            '<p style="font-size:.85em;font-weight:700;color:#374151;margin:8px 0 4px;">worker.js</p>' +
            '<textarea readonly style="width:100%;height:160px;font-family:monospace;font-size:.78em;border:1px solid #e5e7eb;border-radius:6px;padding:10px;resize:vertical;background:#f8fafc;">' + escHtml(workerJs) + '</textarea>' +
            '<p style="font-size:.85em;font-weight:700;color:#374151;margin:10px 0 4px;">wrangler.toml (CLI users)</p>' +
            '<textarea readonly style="width:100%;height:120px;font-family:monospace;font-size:.78em;border:1px solid #e5e7eb;border-radius:6px;padding:10px;resize:vertical;background:#f8fafc;">' + escHtml(wranglerToml) + '</textarea>';
    }

    if (uptimeSlugInput && uptimeUrlDisplay) {
        uptimeSlugInput.addEventListener('input', function () {
            var slug = uptimeSlugInput.value.trim();
            var base = uptimeUrlDisplay.textContent.replace(/\/cf-callback\/.*$/, '/cf-callback/');
            uptimeUrlDisplay.textContent = base + ( slug || 'ready' );
        });
        uptimeSlugInput.addEventListener('change', function () {
            var ntfy = uptimeNtfyInput ? uptimeNtfyInput.value.trim() : '';
            post('csdt_uptime_save_settings', { ntfy_url: ntfy, ready_slug: uptimeSlugInput.value.trim() }).then(function (res) {
                if (res.success && res.data.ready_url && uptimeUrlDisplay) {
                    uptimeUrlDisplay.textContent = res.data.ready_url;
                }
            });
        });
    }

    if (uptimeDeployBtn) {
        uptimeDeployBtn.addEventListener('click', function () {
            var ntfy = uptimeNtfyInput ? uptimeNtfyInput.value.trim() : '';
            var slug = uptimeSlugInput ? uptimeSlugInput.value.trim() : '';
            uptimeDeployBtn.disabled = true;
            uptimeDeployBtn.textContent = '⏳ Deploying…';
            if (uptimeDeploying) uptimeDeploying.style.display = '';
            if (uptimeDeployRes) uptimeDeployRes.innerHTML = '';

            // Save slug before deploy so the worker gets the correct READY_URL
            var saveFirst = slug ? post('csdt_uptime_save_settings', { ntfy_url: ntfy, ready_slug: slug }) : Promise.resolve();
            saveFirst.then(function () {
            post('csdt_uptime_deploy_worker', { ntfy_url: ntfy }).then(function (res) {
                uptimeDeployBtn.disabled = false;
                uptimeDeployBtn.textContent = '🚀 Deploy Worker to Cloudflare';
                if (uptimeDeploying) uptimeDeploying.style.display = 'none';

                if (!res.success) {
                    if (uptimeDeployRes) {
                        uptimeDeployRes.innerHTML = '<div style="background:#fef2f2;border-left:3px solid #dc2626;padding:10px 14px;border-radius:0 6px 6px 0;font-size:.87em;color:#7f1d1d;">' +
                            '⚠ ' + escHtml((res.data && res.data.message) || 'Deploy failed') + '</div>';
                    }
                    // Show manual fallback
                    post('csdt_uptime_setup').then(function (sr) {
                        if (sr.success) renderManualDeploy(sr.data.worker_js, sr.data.wrangler_toml);
                    });
                    return;
                }

                if (uptimeDeployRes) {
                    var d = res.data;
                    uptimeDeployRes.innerHTML =
                        '<div style="background:#f0fdf4;border-left:3px solid #16a34a;padding:10px 14px;border-radius:0 6px 6px 0;font-size:.87em;color:#166534;">' +
                        '✅ ' + escHtml(d.message) +
                        (d.worker_url ? ' <a href="' + escHtml(d.worker_url) + '" target="_blank" rel="noopener" style="color:#16a34a;font-weight:600;">View Worker →</a>' : '') +
                        (!d.cron_ok ? '<br><span style="color:#ca8a04;">⚠ Cron trigger could not be set automatically — go to the Worker dashboard → Triggers → Add Cron → <code>* * * * *</code></span>' : '') +
                        '</div>';
                }
                if (uptimeTokenDisplay && res.data && res.data.token) { uptimeTokenDisplay.value = res.data.token; if (uptimeTokenWrap) uptimeTokenWrap.style.display = 'flex'; }
                loadUptimeHistory();
            }).catch(function () {
                uptimeDeployBtn.disabled = false;
                uptimeDeployBtn.textContent = '🚀 Deploy Worker to Cloudflare';
                if (uptimeDeploying) uptimeDeploying.style.display = 'none';
                if (uptimeDeployRes) uptimeDeployRes.innerHTML = '<p style="color:#dc2626;font-size:.9em;">Request failed — please reload.</p>';
            });
            }); // saveFirst
        });
    }

    if (uptimeSaveBtn) {
        uptimeSaveBtn.addEventListener('click', function () {
            var ntfy = uptimeNtfyInput ? uptimeNtfyInput.value.trim() : '';
            var slug = uptimeSlugInput ? uptimeSlugInput.value.trim() : '';
            uptimeSaveBtn.disabled = true;
            post('csdt_uptime_save_settings', { ntfy_url: ntfy, ready_slug: slug }).then(function (res) {
                uptimeSaveBtn.disabled = false;
                if (!uptimeSaveStatus) return;
                if (res.success) {
                    if (res.data.ready_url && uptimeUrlDisplay) uptimeUrlDisplay.textContent = res.data.ready_url;
                    uptimeSaveStatus.style.display = '';
                    uptimeSaveStatus.style.color = '#16a34a';
                    uptimeSaveStatus.textContent = '✓ Saved';
                    setTimeout(function () { uptimeSaveStatus.style.display = 'none'; }, 2500);
                } else {
                    uptimeSaveStatus.style.display = '';
                    uptimeSaveStatus.style.color = '#dc2626';
                    uptimeSaveStatus.textContent = '✗ Save failed';
                }
            }).catch(function () {
                uptimeSaveBtn.disabled = false;
            });
        });
    }

    if (uptimeTestBtn) {
        uptimeTestBtn.addEventListener('click', function () {
            uptimeTestBtn.disabled = true;
            uptimeTestBtn.textContent = '⏳ Testing…';
            if (uptimeDeployRes) uptimeDeployRes.innerHTML = '';
            post('csdt_uptime_test_endpoint').then(function (res) {
                uptimeTestBtn.disabled = false;
                uptimeTestBtn.textContent = '🧪 Test Endpoint';
                if (!uptimeDeployRes) return;
                if (!res.success) {
                    uptimeDeployRes.innerHTML = '<div style="background:#fef2f2;border-left:3px solid #dc2626;padding:10px 14px;border-radius:0 6px 6px 0;font-size:.87em;color:#7f1d1d;">⚠ ' + escHtml((res.data && res.data.message) || 'Test failed') + '</div>';
                    return;
                }
                var d = res.data;
                var ok = d.ok;
                var col = ok ? '#166534' : '#7f1d1d';
                var bg  = ok ? '#f0fdf4' : '#fef2f2';
                var brd = ok ? '#16a34a' : '#dc2626';
                var via = d.via === 'cf_worker'
                    ? '<span style="font-size:.8em;background:rgba(0,0,0,.08);padding:1px 6px;border-radius:3px;margin-left:6px;">via CF Worker</span>'
                    : '<span style="font-size:.8em;background:rgba(0,0,0,.08);padding:1px 6px;border-radius:3px;margin-left:6px;">direct (redeploy to enable CF route)</span>';
                var checksHtml = '';
                if (d.checks) {
                    checksHtml = '<div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;">';
                    ['db','fpm','wp'].forEach(function(k) {
                        var c = d.checks[k] || {};
                        var icon = c.ok !== false ? '✅' : '❌';
                        checksHtml += '<span style="font-size:.82em;background:rgba(0,0,0,.05);padding:2px 8px;border-radius:4px;">' + icon + ' ' + k.toUpperCase() + '</span>';
                    });
                    checksHtml += '</div>';
                }
                uptimeDeployRes.innerHTML = '<div style="background:' + bg + ';border-left:3px solid ' + brd + ';padding:10px 14px;border-radius:0 6px 6px 0;font-size:.87em;color:' + col + ';">' +
                    (ok ? '✅ Endpoint healthy' : '🔴 Endpoint returned HTTP ' + d.status_code) +
                    ' — <strong>' + d.ms + 'ms</strong>' + via +
                    checksHtml + '</div>';
                loadUptimeHistory();
            }).catch(function () {
                uptimeTestBtn.disabled = false;
                uptimeTestBtn.textContent = '🧪 Test Endpoint';
            });
        });
    }

    if (uptimeRefreshBtn) {
        uptimeRefreshBtn.addEventListener('click', function () {
            uptimeRefreshBtn.disabled = true;
            uptimeRefreshBtn.textContent = '⏳ Loading…';
            loadUptimeHistory().then(function () {
                uptimeRefreshBtn.disabled = false;
                uptimeRefreshBtn.textContent = '↻ Refresh';
            });
        });
    }

    function loadUptimeHistory() {
        return post('csdt_uptime_history').then(function (res) {
            if (!res.success || !uptimeStatusInner) return;
            renderUptimeStatus(res.data);
            if (uptimeStatusWrap) uptimeStatusWrap.style.display = '';
        }).catch(function () {});
    }

    function fmtAgo(ts) {
        if (!ts) return null;
        var secs = Math.floor(Date.now() / 1000) - ts;
        if (secs < 5)   return 'just now';
        if (secs < 60)  return secs + 's ago';
        if (secs < 3600) return Math.round(secs / 60) + 'm ago';
        return Math.round(secs / 3600) + 'h ago';
    }

    function renderUptimeStatus(d) {
        if (!uptimeStatusInner) return;
        var lp     = d.last_ping;
        var isUp   = lp && lp.up;
        var age    = lp ? lp.age_seconds : null;
        var ageStr = age != null ? (age < 60 ? age + 's ago' : Math.round(age / 60) + 'm ago') : '—';

        var statusColor = !lp ? '#6b7280' : (isUp ? '#16a34a' : '#dc2626');
        var statusLabel = !lp ? 'No pings yet' : (isUp ? '✅ UP' : '🔴 DOWN');
        var msLabel     = lp  ? lp.ms + 'ms' : '';

        var html =
            '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;margin-bottom:20px;">' +
            uptimeStatCard(statusLabel, msLabel, statusColor) +
            (d.uptime_24h != null ? uptimeStatCard(d.uptime_24h + '%', '24h uptime', d.uptime_24h >= 99 ? '#16a34a' : d.uptime_24h >= 95 ? '#ca8a04' : '#dc2626') : '') +
            (d.uptime_7d  != null ? uptimeStatCard(d.uptime_7d  + '%', '7d uptime',  d.uptime_7d  >= 99 ? '#16a34a' : d.uptime_7d  >= 95 ? '#ca8a04' : '#dc2626') : '') +
            (d.avg_ms_24h != null ? uptimeStatCard(d.avg_ms_24h + 'ms', 'avg resp', d.avg_ms_24h < 500 ? '#16a34a' : d.avg_ms_24h < 1500 ? '#ca8a04' : '#dc2626') : '') +
            '</div>';

        // Last-ping age
        if (lp) {
            html += '<p style="font-size:.82em;color:#6b7280;margin:0 0 14px;">Last ping: ' + escHtml(ageStr) + '</p>';
        }

        // Readiness probe status
        html += '<div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:14px 16px;margin-bottom:18px;">';
        html += '<p style="font-size:.82em;font-weight:700;color:#374151;margin:0 0 10px;">Readiness Probe</p>';

        var checks = d.readiness_checks;
        if (checks) {
            html += '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:10px;">';
            ['db','fpm','wp'].forEach(function(key) {
                var c    = checks[key] || {};
                var ok   = c.ok !== false;
                var col  = ok ? '#16a34a' : '#dc2626';
                var icon = ok ? '✅' : '❌';
                var sub  = key === 'fpm' && c.saturation_pct != null
                    ? c.active + '/' + c.total + ' workers (' + c.saturation_pct + '%)'
                    : key === 'db' ? (c.message || '') : key === 'wp' && c.version ? 'WP ' + c.version : '';
                html += '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:8px 10px;text-align:center;">';
                html += '<div style="font-size:1em;font-weight:700;color:' + col + ';">' + icon + ' ' + key.toUpperCase() + '</div>';
                if (sub) html += '<div style="font-size:.72em;color:#6b7280;margin-top:2px;">' + escHtml(sub) + '</div>';
                html += '</div>';
            });
            html += '</div>';
        } else {
            html += '<p style="font-size:.82em;color:#9ca3af;margin:0 0 8px;">Not yet probed — deploy the Worker to begin.</p>';
        }

        var lastProbed = fmtAgo(d.readiness_last);
        var lastBad    = fmtAgo(d.readiness_bad);
        html += '<div style="display:flex;flex-direction:column;gap:4px;">';
        html += '<p style="font-size:.78em;color:#6b7280;margin:0;">Last queried: <strong style="color:#374151;">' + escHtml(lastProbed || 'Never') + '</strong></p>';
        if (lastBad) {
            html += '<p style="font-size:.78em;color:#dc2626;margin:0;">Last failed query (bad token): <strong>' + escHtml(lastBad) + '</strong></p>';
        }
        if (d.ready_url) {
            html += '<p style="font-size:.75em;color:#9ca3af;margin:4px 0 0;word-break:break-all;">Endpoint: ' + escHtml(d.ready_url) + '</p>';
        }
        html += '</div></div>';

        // Response-time chart (raw — last 60 pings)
        var raw = d.raw || [];
        if (raw.length > 0) {
            var recent = raw.slice(-60);
            var maxMs  = Math.max.apply(null, recent.map(function(r){ return r.ms; })) || 1;
            html += '<p style="font-size:.82em;font-weight:700;color:#374151;margin:0 0 6px;">Response time — last ' + recent.length + ' pings</p>';
            html += '<div style="display:flex;align-items:flex-end;gap:1px;height:48px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;padding:4px 6px;overflow:hidden;">';
            recent.forEach(function (r) {
                var h   = Math.max(4, Math.round((r.ms / maxMs) * 40));
                var col = r.up ? '#34d399' : '#f87171';
                html += '<div style="flex:1;min-width:2px;height:' + h + 'px;background:' + col + ';border-radius:1px;" title="' + (r.up ? 'UP' : 'DOWN') + ' ' + r.ms + 'ms"></div>';
            });
            html += '</div>';
            html += '<p style="font-size:.75em;color:#9ca3af;margin:4px 0 0;">Green = up · Red = down · Height = response time</p>';
        }

        uptimeStatusInner.innerHTML = html;
    }

    function uptimeStatCard(value, label, color) {
        return '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px 16px;text-align:center;">' +
            '<div style="font-size:1.3em;font-weight:800;color:' + color + ';line-height:1.2;">' + escHtml(String(value)) + '</div>' +
            '<div style="font-size:.75em;color:#6b7280;font-weight:600;margin-top:4px;">' + escHtml(label) + '</div>' +
            '</div>';
    }

    // Auto-load history — always show so readiness timestamps are visible
    (function () {
        post('csdt_uptime_history').then(function (res) {
            if (!res.success) return;
            renderUptimeStatus(res.data);
            if (uptimeStatusWrap) uptimeStatusWrap.style.display = '';
        }).catch(function () {});
    }());

    // ── Update Risk Scorer ───────────────────────────────────────────────────

    var riskScanBtn     = document.getElementById('csdt-update-risk-scan-btn');
    var riskScanningMsg = document.getElementById('csdt-update-risk-scanning');
    var riskResultsDiv  = document.getElementById('csdt-update-risk-results');

    if (riskScanBtn) {
        riskScanBtn.addEventListener('click', function () {
            riskScanBtn.disabled  = true;
            riskScanBtn.textContent = '⏳ Scanning…';
            if (riskScanningMsg) riskScanningMsg.style.display = '';
            if (riskResultsDiv)  riskResultsDiv.style.display  = 'none';

            post('csdt_update_risk_scan').then(function (res) {
                riskScanBtn.disabled  = false;
                riskScanBtn.innerHTML = '🔍 Scan for Available Updates';
                if (riskScanningMsg) riskScanningMsg.style.display = 'none';

                if (!res.success) {
                    if (riskResultsDiv) {
                        riskResultsDiv.innerHTML = '<p style="color:#dc2626;font-size:.9em;">Scan failed — please reload and try again.</p>';
                        riskResultsDiv.style.display = '';
                    }
                    return;
                }
                renderUpdateRiskResults(res.data.plugins || []);
            }).catch(function () {
                riskScanBtn.disabled  = false;
                riskScanBtn.innerHTML = '🔍 Scan for Available Updates';
                if (riskScanningMsg) riskScanningMsg.style.display = 'none';
                if (riskResultsDiv) {
                    riskResultsDiv.innerHTML = '<p style="color:#dc2626;font-size:.9em;">Request failed — please reload and try again.</p>';
                    riskResultsDiv.style.display = '';
                }
            });
        });
    }

    var RISK_BADGE = {
        patch:    { bg: '#f0fdf4', border: '#86efac', badge: '#16a34a', label: '🟢 Patch',    text: 'Low risk — bug fixes only.' },
        minor:    { bg: '#fefce8', border: '#fde68a', badge: '#ca8a04', label: '🟡 Minor',    text: 'Review changelog — new features, possible deprecations.' },
        breaking: { bg: '#fef2f2', border: '#fca5a5', badge: '#dc2626', label: '🔴 Breaking', text: 'High risk — major version change. Test in staging first.' },
    };

    function renderUpdateRiskResults(plugins) {
        if (!riskResultsDiv) return;
        if (plugins.length === 0) {
            riskResultsDiv.innerHTML =
                '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:16px 20px;">' +
                '<p style="margin:0;font-weight:600;color:#166534;">✅ All plugins are up to date — nothing to assess.</p></div>';
            riskResultsDiv.style.display = '';
            return;
        }

        var html =
            '<p style="margin:0 0 14px;color:#6b7280;font-size:.87em;">' + plugins.length + ' update' + (plugins.length !== 1 ? 's' : '') + ' available. Click <strong>Assess Risk</strong> on each row to get an AI risk score before updating.</p>' +
            '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">' +
            '<table style="width:100%;border-collapse:collapse;font-size:.88em;" id="csdt-risk-table">' +
            '<thead><tr style="background:#f8fafc;">' +
            '<th style="padding:10px 14px;text-align:left;font-weight:700;color:#374151;border-bottom:1px solid #e5e7eb;">Plugin</th>' +
            '<th style="padding:10px 14px;text-align:center;font-weight:700;color:#374151;border-bottom:1px solid #e5e7eb;">Current</th>' +
            '<th style="padding:10px 14px;text-align:center;font-weight:700;color:#374151;border-bottom:1px solid #e5e7eb;">New</th>' +
            '<th style="padding:10px 14px;text-align:center;font-weight:700;color:#374151;border-bottom:1px solid #e5e7eb;">Risk</th>' +
            '<th style="padding:10px 14px;text-align:center;font-weight:700;color:#374151;border-bottom:1px solid #e5e7eb;"></th>' +
            '</tr></thead><tbody>';

        plugins.forEach(function (p, i) {
            var bg  = i % 2 === 0 ? '#fff' : '#f8fafc';
            var rid = 'risk-row-' + i;
            html +=
                '<tr style="background:' + bg + ';border-bottom:1px solid #f1f5f9;" id="' + rid + '">' +
                '<td style="padding:10px 14px;font-weight:600;color:#0f172a;">' + escHtml(p.name) + '</td>' +
                '<td style="padding:10px 14px;text-align:center;color:#6b7280;font-size:.85em;">' + escHtml(p.current_version) + '</td>' +
                '<td style="padding:10px 14px;text-align:center;color:#0ea5e9;font-weight:600;font-size:.85em;">' + escHtml(p.new_version) + '</td>' +
                '<td style="padding:10px 14px;text-align:center;" class="risk-badge-cell-' + i + '"><span style="color:#9ca3af;font-size:.82em;">—</span></td>' +
                '<td style="padding:10px 14px;text-align:center;">' +
                '<button class="csdt-assess-risk-btn" ' +
                'data-idx="' + i + '" ' +
                'data-slug="' + escHtml(p.slug) + '" ' +
                'data-name="' + escHtml(p.name) + '" ' +
                'data-current="' + escHtml(p.current_version) + '" ' +
                'data-new="' + escHtml(p.new_version) + '" ' +
                'style="background:#6366f1;color:#fff;border:none;font-size:.8em;font-weight:700;padding:5px 12px;border-radius:6px;cursor:pointer;white-space:nowrap;">Assess Risk</button>' +
                '</td>' +
                '</tr>' +
                '<tr class="risk-reason-row-' + i + '" style="display:none;"><td colspan="5" style="padding:0 14px 10px 42px;font-size:.84em;color:#374151;line-height:1.5;" class="risk-reason-cell-' + i + '"></td></tr>';
        });

        html += '</tbody></table></div>';
        riskResultsDiv.innerHTML = html;
        riskResultsDiv.style.display = '';

        riskResultsDiv.addEventListener('click', function (e) {
            var btn = e.target.closest('.csdt-assess-risk-btn');
            if (!btn || btn.disabled) return;
            var idx     = btn.getAttribute('data-idx');
            var slug    = btn.getAttribute('data-slug');
            var name    = btn.getAttribute('data-name');
            var current = btn.getAttribute('data-current');
            var newVer  = btn.getAttribute('data-new');

            btn.disabled    = true;
            btn.textContent = '⏳…';

            post('csdt_update_risk_assess', { slug: slug, name: name, current_version: current, new_version: newVer })
                .then(function (res) {
                    var risk   = (res.success && res.data && res.data.risk) ? res.data.risk : 'minor';
                    var reason = (res.success && res.data && res.data.reason) ? res.data.reason : '';
                    var rb     = RISK_BADGE[risk] || RISK_BADGE.minor;

                    var badgeCell  = riskResultsDiv.querySelector('.risk-badge-cell-' + idx);
                    var reasonRow  = riskResultsDiv.querySelector('.risk-reason-row-' + idx);
                    var reasonCell = riskResultsDiv.querySelector('.risk-reason-cell-' + idx);

                    if (badgeCell) {
                        badgeCell.innerHTML =
                            '<span style="background:' + rb.bg + ';border:1px solid ' + rb.border + ';color:' + rb.badge + ';font-weight:700;font-size:.78em;padding:3px 10px;border-radius:20px;white-space:nowrap;">' +
                            escHtml(rb.label) + '</span>';
                    }
                    if (reasonCell) {
                        reasonCell.innerHTML = '<em style="color:#6b7280;">' + escHtml(rb.text) + '</em>' + ( reason ? ' ' + escHtml(reason) : '' );
                    }
                    if (reasonRow) reasonRow.style.display = '';

                    btn.textContent = 'Re-assess';
                    btn.disabled    = false;
                })
                .catch(function () {
                    btn.textContent = 'Assess Risk';
                    btn.disabled    = false;
                });
        });
    }

    // ── Database Intelligence Engine ─────────────────────────────────────────

    var dbScanBtn     = document.getElementById('csdt-db-intelligence-scan-btn');
    var dbScanningMsg = document.getElementById('csdt-db-intelligence-scanning');
    var dbResultsDiv  = document.getElementById('csdt-db-intelligence-results');

    if (dbScanBtn) {
        dbScanBtn.addEventListener('click', function () {
            dbScanBtn.disabled    = true;
            dbScanBtn.textContent = '⏳ Scanning…';
            if (dbScanningMsg) dbScanningMsg.style.display = '';
            if (dbResultsDiv)  dbResultsDiv.style.display  = 'none';

            post('csdt_db_intelligence_scan').then(function (res) {
                dbScanBtn.disabled  = false;
                dbScanBtn.innerHTML = '🔍 Analyse Database';
                if (dbScanningMsg) dbScanningMsg.style.display = 'none';

                if (!res.success) {
                    if (dbResultsDiv) {
                        dbResultsDiv.innerHTML = '<p style="color:#dc2626;font-size:.9em;">Scan failed — please reload and try again.</p>';
                        dbResultsDiv.style.display = '';
                    }
                    return;
                }
                renderDbIntelligence(res.data);
            }).catch(function () {
                dbScanBtn.disabled  = false;
                dbScanBtn.innerHTML = '🔍 Analyse Database';
                if (dbScanningMsg) dbScanningMsg.style.display = 'none';
                if (dbResultsDiv) {
                    dbResultsDiv.innerHTML = '<p style="color:#dc2626;font-size:.9em;">Request failed — please reload and try again.</p>';
                    dbResultsDiv.style.display = '';
                }
            });
        });
    }

    var DB_SEV_COLOR = {
        high:   { bg: '#fff7ed', border: '#fed7aa', badge: '#ea580c', text: '#7c2d12' },
        medium: { bg: '#fefce8', border: '#fde68a', badge: '#ca8a04', text: '#713f12' },
        low:    { bg: '#f0fdf4', border: '#86efac', badge: '#16a34a', text: '#14532d' },
        info:   { bg: '#f0f9ff', border: '#7dd3fc', badge: '#0284c7', text: '#0c4a6e' },
    };

    function renderDbIntelligence(data) {
        if (!dbResultsDiv) return;
        var stats    = data.stats    || {};
        var findings = data.findings || [];

        // Stats cards
        var totalMb     = stats.total_db_kb     ? (stats.total_db_kb / 1024).toFixed(1)     : '—';
        var autoloadKb  = stats.autoload_total_kb != null ? stats.autoload_total_kb  : '—';
        var revisionsK  = stats.revisions_count  != null ? stats.revisions_count.toLocaleString() : '—';
        var orphansN    = stats.orphaned_postmeta != null ? stats.orphaned_postmeta.toLocaleString() : '—';

        var statsHtml =
            '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;margin-bottom:24px;">' +
            dbStatCard('Total DB', totalMb + ' MB', '#0f172a') +
            dbStatCard('Autoload', autoloadKb + ' KB', autoloadKb > 500 ? '#dc2626' : '#16a34a') +
            dbStatCard('Revisions', revisionsK, stats.revisions_count > 500 ? '#ea580c' : '#374151') +
            dbStatCard('Orphan Meta', orphansN, stats.orphaned_postmeta > 50 ? '#ea580c' : '#374151') +
            '</div>';

        // Top autoloaded table
        var topHtml = '';
        if (stats.top_autoloaded && stats.top_autoloaded.length) {
            topHtml =
                '<details style="margin-bottom:20px;">' +
                '<summary style="cursor:pointer;font-size:.88em;font-weight:600;color:#374151;padding:8px 0;">📋 Top ' + stats.top_autoloaded.length + ' autoloaded options by size</summary>' +
                '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;margin-top:8px;">' +
                '<table style="width:100%;border-collapse:collapse;font-size:.84em;">' +
                '<thead><tr style="background:#f8fafc;"><th style="padding:7px 12px;text-align:left;color:#374151;border-bottom:1px solid #e5e7eb;">Option name</th><th style="padding:7px 12px;text-align:right;color:#374151;border-bottom:1px solid #e5e7eb;white-space:nowrap;">Size (KB)</th></tr></thead><tbody>';
            stats.top_autoloaded.forEach(function (r, i) {
                var bg = i % 2 === 0 ? '#fff' : '#f8fafc';
                topHtml += '<tr style="background:' + bg + ';border-bottom:1px solid #f1f5f9;">' +
                    '<td style="padding:7px 12px;color:#374151;word-break:break-all;">' + escHtml(r.option_name) + '</td>' +
                    '<td style="padding:7px 12px;text-align:right;font-weight:600;color:#0f172a;">' + escHtml(String(r.size_kb)) + '</td>' +
                    '</tr>';
            });
            topHtml += '</tbody></table></div></details>';
        }

        // Findings
        var findingsHtml = '<div style="display:flex;flex-direction:column;gap:12px;" id="csdt-db-findings">';
        findings.forEach(function (f, i) {
            var sev = (f.severity || 'info').toLowerCase();
            var col = DB_SEV_COLOR[sev] || DB_SEV_COLOR.info;
            var fixBtn = '';
            if (f.fix_action) {
                fixBtn = '<button class="csdt-db-fix-btn" data-fix-id="' + escHtml(f.fix_action) + '" data-idx="' + i + '" ' +
                    'style="background:#10b981;color:#fff;border:none;font-size:.8em;font-weight:700;padding:6px 14px;border-radius:6px;cursor:pointer;margin-top:8px;">⚡ Fix It</button>' +
                    '<span class="csdt-db-fix-status-' + i + '" style="display:none;margin-left:8px;font-size:.82em;"></span>';
            }
            findingsHtml +=
                '<div style="background:' + col.bg + ';border:1px solid ' + col.border + ';border-radius:8px;padding:14px 18px;">' +
                '<div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">' +
                '<span style="background:' + col.badge + ';color:#fff;font-size:.7em;font-weight:700;padding:2px 8px;border-radius:20px;text-transform:uppercase;">' + escHtml(sev) + '</span>' +
                '<span style="font-weight:700;color:#0f172a;font-size:.93em;">' + escHtml(f.title) + '</span>' +
                '</div>' +
                '<p style="margin:0 0 6px;color:#4b5563;font-size:.87em;line-height:1.6;">' + escHtml(f.detail) + '</p>' +
                '<div style="background:rgba(255,255,255,.7);border-left:2px solid ' + col.badge + ';padding:7px 11px;border-radius:0 4px 4px 0;font-size:.84em;color:#374151;">' +
                '<strong style="color:' + col.text + ';">Fix: </strong>' + escHtml(f.fix) +
                '</div>' +
                fixBtn +
                '</div>';
        });
        findingsHtml += '</div>';

        dbResultsDiv.innerHTML = statsHtml + topHtml + findingsHtml;
        dbResultsDiv.style.display = '';

        // Fix button delegation
        dbResultsDiv.addEventListener('click', function (e) {
            var btn = e.target.closest('.csdt-db-fix-btn');
            if (!btn || btn.disabled) return;
            var fixId = btn.getAttribute('data-fix-id');
            var idx   = btn.getAttribute('data-idx');
            var statusEl = dbResultsDiv.querySelector('.csdt-db-fix-status-' + idx);
            btn.disabled    = true;
            btn.textContent = '⏳ Fixing…';
            if (statusEl) statusEl.style.display = 'none';

            post('csdt_db_intelligence_fix', { fix_id: fixId }).then(function (res) {
                if (res && res.success) {
                    btn.textContent    = '✅ Done';
                    btn.style.background = '#6b7280';
                    if (statusEl) {
                        statusEl.style.display = 'inline';
                        statusEl.style.color   = '#16a34a';
                        statusEl.textContent   = res.data && res.data.message ? res.data.message : 'Done';
                    }
                } else {
                    btn.disabled    = false;
                    btn.textContent = '⚡ Fix It';
                    if (statusEl) {
                        statusEl.style.display = 'inline';
                        statusEl.style.color   = '#dc2626';
                        statusEl.textContent   = (res && res.data) || 'Error';
                    }
                }
            }).catch(function () {
                btn.disabled    = false;
                btn.textContent = '⚡ Fix It';
                if (statusEl) {
                    statusEl.style.display = 'inline';
                    statusEl.style.color   = '#dc2626';
                    statusEl.textContent   = 'Request failed';
                }
            });
        });
    }

    function dbStatCard(label, value, color) {
        return '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px 16px;text-align:center;">' +
            '<div style="font-size:1.4em;font-weight:800;color:' + color + ';line-height:1.2;">' + escHtml(String(value)) + '</div>' +
            '<div style="font-size:.75em;color:#6b7280;font-weight:600;margin-top:4px;">' + escHtml(label) + '</div>' +
            '</div>';
    }

    // ── Orphaned Table Cleanup ───────────────────────────────────────────────

    var orphanScanBtn  = document.getElementById('csdt-orphan-scan-btn');
    var orphanScanning = document.getElementById('csdt-orphan-scanning');
    var orphanResults  = document.getElementById('csdt-orphan-results');

    function fmtSize(kb) {
        if (!kb) return '0 KB';
        return kb >= 1024 ? (kb / 1024).toFixed(1) + ' MB' : kb + ' KB';
    }

    function renderOrphanResults(tables) {
        if (!orphanResults) return;
        if (!tables || !tables.length) {
            orphanResults.innerHTML =
                '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:14px 16px;">' +
                '<p style="margin:0;font-weight:700;color:#15803d;">✓ No orphaned tables found</p>' +
                '<p style="margin:6px 0 0;font-size:.88em;color:#166534;">All non-core tables appear to belong to active plugins.</p>' +
                '</div>';
            orphanResults.style.display = '';
            return;
        }

        var totalKb   = tables.reduce(function (s, t) { return s + (t.size_kb || 0); }, 0);
        var totalRows = tables.reduce(function (s, t) { return s + (t.rows || 0); }, 0);

        var html = '<div style="margin-bottom:14px;display:flex;gap:12px;flex-wrap:wrap;">' +
            dbStatCard(tables.length, 'orphaned tables', '#dc2626') +
            dbStatCard(totalRows.toLocaleString(), 'total rows', '#6b7280') +
            dbStatCard(fmtSize(totalKb), 'wasted space', '#ca8a04') +
            '</div>';

        html += '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">';
        html += '<table style="width:100%;border-collapse:collapse;font-size:.85em;">';
        html += '<thead><tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb;">' +
            '<th style="padding:8px 10px;width:32px;"><input type="checkbox" id="csdt-orphan-select-all" title="Select all"></th>' +
            '<th style="padding:8px 10px;text-align:left;font-weight:600;color:#374151;">Table</th>' +
            '<th style="padding:8px 10px;text-align:left;font-weight:600;color:#374151;">Likely Plugin</th>' +
            '<th style="padding:8px 10px;text-align:right;font-weight:600;color:#374151;">Rows</th>' +
            '<th style="padding:8px 10px;text-align:right;font-weight:600;color:#374151;">Size</th>' +
            '</tr></thead><tbody>';

        tables.forEach(function (t) {
            html += '<tr style="border-top:1px solid #f3f4f6;">' +
                '<td style="padding:7px 10px;"><input type="checkbox" class="csdt-orphan-chk" value="' + escHtml(t.table) + '"></td>' +
                '<td style="padding:7px 10px;font-family:monospace;font-size:.9em;color:#1e293b;">' + escHtml(t.table) + '</td>' +
                '<td style="padding:7px 10px;color:#6b7280;">' + escHtml(t.plugin) + '</td>' +
                '<td style="padding:7px 10px;text-align:right;color:#374151;">' + (t.rows || 0).toLocaleString() + '</td>' +
                '<td style="padding:7px 10px;text-align:right;color:#374151;">' + fmtSize(t.size_kb) + '</td>' +
                '</tr>';
        });

        html += '</tbody></table></div>';
        html += '<div style="margin-top:12px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">' +
            '<button id="csdt-orphan-drop-btn" class="cs-btn-primary" style="background:#dc2626;border-color:#b91c1c;" disabled>' +
            '🗑 Drop Selected</button>' +
            '<span id="csdt-orphan-drop-status" style="font-size:.85em;"></span>' +
            '</div>';

        orphanResults.innerHTML = html;
        orphanResults.style.display = '';

        var selectAll  = document.getElementById('csdt-orphan-select-all');
        var dropBtn    = document.getElementById('csdt-orphan-drop-btn');
        var dropStatus = document.getElementById('csdt-orphan-drop-status');

        function updateDropBtn() {
            var checked = orphanResults.querySelectorAll('.csdt-orphan-chk:checked');
            dropBtn.disabled    = checked.length === 0;
            dropBtn.textContent = checked.length
                ? '🗑 Drop ' + checked.length + ' table' + (checked.length === 1 ? '' : 's')
                : '🗑 Drop Selected';
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                orphanResults.querySelectorAll('.csdt-orphan-chk').forEach(function (c) { c.checked = selectAll.checked; });
                updateDropBtn();
            });
        }
        orphanResults.querySelectorAll('.csdt-orphan-chk').forEach(function (c) {
            c.addEventListener('change', updateDropBtn);
        });

        if (dropBtn) {
            dropBtn.addEventListener('click', function () {
                var checked = Array.prototype.slice.call(orphanResults.querySelectorAll('.csdt-orphan-chk:checked'));
                if (!checked.length) return;
                var names = checked.map(function (c) { return c.value; });
                if (!confirm('Permanently DROP ' + names.length + ' table(s)?\n\n' + names.join('\n') + '\n\nThis cannot be undone.')) return;

                dropBtn.disabled    = true;
                dropBtn.textContent = '⏳ Dropping…';
                if (dropStatus) { dropStatus.style.color = '#6b7280'; dropStatus.textContent = ''; }

                post('csdt_db_drop_tables', { tables: JSON.stringify(names) })
                    .then(function (res) {
                        if (res && res.success) {
                            if (dropStatus) { dropStatus.style.color = '#16a34a'; dropStatus.textContent = '✓ ' + (res.data.message || 'Done'); }
                            runOrphanScan();
                        } else {
                            dropBtn.disabled    = false;
                            dropBtn.textContent = '🗑 Drop Selected';
                            var msg = (res && res.data && res.data.message) || (res && res.data) || 'Error';
                            if (dropStatus) { dropStatus.style.color = '#dc2626'; dropStatus.textContent = '✕ ' + msg; }
                        }
                    })
                    .catch(function () {
                        dropBtn.disabled    = false;
                        dropBtn.textContent = '🗑 Drop Selected';
                        if (dropStatus) { dropStatus.style.color = '#dc2626'; dropStatus.textContent = 'Request failed'; }
                    });
            });
        }
    }

    function runOrphanScan() {
        var btn = document.getElementById('csdt-orphan-scan-btn');
        var scanning = document.getElementById('csdt-orphan-scanning');
        var results  = document.getElementById('csdt-orphan-results');
        if (!btn) return;
        btn.disabled    = true;
        btn.textContent = '⏳ Scanning…';
        if (scanning) scanning.style.display = '';
        if (results)  results.style.display  = 'none';

        post('csdt_db_orphaned_scan').then(function (res) {
            btn.disabled  = false;
            btn.innerHTML = '🔍 Scan for Orphaned Tables';
            if (scanning) scanning.style.display = 'none';
            if (!results) return;
            if (!res.success) {
                results.innerHTML = '<p style="color:#dc2626;font-size:.9em;">Scan failed: ' + escHtml((res && res.data) || 'unknown error') + '</p>';
                results.style.display = '';
                return;
            }
            renderOrphanResults(res.data.tables);
        }).catch(function (err) {
            btn.disabled  = false;
            btn.innerHTML = '🔍 Scan for Orphaned Tables';
            if (scanning) scanning.style.display = 'none';
            if (results) {
                results.innerHTML = '<p style="color:#dc2626;font-size:.9em;">Request failed — please reload and try again.</p>';
                results.style.display = '';
            }
        });
    }

    // Use event delegation so it works regardless of getElementById timing
    document.addEventListener('click', function (e) {
        if (e.target && e.target.id === 'csdt-orphan-scan-btn') {
            runOrphanScan();
        }
    });

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
