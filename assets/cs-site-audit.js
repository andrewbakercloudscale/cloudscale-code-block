/* global csdtSiteAudit */
'use strict';

(function () {
    var ajaxUrl = csdtSiteAudit.ajaxUrl;
    var nonce   = csdtSiteAudit.nonce;

    var auditBtn      = document.getElementById('csdt-site-audit-btn');
    var progressWrap  = document.getElementById('csdt-site-audit-progress');
    var progressText  = document.getElementById('csdt-site-audit-progress-text');
    var resultsDiv    = document.getElementById('csdt-site-audit-results');

    var SEV_ORDER = { critical: 0, high: 1, medium: 2, low: 3, info: 4 };
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
        return '<div style="background:' + col.bg + ';border:1px solid ' + col.border +
            ';border-radius:8px;padding:16px 20px;margin-bottom:12px;">' +
            '<div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:8px;">' +
            '<span style="font-size:1.1em;margin-top:1px;">' + icon + '</span>' +
            '<div style="flex:1;min-width:0;">' +
            '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">' +
            '<span style="background:' + col.badge + ';color:#fff;font-size:.7em;font-weight:700;padding:2px 8px;border-radius:20px;text-transform:uppercase;letter-spacing:.04em;">' + escHtml(sev) + '</span>' +
            '<span style="background:#e2e8f0;color:#475569;font-size:.7em;font-weight:600;padding:2px 8px;border-radius:20px;">' + escHtml(f.category || '') + '</span>' +
            ( f.affected ? '<span style="color:#94a3b8;font-size:.78em;">→ ' + escHtml(f.affected) + '</span>' : '' ) +
            '</div>' +
            '<p style="margin:0 0 6px;font-weight:700;color:#0f172a;font-size:.95em;line-height:1.4;">' + escHtml(f.title || '') + '</p>' +
            '<p style="margin:0 0 8px;color:#4b5563;font-size:.87em;line-height:1.6;">' + escHtml(f.detail || '') + '</p>' +
            '<div style="background:rgba(255,255,255,.7);border-left:2px solid ' + col.badge + ';padding:8px 12px;border-radius:0 4px 4px 0;font-size:.85em;color:#374151;line-height:1.5;">' +
            '<strong style="color:' + col.text + ';">Fix: </strong>' + escHtml(f.fix || '') +
            '</div>' +
            '</div></div></div>';
    }

    function renderResults(data) {
        var findings  = (data.findings || []).slice().sort(function (a, b) {
            return (SEV_ORDER[a.severity] || 4) - (SEV_ORDER[b.severity] || 4);
        });
        var counts    = data.counts || {};
        var postCount = data.post_count || 0;
        var aiUsed    = data.ai_used;

        var html = '';

        // Header bar
        html += '<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">' +
            '<h3 style="margin:0;color:#0f172a;font-size:1em;font-weight:700;">Site Audit Results</h3>' +
            '<span style="color:#6b7280;font-size:.85em;">— ' + postCount + ' posts/pages scanned</span>' +
            ( aiUsed
                ? '<span style="background:#f0fdf4;border:1px solid #86efac;color:#15803d;font-size:.75em;font-weight:600;padding:2px 8px;border-radius:20px;">🤖 AI analysis</span>'
                : '<span style="background:#f0f9ff;border:1px solid #7dd3fc;color:#0369a1;font-size:.75em;font-weight:600;padding:2px 8px;border-radius:20px;">Rule-based</span>' ) +
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
    }

    if (auditBtn) {
        auditBtn.addEventListener('click', function () {
            auditBtn.disabled   = true;
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
                auditBtn.textContent = '🚀 Run Site Audit';
                if (progressWrap) progressWrap.style.display = 'none';

                if (!res.success) {
                    resultsDiv.innerHTML = '<p style="color:#dc2626;font-size:.9em;">Audit failed — please reload and try again.</p>';
                    resultsDiv.style.display = '';
                    return;
                }
                renderResults(res.data);
            }).catch(function () {
                auditBtn.disabled    = false;
                auditBtn.textContent = '🚀 Run Site Audit';
                if (progressWrap) progressWrap.style.display = 'none';
                resultsDiv.innerHTML = '<p style="color:#dc2626;font-size:.9em;">Request failed — please reload and try again.</p>';
                resultsDiv.style.display = '';
            });
        });
    }
}());
