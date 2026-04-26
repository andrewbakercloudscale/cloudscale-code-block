/* global csdtVulnScan */
(function () {
    'use strict';

    var cfg      = csdtVulnScan;
    var ajaxUrl  = cfg.ajaxUrl;
    var nonce    = cfg.nonce;

    var POLL_INTERVAL = 3000; // ms between status polls

    // Maps AI audit finding text patterns → Quick Fix modals in the Quick Fixes panel.
    // When a finding title/detail matches, a "Fix It →" button is injected into the card.
    var QUICK_FIX_MAP = [
        { pattern: /database.*prefix|table.*prefix|wp_.*prefix|prefix.*(wp_|default)/i, label: 'Fix Prefix\u2026', modal: 'csdt-db-prefix-modal' },
        { pattern: /fail2ban|brute.?force.*log|log.*brute.?force/i,                     label: 'Configure\u2026',   modal: 'csdt-fail2ban-modal' },
    ];

    // ── Helpers ───────────────────────────────────────────────────────

    function post(action, params) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', nonce);
        Object.keys(params).forEach(function (k) { fd.append(k, params[k]); });
        return fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function timeSince(unixTs) {
        var secs = Math.floor(Date.now() / 1000) - unixTs;
        if (secs < 120)             return secs + 's';
        var mins = Math.floor(secs / 60);
        if (mins < 120)             return mins + 'm';
        var hours = Math.floor(mins / 60);
        if (hours < 48)             return hours + 'h';
        return Math.floor(hours / 24) + 'd';
    }

    function scoreClass(score) {
        if (score >= 90) return 'cs-audit-score-excellent';
        if (score >= 75) return 'cs-audit-score-good';
        if (score >= 55) return 'cs-audit-score-fair';
        if (score >= 35) return 'cs-audit-score-poor';
        return 'cs-audit-score-critical';
    }

    // ── PDF export (print-to-PDF — no external dependencies) ────────

    function exportSecurityPDF(data, scanType) {
        var r     = data.report;
        var now   = new Date().toLocaleString();
        var site  = window.location.hostname;
        var title = scanType === 'deep' ? 'AI Deep Dive Cyber Audit Report' : 'AI Cyber Audit Report';
        var sc    = r.score || 0;
        var scoreColor = sc >= 90 ? '#16a34a' : sc >= 75 ? '#4ade80' : sc >= 55 ? '#d97706' : sc >= 35 ? '#f97316' : '#dc2626';

        function secBg(key) {
            return key === 'critical' ? '#dc2626' : key === 'high' ? '#d97706' : key === 'medium' ? '#ca8a04' : key === 'low' ? '#2563eb' : '#16a34a';
        }

        function esc(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

        var sectionsHtml = '';
        [['critical','Critical'],['high','High'],['medium','Medium'],['low','Low'],['good','Good Practices']].forEach(function(pair) {
            var items = r[pair[0]];
            if (!items || !items.length) return;
            sectionsHtml += '<div style="margin:0 0 18px"><div style="background:' + secBg(pair[0]) + ';color:#fff;font-weight:700;font-size:11px;padding:5px 10px;border-radius:4px 4px 0 0;letter-spacing:.05em;">' + pair[1].toUpperCase() + ' (' + items.length + ')</div>';
            items.forEach(function(item) {
                sectionsHtml += '<div style="border:1px solid #e5e7eb;border-top:none;padding:10px 12px;">';
                sectionsHtml += '<div style="font-weight:600;font-size:13px;color:#111;margin-bottom:4px;">' + esc(item.title) + '</div>';
                if (item.detail) sectionsHtml += '<div style="font-size:12px;color:#374151;margin-bottom:4px;">' + esc(item.detail) + '</div>';
                if (item.fix)    sectionsHtml += '<div style="font-size:11px;color:#6b7280;font-style:italic;">Fix: ' + esc(item.fix) + '</div>';
                sectionsHtml += '</div>';
            });
            sectionsHtml += '</div>';
        });

        var html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' + esc(title) + '</title>' +
            '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#111;margin:0;padding:32px 40px;max-width:900px;}' +
            'h1{font-size:22px;font-weight:800;color:#0f172a;margin:0 0 4px;}' +
            '.meta{font-size:12px;color:#6b7280;margin-bottom:24px;}' +
            '.score-row{display:flex;align-items:center;gap:20px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 20px;margin-bottom:24px;}' +
            '.score-circle{width:60px;height:60px;border-radius:50%;border:3px solid ' + scoreColor + ';display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0;}' +
            '.score-num{font-size:20px;font-weight:800;color:' + scoreColor + ';line-height:1;}' +
            '.score-lbl{font-size:9px;color:' + scoreColor + ';font-weight:600;text-transform:uppercase;}' +
            '.summary{font-size:13px;color:#374151;line-height:1.65;}' +
            '@media print{body{padding:0;} @page{margin:20mm 18mm;}}</style></head><body>' +
            '<h1>' + esc(title) + '</h1>' +
            '<div class="meta">' + esc(site) + ' &nbsp;·&nbsp; ' + esc(now) + ' &nbsp;·&nbsp; Model: ' + esc(data.model_used || '?') + '</div>' +
            '<div class="score-row"><div class="score-circle"><div class="score-num">' + sc + '</div><div class="score-lbl">' + esc(r.score_label || '') + '</div></div>' +
            '<div class="summary">' + esc(r.summary || '') + '</div></div>' +
            sectionsHtml +
            '</body></html>';

        var win = window.open('', '_blank');
        if (!win) { alert('Please allow pop-ups for this page, then try again.'); return; }
        win.document.write(html);
        win.document.close();
        win.focus();
        setTimeout(function() { win.print(); }, 400);
    }

    // ── Render audit report ───────────────────────────────────────────

    function renderReport(data, container, scanType) {
        if (!container) return;
        var r   = data.report;
        var cls = scoreClass(r.score);
        var age = data.from_cache ? ' · cached ' + timeSince(data.scanned_at) + ' ago' : '';
        var html = '';

        html += '<div style="margin:0 0 14px"><button class="cs-audit-pdf-btn button button-secondary" data-scan-type="' + escHtml(scanType || 'standard') + '">&#8595; Download PDF Report</button></div>';
        html += '<div class="cs-audit-header">';
        html += '<div class="cs-audit-score-circle ' + cls + '">';
        html += '<span class="cs-audit-score-num">' + escHtml(r.score) + '</span>';
        html += '<span class="cs-audit-score-lbl">' + escHtml(r.score_label || '') + '</span>';
        html += '</div>';
        html += '<div class="cs-audit-meta">';
        html += '<p class="cs-audit-summary-text">' + escHtml(r.summary || '') + '</p>';
        html += '<span class="cs-audit-meta-line">Model: ' + escHtml(data.model_used || '') + age + '</span>';
        html += '</div>';
        html += '</div>';

        var secs = [
            { key: 'critical', label: 'Critical',       cls: 'cs-audit-sec-critical' },
            { key: 'high',     label: 'High',           cls: 'cs-audit-sec-high'     },
            { key: 'medium',   label: 'Medium',         cls: 'cs-audit-sec-medium'   },
            { key: 'low',      label: 'Low',            cls: 'cs-audit-sec-low'      },
            { key: 'good',     label: 'Good Practices', cls: 'cs-audit-sec-good'     },
        ];

        secs.forEach(function (sec) {
            var items = r[sec.key];
            if (!items || !items.length) return;
            html += '<div class="cs-audit-section ' + sec.cls + '">';
            html += '<h4 class="cs-audit-section-title">' + escHtml(sec.label) + ' (' + items.length + ')</h4>';
            if (sec.key === 'good') {
                items.forEach(function (g) {
                    html += '<div class="cs-audit-good-item">';
                    html += '<span class="cs-audit-good-check">✓</span>';
                    html += '<div><strong>' + escHtml(g.title) + '</strong>';
                    if (g.detail) html += ' — ' + escHtml(g.detail);
                    html += '</div></div>';
                });
            } else {
                items.forEach(function (issue) {
                    var haystack = (issue.title || '') + ' ' + (issue.detail || '');
                    var qf = null;
                    QUICK_FIX_MAP.forEach(function (m) { if (!qf && m.pattern.test(haystack)) qf = m; });
                    html += '<div class="cs-audit-issue">';
                    html += '<div class="cs-audit-issue-title" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">';
                    html += '<span>' + escHtml(issue.title) + '</span>';
                    if (qf) html += '<button type="button" class="button button-small cs-audit-qf-btn" data-modal="' + escHtml(qf.modal) + '" style="font-size:11px;padding:1px 8px;height:auto;line-height:1.6">' + escHtml(qf.label) + ' \u2192</button>';
                    html += '</div>';
                    if (issue.detail) html += '<div class="cs-audit-issue-detail">' + escHtml(issue.detail) + '</div>';
                    if (issue.fix)    html += '<div class="cs-audit-issue-fix">' + escHtml(issue.fix) + '</div>';
                    html += '</div>';
                });
            }
            html += '</div>';
        });

        // Code triage section (deep scan only)
        if (scanType === 'deep' && data.code_triage && !data.code_triage.skipped && data.code_triage.results && data.code_triage.results.length) {
            var triage = data.code_triage;
            var confirmed  = triage.results.filter(function (x) { return x.verdict === 'confirmed'; });
            var needsCtx   = triage.results.filter(function (x) { return x.verdict === 'needs_context'; });
            var falsePos   = triage.results.filter(function (x) { return x.verdict === 'false_positive'; });
            html += '<div class="cs-audit-section cs-audit-sec-code-triage">';
            html += '<h4 class="cs-audit-section-title">Code Triage — ' + triage.snippets_triaged + ' snippet' + (triage.snippets_triaged !== 1 ? 's' : '') + ' analysed';
            if (confirmed.length)  html += ' &nbsp;·&nbsp; <span style="color:#dc2626">' + confirmed.length + ' confirmed</span>';
            if (needsCtx.length)   html += ' &nbsp;·&nbsp; <span style="color:#d97706">' + needsCtx.length + ' inconclusive</span>';
            if (falsePos.length)   html += ' &nbsp;·&nbsp; <span style="color:#22c55e">' + falsePos.length + ' false positive' + (falsePos.length !== 1 ? 's' : '') + '</span>';
            html += '</h4>';

            triage.results.forEach(function (item) {
                var isFp = item.verdict === 'false_positive';
                var isConfirmed = item.verdict === 'confirmed';
                var severityColour = { critical: '#dc2626', high: '#ea580c', medium: '#d97706', low: '#ca8a04', none: '#888' };
                var badgeColor = isConfirmed ? (severityColour[item.severity] || '#888') : (isFp ? '#22c55e' : '#d97706');
                var badgeText  = isConfirmed ? (item.severity || 'confirmed') : (isFp ? 'false positive' : 'needs context');
                html += '<div class="cs-triage-item" style="margin:8px 0;padding:10px 12px;border-radius:6px;background:' + (isFp ? '#f0fdf4' : isConfirmed ? '#fff5f5' : '#fffbeb') + ';border-left:3px solid ' + badgeColor + '">';
                html += '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">';
                html += '<span style="font-size:11px;font-weight:700;text-transform:uppercase;color:#fff;background:' + badgeColor + ';padding:2px 7px;border-radius:3px">' + escHtml(badgeText) + '</span>';
                if (item.type && isConfirmed) html += '<span style="font-size:12px;font-weight:600;color:#111">' + escHtml(item.type) + '</span>';
                html += '<span style="font-size:11px;color:#666;font-family:monospace">' + escHtml(item.plugin) + ' / ' + escHtml(item.file) + ':' + escHtml(String(item.line)) + '</span>';
                html += '</div>';
                if (item.explanation) html += '<div style="font-size:13px;color:#333;margin:3px 0">' + escHtml(item.explanation) + '</div>';
                if (isConfirmed && item.fix) html += '<div class="cs-audit-issue-fix" style="margin-top:4px">' + escHtml(item.fix) + '</div>';
                html += '</div>';
            });
            html += '</div>';
        } else if (scanType === 'deep' && data.code_triage && data.code_triage.skipped && data.code_triage.reason === 'no_findings') {
            html += '<div class="cs-audit-section cs-audit-sec-good"><h4 class="cs-audit-section-title">Code Triage</h4>';
            html += '<div class="cs-audit-good-item"><span class="cs-audit-good-check">✓</span><div>No suspicious patterns found in active plugin code — triage not required.</div></div></div>';
        }

        container.innerHTML = html;

        var pdfBtn = container.querySelector('.cs-audit-pdf-btn');
        if (pdfBtn) {
            pdfBtn.addEventListener('click', function () { exportSecurityPDF(data, scanType); });
        }

        container.querySelectorAll('.cs-audit-qf-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var modalId = btn.getAttribute('data-modal');
                var modal = document.getElementById(modalId);
                if (modal) modal.style.display = 'flex';
            });
        });
    }

    // ── Progress bar ──────────────────────────────────────────────────

    // Eased fake-progress: advances quickly to ~80% then slows sharply.
    // Each call bumps by a decreasing step so it never reaches 100% on its own.
    function ProgressBar(progressEl) {
        var fill = progressEl ? progressEl.querySelector('.cs-scan-progress-fill') : null;
        var pct  = 0;
        if (progressEl) progressEl.classList.add('is-active');
        if (fill) { fill.style.transition = 'none'; fill.style.width = '0%'; }

        this.tick = function () {
            var step = pct < 40 ? 12 : pct < 65 ? 7 : pct < 80 ? 3 : 0.8;
            pct = Math.min(pct + step, 92);
            if (fill) { fill.style.transition = 'width 2.8s ease'; fill.style.width = pct + '%'; }
        };

        this.complete = function () {
            if (fill) { fill.style.transition = 'width 0.4s ease'; fill.style.width = '100%'; }
            setTimeout(function () {
                if (progressEl) progressEl.classList.remove('is-active');
                if (fill) { fill.style.transition = 'none'; fill.style.width = '0%'; }
            }, 500);
        };

        this.reset = function () {
            if (progressEl) progressEl.classList.remove('is-active');
            if (fill) { fill.style.transition = 'none'; fill.style.width = '0%'; }
        };
    }

    // ── Polling ───────────────────────────────────────────────────────

    function startPolling(type, scanBtn, cancelBtn, statusEl, resultsEl, progressEl) {
        var bar = new ProgressBar(progressEl);
        bar.tick();
        if (cancelBtn) cancelBtn.style.display = '';

        function finish() {
            if (cancelBtn) cancelBtn.style.display = 'none';
            if (scanBtn)   scanBtn.disabled = false;
        }

        var timer = setInterval(function () {
            bar.tick();
            post('csdt_devtools_scan_status', { type: type })
                .then(function (res) {
                    if (!res.success) return;
                    var d = res.data;
                    if (d.status === 'running') return;

                    clearInterval(timer);
                    finish();

                    if (d.status === 'complete') {
                        bar.complete();
                        if (statusEl) { statusEl.textContent = ''; statusEl.className = 'cs-vuln-inline-msg'; }
                        if (resultsEl && d.data) {
                            renderReport(d.data, resultsEl, type);
                            resultsEl.style.display = 'block';
                        }
                    } else if (d.status === 'error') {
                        bar.reset();
                        if (statusEl) { statusEl.textContent = '❌ ' + (d.message || 'Scan failed.'); statusEl.className = 'cs-vuln-inline-msg cs-vuln-msg-err'; }
                    } else {
                        bar.reset();
                        if (statusEl) { statusEl.textContent = ''; statusEl.className = 'cs-vuln-inline-msg'; }
                    }
                })
                .catch(function () {});
        }, POLL_INTERVAL);

        if (cancelBtn) {
            cancelBtn.onclick = function () {
                clearInterval(timer);
                finish();
                bar.reset();
                if (statusEl) { statusEl.textContent = ''; statusEl.className = 'cs-vuln-inline-msg'; }
                post('csdt_devtools_cancel_scan', { type: type }).catch(function () {});
            };
        }
    }

    // ── Standard scan ────────────────────────────────────────────────

    function runScan(cacheOnly) {
        var scanBtn    = document.getElementById('cs-vuln-scan-btn');
        var statusEl   = document.getElementById('cs-vuln-scan-status');
        var resultsEl  = document.getElementById('cs-vuln-results');
        var progressEl = document.getElementById('cs-vuln-progress');

        if (cacheOnly) {
            post('csdt_devtools_vuln_scan', { cache_only: '1' })
                .then(function (res) {
                    if (!res.success || res.data.no_cache) return;
                    if (resultsEl) { renderReport(res.data, resultsEl, 'standard'); resultsEl.style.display = 'block'; }
                    if (statusEl) { statusEl.textContent = ''; statusEl.className = 'cs-vuln-inline-msg'; }
                })
                .catch(function () {});
            return;
        }

        if (scanBtn)   scanBtn.disabled = true;
        if (statusEl)  { statusEl.textContent = '⏳ Running AI cyber audit…'; statusEl.className = 'cs-vuln-inline-msg'; }
        if (resultsEl) resultsEl.style.display = 'none';

        post('csdt_devtools_vuln_scan', {})
            .then(function (res) {
                if (!res.success) {
                    if (scanBtn) scanBtn.disabled = false;
                    var err = res.data && res.data.message ? res.data.message : 'Failed to start scan.';
                    if (statusEl) { statusEl.textContent = '❌ ' + err; statusEl.className = 'cs-vuln-inline-msg cs-vuln-msg-err'; }
                    return;
                }
                startPolling('standard', scanBtn, document.getElementById('cs-vuln-cancel-btn'), statusEl, resultsEl, progressEl);
            })
            .catch(function (e) {
                if (scanBtn) scanBtn.disabled = false;
                if (statusEl) { statusEl.textContent = '❌ ' + e.message; statusEl.className = 'cs-vuln-inline-msg cs-vuln-msg-err'; }
            });
    }

    // ── Deep dive scan ───────────────────────────────────────────────

    function runDeepScan(cacheOnly) {
        var scanBtn    = document.getElementById('cs-deep-scan-btn');
        var statusEl   = document.getElementById('cs-deep-scan-status');
        var resultsEl  = document.getElementById('cs-deep-results');
        var progressEl = document.getElementById('cs-deep-progress');

        if (cacheOnly) {
            post('csdt_devtools_deep_scan', { cache_only: '1' })
                .then(function (res) {
                    if (!res.success || res.data.no_cache) return;
                    if (resultsEl) { renderReport(res.data, resultsEl, 'deep'); resultsEl.style.display = 'block'; }
                    if (statusEl) { statusEl.textContent = ''; statusEl.className = 'cs-vuln-inline-msg'; }
                })
                .catch(function () {});
            return;
        }

        if (scanBtn)   scanBtn.disabled = true;
        if (statusEl)  { statusEl.textContent = '⏳ Running AI Deep Dive Cyber Audit… this may take 60–90s.'; statusEl.className = 'cs-vuln-inline-msg'; }
        if (resultsEl) resultsEl.style.display = 'none';

        post('csdt_devtools_deep_scan', {})
            .then(function (res) {
                if (!res.success) {
                    if (scanBtn) scanBtn.disabled = false;
                    var err = res.data && res.data.message ? res.data.message : 'Failed to start deep scan.';
                    if (statusEl) { statusEl.textContent = '❌ ' + err; statusEl.className = 'cs-vuln-inline-msg cs-vuln-msg-err'; }
                    return;
                }
                startPolling('deep', scanBtn, document.getElementById('cs-deep-cancel-btn'), statusEl, resultsEl, progressEl);
            })
            .catch(function (e) {
                if (scanBtn) scanBtn.disabled = false;
                if (statusEl) { statusEl.textContent = '❌ ' + e.message; statusEl.className = 'cs-vuln-inline-msg cs-vuln-msg-err'; }
            });
    }

    // ── Init ──────────────────────────────────────────────────────────

    var MODEL_OPTS = {
        anthropic: {
            standard: [
                { v: '_auto',                    l: '✨ Auto — Sonnet 4.6 (quality + speed)' },
                { v: 'claude-opus-4-7',          l: 'Claude Opus 4.7 (best quality)' },
                { v: 'claude-sonnet-4-6',        l: 'Claude Sonnet 4.6' },
                { v: 'claude-haiku-4-5-20251001', l: 'Claude Haiku 4.5 (fastest)' },
            ],
            deep: [
                { v: '_auto_deep',               l: '✨ Auto — Opus 4.7 (best quality)' },
                { v: 'claude-opus-4-7',          l: 'Claude Opus 4.7' },
                { v: 'claude-sonnet-4-6',        l: 'Claude Sonnet 4.6 (faster)' },
                { v: 'claude-haiku-4-5-20251001', l: 'Claude Haiku 4.5 (fastest)' },
            ],
        },
        gemini: {
            standard: [
                { v: '_auto',              l: '✨ Auto — Gemini 2.0 Flash' },
                { v: 'gemini-2.0-flash',   l: 'Gemini 2.0 Flash' },
                { v: 'gemini-2.0-flash-lite', l: 'Gemini 2.0 Flash Lite (cheapest)' },
                { v: 'gemini-1.5-pro',     l: 'Gemini 1.5 Pro' },
            ],
            deep: [
                { v: '_auto_deep',         l: '✨ Auto — Gemini 2.0 Flash' },
                { v: 'gemini-2.0-flash',   l: 'Gemini 2.0 Flash' },
                { v: 'gemini-1.5-pro',     l: 'Gemini 1.5 Pro (highest capability)' },
                { v: 'gemini-2.0-flash-lite', l: 'Gemini 2.0 Flash Lite (cheapest)' },
            ],
        },
    };

    document.addEventListener('DOMContentLoaded', function () {
        var providerSel       = document.getElementById('cs-sec-provider');
        var rowAnthropicKey   = document.getElementById('cs-row-anthropic-key');
        var rowGeminiKey      = document.getElementById('cs-row-gemini-key');
        var keyInput          = document.getElementById('cs-sec-api-key');
        var keyStatus         = document.getElementById('cs-sec-key-status');
        var testKeyBtn        = document.getElementById('cs-sec-test-key');
        var geminiKeyInput    = document.getElementById('cs-sec-gemini-key');
        var geminiKeyStatus   = document.getElementById('cs-sec-gemini-key-status');
        var testGeminiKeyBtn  = document.getElementById('cs-sec-test-gemini-key');
        var modelSel          = document.getElementById('cs-sec-model');
        var deepModelSel      = document.getElementById('cs-sec-deep-model');
        var deepModelBadge    = document.getElementById('cs-deep-model-badge');
        var vulnModelBadge    = document.getElementById('cs-vuln-model-badge');
        var promptArea        = document.getElementById('cs-sec-prompt');
        var copyBtn           = document.getElementById('cs-sec-copy-prompt');
        var resetBtn          = document.getElementById('cs-sec-reset-prompt');
        var saveBtn           = document.getElementById('cs-sec-save');
        var savedMsg          = document.getElementById('cs-sec-saved');
        var scanBtn           = document.getElementById('cs-vuln-scan-btn');
        var deepBtn           = document.getElementById('cs-deep-scan-btn');

        if (promptArea) promptArea.value = cfg.savedPrompt || cfg.defaultPrompt || '';
        if (scanBtn)  scanBtn.disabled  = !cfg.hasKey;
        if (deepBtn)  deepBtn.disabled  = !cfg.hasKey;

        // ── Provider / model helpers ──────────────────────────────────

        function populateSelect(sel, opts, savedVal) {
            if (!sel) return;
            sel.innerHTML = '';
            opts.forEach(function (o) {
                var opt = document.createElement('option');
                opt.value = o.v;
                opt.textContent = o.l;
                sel.appendChild(opt);
            });
            if (savedVal) sel.value = savedVal;
        }

        function applyProvider(provider) {
            var isGemini = provider === 'gemini';
            if (rowAnthropicKey) rowAnthropicKey.style.display = isGemini ? 'none' : '';
            if (rowGeminiKey)    rowGeminiKey.style.display    = isGemini ? ''     : 'none';
            var opts = MODEL_OPTS[provider] || MODEL_OPTS.anthropic;
            populateSelect(modelSel,     opts.standard, isGemini ? '_auto'      : cfg.savedModel);
            populateSelect(deepModelSel, opts.deep,     isGemini ? '_auto_deep' : cfg.savedDeepModel);
            updateModelBadges();
        }

        var MODEL_NAMES = {
            '_auto': 'Auto AI Model', '_auto_deep': 'Auto AI Model',
            'claude-opus-4-7': 'Opus 4.7', 'claude-sonnet-4-6': 'Sonnet 4.6',
            'claude-haiku-4-5-20251001': 'Haiku 4.5',
            'gemini-2.0-flash': 'Gemini Flash', 'gemini-2.0-flash-lite': 'Flash Lite',
            'gemini-1.5-pro': 'Gemini 1.5 Pro',
        };

        function updateModelBadges() {
            if (vulnModelBadge && modelSel)     vulnModelBadge.textContent = 'Using ' + (MODEL_NAMES[modelSel.value]     || modelSel.value);
            if (deepModelBadge && deepModelSel) deepModelBadge.textContent = 'Using ' + (MODEL_NAMES[deepModelSel.value] || deepModelSel.value);
        }

        // Init provider
        if (providerSel && cfg.savedProvider) providerSel.value = cfg.savedProvider;
        if (keyInput    && cfg.maskedKey)     keyInput.placeholder    = cfg.maskedKey;
        if (geminiKeyInput && cfg.maskedGemini) geminiKeyInput.placeholder = cfg.maskedGemini;
        applyProvider(providerSel ? providerSel.value : 'anthropic');

        if (providerSel) {
            providerSel.addEventListener('change', function () { applyProvider(this.value); });
        }
        if (modelSel)     modelSel.addEventListener('change',     updateModelBadges);
        if (deepModelSel) deepModelSel.addEventListener('change', updateModelBadges);

        // ── Test key buttons ──────────────────────────────────────────

        function testKey(btn, inputEl, statusEl, provider) {
            if (!btn) return;
            btn.addEventListener('click', function () {
                var key = inputEl ? inputEl.value.trim() : '';
                btn.disabled = true;
                if (statusEl) { statusEl.textContent = 'Testing…'; statusEl.className = 'cs-sec-key-status'; }
                post('csdt_devtools_security_test_key', Object.assign({ provider: provider }, key ? { api_key: key } : {}))
                    .then(function (res) {
                        btn.disabled = false;
                        if (statusEl) {
                            statusEl.textContent = res.success ? (res.data.message || '✓ Valid') : ('✗ ' + (res.data && res.data.message ? res.data.message : 'Invalid'));
                            statusEl.className   = 'cs-sec-key-status ' + (res.success ? 'ok' : 'err');
                        }
                    })
                    .catch(function () {
                        btn.disabled = false;
                        if (statusEl) { statusEl.textContent = '✗ Connection error'; statusEl.className = 'cs-sec-key-status err'; }
                    });
            });
        }

        testKey(testKeyBtn,       keyInput,       keyStatus,       'anthropic');
        testKey(testGeminiKeyBtn, geminiKeyInput, geminiKeyStatus, 'gemini');

        // ── Prompt ────────────────────────────────────────────────────

        if (copyBtn && promptArea) {
            copyBtn.addEventListener('click', function () {
                navigator.clipboard.writeText(promptArea.value).then(function () {
                    var orig = copyBtn.textContent;
                    copyBtn.textContent = '✓ Copied';
                    setTimeout(function () { copyBtn.textContent = orig; }, 1500);
                });
            });
        }

        if (resetBtn && promptArea) {
            resetBtn.addEventListener('click', function () { promptArea.value = cfg.defaultPrompt || ''; });
        }

        // ── Save ──────────────────────────────────────────────────────

        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                saveBtn.disabled = true;
                if (savedMsg) savedMsg.style.opacity = '0';

                var provider = providerSel ? providerSel.value : 'anthropic';
                var params = {
                    provider:   provider,
                    model:      modelSel     ? modelSel.value     : '_auto',
                    deep_model: deepModelSel ? deepModelSel.value : '_auto_deep',
                    prompt:     promptArea   ? promptArea.value   : '',
                };
                var rawAnt = keyInput       ? keyInput.value.trim()       : '';
                var rawGem = geminiKeyInput ? geminiKeyInput.value.trim() : '';
                if (rawAnt) params.api_key    = rawAnt;
                if (rawGem) params.gemini_key = rawGem;

                post('csdt_devtools_vuln_save_key', params)
                    .then(function (res) {
                        saveBtn.disabled = false;
                        if (res.success) {
                            if (savedMsg) { savedMsg.style.opacity = '1'; setTimeout(function () { savedMsg.style.opacity = '0'; }, 2500); }
                            if (scanBtn) scanBtn.disabled = !res.data.has_key;
                            if (deepBtn) deepBtn.disabled = !res.data.has_key;
                            if (keyInput       && res.data.masked)       { keyInput.value = '';       keyInput.placeholder       = res.data.masked; }
                            if (geminiKeyInput && res.data.maskedGemini) { geminiKeyInput.value = ''; geminiKeyInput.placeholder = res.data.maskedGemini; }
                            if (keyStatus)      { keyStatus.textContent      = ''; keyStatus.className      = 'cs-sec-key-status'; }
                            if (geminiKeyStatus){ geminiKeyStatus.textContent = ''; geminiKeyStatus.className = 'cs-sec-key-status'; }
                        }
                    })
                    .catch(function () { saveBtn.disabled = false; });
            });
        }

        // ── Scheduled scan UI ────────────────────────────────────────

        var schedEnabled  = document.getElementById('cs-sched-enabled');
        var schedOptions  = document.getElementById('cs-sched-options');
        var schedFreq     = document.getElementById('cs-sched-freq');
        var schedType     = document.getElementById('cs-sched-type');
        var schedEmail    = document.getElementById('cs-sched-email');
        var schedNtfyUrl  = document.getElementById('cs-sched-ntfy-url');
        var schedNtfyTok  = document.getElementById('cs-sched-ntfy-token');
        var schedSaveBtn  = document.getElementById('cs-sched-save');
        var schedSavedMsg = document.getElementById('cs-sched-saved');

        if (schedEnabled && schedOptions) {
            schedEnabled.addEventListener('change', function () {
                if (schedEnabled.checked) {
                    schedOptions.style.display = 'flex';
                    schedOptions.style.flexDirection = 'column';
                    schedOptions.style.gap = '16px';
                } else {
                    schedOptions.style.display = 'none';
                }
            });
        }

        if (schedSaveBtn) {
            schedSaveBtn.addEventListener('click', function () {
                schedSaveBtn.disabled = true;
                var params = {
                    enabled:      schedEnabled && schedEnabled.checked ? '1' : '0',
                    freq:         schedFreq    ? schedFreq.value    : 'weekly',
                    type:         schedType    ? schedType.value    : 'deep',
                    email_notify: schedEmail   && schedEmail.checked ? '1' : '0',
                    ntfy_url:     schedNtfyUrl ? schedNtfyUrl.value.trim() : '',
                    ntfy_token:   schedNtfyTok ? schedNtfyTok.value.trim() : '',
                };
                post('csdt_devtools_save_schedule', params)
                    .then(function (res) {
                        schedSaveBtn.disabled = false;
                        if (res.success && schedSavedMsg) {
                            schedSavedMsg.style.opacity = '1';
                            setTimeout(function () { schedSavedMsg.style.opacity = '0'; }, 2500);
                            if (schedNtfyTok) { schedNtfyTok.value = ''; schedNtfyTok.placeholder = '••••••••'; }
                        }
                    })
                    .catch(function () { schedSaveBtn.disabled = false; });
            });
        }

        // ── Scan buttons ──────────────────────────────────────────────

        if (scanBtn) scanBtn.addEventListener('click', function () { runScan(false); });
        if (deepBtn) deepBtn.addEventListener('click', function () { runDeepScan(false); });

        // Silently pre-fill cached results on page load
        if (cfg.hasKey) {
            runScan(true);
            runDeepScan(true);
        }

        // ── Quick fix buttons (PHP-rendered initial state) ────────────
        wireQuickFixButtons();

        // ── Scan history chart ────────────────────────────────────────
        // Defer one frame so CSS layout is finalised before measuring canvas width.
        requestAnimationFrame(function () {
            renderScanHistoryChart(cfg.scanHistory || []);
        });
        // Redraw on viewport resize (orientation change, split-screen, etc.)
        var resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                renderScanHistoryChart(cfg.scanHistory || []);
            }, 150);
        });
    });

    // ── Quick Fixes ──────────────────────────────────────────────────────

    function renderQuickFixes(fixes) {
        var panel = document.getElementById('cs-quick-fixes-panel');
        if (!panel) return;
        var html = '';
        fixes.forEach(function (fix) {
            var isFixed = !!fix.fixed;
            var bg     = isFixed ? 'rgba(0,0,0,0.02)' : '#fff';
            var border = isFixed ? 'rgba(0,0,0,0.07)'  : 'rgba(0,0,0,0.12)';
            html += '<div class="cs-quick-fix-row" data-fix-id="' + escHtml(fix.id) + '" style="display:flex;align-items:flex-start;gap:12px;padding:10px 14px;margin-bottom:6px;background:' + bg + ';border-radius:6px;border:1px solid ' + border + ';">';
            html += '<div style="flex-shrink:0;font-size:16px;line-height:1.5;padding-top:1px;">' + (isFixed ? '<span style="color:#16a34a;">✓</span>' : '<span style="color:#d97706;">⚠</span>') + '</div>';
            html += '<div style="flex:1;min-width:0;">';
            html += '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">';
            html += '<div style="font-size:13px;font-weight:600;color:' + (isFixed ? '#6b7280' : '#1d2327') + ';">' + escHtml(fix.title) + '</div>';
            if (isFixed) { html += '<span style="flex-shrink:0;font-size:12px;color:#16a34a;font-weight:600;white-space:nowrap;">Fixed ✓</span>'; }
            html += '</div>';
            html += '<div style="font-size:12px;color:#50575e;margin-top:2px;">' + escHtml(fix.detail) + '</div>';
            if (!isFixed) {
                html += '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;">';
                if (fix.fix_modal) {
                    var modalStyle = fix.risk === 'destructive' ? 'background:#dc2626;border-color:#b91c1c;' : '';
                    html += '<button type="button" class="cs-btn-primary cs-btn-sm" style="' + modalStyle + '" onclick="document.getElementById(\'' + escHtml(fix.fix_modal) + '\').style.display=\'flex\';">' + escHtml(fix.fix_label) + '</button>';
                } else {
                    var btnStyle = fix.risk === 'moderate' ? 'background:#d97706;border-color:#b45309;' : (fix.risk === 'destructive' ? 'background:#dc2626;border-color:#b91c1c;' : '');
                    var confirmAttr = (fix.risk === 'moderate' && fix.confirm_msg) ? ' data-confirm-msg="' + escHtml(fix.confirm_msg) + '"' : '';
                    html += '<button type="button" class="cs-btn-primary cs-btn-sm cs-quick-fix-btn" style="' + btnStyle + '" data-fix-id="' + escHtml(fix.id) + '" data-risk="' + escHtml(fix.risk || 'safe') + '"' + confirmAttr + '>' + escHtml(fix.fix_label) + '</button>';
                }
                if (fix.dismiss_label && fix.dismiss_id) {
                    html += '<button type="button" class="cs-btn-secondary cs-btn-sm cs-quick-fix-btn" data-fix-id="' + escHtml(fix.dismiss_id) + '" style="font-size:11px;">' + escHtml(fix.dismiss_label) + '</button>';
                }
                html += '</div>';
            }
            html += '</div>';
            html += '</div>';
        });
        panel.innerHTML = html;
        wireQuickFixButtons();
    }

    function applyQuickFix(btn, fixId) {
        var orig = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Applying…';
        post('csdt_devtools_quick_fix', { fix_action: 'apply', fix_id: fixId })
            .then(function (res) {
                if (res.success && res.data && res.data.fixes) {
                    renderQuickFixes(res.data.fixes);
                    if (res.data.warning) {
                        var warn = document.createElement('div');
                        warn.style.cssText = 'margin:8px 0;padding:10px 12px;background:#fffbeb;border-left:3px solid #d97706;border-radius:4px;font-size:13px;color:#92400e';
                        warn.textContent = '⚠ ' + res.data.warning;
                        var qfWrap = document.getElementById('cs-quick-fixes-list');
                        if (qfWrap) qfWrap.insertAdjacentElement('afterend', warn);
                    }
                } else {
                    btn.disabled = false;
                    btn.textContent = orig;
                    if (res.data && typeof res.data === 'string') {
                        alert('Fix failed: ' + res.data);
                    }
                }
            })
            .catch(function () {
                btn.disabled = false;
                btn.textContent = orig;
            });
    }

    function wireQuickFixButtons() {
        var btns = document.querySelectorAll('.cs-quick-fix-btn');
        btns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var fixId      = btn.getAttribute('data-fix-id');
                var risk       = btn.getAttribute('data-risk') || 'safe';
                var confirmMsg = btn.getAttribute('data-confirm-msg') || '';

                if (risk === 'moderate' && confirmMsg) {
                    var wrap    = btn.parentNode;
                    var origHtml = wrap.innerHTML;
                    wrap.innerHTML =
                        '<span style="font-size:12px;color:#92400e;font-weight:600;display:inline-flex;align-items:center;gap:4px;">&#x26A0;&#xFE0F; ' + confirmMsg + '</span>' +
                        '<button type="button" data-qf-confirm style="background:#d97706;color:#fff;border:1px solid #b45309;font-size:12px;font-weight:600;padding:4px 12px;border-radius:4px;cursor:pointer;margin-left:8px;">Confirm</button>' +
                        '<button type="button" data-qf-cancel style="background:#fff;color:#374151;border:1px solid #d1d5db;font-size:12px;padding:4px 10px;border-radius:4px;cursor:pointer;margin-left:4px;">Cancel</button>';
                    wrap.querySelector('[data-qf-confirm]').onclick = function () {
                        wrap.innerHTML = origHtml;
                        wireQuickFixButtons();
                        var newBtn = wrap.querySelector('.cs-quick-fix-btn[data-fix-id="' + fixId + '"]');
                        if (newBtn) applyQuickFix(newBtn, fixId);
                    };
                    wrap.querySelector('[data-qf-cancel]').onclick = function () {
                        wrap.innerHTML = origHtml;
                        wireQuickFixButtons();
                    };
                    return;
                }

                applyQuickFix(btn, fixId);
            });
        });
    }

    function renderScanHistoryChart(history) {
        var canvas = document.getElementById('cs-scan-history-chart');
        if (!canvas || !history.length) { return; }

        var data = history.slice().reverse(); // oldest → newest left → right

        var dpr = window.devicePixelRatio || 1;
        var W   = canvas.offsetWidth || canvas.parentElement.offsetWidth || (window.innerWidth - 80);
        var H   = 190;
        canvas.width        = W * dpr;
        canvas.height       = H * dpr;
        canvas.style.height = H + 'px';

        var ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);

        // Single left y-axis (score 0-100). Right side is just a small margin.
        var PAD_L = 44, PAD_R = 16, PAD_T = 38, PAD_B = 38;
        var cW = W - PAD_L - PAD_R;
        var cH = H - PAD_T - PAD_B;

        var LABEL_COLOR = '#64748b';
        var GRID_COLOR  = 'rgba(0,0,0,0.07)';
        var AXIS_COLOR  = 'rgba(0,0,0,0.15)';
        var FONT        = '11px -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif';

        ctx.clearRect(0, 0, W, H);

        // ── Grid + Y-axis (score 0-100) ───────────────────────────────
        [0, 25, 50, 75, 100].forEach(function (v) {
            var y = PAD_T + cH - (v / 100) * cH;
            ctx.strokeStyle = GRID_COLOR;
            ctx.lineWidth   = 1;
            ctx.beginPath(); ctx.moveTo(PAD_L, y); ctx.lineTo(PAD_L + cW, y); ctx.stroke();
            ctx.fillStyle  = LABEL_COLOR;
            ctx.font       = FONT;
            ctx.textAlign  = 'right';
            ctx.fillText(v, PAD_L - 6, y + 4);
        });

        // Y-axis line
        ctx.strokeStyle = AXIS_COLOR;
        ctx.lineWidth   = 1;
        ctx.beginPath(); ctx.moveTo(PAD_L, PAD_T); ctx.lineTo(PAD_L, PAD_T + cH); ctx.stroke();

        // Y-axis title (rotated)
        ctx.save();
        ctx.translate(10, PAD_T + cH / 2);
        ctx.rotate(-Math.PI / 2);
        ctx.fillStyle = '#1e6fd9';
        ctx.font      = 'bold 10px -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Security Score (0–100)', 0, 0);
        ctx.restore();

        // ── X-axis: date labels ───────────────────────────────────────
        var step = cW / Math.max(data.length - 1, 1);
        // On narrow screens skip labels when they would overlap (min 36px apart)
        var labelEvery = Math.ceil(36 / Math.max(step, 1));
        ctx.fillStyle = LABEL_COLOR;
        ctx.font      = FONT;
        ctx.textAlign = 'center';
        data.forEach(function (entry, i) {
            var x = PAD_L + i * step;
            var d = new Date((entry.scanned_at || 0) * 1000);
            if (i % labelEvery === 0 || i === data.length - 1) {
                ctx.fillText((d.getMonth() + 1) + '/' + d.getDate(), x, PAD_T + cH + 14);
            }
            ctx.strokeStyle = GRID_COLOR;
            ctx.lineWidth   = 1;
            ctx.beginPath(); ctx.moveTo(x, PAD_T); ctx.lineTo(x, PAD_T + cH); ctx.stroke();
        });

        // Bottom axis line
        ctx.strokeStyle = AXIS_COLOR;
        ctx.lineWidth   = 1;
        ctx.beginPath(); ctx.moveTo(PAD_L, PAD_T + cH); ctx.lineTo(PAD_L + cW, PAD_T + cH); ctx.stroke();

        function scoreX(i) { return PAD_L + i * step; }
        function scoreY(v) { return PAD_T + cH - ((v || 0) / 100) * cH; }

        // ── Issue bars (max 25% of chart height; count labeled on top) ─
        var BAR_MAX_H = cH * 0.25;
        var maxIssues = 1;
        data.forEach(function (e) {
            var tot = (e.critical_count || 0) + (e.high_count || 0);
            if (tot > maxIssues) maxIssues = tot;
        });
        var barW = Math.max(6, Math.min(20, step * 0.4));
        data.forEach(function (e, i) {
            var hc    = e.high_count     || 0;
            var cc    = e.critical_count || 0;
            var tot   = hc + cc;
            if (!tot) return;
            var x     = scoreX(i) - barW / 2;
            var yBase = PAD_T + cH;
            var totalBarH = (tot / maxIssues) * BAR_MAX_H;
            var hBarH = (hc / tot) * totalBarH;
            var cBarH = totalBarH - hBarH;
            if (hc > 0) {
                ctx.fillStyle = 'rgba(251,146,60,0.80)';
                ctx.fillRect(x, yBase - hBarH, barW, hBarH);
            }
            if (cc > 0) {
                ctx.fillStyle = 'rgba(220,38,38,0.85)';
                ctx.fillRect(x, yBase - hBarH - cBarH, barW, cBarH);
            }
            // Count label above bar: show C:N and/or H:N so type is explicit
            var lbl = (cc > 0 ? 'C:' + cc : '') + (cc > 0 && hc > 0 ? ' ' : '') + (hc > 0 ? 'H:' + hc : '');
            ctx.fillStyle = cc > 0 ? '#dc2626' : '#ea580c';
            ctx.font      = 'bold ' + FONT;
            ctx.textAlign = 'center';
            ctx.fillText(lbl, scoreX(i), yBase - totalBarH - 3);
        });

        // ── Score area fill ───────────────────────────────────────────
        var grad = ctx.createLinearGradient(0, PAD_T, 0, PAD_T + cH);
        grad.addColorStop(0, 'rgba(30,111,217,0.15)');
        grad.addColorStop(1, 'rgba(30,111,217,0.02)');
        ctx.fillStyle = grad;
        ctx.beginPath();
        ctx.moveTo(scoreX(0), PAD_T + cH);
        data.forEach(function (e, i) { ctx.lineTo(scoreX(i), scoreY(e.score)); });
        ctx.lineTo(scoreX(data.length - 1), PAD_T + cH);
        ctx.closePath();
        ctx.fill();

        // ── Score line ────────────────────────────────────────────────
        ctx.strokeStyle = '#1e6fd9';
        ctx.lineWidth   = 2.5;
        ctx.lineJoin    = 'round';
        ctx.beginPath();
        data.forEach(function (e, i) {
            i === 0 ? ctx.moveTo(scoreX(i), scoreY(e.score)) : ctx.lineTo(scoreX(i), scoreY(e.score));
        });
        ctx.stroke();

        // ── Score dots + value labels ─────────────────────────────────
        data.forEach(function (e, i) {
            var x = scoreX(i), y = scoreY(e.score);
            ctx.beginPath();
            ctx.arc(x, y, 4, 0, Math.PI * 2);
            ctx.fillStyle   = '#1e6fd9';
            ctx.strokeStyle = '#ffffff';
            ctx.lineWidth   = 2;
            ctx.fill();
            ctx.stroke();
            // Score value above dot
            ctx.fillStyle = '#1e40af';
            ctx.font      = 'bold 10px -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(e.score, x, y - 8);
        });

        // ── Legend ────────────────────────────────────────────────────
        var legItems = [
            { color: '#1e6fd9',              type: 'line', label: 'Score' },
            { color: 'rgba(220,38,38,0.85)', type: 'bar',  label: 'Critical' },
            { color: 'rgba(251,146,60,0.80)', type: 'bar', label: 'High' },
        ];
        var legX = PAD_L + 4, legY = 14;
        ctx.font      = FONT;
        ctx.textAlign = 'left';
        var offset = 0;
        legItems.forEach(function (item) {
            ctx.fillStyle = item.color;
            if (item.type === 'line') { ctx.fillRect(legX + offset, legY - 4, 12, 3); }
            else                      { ctx.fillRect(legX + offset, legY - 6, 9, 8); }
            ctx.fillStyle = '#374151';
            ctx.fillText(item.label, legX + offset + 15, legY);
            offset += ctx.measureText(item.label).width + 28;
        });
    }

    // ── DB Prefix Migration Modal ────────────────────────────────────────

    (function () {
        var modal       = document.getElementById('csdt-db-prefix-modal');
        if (!modal) { return; }

        var step1       = document.getElementById('csdt-dbp-step1');
        var step2       = document.getElementById('csdt-dbp-step2');
        var step3       = document.getElementById('csdt-dbp-step3');
        var backupOk    = document.getElementById('csdt-dbp-backup-ok');
        var preflightBtn= document.getElementById('csdt-dbp-preflight-btn');
        var preflightOut= document.getElementById('csdt-dbp-preflight-out');
        var backBtn     = document.getElementById('csdt-dbp-back-btn');
        var migrateBtn  = document.getElementById('csdt-dbp-migrate-btn');
        var resultOut   = document.getElementById('csdt-dbp-result-out');
        var closeBtn    = document.getElementById('csdt-dbp-close');

        function closeModal() {
            modal.style.display = 'none';
            // Reset to step 1
            step1.style.display = '';
            step2.style.display = 'none';
            step3.style.display = 'none';
            backupOk.checked = false;
            preflightBtn.disabled = true;
            preflightBtn.style.opacity = '.5';
            preflightOut.innerHTML = '';
            resultOut.innerHTML = '';
            migrateBtn.disabled = false;
        }

        closeBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) { closeModal(); }
        });

        backupOk.addEventListener('change', function () {
            preflightBtn.disabled = !this.checked;
            preflightBtn.style.opacity = this.checked ? '1' : '.5';
        });

        preflightBtn.addEventListener('click', function () {
            preflightBtn.disabled = true;
            preflightBtn.textContent = 'Checking…';
            post('csdt_db_prefix_preflight', {})
                .then(function (res) {
                    preflightBtn.textContent = 'Next: Pre-flight check →';
                    if (!res.success) {
                        preflightOut.innerHTML = '<span style="color:#dc2626;">✕ ' + escHtml(res.data || 'Pre-flight failed.') + '</span>';
                        step1.style.display = 'none';
                        step2.style.display = '';
                        migrateBtn.disabled = true;
                        return;
                    }
                    var d = res.data;

                    var html = '<strong>Pre-flight results:</strong><br><br>';
                    if (d.cfg_getenv) {
                        html += '<div style="background:#fef9c3;border:1px solid #fde68a;border-radius:4px;padding:8px 10px;margin-bottom:10px;font-size:12px;color:#713f12;">' +
                            '⚠ <strong>Docker config detected</strong> — <code>wp-config.php</code> uses <code>getenv_docker()</code> for the table prefix. ' +
                            'The migration will replace that line with a static assignment so the new prefix persists across container restarts.' +
                            '</div>';
                    }
                    html += '• Current prefix: <code style="background:#e4e6ea;padding:1px 4px;border-radius:3px;">' + escHtml(d.current_prefix) + '</code><br>';
                    html += '• New prefix: <code style="background:#e4e6ea;padding:1px 4px;border-radius:3px;">' + escHtml(d.new_prefix) + '</code><br>';
                    html += '• Core tables to rename: <strong>' + d.table_count + '</strong><br>';
                    if (d.skipped_count) {
                        html += '• Non-core tables skipped: <strong>' + d.skipped_count + '</strong> <span style="color:#6b7280;font-size:.88em;">(use Optimizer → Orphaned Table Cleanup to review)</span><br>';
                    }
                    html += '• wp-config.php: ' + (d.cfg_writable ? '<span style="color:#16a34a;">✓ Writable</span>' : '<span style="color:#6b7280;">🔒 Read-only (0400) — migration will unlock temporarily</span>') + '<br>';
                    if (d.tables && d.tables.length) {
                        html += '<details style="margin-top:8px;"><summary style="cursor:pointer;color:#1e6fd9;font-size:12px;">View ' + d.tables.length + ' tables</summary>';
                        html += '<div style="margin-top:6px;font-size:11px;color:#50575e;max-height:120px;overflow-y:auto;">' + d.tables.map(function(t){ return escHtml(t); }).join('<br>') + '</div></details>';
                    }
                    html += '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:4px;padding:8px 10px;margin-top:10px;font-size:12px;color:#166534;">↩ <strong>Rollback saved automatically</strong> — after the rename you can undo from the <strong>Home tab</strong> at any time.</div>';
                    preflightOut.innerHTML = html;
                    step1.style.display = 'none';
                    step2.style.display = '';
                    migrateBtn.disabled = false;
                })
                .catch(function () {
                    preflightBtn.disabled = false;
                    preflightBtn.textContent = 'Next: Pre-flight check →';
                    preflightOut.innerHTML = '<span style="color:#dc2626;">Request failed — please try again.</span>';
                    step1.style.display = 'none';
                    step2.style.display = '';
                    migrateBtn.disabled = true;
                });
        });

        backBtn.addEventListener('click', function () {
            step2.style.display = 'none';
            step1.style.display = '';
            preflightBtn.disabled = !backupOk.checked;
            preflightBtn.style.opacity = backupOk.checked ? '1' : '.5';
            preflightBtn.textContent = 'Next: Pre-flight check →';
        });

        migrateBtn.addEventListener('click', function () {
            if (!confirm('This will rename all wp_ tables to a new prefix. A rollback snapshot will be saved — undo from the Home tab at any time. Proceed?')) {
                return;
            }
            migrateBtn.disabled = true;
            migrateBtn.textContent = 'Renaming tables…';
            post('csdt_db_prefix_migrate', {})
                .then(function (res) {
                    step2.style.display = 'none';
                    step3.style.display = '';
                    if (res.success) {
                        resultOut.innerHTML =
                            '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:14px 16px;">' +
                            '<p style="margin:0 0 6px;font-weight:700;color:#15803d;font-size:14px;">✓ Migration complete</p>' +
                            '<p style="margin:0 0 8px;font-size:13px;color:#166534;">' + escHtml(res.data.message) + '</p>' +
                            '<p style="margin:0;font-size:13px;color:#166534;font-weight:600;">⚠ Your session has been invalidated — please log in again.</p>' +
                            '</div>' +
                            '<div style="background:#fef9c3;border:1px solid #fde68a;border-radius:6px;padding:10px 14px;margin-top:10px;font-size:12px;color:#713f12;">' +
                            '🔄 If the site shows 404s after reloading, use the rollback button below to undo immediately.' +
                            '</div>' +
                            '<div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;">' +
                            '<button class="cs-btn-primary" onclick="window.location.reload()">Reload page</button>' +
                            '<button class="cs-btn-secondary" id="csdt-dbp-rollback-btn" style="border-color:#ef4444;color:#dc2626;">↩ Rollback</button>' +
                            '</div>' +
                            '<span id="csdt-dbp-rollback-msg" style="display:block;margin-top:6px;font-size:12px;"></span>';
                        var rbBtn = document.getElementById('csdt-dbp-rollback-btn');
                        if (rbBtn) {
                            rbBtn.addEventListener('click', function () {
                                if (!confirm('Rollback will rename all tables back to the old prefix and restore wp-config.php. Continue?')) { return; }
                                rbBtn.disabled = true; rbBtn.textContent = '⏳ Rolling back…';
                                var msg = document.getElementById('csdt-dbp-rollback-msg');
                                post('csdt_db_prefix_rollback', {}).then(function (r) {
                                    rbBtn.disabled = false; rbBtn.textContent = '↩ Rollback';
                                    if (msg) {
                                        msg.style.color = r.success ? '#16a34a' : '#dc2626';
                                        msg.textContent = (r.data && r.data.message) || (r.success ? 'Rolled back.' : 'Rollback failed.');
                                    }
                                    if (r.success) { rbBtn.disabled = true; rbBtn.textContent = '✓ Rolled back'; }
                                }).catch(function (e) {
                                    rbBtn.disabled = false; rbBtn.textContent = '↩ Rollback';
                                    if (msg) { msg.style.color = '#dc2626'; msg.textContent = 'Error: ' + e.message; }
                                });
                            });
                        }
                    } else {
                        resultOut.innerHTML =
                            '<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:14px 16px;">' +
                            '<p style="margin:0 0 6px;font-weight:700;color:#dc2626;font-size:14px;">✕ Migration failed</p>' +
                            '<p style="margin:0;font-size:13px;color:#991b1b;">' + escHtml(res.data || 'Unknown error') + '</p>' +
                            '</div>' +
                            '<div style="margin-top:12px;"><button class="cs-btn-secondary" id="csdt-dbp-retry-btn">← Go Back</button></div>';
                        var retryBtn = document.getElementById('csdt-dbp-retry-btn');
                        if (retryBtn) {
                            retryBtn.addEventListener('click', function () {
                                step3.style.display = 'none';
                                step1.style.display = '';
                                backupOk.checked = false;
                                preflightBtn.disabled = true;
                                preflightBtn.style.opacity = '.5';
                            });
                        }
                    }
                })
                .catch(function () {
                    step2.style.display = 'none';
                    step3.style.display = '';
                    resultOut.innerHTML =
                        '<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:14px 16px;">' +
                        '<p style="margin:0 0 6px;font-weight:700;color:#dc2626;">✕ Request failed</p>' +
                        '<p style="margin:0;font-size:13px;color:#991b1b;">Network error — please check your connection and try again.</p>' +
                        '</div>';
                });
        });
    }());

    // ── Threat Monitor ────────────────────────────────────────────────────────

    (function () {
        var saveBtn   = document.getElementById('csdt-tm-save');
        var savedMsg  = document.getElementById('csdt-tm-saved');
        var resetBtn  = document.getElementById('csdt-tm-reset');
        var resetMsg  = document.getElementById('csdt-tm-reset-msg');
        var masterChk = document.getElementById('csdt-tm-enabled');
        var optPanel  = document.getElementById('csdt-tm-options');

        if (!saveBtn) return;

        if (masterChk && optPanel) {
            masterChk.addEventListener('change', function () {
                optPanel.style.opacity         = this.checked ? '1' : '0.5';
                optPanel.style.pointerEvents   = this.checked ? '' : 'none';
            });
        }

        saveBtn.addEventListener('click', function () {
            saveBtn.disabled = true;
            post('csdt_threat_monitor_save', {
                enabled:         document.getElementById('csdt-tm-enabled')?.checked ? '1' : '0',
                file_integrity:  document.getElementById('csdt-tm-file')?.checked    ? '1' : '0',
                new_admin:       document.getElementById('csdt-tm-admin')?.checked   ? '1' : '0',
                probe:           document.getElementById('csdt-tm-probe')?.checked   ? '1' : '0',
                probe_threshold: document.getElementById('csdt-tm-probe-threshold')?.value || '25',
            }).then(function (res) {
                saveBtn.disabled = false;
                if (res.success && savedMsg) {
                    savedMsg.classList.add('visible');
                    setTimeout(function () { savedMsg.classList.remove('visible'); }, 2500);
                }
            }).catch(function () { saveBtn.disabled = false; });
        });

        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                resetBtn.disabled = true;
                post('csdt_threat_integrity_reset', {}).then(function (res) {
                    resetBtn.disabled = false;
                    if (res.success && resetMsg) {
                        resetMsg.textContent = res.data.message || 'Baseline reset.';
                        resetMsg.style.display = '';
                        setTimeout(function () { resetMsg.style.display = 'none'; }, 5000);
                    }
                }).catch(function () { resetBtn.disabled = false; });
            });
        }
    }());

    // ── Scan History — View Report modal ─────────────────────────────────

    (function () {
        // Create modal once
        var modal = document.createElement('div');
        modal.id = 'csdt-history-report-modal';
        modal.style.cssText = 'display:none;position:fixed;inset:0;z-index:100003;background:rgba(0,0,0,0.6);align-items:flex-start;justify-content:center;padding:32px 16px;overflow-y:auto;';
        modal.innerHTML =
            '<div style="background:#fff;border-radius:10px;max-width:720px;width:100%;margin:0 auto;box-shadow:0 8px 40px rgba(0,0,0,0.3);">' +
            '<div id="csdt-hrm-header" style="padding:16px 20px 12px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:12px;">' +
            '<div id="csdt-hrm-score" style="width:52px;height:52px;border-radius:8px;display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0;"></div>' +
            '<div style="flex:1;min-width:0;">' +
            '<div id="csdt-hrm-title" style="font-size:14px;font-weight:700;color:#111;"></div>' +
            '<div id="csdt-hrm-summary" style="font-size:12px;color:#6b7280;margin-top:3px;line-height:1.5;"></div>' +
            '</div>' +
            '<button type="button" id="csdt-hrm-close" style="margin-left:auto;background:none;border:none;font-size:22px;cursor:pointer;color:#888;line-height:1;padding:0;flex-shrink:0;">&times;</button>' +
            '</div>' +
            '<div id="csdt-hrm-body" style="padding:18px 20px 24px;max-height:72vh;overflow-y:auto;"></div>' +
            '</div>';
        document.body.appendChild(modal);

        document.getElementById('csdt-hrm-close').addEventListener('click', function () { modal.style.display = 'none'; });
        modal.addEventListener('click', function (e) { if (e.target === modal) modal.style.display = 'none'; });

        function scoreColor(s) {
            return s >= 90 ? '#16a34a' : s >= 75 ? '#22c55e' : s >= 55 ? '#f59e0b' : s >= 35 ? '#f97316' : '#dc2626';
        }

        function renderHistoryFindings(findings) {
            var secs = [
                { key:'critical', label:'Critical', color:'#dc2626', bg:'#fef2f2', border:'#fecaca' },
                { key:'high',     label:'High',     color:'#ea580c', bg:'#fff7ed', border:'#fed7aa' },
                { key:'medium',   label:'Medium',   color:'#d97706', bg:'#fffbeb', border:'#fde68a' },
                { key:'low',      label:'Low',      color:'#ca8a04', bg:'#fefce8', border:'#fef08a' },
                { key:'good',     label:'Good Practices', color:'#16a34a', bg:'#f0fdf4', border:'#bbf7d0' },
            ];
            var html = '';
            secs.forEach(function (sec) {
                var items = findings[sec.key];
                if (!items || !items.length) return;
                html += '<div style="margin-bottom:18px;">';
                html += '<div style="font-size:11px;font-weight:700;color:' + sec.color + ';text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;padding-bottom:5px;border-bottom:2px solid ' + sec.color + ';">' +
                    escHtml(sec.label) + ' (' + items.length + ')</div>';
                items.forEach(function (issue) {
                    if (sec.key === 'good') {
                        html += '<div style="display:flex;gap:8px;margin-bottom:6px;font-size:13px;">' +
                            '<span style="color:#16a34a;font-weight:700;flex-shrink:0;">✓</span>' +
                            '<span style="color:#374151;"><strong>' + escHtml(issue.title) + '</strong>' +
                            (issue.detail ? ' — ' + escHtml(issue.detail) : '') + '</span></div>';
                    } else {
                        html += '<div style="background:' + sec.bg + ';border:1px solid ' + sec.border + ';border-left:3px solid ' + sec.color + ';border-radius:0 5px 5px 0;padding:10px 12px;margin-bottom:8px;">';
                        html += '<div style="font-size:13px;font-weight:600;color:#111;margin-bottom:4px;">' + escHtml(issue.title) + '</div>';
                        if (issue.detail) html += '<div style="font-size:12px;color:#374151;line-height:1.5;margin-bottom:4px;">' + escHtml(issue.detail) + '</div>';
                        if (issue.fix)    html += '<div style="font-size:12px;color:#1d4ed8;font-style:italic;">💡 ' + escHtml(issue.fix) + '</div>';
                        html += '</div>';
                    }
                });
                html += '</div>';
            });
            return html || '<p style="color:#94a3b8;font-size:13px;">No findings recorded.</p>';
        }

        document.addEventListener('click', function (e) {
            var pdfBtn = e.target.closest('.csdt-history-pdf-btn');
            if (pdfBtn) {
                var idx      = pdfBtn.dataset.idx;
                var scanType = pdfBtn.dataset.scanType || 'standard';
                var orig     = pdfBtn.textContent;
                pdfBtn.disabled = true;
                pdfBtn.textContent = '…';
                post('csdt_scan_history_item', { idx: idx })
                    .then(function (resp) {
                        pdfBtn.disabled = false;
                        pdfBtn.textContent = orig;
                        if (!resp.success || !resp.data || !resp.data.findings) {
                            alert('No findings data for this scan.'); return;
                        }
                        var entry = resp.data;
                        exportSecurityPDF({
                            model_used: entry.model_used || '',
                            report: {
                                score:       entry.score,
                                score_label: entry.score_label,
                                summary:     entry.summary,
                                critical:    entry.findings.critical || [],
                                high:        entry.findings.high     || [],
                                medium:      entry.findings.medium   || [],
                                low:         entry.findings.low      || [],
                                good:        entry.findings.good     || [],
                            }
                        }, scanType);
                    })
                    .catch(function () {
                        pdfBtn.disabled = false;
                        pdfBtn.textContent = orig;
                        alert('Failed to load report data.');
                    });
                return;
            }
        });

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.csdt-view-report-btn');
            if (!btn) return;

            var idx     = btn.dataset.idx;
            var score   = parseInt(btn.dataset.score, 10) || 0;
            var label   = btn.dataset.label || '';
            var type    = btn.dataset.type || '';
            var date    = btn.dataset.date || '';
            var summary = btn.dataset.summary || '';
            var sc      = scoreColor(score);

            // Populate header immediately
            document.getElementById('csdt-hrm-score').style.background = sc;
            document.getElementById('csdt-hrm-score').innerHTML =
                '<span style="color:#fff;font-size:22px;font-weight:900;line-height:1;">' + escHtml(String(score)) + '</span>' +
                '<span style="color:rgba(255,255,255,.8);font-size:10px;">' + escHtml(label) + '</span>';
            document.getElementById('csdt-hrm-title').textContent = type + '  ·  ' + date;
            document.getElementById('csdt-hrm-summary').textContent = summary;
            document.getElementById('csdt-hrm-body').innerHTML = '<p style="color:#94a3b8;font-size:13px;text-align:center;padding:24px 0;">Loading…</p>';
            modal.style.display = 'flex';

            // Fetch findings
            post('csdt_scan_history_item', { idx: idx })
                .then(function (resp) {
                    if (!resp.success || !resp.data || !resp.data.findings) {
                        document.getElementById('csdt-hrm-body').innerHTML = '<p style="color:#dc2626;font-size:13px;">Report data not available for this scan (scans before v1.9.214 did not store findings).</p>';
                        return;
                    }
                    document.getElementById('csdt-hrm-body').innerHTML = renderHistoryFindings(resp.data.findings);
                })
                .catch(function () {
                    document.getElementById('csdt-hrm-body').innerHTML = '<p style="color:#dc2626;font-size:13px;">Failed to load report.</p>';
                });
        });
    }());

})();
