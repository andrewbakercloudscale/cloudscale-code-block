/* global csdtSiteAudit */
'use strict';

(function () {
    var ajaxUrl = csdtSiteAudit.ajaxUrl;
    var nonce   = csdtSiteAudit.nonce;

    var auditBtn      = document.getElementById('csdt-site-audit-btn');
    var progressWrap  = document.getElementById('csdt-site-audit-progress');
    var progressText  = document.getElementById('csdt-site-audit-progress-text');
    var resultsDiv    = document.getElementById('csdt-site-audit-results');

    var SEV_ORDER = { critical: 1, high: 2, medium: 3, low: 4, info: 5 };
    var SEV_COLOR = {
        critical: { bg: '#fef2f2', border: '#fca5a5', badge: '#dc2626', text: '#7f1d1d' },
        high:     { bg: '#fff7ed', border: '#fed7aa', badge: '#ea580c', text: '#7c2d12' },
        medium:   { bg: '#fefce8', border: '#fde68a', badge: '#ca8a04', text: '#713f12' },
        low:      { bg: '#f0fdf4', border: '#86efac', badge: '#16a34a', text: '#14532d' },
        info:     { bg: '#f0f9ff', border: '#7dd3fc', badge: '#0284c7', text: '#0c4a6e' },
    };
    var CAT_ICON = {
        'SEO': '📈', 'Content': '📝', 'Performance': '⚡', 'Database': '🗄️',
        'Security': '🔒', 'Plugins': '🔌', 'info': 'ℹ️',
    };

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function post(action, params) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', nonce);
        if (params) Object.keys(params).forEach(function (k) { fd.append(k, params[k]); });
        return fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    function secPost(action, params) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', csdtSiteAudit.secNonce || '');
        if (params) Object.keys(params).forEach(function (k) { fd.append(k, params[k]); });
        return fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    function renderScorecard(counts) {
        var total = Object.values(counts).reduce(function (s, n) { return s + n; }, 0);
        if (total === 0) return '';
        var items = ['critical', 'high', 'medium', 'low', 'info'].map(function (sev) {
            var c = counts[sev] || 0;
            if (c === 0) return '';
            var col = SEV_COLOR[sev];
            return '<div style="display:flex;flex-direction:column;align-items:center;background:' + col.bg +
                ';border:1px solid ' + col.border + ';border-radius:8px;padding:10px 16px;min-width:70px;">' +
                '<span style="font-size:1.5em;font-weight:800;color:' + col.badge + ';">' + c + '</span>' +
                '<span style="font-size:.75em;font-weight:600;color:' + col.text + ';text-transform:capitalize;">' + sev + '</span>' +
                '</div>';
        }).join('');
        return '<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px;">' + items + '</div>';
    }

    function renderFindingCard(f) {
        var sev = (f.severity || 'info').toLowerCase();
        var col = SEV_COLOR[sev] || SEV_COLOR.info;
        var icon = CAT_ICON[f.category] || '🔍';
        var ctaHtml = '';
        if (f.cta && f.cta.url && f.cta.label) {
            ctaHtml = '<div style="margin-top:10px;background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);border-radius:6px;padding:12px 14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">' +
                '<div style="flex:1;min-width:0;">' +
                '<div style="font-size:.78em;font-weight:700;color:#a5b4fc;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;">CloudScale Recommendation</div>' +
                '<div style="font-size:.83em;color:#cbd5e1;line-height:1.4;">' + escHtml(f.cta.desc || '') + '</div>' +
                '</div>' +
                '<a href="' + escHtml(f.cta.url) + '" target="_blank" rel="noopener" style="flex-shrink:0;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;text-decoration:none;font-size:.8em;font-weight:700;padding:7px 14px;border-radius:6px;white-space:nowrap;">' +
                escHtml(f.cta.label) + '</a>' +
                '</div>';
        }
        var fixActionHtml = '';
        if (f.fix_action) {
            var seoFixActions   = { seo_ai_desc: 1, seo_ai_title: 1 };
            var quickFixActions = { cron_health: 'cron_health', expired_transients: 'expired_transients' };
            var modalFixActions = { db_prefix_modal: 'csdt-db-prefix-modal' };
            if (seoFixActions[f.fix_action]) {
                fixActionHtml = '<div style="margin-top:8px;"><a href="' + escHtml(csdtSiteAudit.seoAiUrl || '') +
                    '" style="display:inline-block;background:#10b981;color:#fff;text-decoration:none;font-size:.8em;font-weight:700;padding:6px 14px;border-radius:6px;">⚡ Fix It — Open SEO AI</a></div>';
            } else if (quickFixActions[f.fix_action]) {
                fixActionHtml = '<div style="margin-top:8px;">' +
                    '<button class="csdt-fix-it-btn" data-fix-id="' + escHtml(quickFixActions[f.fix_action]) +
                    '" style="background:#10b981;color:#fff;border:none;font-size:.8em;font-weight:700;padding:6px 14px;border-radius:6px;cursor:pointer;">⚡ Fix It</button>' +
                    '<span class="csdt-fix-it-status" style="display:none;margin-left:8px;font-size:.82em;"></span></div>';
            } else if (modalFixActions[f.fix_action]) {
                fixActionHtml = '<div style="margin-top:8px;">' +
                    '<button class="csdt-modal-fix-btn" data-modal="' + escHtml(modalFixActions[f.fix_action]) +
                    '" style="background:#10b981;color:#fff;border:none;font-size:.8em;font-weight:700;padding:6px 14px;border-radius:6px;cursor:pointer;">⚡ Fix It →</button>' +
                    '</div>';
            }
        }
        var linksHtml = '';
        if (f.links && f.links.length) {
            linksHtml = '<ul style="margin:0 0 8px;padding-left:0;list-style:none;font-size:.83em;">' +
                f.links.map(function (l) {
                    return '<li style="margin-bottom:3px;">' +
                        '<a href="' + escHtml(l.url) + '" target="_blank" rel="noopener" style="color:#2563eb;word-break:break-all;">' + escHtml(l.url) + '</a>' +
                        (l.words ? ' <span style="color:#6b7280;">(' + l.words + ' words)</span>' : '') +
                        '</li>';
                }).join('') + '</ul>';
        }
        return '<div style="background:' + col.bg + ';border:1px solid ' + col.border +
            ';border-radius:8px;padding:16px 20px;margin-bottom:12px;">' +
            '<div style="display:flex;align-items:flex-start;gap:12px;">' +
            '<span style="font-size:1.1em;margin-top:1px;">' + icon + '</span>' +
            '<div style="flex:1;min-width:0;">' +
            '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">' +
            '<span style="background:' + col.badge + ';color:#fff;font-size:.7em;font-weight:700;padding:2px 8px;border-radius:20px;text-transform:uppercase;letter-spacing:.04em;">' + escHtml(sev) + '</span>' +
            '<span style="background:#e2e8f0;color:#475569;font-size:.7em;font-weight:600;padding:2px 8px;border-radius:20px;">' + escHtml(f.category || '') + '</span>' +
            ( f.affected ? '<span style="color:#94a3b8;font-size:.78em;">→ ' + escHtml(f.affected) + '</span>' : '' ) +
            '</div>' +
            '<p style="margin:0 0 6px;font-weight:700;color:#0f172a;font-size:.95em;line-height:1.4;">' + escHtml(f.title || '') + '</p>' +
            '<p style="margin:0 0 8px;color:#4b5563;font-size:.87em;line-height:1.6;">' + escHtml(f.detail || '') + '</p>' +
            linksHtml +
            '<div style="background:rgba(255,255,255,.7);border-left:2px solid ' + col.badge + ';padding:8px 12px;border-radius:0 4px 4px 0;font-size:.85em;color:#374151;line-height:1.5;">' +
            '<strong style="color:' + col.text + ';">Fix: </strong>' + escHtml(f.fix || '') +
            '</div>' +
            fixActionHtml +
            ctaHtml +
            '</div></div></div>';
    }

    function renderResults(data, runAt) {
        var findings  = (data.findings || []).slice().sort(function (a, b) {
            return (SEV_ORDER[a.severity] || 4) - (SEV_ORDER[b.severity] || 4);
        });
        var counts    = data.counts || {};
        var postCount = data.post_count || 0;
        var aiUsed    = data.ai_used;

        var html = '';

        // Header bar
        var runLabel = runAt
            ? '<span style="color:#6b7280;font-size:.8em;background:#f8fafc;border:1px solid #e2e8f0;border-radius:4px;padding:2px 8px;">Last run: ' + escHtml(new Date(runAt * 1000).toLocaleString()) + '</span>'
            : '';
        html += '<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">' +
            '<h3 style="margin:0;color:#0f172a;font-size:1em;font-weight:700;">Site Audit Results</h3>' +
            '<span style="color:#6b7280;font-size:.85em;">— ' + postCount + ' posts/pages scanned</span>' +
            ( aiUsed
                ? '<span style="background:#f0fdf4;border:1px solid #86efac;color:#15803d;font-size:.75em;font-weight:600;padding:2px 8px;border-radius:20px;">🤖 AI analysis</span>'
                : '<span style="background:#f0f9ff;border:1px solid #7dd3fc;color:#0369a1;font-size:.75em;font-weight:600;padding:2px 8px;border-radius:20px;">Rule-based</span>' ) +
            runLabel +
            '<button id="csdt-audit-pdf-btn" style="margin-left:auto;background:#0f172a;color:#fff;border:none;border-radius:6px;padding:5px 12px;font-size:.78em;font-weight:600;cursor:pointer;">⬇ Download PDF</button>' +
            '</div>';

        // Scorecard
        html += renderScorecard(counts);

        // Filter buttons
        var categories = [];
        findings.forEach(function (f) {
            if (f.category && categories.indexOf(f.category) === -1) categories.push(f.category);
        });

        if (categories.length > 1) {
            html += '<div id="csdt-audit-filters" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">' +
                '<button class="csdt-audit-filter-btn csdt-filter-active" data-filter="all" style="font-size:.8em;padding:4px 12px;border-radius:20px;border:1px solid #6366f1;background:#6366f1;color:#fff;cursor:pointer;font-weight:600;">All</button>';
            categories.forEach(function (cat) {
                var icon = CAT_ICON[cat] || '';
                html += '<button class="csdt-audit-filter-btn" data-filter="' + escHtml(cat) +
                    '" style="font-size:.8em;padding:4px 12px;border-radius:20px;border:1px solid #e5e7eb;background:#fff;color:#374151;cursor:pointer;">' +
                    icon + ' ' + escHtml(cat) + '</button>';
            });
            html += '</div>';
        }

        // Finding cards
        html += '<div id="csdt-audit-cards">';
        findings.forEach(function (f) { html += renderFindingCard(f); });
        html += '</div>';

        resultsDiv.innerHTML = html;
        resultsDiv.style.display = '';

        // Wire up filter buttons
        var filterBtns = resultsDiv.querySelectorAll('.csdt-audit-filter-btn');
        var cards      = resultsDiv.querySelectorAll('#csdt-audit-cards > div');
        filterBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var filter = btn.getAttribute('data-filter');
                filterBtns.forEach(function (b) {
                    b.style.background = '#fff';
                    b.style.color      = '#374151';
                    b.style.borderColor = '#e5e7eb';
                    b.classList.remove('csdt-filter-active');
                });
                btn.style.background  = '#6366f1';
                btn.style.color       = '#fff';
                btn.style.borderColor = '#6366f1';
                btn.classList.add('csdt-filter-active');

                cards.forEach(function (card, i) {
                    var cat = (findings[i] && findings[i].category) || '';
                    card.style.display = ( filter === 'all' || cat === filter ) ? '' : 'none';
                });
            });
        });

        // Wire modal Fix It buttons
        resultsDiv.addEventListener('click', function (e) {
            var mBtn = e.target.closest('.csdt-modal-fix-btn');
            if (mBtn) {
                var modal = document.getElementById(mBtn.getAttribute('data-modal'));
                if (modal) modal.style.display = 'flex';
            }
        });

        // Wire Fix It buttons
        resultsDiv.addEventListener('click', function (e) {
            var btn = e.target.closest('.csdt-fix-it-btn');
            if (!btn) return;
            var fixId  = btn.getAttribute('data-fix-id');
            var status = btn.parentNode.querySelector('.csdt-fix-it-status');
            btn.disabled = true;
            btn.textContent = '⏳ Fixing…';
            if (status) { status.style.display = 'none'; }
            secPost('csdt_devtools_quick_fix', { fix_action: 'apply', fix_id: fixId })
                .then(function (res) {
                    if (res && res.success) {
                        btn.textContent = '✅ Fixed';
                        btn.style.background = '#6b7280';
                        if (status) { status.style.display = 'inline'; status.style.color = '#16a34a'; status.textContent = res.data && res.data.message ? res.data.message : 'Done'; }
                    } else {
                        btn.disabled = false;
                        btn.textContent = '⚡ Fix It';
                        if (status) { status.style.display = 'inline'; status.style.color = '#dc2626'; status.textContent = (res && res.data) || 'Error'; }
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.textContent = '⚡ Fix It';
                    if (status) { status.style.display = 'inline'; status.style.color = '#dc2626'; status.textContent = 'Request failed'; }
                });
        });

        // Wire PDF button
        var pdfBtn = document.getElementById('csdt-audit-pdf-btn');
        if (pdfBtn) {
            pdfBtn.addEventListener('click', function () { exportAuditPDF(findings, postCount, aiUsed); });
        }
    }

    function exportAuditPDF(findings, postCount, aiUsed) {
        var now  = new Date().toLocaleString();
        var site = window.location.hostname;
        var SEV_LABEL = { critical: 'Critical', high: 'High', medium: 'Medium', low: 'Low', info: 'Info' };
        var SEV_BG    = { critical: '#dc2626', high: '#ea580c', medium: '#ca8a04', low: '#16a34a', info: '#0284c7' };

        function esc(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

        var cardsHtml = '';
        ['critical','high','medium','low','info'].forEach(function (sev) {
            var group = findings.filter(function (f) { return (f.severity || 'info').toLowerCase() === sev; });
            if (!group.length) return;
            cardsHtml += '<div style="margin:0 0 18px">' +
                '<div style="background:' + SEV_BG[sev] + ';color:#fff;font-weight:700;font-size:11px;padding:5px 10px;border-radius:4px 4px 0 0;letter-spacing:.05em;">' +
                SEV_LABEL[sev].toUpperCase() + ' (' + group.length + ')</div>';
            group.forEach(function (f) {
                cardsHtml += '<div style="border:1px solid #e5e7eb;border-top:none;padding:10px 12px;">' +
                    '<div style="font-weight:600;font-size:13px;color:#111;margin-bottom:3px;">' + esc(f.title) + '</div>' +
                    ( f.affected ? '<div style="font-size:11px;color:#6b7280;margin-bottom:3px;">→ ' + esc(f.affected) + '</div>' : '' ) +
                    '<div style="font-size:12px;color:#374151;margin-bottom:3px;">' + esc(f.detail) + '</div>' +
                    '<div style="font-size:11px;color:#4b5563;font-style:italic;">Fix: ' + esc(f.fix) + '</div>' +
                    '</div>';
            });
            cardsHtml += '</div>';
        });

        var html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Site Audit Report — ' + esc(site) + '</title>' +
            '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#111;margin:0;padding:32px 40px;max-width:900px;}' +
            'h1{font-size:22px;font-weight:800;color:#0f172a;margin:0 0 4px;}' +
            '.meta{font-size:12px;color:#6b7280;margin-bottom:24px;}' +
            '@media print{body{padding:0;}@page{margin:20mm 18mm;}.no-print{display:none;}}</style></head><body>' +
            '<h1>Site Audit Report</h1>' +
            '<div class="meta">' + esc(site) + ' &nbsp;·&nbsp; ' + esc(now) +
            ' &nbsp;·&nbsp; ' + postCount + ' posts/pages' +
            ' &nbsp;·&nbsp; ' + ( aiUsed ? '🤖 AI analysis' : 'Rule-based' ) + '</div>' +
            cardsHtml + '</body></html>';

        var win = window.open('', '_blank');
        if (!win) { alert('Please allow pop-ups for this page to export the PDF.'); return; }
        win.document.write(html);
        win.document.close();
        win.focus();
        setTimeout(function () { win.print(); }, 400);
    }

    if (auditBtn) {
        auditBtn.addEventListener('click', function () {
            auditBtn.disabled    = true;
            auditBtn.textContent = '⏳ Running…';
            if (progressWrap) progressWrap.style.display = '';
            if (progressText) progressText.textContent  = 'Gathering site data…';
            resultsDiv.style.display = 'none';

            var progressSteps = [
                [1200, 'Analysing content and SEO…'],
                [2400, 'Checking database health…'],
                [4000, 'Running AI analysis…'],
            ];
            progressSteps.forEach(function (step) {
                setTimeout(function () {
                    if (progressText && progressWrap.style.display !== 'none') {
                        progressText.textContent = step[1];
                    }
                }, step[0]);
            });

            post('csdt_site_audit').then(function (res) {
                auditBtn.disabled    = false;
                auditBtn.textContent = '🔄 Re-run Audit';
                if (progressWrap) progressWrap.style.display = 'none';

                if (!res.success) {
                    resultsDiv.innerHTML = '<p style="color:#dc2626;font-size:.9em;">Audit failed — please reload and try again.</p>';
                    resultsDiv.style.display = '';
                    return;
                }
                renderResults(res.data, Math.floor(Date.now() / 1000));
            }).catch(function () {
                auditBtn.disabled    = false;
                auditBtn.textContent = '🔄 Re-run Audit';
                if (progressWrap) progressWrap.style.display = 'none';
                resultsDiv.innerHTML = '<p style="color:#dc2626;font-size:.9em;">Request failed — please reload and try again.</p>';
                resultsDiv.style.display = '';
            });
        });
    }

    // Load cached results on page open
    if (csdtSiteAudit.cached && csdtSiteAudit.cachedAt) {
        renderResults(csdtSiteAudit.cached, csdtSiteAudit.cachedAt);
        if (auditBtn) { auditBtn.textContent = '🔄 Re-run Audit'; }
    }
}());
