/* global csdtVulnScan */
(function () {
    'use strict';

    var cfg      = csdtVulnScan;
    var ajaxUrl  = cfg.ajaxUrl;
    var nonce    = cfg.nonce;

    var POLL_INTERVAL = 3000; // ms between status polls

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

    // ── PDF export ────────────────────────────────────────────────────

    function exportSecurityPDF(data, scanType) {
        var r    = data.report;
        var now  = new Date().toISOString().replace('T', ' ').slice(0, 19) + ' UTC';
        var site = window.location.hostname;
        var title = scanType === 'deep' ? 'Cyber Deep Dive Report' : 'AI Security Audit Report';

        function esc(s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        var secColors = { critical: '#c53030', high: '#c05621', medium: '#b7791f', low: '#2b6cb0', good: '#276749' };
        var secBg     = { critical: '#fff5f5', high: '#fffaf0', medium: '#fffbeb', low: '#ebf8ff', good: '#f0fff4' };

        var css = [
            'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:12px;color:#1a202c;margin:0;padding:28px 32px;line-height:1.5}',
            'h1{font-size:20px;font-weight:700;margin:0 0 2px}',
            '.meta{font-size:11px;color:#718096;margin-bottom:24px}',
            '.score-row{display:flex;align-items:center;gap:20px;margin-bottom:20px;padding:14px 18px;background:#f8fafc;border-radius:6px;border:1px solid #e2e8f0}',
            '.score-circle{width:64px;height:64px;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0}',
            '.score-num{font-size:22px;font-weight:800;line-height:1}',
            '.score-lbl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-top:2px}',
            '.summary{font-size:12px;color:#2d3748;line-height:1.6}',
            '.model-line{font-size:10px;color:#718096;margin-top:6px}',
            'h2{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#4a5568;border-bottom:1px solid #e2e8f0;padding-bottom:5px;margin:20px 0 10px}',
            '.issue{padding:8px 12px;margin-bottom:7px;border-radius:4px;border-left:3px solid;page-break-inside:avoid}',
            '.issue-title{font-weight:700;font-size:12px}',
            '.issue-detail{font-size:11px;color:#4a5568;margin-top:3px}',
            '.issue-fix{font-size:11px;color:#2d3748;margin-top:5px;padding:6px 10px;background:#fff;border:1px solid #e2e8f0;border-radius:3px}',
            '.good-item{display:flex;gap:8px;padding:5px 0;border-bottom:1px solid #f0f0f0;font-size:11px}',
            '.good-check{color:#276749;font-weight:700;flex-shrink:0}',
            'code{font-family:"SFMono-Regular",Consolas,monospace;font-size:10px;background:#edf2f7;padding:1px 4px;border-radius:2px}',
            '@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}.issue{page-break-inside:avoid}}',
        ].join('\n');

        var scoreCls = scoreClass(r.score);
        var scoreStyleMap = {
            'cs-audit-score-excellent': { bg: '#c6f6d5', color: '#22543d' },
            'cs-audit-score-good':      { bg: '#bee3f8', color: '#2a4365' },
            'cs-audit-score-fair':      { bg: '#fefcbf', color: '#744210' },
            'cs-audit-score-poor':      { bg: '#fed7d7', color: '#742a2a' },
            'cs-audit-score-critical':  { bg: '#feb2b2', color: '#63171b' },
        };
        var ss = scoreStyleMap[scoreCls] || scoreStyleMap['cs-audit-score-fair'];

        var html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + esc(title) + ' — ' + esc(site) + '</title><style>' + css + '</style></head><body>';
        html += '<h1>' + esc(title) + '</h1>';
        html += '<div class="meta">' + esc(site) + ' &nbsp;·&nbsp; ' + esc(now) + ' &nbsp;·&nbsp; Model: ' + esc(data.model_used || '?') + '</div>';

        html += '<div class="score-row">';
        html += '<div class="score-circle" style="background:' + ss.bg + ';color:' + ss.color + '">';
        html += '<span class="score-num">' + esc(r.score) + '</span>';
        html += '<span class="score-lbl">' + esc(r.score_label || '') + '</span>';
        html += '</div>';
        html += '<div><div class="summary">' + esc(r.summary || '') + '</div></div>';
        html += '</div>';

        var secs = [
            { key: 'critical', label: 'Critical' },
            { key: 'high',     label: 'High' },
            { key: 'medium',   label: 'Medium' },
            { key: 'low',      label: 'Low' },
            { key: 'good',     label: 'Good Practices' },
        ];

        secs.forEach(function (sec) {
            var items = r[sec.key];
            if (!items || !items.length) return;
            html += '<h2>' + esc(sec.label) + ' (' + items.length + ')</h2>';
            if (sec.key === 'good') {
                items.forEach(function (g) {
                    html += '<div class="good-item"><span class="good-check">✓</span><span><strong>' + esc(g.title) + '</strong>';
                    if (g.detail) html += ' — ' + esc(g.detail);
                    html += '</span></div>';
                });
            } else {
                items.forEach(function (issue) {
                    html += '<div class="issue" style="border-left-color:' + (secColors[sec.key] || '#718096') + ';background:' + (secBg[sec.key] || '#f7fafc') + '">';
                    html += '<div class="issue-title">' + esc(issue.title) + '</div>';
                    if (issue.detail) html += '<div class="issue-detail">' + esc(issue.detail) + '</div>';
                    if (issue.fix)    html += '<div class="issue-fix">' + esc(issue.fix) + '</div>';
                    html += '</div>';
                });
            }
        });

        html += '</body></html>';

        var w = window.open('', '_blank', 'width=900,height=700');
        if (!w) { alert('Pop-up blocked — allow pop-ups for this page and try again.'); return; }
        w.document.open();
        w.document.write(html);
        w.document.close();
        w.onload = function () { w.focus(); w.print(); };
        setTimeout(function () { try { w.focus(); w.print(); } catch(e) {} }, 600);
    }

    // ── Render audit report ───────────────────────────────────────────

    function renderReport(data, container, scanType) {
        if (!container) return;
        var r   = data.report;
        var cls = scoreClass(r.score);
        var age = data.from_cache ? ' · cached ' + timeSince(data.scanned_at) + ' ago' : '';
        var html = '';

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
        html += '<div style="margin:10px 0 16px"><button class="cs-audit-pdf-btn button button-secondary" data-scan-type="' + escHtml(scanType || 'standard') + '">&#8595; Download PDF Report</button></div>';

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
                    html += '<div class="cs-audit-issue">';
                    html += '<div class="cs-audit-issue-title">' + escHtml(issue.title) + '</div>';
                    if (issue.detail) html += '<div class="cs-audit-issue-detail">' + escHtml(issue.detail) + '</div>';
                    if (issue.fix)    html += '<div class="cs-audit-issue-fix">' + escHtml(issue.fix) + '</div>';
                    html += '</div>';
                });
            }
            html += '</div>';
        });

        container.innerHTML = html;

        var pdfBtn = container.querySelector('.cs-audit-pdf-btn');
        if (pdfBtn) {
            pdfBtn.addEventListener('click', function () { exportSecurityPDF(data, scanType); });
        }
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

    function startPolling(type, scanBtn, statusEl, resultsEl, progressEl) {
        var bar   = new ProgressBar(progressEl);
        bar.tick(); // first immediate tick

        var timer = setInterval(function () {
            bar.tick();
            post('csdt_devtools_scan_status', { type: type })
                .then(function (res) {
                    if (!res.success) return;
                    var d = res.data;
                    if (d.status === 'running') return; // keep waiting

                    clearInterval(timer);
                    if (scanBtn) scanBtn.disabled = false;

                    if (d.status === 'complete') {
                        bar.complete();
                        var lbl = d.data && d.data.report ? d.data.report.score_label : '';
                        var msg = '✅ ' + (type === 'deep' ? 'Deep dive' : 'Audit') + ' complete' + (lbl ? ' — ' + lbl : '');
                        if (statusEl) { statusEl.textContent = msg; statusEl.className = 'cs-vuln-inline-msg cs-vuln-msg-ok'; }
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
                .catch(function () {}); // ignore transient poll errors
        }, POLL_INTERVAL);
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
                    var lbl = res.data.report ? res.data.report.score_label : '';
                    if (statusEl) { statusEl.textContent = '✅ Audit complete — ' + (lbl || 'cached'); statusEl.className = 'cs-vuln-inline-msg cs-vuln-msg-ok'; }
                })
                .catch(function () {});
            return;
        }

        if (scanBtn)   scanBtn.disabled = true;
        if (statusEl)  { statusEl.textContent = '⏳ Running AI security audit…'; statusEl.className = 'cs-vuln-inline-msg'; }
        if (resultsEl) resultsEl.style.display = 'none';

        post('csdt_devtools_vuln_scan', {})
            .then(function (res) {
                if (!res.success) {
                    if (scanBtn) scanBtn.disabled = false;
                    var err = res.data && res.data.message ? res.data.message : 'Failed to start scan.';
                    if (statusEl) { statusEl.textContent = '❌ ' + err; statusEl.className = 'cs-vuln-inline-msg cs-vuln-msg-err'; }
                    return;
                }
                startPolling('standard', scanBtn, statusEl, resultsEl, progressEl);
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
                    var lbl = res.data.report ? res.data.report.score_label : '';
                    if (statusEl) { statusEl.textContent = '✅ Deep dive complete — ' + (lbl || 'cached'); statusEl.className = 'cs-vuln-inline-msg cs-vuln-msg-ok'; }
                })
                .catch(function () {});
            return;
        }

        if (scanBtn)   scanBtn.disabled = true;
        if (statusEl)  { statusEl.textContent = '⏳ Running Cyber Deep Dive… this may take 60–90s.'; statusEl.className = 'cs-vuln-inline-msg'; }
        if (resultsEl) resultsEl.style.display = 'none';

        post('csdt_devtools_deep_scan', {})
            .then(function (res) {
                if (!res.success) {
                    if (scanBtn) scanBtn.disabled = false;
                    var err = res.data && res.data.message ? res.data.message : 'Failed to start deep scan.';
                    if (statusEl) { statusEl.textContent = '❌ ' + err; statusEl.className = 'cs-vuln-inline-msg cs-vuln-msg-err'; }
                    return;
                }
                startPolling('deep', scanBtn, statusEl, resultsEl, progressEl);
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
            updateDeepModelBadge();
        }

        function updateDeepModelBadge() {
            if (!deepModelBadge || !deepModelSel) return;
            var v = deepModelSel.value;
            var names = {
                '_auto': 'Auto', '_auto_deep': 'Auto',
                'claude-opus-4-7': 'Opus 4.7', 'claude-sonnet-4-6': 'Sonnet 4.6',
                'claude-haiku-4-5-20251001': 'Haiku 4.5',
                'gemini-2.0-flash': 'Gemini Flash', 'gemini-2.0-flash-lite': 'Flash Lite',
                'gemini-1.5-pro': 'Gemini 1.5 Pro',
            };
            deepModelBadge.textContent = 'Using ' + (names[v] || v);
        }

        // Init provider
        if (providerSel && cfg.savedProvider) providerSel.value = cfg.savedProvider;
        if (keyInput    && cfg.maskedKey)     keyInput.placeholder    = cfg.maskedKey;
        if (geminiKeyInput && cfg.maskedGemini) geminiKeyInput.placeholder = cfg.maskedGemini;
        applyProvider(providerSel ? providerSel.value : 'anthropic');

        if (providerSel) {
            providerSel.addEventListener('change', function () { applyProvider(this.value); });
        }
        if (deepModelSel) deepModelSel.addEventListener('change', updateDeepModelBadge);

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

        // ── Scan buttons ──────────────────────────────────────────────

        if (scanBtn) scanBtn.addEventListener('click', function () { runScan(false); });
        if (deepBtn) deepBtn.addEventListener('click', function () { runDeepScan(false); });

        // Silently pre-fill cached results on page load
        if (cfg.hasKey) {
            runScan(true);
            runDeepScan(true);
        }
    });
})();
