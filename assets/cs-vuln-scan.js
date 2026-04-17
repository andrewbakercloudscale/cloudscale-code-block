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

    // ── PDF export (jsPDF — direct download, no print dialog) ────────

    var JSPDF_CDN = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';

    function exportSecurityPDF(data, scanType) {
        function build() {
            var r     = data.report;
            var now   = new Date().toISOString().replace('T', ' ').slice(0, 19) + ' UTC';
            var site  = window.location.hostname;
            var title = scanType === 'deep' ? 'AI Deep Dive Cyber Audit Report' : 'AI Cyber Audit Report';
            var doc   = new window.jspdf.jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
            var pageW = doc.internal.pageSize.getWidth();
            var pageH = doc.internal.pageSize.getHeight();
            var margin = 18;
            var cW     = pageW - margin * 2;
            var y      = 20;

            function checkPage(need) {
                if (y + (need || 10) > pageH - 14) { doc.addPage(); y = 20; }
            }

            function addWrapped(str, x, size, r, g, b, style, maxW) {
                doc.setFont('helvetica', style || 'normal');
                doc.setFontSize(size);
                doc.setTextColor(r, g, b);
                var lines = doc.splitTextToSize(String(str || ''), maxW || (cW - (x - margin)));
                checkPage(lines.length * (size * 0.38) + 2);
                doc.text(lines, x, y);
                y += lines.length * (size * 0.38) + 1.5;
            }

            // Header
            addWrapped(title, margin, 18, 15, 23, 42, 'bold');
            y += 1;
            addWrapped(site + '  ·  ' + now + '  ·  Model: ' + (data.model_used || '?'), margin, 9, 100, 116, 139);
            y += 5;

            // Score circle + summary
            var sc = r.score || 0;
            var sRgb = sc >= 90 ? [39,103,73] : sc >= 75 ? [43,108,176] : sc >= 55 ? [183,121,31] : sc >= 35 ? [192,86,33] : [197,48,48];
            doc.setDrawColor(sRgb[0], sRgb[1], sRgb[2]);
            doc.setLineWidth(0.8);
            doc.circle(margin + 8, y + 5, 7, 'S');
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(11);
            doc.setTextColor(sRgb[0], sRgb[1], sRgb[2]);
            doc.text(String(sc), margin + 8, y + 4.5, { align: 'center' });
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(7);
            doc.text((r.score_label || '').toUpperCase(), margin + 8, y + 8.5, { align: 'center' });
            var sumLines = doc.splitTextToSize(r.summary || '', cW - 20);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(10);
            doc.setTextColor(45, 55, 72);
            doc.text(sumLines, margin + 19, y + 2);
            y += Math.max(17, sumLines.length * 3.8 + 3) + 6;

            // Sections
            var secDefs = [
                { key: 'critical', label: 'Critical',       rgb: [197,48,48]  },
                { key: 'high',     label: 'High',           rgb: [192,86,33]  },
                { key: 'medium',   label: 'Medium',         rgb: [183,121,31] },
                { key: 'low',      label: 'Low',            rgb: [43,108,176] },
                { key: 'good',     label: 'Good Practices', rgb: [39,103,73]  },
            ];

            secDefs.forEach(function (sec) {
                var items = r[sec.key];
                if (!items || !items.length) return;

                checkPage(14);
                doc.setFillColor(sec.rgb[0], sec.rgb[1], sec.rgb[2]);
                doc.rect(margin, y - 4, cW, 7, 'F');
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(9);
                doc.setTextColor(255, 255, 255);
                doc.text(sec.label.toUpperCase() + ' (' + items.length + ')', margin + 3, y + 0.5);
                y += 9;

                items.forEach(function (item) {
                    checkPage(16);
                    if (sec.key === 'good') {
                        doc.setFont('helvetica', 'bold');
                        doc.setFontSize(9);
                        doc.setTextColor(39, 103, 73);
                        doc.text('[OK]', margin, y);
                        var tl = doc.splitTextToSize(item.title, cW - 12);
                        doc.setTextColor(30, 41, 59);
                        doc.text(tl, margin + 10, y);
                        y += tl.length * 3.5 + 1;
                        if (item.detail) {
                            doc.setFont('helvetica', 'normal');
                            doc.setFontSize(8);
                            doc.setTextColor(100, 116, 139);
                            var dl = doc.splitTextToSize(item.detail, cW - 12);
                            doc.text(dl, margin + 10, y);
                            y += dl.length * 3 + 1;
                        }
                    } else {
                        var tl2 = doc.splitTextToSize(item.title, cW);
                        doc.setFont('helvetica', 'bold');
                        doc.setFontSize(10);
                        doc.setTextColor(30, 41, 59);
                        doc.text(tl2, margin, y);
                        y += tl2.length * 3.8 + 1;
                        if (item.detail) {
                            var dl2 = doc.splitTextToSize(item.detail, cW);
                            doc.setFont('helvetica', 'normal');
                            doc.setFontSize(9);
                            doc.setTextColor(71, 85, 105);
                            doc.text(dl2, margin, y);
                            y += dl2.length * 3.5 + 1;
                        }
                        if (item.fix) {
                            var fl = doc.splitTextToSize('Fix: ' + item.fix, cW - 4);
                            doc.setFont('helvetica', 'italic');
                            doc.setFontSize(8.5);
                            doc.setTextColor(100, 116, 139);
                            doc.text(fl, margin + 4, y);
                            y += fl.length * 3.2 + 2;
                        }
                    }
                    y += 3;
                });
                y += 4;
            });

            var fname = (scanType === 'deep' ? 'cyber-deep-dive' : 'security-audit') + '-' + site + '.pdf';
            doc.save(fname);
        }

        if (window.jspdf) {
            build();
        } else {
            var s = document.createElement('script');
            s.src = JSPDF_CDN;
            s.onload = build;
            document.head.appendChild(s);
        }
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
