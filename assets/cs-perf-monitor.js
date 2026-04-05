/**
 * CloudScale Performance Monitor — DevTools-style admin + frontend panel
 *
 * Tabs: DB Queries | HTTP/REST | PHP Errors | Summary
 * Features: call-chain trace, EXPLAIN on demand, N+1 detection,
 *           multi-column sort, colour-coded severity, export JSON.
 *
 * @since 1.8.0
 */
(function () {
    'use strict';

    var LS_OPEN   = 'cs_perf_open';
    var LS_HEIGHT = 'cs_perf_height';
    var LS_TAB    = 'cs_perf_tab';
    var DEFAULT_H = 340;
    var MIN_H     = 150;
    var MAX_H_PCT = 0.82;

    var T_MEDIUM   = 10;
    var T_SLOW     = 50;
    var T_CRITICAL = 200;
    var N1_THRESH  = 3;

    // ── State ─────────────────────────────────────────────────────────────────
    var data      = window.csPerfData || { queries: [], http: [], errors: [], logs: [], meta: {} };
    var meta      = data.meta || {};
    var sortCol   = 'time';
    var sortDir   = 'desc';
    var activeTab = localStorage.getItem(LS_TAB) || 'db';

    var filteredDB   = [];
    var filteredHTTP = [];
    var n1Patterns   = {};

    // ── DOM refs ──────────────────────────────────────────────────────────────
    var panel, toggleBtn, exportBtn, resizeHandle, footTxt, totalTxt, ctxStrip;
    var tabBtns, panes, filterBar;
    var searchInput, pluginSel, speedSel, dupeChk;
    var dbTbody, httpTbody, logList, summaryWrap;
    var dbCount, httpCount, logCount;
    var badgeDB, badgeHTTP, badgeLOG;
    var logSearch, logLevel, logSource;

    // ── Bootstrap ─────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        panel        = document.getElementById('cs-perf');
        toggleBtn    = document.getElementById('cs-perf-toggle');
        exportBtn    = document.getElementById('cs-perf-export');
        resizeHandle = document.getElementById('cs-perf-resize');
        footTxt      = document.getElementById('cs-perf-foot-txt');
        totalTxt     = document.getElementById('cs-perf-ttl');
        ctxStrip     = document.getElementById('cs-perf-ctx');
        tabBtns      = Array.prototype.slice.call(document.querySelectorAll('.cs-ptab'));
        panes        = Array.prototype.slice.call(document.querySelectorAll('.cs-ppane'));
        filterBar    = document.getElementById('cs-perf-filters');
        searchInput  = document.getElementById('cs-pf-search');
        pluginSel    = document.getElementById('cs-pf-plugin');
        speedSel     = document.getElementById('cs-pf-speed');
        dupeChk      = document.getElementById('cs-pf-dupe');
        dbTbody      = document.getElementById('cs-db-rows');
        httpTbody    = document.getElementById('cs-http-rows');
        logList      = document.getElementById('cs-log-list');
        summaryWrap  = document.getElementById('cs-summary-wrap');
        dbCount      = document.getElementById('cs-ptc-db');
        httpCount    = document.getElementById('cs-ptc-http');
        logCount     = document.getElementById('cs-ptc-log');
        badgeDB      = document.getElementById('cs-pb-db');
        badgeHTTP    = document.getElementById('cs-pb-http');
        badgeLOG     = document.getElementById('cs-pb-log');
        logSearch    = document.getElementById('cs-lf-search');
        logLevel     = document.getElementById('cs-lf-level');
        logSource    = document.getElementById('cs-lf-source');

        if (!panel) return;

        computeN1Patterns();
        populatePluginFilter();
        updateBadges();
        updateTotalTime();
        renderPageContext();
        applyFilters();
        renderLogs();
        renderSummary();
        restoreState();
        bindEvents();
    });

    // ── Page context strip ────────────────────────────────────────────────────
    function renderPageContext() {
        if (!ctxStrip) return;
        var parts = [];
        if (meta.url)       parts.push('<span class="cs-ctx-url">' + esc(meta.url) + '</span>');
        if (meta.wp_screen) parts.push('<span class="cs-ctx-sep">·</span><span class="cs-ctx-page">' + esc(meta.wp_screen) + '</span>');
        if (meta.page_type) parts.push('<span class="cs-ctx-sep">·</span><span class="cs-ctx-page">' + esc(meta.page_type) + '</span>');
        if (meta.template)  parts.push('<span class="cs-ctx-sep">·</span><span class="cs-ctx-tmpl">' + esc(meta.template) + '</span>');
        ctxStrip.innerHTML = parts.join(' ') || '';
        ctxStrip.style.display = parts.length ? '' : 'none';
    }

    // ── N+1 detection ─────────────────────────────────────────────────────────
    function computeN1Patterns() {
        n1Patterns = {};
        data.queries.forEach(function (q) {
            var fp = normalisePattern(q.sql);
            if (!n1Patterns[fp]) n1Patterns[fp] = { count: 0, total_ms: 0, plugin: q.plugin, example: q.sql };
            n1Patterns[fp].count++;
            n1Patterns[fp].total_ms += q.time_ms;
        });
        Object.keys(n1Patterns).forEach(function (k) {
            if (n1Patterns[k].count < N1_THRESH) delete n1Patterns[k];
        });
    }

    function normalisePattern(sql) {
        return sql.replace(/\s+/g, ' ').toLowerCase().trim()
            .replace(/'(?:[^'\\]|\\.)*'/g, "'?'")
            .replace(/\b\d+(\.\d+)?\b/g, '?')
            .replace(/\((\s*\?,?\s*){2,}\)/g, '(...)');
    }

    function isN1(sql) {
        return Object.prototype.hasOwnProperty.call(n1Patterns, normalisePattern(sql));
    }

    // ── Panel open / close ────────────────────────────────────────────────────
    function restoreState() {
        var open = localStorage.getItem(LS_OPEN) === '1';
        var h    = parseInt(localStorage.getItem(LS_HEIGHT), 10) || DEFAULT_H;
        if (open) openPanel(h, false);
        switchTab(activeTab, false);
    }

    function openPanel(h, animate) {
        if (!animate) panel.style.transition = 'none';
        panel.classList.remove('cs-perf-collapsed');
        panel.classList.add('cs-perf-open');
        panel.style.height = clampHeight(h) + 'px';
        document.getElementById('cs-perf-toggle-arrow').innerHTML = '&#9660;';
        toggleBtn.setAttribute('aria-expanded', 'true');
        localStorage.setItem(LS_OPEN, '1');
        if (!animate) { void panel.offsetHeight; panel.style.transition = ''; }
    }

    function closePanel() {
        panel.classList.remove('cs-perf-open');
        panel.classList.add('cs-perf-collapsed');
        panel.style.height = '';
        document.getElementById('cs-perf-toggle-arrow').innerHTML = '&#9650;';
        toggleBtn.setAttribute('aria-expanded', 'false');
        localStorage.setItem(LS_OPEN, '0');
    }

    function togglePanel() {
        if (panel.classList.contains('cs-perf-open')) closePanel();
        else openPanel(parseInt(localStorage.getItem(LS_HEIGHT), 10) || DEFAULT_H, true);
    }

    // ── Tab switching ─────────────────────────────────────────────────────────
    function switchTab(tab, save) {
        activeTab = tab;
        if (save !== false) localStorage.setItem(LS_TAB, tab);
        tabBtns.forEach(function (btn) {
            var on = btn.dataset.tab === tab;
            btn.classList.toggle('active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panes.forEach(function (pane) {
            pane.classList.toggle('active', pane.id === 'cs-pp-' + tab);
        });
        var showFilters = tab === 'db' || tab === 'http';
        filterBar.style.display = showFilters ? '' : 'none';
        if (dupeChk) dupeChk.parentElement.style.display = tab === 'db' ? '' : 'none';
        var logFiltersEl = document.querySelector('.cs-log-filters');
        if (logFiltersEl) logFiltersEl.style.display = tab === 'logs' ? '' : 'none';
    }

    // ── Plugin filter dropdown ────────────────────────────────────────────────
    function populatePluginFilter() {
        var seen = {};
        data.queries.forEach(function (q) { seen[q.plugin] = 1; });
        data.http.forEach(function (h)    { seen[h.plugin] = 1; });
        Object.keys(seen).sort().forEach(function (name) {
            var opt   = document.createElement('option');
            opt.value = name; opt.text = name;
            pluginSel.appendChild(opt);
        });
    }

    // ── Badges ────────────────────────────────────────────────────────────────
    function updateBadges() {
        badgeDB.querySelector('em').textContent   = meta.query_count || 0;
        badgeHTTP.querySelector('em').textContent = meta.http_count  || 0;
        var lc = (data.logs || []).length;
        if (lc > 0 && badgeLOG) { badgeLOG.style.display = ''; badgeLOG.querySelector('em').textContent = lc; }
    }

    function updateTotalTime() {
        var parts = [];
        if (meta.query_count > 0) parts.push(meta.query_count + ' queries / ' + fmtMs(meta.query_total_ms));
        if (meta.http_count   > 0) parts.push(meta.http_count + ' HTTP / ' + fmtMs(meta.http_total_ms));
        if (meta.page_load_ms > 0) parts.push('Page: ' + fmtMs(meta.page_load_ms));
        totalTxt.textContent = parts.join('  ·  ');
    }

    // ── Filters ───────────────────────────────────────────────────────────────
    function applyFilters() {
        var search    = (searchInput.value || '').toLowerCase().trim();
        var plugin    = pluginSel.value;
        var threshold = parseInt(speedSel.value, 10) || 0;
        var dupeOnly  = dupeChk.checked;

        filteredDB = data.queries.filter(function (q) {
            if (plugin    && q.plugin  !== plugin)  return false;
            if (threshold && q.time_ms < threshold) return false;
            if (dupeOnly  && !q.is_dupe)            return false;
            if (search && q.sql.toLowerCase().indexOf(search)    === -1
                       && q.plugin.toLowerCase().indexOf(search) === -1
                       && q.caller.toLowerCase().indexOf(search) === -1) return false;
            return true;
        });

        filteredHTTP = data.http.filter(function (h) {
            if (plugin    && h.plugin !== plugin)   return false;
            if (threshold && h.time_ms < threshold) return false;
            if (search && h.url.toLowerCase().indexOf(search)    === -1
                       && h.plugin.toLowerCase().indexOf(search) === -1) return false;
            return true;
        });

        renderDB();
        renderHTTP();
        updateFooter();
        updateTabCounts();
    }

    function updateTabCounts() {
        dbCount.textContent   = filteredDB.length;
        httpCount.textContent = filteredHTTP.length;
        if (logCount) logCount.textContent = (data.logs || []).length;
    }

    // ── Multi-column sort ─────────────────────────────────────────────────────
    function sortRows(rows) {
        return rows.slice().sort(function (a, b) {
            var aVal, bVal;
            if (sortCol === 'plugin') {
                aVal = (a.plugin || '').toLowerCase();
                bVal = (b.plugin || '').toLowerCase();
                var cmp = aVal.localeCompare(bVal);
                return sortDir === 'asc' ? cmp : -cmp;
            }
            if (sortCol === 'rows') {
                aVal = a.rows < 0 ? -1 : a.rows;
                bVal = b.rows < 0 ? -1 : b.rows;
            } else {
                // default: time
                aVal = a.time_ms;
                bVal = b.time_ms;
            }
            return sortDir === 'desc' ? bVal - aVal : aVal - bVal;
        });
    }

    function updateSortHeaders() {
        Array.prototype.forEach.call(document.querySelectorAll('.cs-sortable'), function (th) {
            var col = th.dataset.sort;
            th.classList.toggle('cs-sort-active', col === sortCol);
            var labels = { time: 'Time', plugin: 'Plugin', rows: 'Rows' };
            var arrow  = col !== sortCol ? '&#8597;' : (sortDir === 'desc' ? '&#8595;' : '&#8593;');
            th.innerHTML = (labels[col] || col) + '&nbsp;' + arrow;
        });
    }

    // ── DB table ──────────────────────────────────────────────────────────────
    function renderDB() {
        if (!dbTbody) return;

        if (!meta.savequeries_active) {
            dbTbody.innerHTML = '<tr><td colspan="5"><div class="cs-sq-warning">'
                + '<strong>&#9888; SAVEQUERIES is not active.</strong><br>'
                + 'Another plugin or wp-config.php defined <code>SAVEQUERIES</code> as <code>false</code> '
                + 'before this plugin loaded. Add <code>define(\'SAVEQUERIES\', true);</code> '
                + 'to wp-config.php to override.'
                + '</div></td></tr>';
            return;
        }

        if (filteredDB.length === 0) {
            dbTbody.innerHTML = '<tr><td colspan="5" class="cs-empty">'
                + '<span class="cs-empty-icon">&#128200;</span>'
                + (data.queries.length === 0 ? 'No queries captured on this page load.' : 'No queries match the current filters.')
                + '</td></tr>';
            return;
        }

        var sorted = sortRows(filteredDB);
        var maxMs  = Math.max.apply(null, sorted.map(function (q) { return q.time_ms; }));

        var html = '';
        sorted.forEach(function (q, i) {
            var rowN1    = isN1(q.sql);
            var rowClass = rowSpeedClass(q.time_ms) + (q.is_dupe ? ' cs-row-dupe' : '');
            var sqlShort = truncate(q.sql, 88);
            var rowsText = q.rows >= 0 ? q.rows : '–';
            var canExp   = /^(SELECT|SHOW|DESCRIBE)\b/i.test(q.sql);

            var tags = '';
            if (q.time_ms >= T_CRITICAL)  tags += '<span class="cs-tag cs-tag-critical">critical</span> ';
            else if (q.time_ms >= T_SLOW) tags += '<span class="cs-tag cs-tag-slow">slow</span> ';
            if (q.is_dupe)                tags += '<span class="cs-tag cs-tag-dupe">dupe</span> ';
            if (rowN1)                    tags += '<span class="cs-tag cs-tag-n1">N+1</span> ';

            html += '<tr class="cs-expandable ' + rowClass + '" data-idx="' + i + '">'
                + '<td class="c-n">' + (i + 1) + '</td>'
                + '<td class="c-q">'
                    + '<span class="' + kwColour(q.keyword) + '">' + esc(q.keyword) + '</span> '
                    + esc(sqlShort.replace(q.keyword, '').trimStart())
                    + (tags ? '<br><span style="margin-top:2px;display:inline-block">' + tags + '</span>' : '')
                    + (q.caller ? '<br><span style="color:#666;font-size:10px">' + esc(q.caller) + '</span>' : '')
                + '</td>'
                + '<td class="c-p">' + pluginChip(q.plugin) + '</td>'
                + '<td class="c-r" style="color:#888">' + rowsText + '</td>'
                + '<td class="c-t">' + timeCell(q.time_ms, maxMs) + '</td>'
                + '</tr>'
                // Detail row — full SQL, call chain, EXPLAIN button
                + '<tr class="cs-row-detail" id="cs-dr-' + i + '" style="display:none"><td colspan="5">'
                    + esc(q.sql)
                    + renderCallStack(q.stack || [])
                    + (canExp
                        ? '<br><button class="cs-explain-btn" data-sql="' + esc(q.sql) + '" data-row="' + i + '">EXPLAIN</button>'
                        + '<div id="cs-exp-' + i + '" class="cs-explain-result"></div>'
                        : '')
                + '</td></tr>';
        });

        dbTbody.innerHTML = html;

        Array.prototype.forEach.call(dbTbody.querySelectorAll('tr.cs-expandable'), function (tr) {
            tr.addEventListener('click', function () {
                var d = document.getElementById('cs-dr-' + tr.dataset.idx);
                if (d) d.style.display = d.style.display === 'none' ? '' : 'none';
            });
        });

        Array.prototype.forEach.call(dbTbody.querySelectorAll('.cs-explain-btn'), function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                runExplain(btn.getAttribute('data-sql'), btn.getAttribute('data-row'), btn);
            });
        });
    }

    // ── Call stack renderer ───────────────────────────────────────────────────
    /**
     * Renders the SAVEQUERIES call-chain array as a visual trace.
     *
     * Frame types:
     *   hook   — teal  — do_action / apply_filters (WP entry point)
     *   plugin — blue  — require from wp-content/plugins/
     *   theme  — purple— require from wp-content/themes/
     *   code   — white — application function / class method
     *   wp     — grey  — WP_Hook, call_user_func etc.
     *   file   — grey  — require from wp core
     *   db     — dark  — wpdb (implicit, badge hidden)
     */
    function renderCallStack(stack) {
        if (!stack || stack.length === 0) return '';

        // Drop trailing db/wp frames — they add no developer-relevant info
        var frames = stack.slice();
        while (frames.length > 0 && (frames[frames.length - 1].type === 'db' || frames[frames.length - 1].type === 'wp')) {
            frames.pop();
        }
        // Also drop leading db frames
        while (frames.length > 0 && frames[0].type === 'db') frames.shift();

        if (frames.length === 0) return '';

        var html = '<div class="cs-stack-trace">'
            + '<div class="cs-stack-hdr">Call chain — ' + frames.length + ' frames (most recent first)</div>'
            + '<div class="cs-stack-frames">';

        frames.forEach(function (f) {
            var typeLabel = f.type === 'plugin' ? 'plugin' :
                            f.type === 'theme'  ? 'theme'  :
                            f.type === 'hook'   ? 'hook'   :
                            f.type === 'code'   ? 'fn'     :
                            f.type === 'file'   ? 'core'   :
                            f.type === 'wp'     ? 'wp'     : '';

            html += '<div class="cs-sf cs-sf-' + esc(f.type) + '">'
                + '<span class="cs-sf-name" title="' + esc(f.frame) + '">' + esc(truncate(f.frame, 90)) + '</span>'
                + (typeLabel ? '<span class="cs-sf-type">' + typeLabel + '</span>' : '')
                + '</div>';
        });

        return html + '</div></div>';
    }

    // ── EXPLAIN AJAX ──────────────────────────────────────────────────────────
    function runExplain(sql, rowIdx, btn) {
        var resultDiv = document.getElementById('cs-exp-' + rowIdx);
        if (!resultDiv) return;
        btn.disabled = true; btn.textContent = 'Loading\u2026';
        resultDiv.innerHTML = '<div class="cs-explain-loading">Running EXPLAIN\u2026</div>';

        var xhr = new XMLHttpRequest();
        xhr.open('POST', meta.ajax_url || '/wp-admin/admin-ajax.php');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function () {
            btn.disabled = false; btn.textContent = 'EXPLAIN';
            try {
                var resp = JSON.parse(xhr.responseText);
                resultDiv.innerHTML = resp.success
                    ? renderExplainTable(resp.data.rows)
                    : '<div class="cs-explain-error">' + esc(resp.data) + '</div>';
            } catch (e) { resultDiv.innerHTML = '<div class="cs-explain-error">Parse error</div>'; }
        };
        xhr.onerror = function () {
            btn.disabled = false; btn.textContent = 'EXPLAIN';
            resultDiv.innerHTML = '<div class="cs-explain-error">Request failed</div>';
        };
        xhr.send('action=cs_perf_explain&nonce=' + encodeURIComponent(meta.explain_nonce || '')
                 + '&sql=' + encodeURIComponent(sql));
    }

    function renderExplainTable(rows) {
        if (!rows || rows.length === 0) return '<div class="cs-explain-empty">No rows returned.</div>';
        var cols = Object.keys(rows[0]);
        var html = '<table class="cs-explain-table"><thead><tr>';
        cols.forEach(function (c) { html += '<th>' + esc(c) + '</th>'; });
        html += '</tr></thead><tbody>';
        rows.forEach(function (row) {
            html += '<tr>';
            cols.forEach(function (c) {
                var val = row[c] !== null && row[c] !== undefined ? String(row[c]) : 'NULL';
                var cls = '';
                if (c === 'type') {
                    cls = val === 'ALL' ? 'cs-exp-bad' : val === 'index' ? 'cs-exp-warn'
                        : ['ref','eq_ref','const','system','range'].indexOf(val) >= 0 ? 'cs-exp-good' : '';
                } else if (c === 'rows')  { var n = parseInt(val,10); cls = n > 10000 ? 'cs-exp-bad' : n > 1000 ? 'cs-exp-warn' : ''; }
                  else if (c === 'key'   && val === 'NULL') cls = 'cs-exp-bad';
                  else if (c === 'Extra' && val.indexOf('Using filesort') !== -1) cls = 'cs-exp-warn';
                html += '<td class="' + cls + '">' + esc(val) + '</td>';
            });
            html += '</tr>';
        });
        return html + '</tbody></table>';
    }

    // ── HTTP table ────────────────────────────────────────────────────────────
    function renderHTTP() {
        if (!httpTbody) return;
        if (filteredHTTP.length === 0) {
            httpTbody.innerHTML = '<tr><td colspan="6" class="cs-empty">'
                + '<span class="cs-empty-icon">&#127760;</span>'
                + (data.http.length === 0 ? 'No outbound HTTP calls.' : 'No calls match the filters.')
                + '</td></tr>';
            return;
        }
        var sorted = filteredHTTP.slice().sort(function (a, b) {
            return sortDir === 'desc' ? b.time_ms - a.time_ms : a.time_ms - b.time_ms;
        });
        var maxMs = Math.max.apply(null, sorted.map(function (h) { return h.time_ms; }));
        var html  = '';
        sorted.forEach(function (h, i) {
            var tags = '';
            if (h.time_ms >= T_CRITICAL)  tags += '<span class="cs-tag cs-tag-critical">critical</span> ';
            else if (h.time_ms >= T_SLOW) tags += '<span class="cs-tag cs-tag-slow">slow</span> ';
            if (h.cached) tags += '<span class="cs-tag cs-tag-cached">cached</span> ';
            if (h.error)  tags += '<span class="cs-tag cs-tag-error">error</span> ';

            html += '<tr class="cs-expandable ' + rowSpeedClass(h.time_ms) + '" data-idx-h="' + i + '">'
                + '<td class="c-n">' + (i + 1) + '</td>'
                + '<td class="c-m">' + methodBadge(h.method) + '</td>'
                + '<td class="c-u" title="' + esc(h.url) + '">'
                    + esc(truncateUrl(h.url, 60))
                    + (tags ? '<br><span style="margin-top:2px;display:inline-block">' + tags + '</span>' : '')
                + '</td>'
                + '<td class="c-p">' + pluginChip(h.plugin) + '</td>'
                + '<td class="c-s">' + statusBadge(h.status, h.error) + '</td>'
                + '<td class="c-t">' + timeCell(h.time_ms, maxMs) + '</td>'
                + '</tr>'
                + '<tr class="cs-row-detail" id="cs-hr-' + i + '" style="display:none"><td colspan="6">'
                + esc(h.url) + (h.error ? '\n\nError: ' + h.error : '') + '</td></tr>';
        });
        httpTbody.innerHTML = html;
        Array.prototype.forEach.call(httpTbody.querySelectorAll('tr.cs-expandable'), function (tr) {
            tr.addEventListener('click', function () {
                var d = document.getElementById('cs-hr-' + tr.dataset.idxH);
                if (d) d.style.display = d.style.display === 'none' ? '' : 'none';
            });
        });
    }

    // ── Debug bar ─────────────────────────────────────────────────────────────
    function renderDebugBar() {
        var statusEl = document.getElementById('cs-debug-status');
        var btnEl    = document.getElementById('cs-debug-toggle');
        if (!statusEl || !btnEl) return;
        var on = meta.wp_debug && meta.wp_debug_log;
        statusEl.textContent    = on ? '● Debug logging ON' : '○ Debug logging OFF';
        statusEl.className      = 'cs-debug-status ' + (on ? 'cs-debug-on' : 'cs-debug-off');
        btnEl.textContent       = on ? 'Disable' : 'Enable debug logging';
        btnEl.className         = 'cs-debug-toggle-btn ' + (on ? 'cs-debug-btn-off' : 'cs-debug-btn-on');
    }

    // ── Logs ──────────────────────────────────────────────────────────────────
    function renderLogs() {
        if (!logList) return;
        var logs    = data.logs || [];
        var search  = logSearch  ? logSearch.value.toLowerCase()  : '';
        var level   = logLevel   ? logLevel.value.toLowerCase()   : '';
        var source  = logSource  ? logSource.value                : '';

        var filtered = logs.filter(function (e) {
            if (level  && (e.level  || '').toLowerCase().indexOf(level)  === -1) return false;
            if (source && (e.source || '') !== source) return false;
            if (search && (e.message || '').toLowerCase().indexOf(search) === -1
                       && (e.ts || '').toLowerCase().indexOf(search) === -1) return false;
            return true;
        });

        if (filtered.length === 0) {
            logList.innerHTML = logs.length === 0
                ? '<div class="cs-empty"><span class="cs-empty-icon">&#9989;</span>No log entries found. Enable WP_DEBUG and WP_DEBUG_LOG in wp-config.php to capture logs.</div>'
                : '<div class="cs-empty"><span class="cs-empty-icon">&#128269;</span>No log entries match the current filters.</div>';
            return;
        }

        logList.innerHTML = filtered.map(function (e) {
            var lvl = (e.level || 'info').toLowerCase();
            var levelClass = lvl.indexOf('fatal') !== -1   ? 'cs-log-fatal'
                           : lvl.indexOf('error') !== -1   ? 'cs-log-error'
                           : lvl.indexOf('warn')  !== -1   ? 'cs-log-warning'
                           : lvl.indexOf('dep')   !== -1   ? 'cs-log-deprecated'
                           : lvl.indexOf('notic') !== -1   ? 'cs-log-notice'
                           : 'cs-log-info';
            var srcLabel = e.source === 'php_handler' ? '<span class="cs-log-src-tag">this request</span>' : '';
            return '<div class="cs-log-entry ' + levelClass + '">'
                + '<span class="cs-log-level">' + esc(e.level || 'info') + '</span>'
                + srcLabel
                + (e.ts ? '<span class="cs-log-ts">' + esc(e.ts) + '</span>' : '')
                + '<div class="cs-log-msg">' + esc(e.message || '') + '</div>'
                + '</div>';
        }).join('');
    }

    // ── Summary ───────────────────────────────────────────────────────────────
    function renderSummary() {
        if (!summaryWrap) return;
        var queries = data.queries, http = data.http, logs = data.logs || [];

        var slowQ  = queries.filter(function (q) { return q.time_ms >= T_SLOW; }).length;
        var critQ  = queries.filter(function (q) { return q.time_ms >= T_CRITICAL; }).length;
        var dupeQ  = queries.filter(function (q) { return q.is_dupe; }).length;
        var n1Cnt  = Object.keys(n1Patterns).length;
        var slowH  = http.filter(function (h) { return h.time_ms >= T_SLOW; }).length;
        var cacH   = http.filter(function (h) { return h.cached; }).length;
        var errH   = http.filter(function (h) { return !!h.error; }).length;
        var errL   = logs.filter(function (e) { return (e.level || '').toLowerCase().indexOf('error') !== -1; }).length;
        var warnL  = logs.filter(function (e) { return (e.level || '').toLowerCase().indexOf('warn') !== -1; }).length;
        var depL   = logs.filter(function (e) { return (e.level || '').toLowerCase().indexOf('dep') !== -1; }).length;

        // Plugin leaderboard
        var byP = {};
        queries.forEach(function (q) {
            if (!byP[q.plugin]) byP[q.plugin] = { count: 0, total_ms: 0, slow: 0, n1: 0 };
            byP[q.plugin].count++; byP[q.plugin].total_ms += q.time_ms;
            if (q.time_ms >= T_SLOW) byP[q.plugin].slow++;
            if (isN1(q.sql))         byP[q.plugin].n1++;
        });
        var pluginList = Object.keys(byP).map(function (p) {
            return { plugin: p, count: byP[p].count, total_ms: byP[p].total_ms, slow: byP[p].slow, n1: byP[p].n1 };
        }).sort(function (a, b) { return b.total_ms - a.total_ms; });
        var maxPMs = pluginList.length > 0 ? pluginList[0].total_ms : 1;

        var top5Q   = queries.slice().sort(function (a, b) { return b.time_ms - a.time_ms; }).slice(0, 5);
        var top5H   = http.slice().sort(function (a, b) { return b.time_ms - a.time_ms; }).slice(0, 5);
        var n1List  = Object.values(n1Patterns).sort(function (a, b) { return b.count - a.count; });

        var dupeGroups = {};
        queries.forEach(function (q) {
            var fp = q.sql.replace(/\s+/g, ' ').toLowerCase().trim();
            if (!dupeGroups[fp]) dupeGroups[fp] = { sql: q.sql, count: 0, total_ms: 0 };
            dupeGroups[fp].count++; dupeGroups[fp].total_ms += q.time_ms;
        });
        var dupeList = Object.values(dupeGroups).filter(function (g) { return g.count > 1; })
            .sort(function (a, b) { return b.count - a.count; }).slice(0, 8);

        var html = '<div class="cs-sum-cards">';

        html += '<div class="cs-sum-card cs-sum-card-db"><div class="cs-sum-card-title">&#128200; DB Queries</div>'
            + '<div class="cs-sum-card-stat">' + meta.query_count + '</div>'
            + '<div class="cs-sum-card-sub"><span>' + fmtMs(meta.query_total_ms) + ' total</span>'
            + (critQ ? '<span class="cs-s-crit">&#9888; ' + critQ + ' critical</span>'
              : slowQ ? '<span class="cs-s-warn">&#9651; ' + slowQ + ' slow</span>'
              : '<span class="cs-s-ok">&#10003; No slow queries</span>')
            + (dupeQ ? '<span class="cs-s-warn">&#9654; ' + dupeQ + ' exact dupes</span>' : '')
            + (n1Cnt ? '<span class="cs-s-warn">&#8635; ' + n1Cnt + ' N+1 pattern' + (n1Cnt > 1 ? 's' : '') + '</span>' : '')
            + '</div></div>';

        html += '<div class="cs-sum-card cs-sum-card-http"><div class="cs-sum-card-title">&#127760; HTTP / REST</div>'
            + '<div class="cs-sum-card-stat">' + meta.http_count + '</div>'
            + '<div class="cs-sum-card-sub"><span>' + fmtMs(meta.http_total_ms) + ' total</span>'
            + (slowH ? '<span class="cs-s-warn">&#9651; ' + slowH + ' slow</span>' : '')
            + (cacH  ? '<span class="cs-s-ok">&#9632; ' + cacH + ' cached</span>' : '')
            + (errH  ? '<span class="cs-s-crit">&#10007; ' + errH + ' errors</span>' : '')
            + (http.length === 0 ? '<span>No outbound calls</span>' : '')
            + '</div></div>';

        html += '<div class="cs-sum-card cs-sum-card-log"><div class="cs-sum-card-title">&#128196; Logs</div>'
            + '<div class="cs-sum-card-stat">' + logs.length + '</div>'
            + '<div class="cs-sum-card-sub">'
            + (errL  ? '<span class="cs-s-crit">&#10007; ' + errL  + ' errors</span>' : '')
            + (warnL ? '<span class="cs-s-warn">&#9651; ' + warnL + ' warnings</span>' : '')
            + (depL  ? '<span class="cs-s-warn">&#8987; ' + depL  + ' deprecated</span>' : '')
            + (logs.length === 0 ? '<span class="cs-s-ok">&#10003; No log entries</span>' : '')
            + '</div></div>';

        html += '</div>'; // cards

        if (pluginList.length > 0) {
            html += '<div><div class="cs-sum-section-title">Plugin Leaderboard — DB query time</div>';
            pluginList.slice(0, 8).forEach(function (p, i) {
                var bar = maxPMs > 0 ? Math.max(2, Math.round((p.total_ms / maxPMs) * 100)) : 2;
                var extras = '';
                if (p.slow) extras += ' &middot; <span style="color:#ce9178">' + p.slow + ' slow</span>';
                if (p.n1)   extras += ' &middot; <span style="color:#c586c0">' + p.n1 + ' N+1</span>';
                html += '<div class="cs-sum-lb-row">'
                    + '<span class="cs-sum-lb-rank">' + (i+1) + '</span>'
                    + '<span class="cs-sum-lb-name" title="' + esc(p.plugin) + '">' + esc(p.plugin) + '</span>'
                    + '<div class="cs-sum-lb-bar-wrap"><div class="cs-sum-lb-bar" style="width:' + bar + '%"></div></div>'
                    + '<span class="cs-sum-lb-val">' + p.count + ' &middot; ' + fmtMs(p.total_ms) + extras + '</span>'
                    + '</div>';
            });
            html += '</div>';
        }

        if (top5Q.length > 0) {
            html += '<div><div class="cs-sum-section-title">Slowest Queries</div>';
            top5Q.forEach(function (q) {
                html += '<div class="cs-sum-top-row">'
                    + '<span class="cs-sum-top-time cs-tv-' + speedClass(q.time_ms) + '">' + fmtMs(q.time_ms) + '</span>'
                    + '<span class="cs-sum-top-sql">' + esc(truncate(q.sql, 80)) + '</span>'
                    + '<span class="cs-sum-top-plugin" title="' + esc(q.plugin) + '">' + esc(q.plugin) + '</span>'
                    + '</div>';
            });
            html += '</div>';
        }

        if (n1List.length > 0) {
            html += '<div><div class="cs-sum-section-title">N+1 Query Patterns (' + n1List.length + ')'
                + ' <span style="font-size:9px;color:#888;text-transform:none;letter-spacing:0">'
                + '— same SQL structure, different values; usually a loop making individual lookups</span></div>';
            n1List.forEach(function (p) {
                html += '<div class="cs-sum-n1-row">'
                    + '<span class="cs-sum-n1-count">&times;' + p.count + '</span>'
                    + '<span class="cs-sum-n1-sql">' + esc(truncate(normalisePattern(p.example), 80)) + '</span>'
                    + '<span class="cs-sum-n1-plugin" title="' + esc(p.plugin) + '">' + esc(p.plugin) + '</span>'
                    + '</div>';
            });
            html += '</div>';
        }

        if (top5H.length > 0) {
            html += '<div><div class="cs-sum-section-title">Slowest HTTP Calls</div>';
            top5H.forEach(function (h) {
                html += '<div class="cs-sum-top-row">'
                    + '<span class="cs-sum-top-time cs-tv-' + speedClass(h.time_ms) + '">' + fmtMs(h.time_ms) + '</span>'
                    + '<span class="cs-sum-top-sql">' + methodBadge(h.method) + ' ' + esc(truncateUrl(h.url, 65)) + '</span>'
                    + '<span class="cs-sum-top-plugin" title="' + esc(h.plugin) + '">' + esc(h.plugin) + '</span>'
                    + '</div>';
            });
            html += '</div>';
        }

        if (dupeList.length > 0) {
            html += '<div><div class="cs-sum-section-title">Exact Duplicate Queries (' + dupeList.length + ' groups)</div>';
            dupeList.forEach(function (g) {
                html += '<div class="cs-sum-dupe-row">'
                    + '<span class="cs-sum-dupe-count">&times;' + g.count + '</span>'
                    + '<span class="cs-sum-dupe-sql">' + esc(truncate(g.sql, 80)) + '</span>'
                    + '<span class="cs-sum-dupe-avg">' + fmtMs(g.count > 0 ? g.total_ms / g.count : 0) + ' avg</span>'
                    + '</div>';
            });
            html += '</div>';
        }

        summaryWrap.innerHTML = html;
    }

    // ── Footer ────────────────────────────────────────────────────────────────
    function updateFooter() {
        var slow  = filteredDB.filter(function (q) { return q.time_ms >= T_SLOW; }).length;
        var crit  = filteredDB.filter(function (q) { return q.time_ms >= T_CRITICAL; }).length;
        var dupes = filteredDB.filter(function (q) { return q.is_dupe; }).length;
        var n1s   = filteredDB.filter(function (q) { return isN1(q.sql); }).length;
        var total = filteredDB.reduce(function (s, q) { return s + q.time_ms; }, 0);
        var hSlow = filteredHTTP.filter(function (h) { return h.time_ms >= T_SLOW; }).length;

        var parts = [filteredDB.length + ' / ' + data.queries.length + ' queries'];
        if (crit)  parts.push('<span class="cs-foot-crit">' + crit + ' critical</span>');
        else if (slow) parts.push('<span class="cs-foot-warn">' + slow + ' slow</span>');
        if (dupes) parts.push('<span class="cs-foot-warn">' + dupes + ' dupes</span>');
        if (n1s)   parts.push('<span class="cs-foot-warn">' + n1s + ' N+1</span>');
        parts.push(fmtMs(total) + ' DB time');
        if (filteredHTTP.length > 0) parts.push(filteredHTTP.length + ' HTTP');
        if (hSlow) parts.push('<span class="cs-foot-warn">' + hSlow + ' slow HTTP</span>');
        footTxt.innerHTML = parts.join('  &middot;  ');
    }

    // ── Export JSON ───────────────────────────────────────────────────────────
    function exportJSON() {
        try {
            var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            var url  = URL.createObjectURL(blob);
            var a    = document.createElement('a');
            a.href = url; a.download = 'cs-perf-' + Date.now() + '.json';
            document.body.appendChild(a); a.click();
            document.body.removeChild(a); URL.revokeObjectURL(url);
        } catch (e) {
            var w = window.open('', '_blank');
            if (w) w.document.write('<pre>' + JSON.stringify(data, null, 2) + '</pre>');
        }
    }

    // ── Resize ────────────────────────────────────────────────────────────────
    function bindResizeHandle() {
        var startY, startH;
        resizeHandle.addEventListener('mousedown', function (e) {
            e.preventDefault(); startY = e.clientY; startH = panel.offsetHeight;
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup',   onUp);
        });
        function onMove(e) {
            panel.style.transition = 'none';
            panel.style.height = clampHeight(startH + (startY - e.clientY)) + 'px';
            if (!panel.classList.contains('cs-perf-open')) {
                panel.classList.add('cs-perf-open'); panel.classList.remove('cs-perf-collapsed');
                document.getElementById('cs-perf-toggle-arrow').innerHTML = '&#9660;'; toggleBtn.setAttribute('aria-expanded', 'true');
                localStorage.setItem(LS_OPEN, '1');
            }
        }
        function onUp() {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup',   onUp);
            localStorage.setItem(LS_HEIGHT, panel.offsetHeight);
            panel.style.transition = '';
        }
    }

    // ── Sort header binding ───────────────────────────────────────────────────
    function bindSortHeaders() {
        Array.prototype.forEach.call(document.querySelectorAll('.cs-sortable'), function (th) {
            th.addEventListener('click', function () {
                var col = th.dataset.sort;
                if (sortCol === col) sortDir = sortDir === 'desc' ? 'asc' : 'desc';
                else { sortCol = col; sortDir = 'desc'; }
                updateSortHeaders();
                applyFilters();
            });
        });
    }

    // ── Events ────────────────────────────────────────────────────────────────
    function bindEvents() {
        document.getElementById('cs-perf-header').addEventListener('click', function (e) {
            if (toggleBtn.contains(e.target) || (exportBtn && exportBtn.contains(e.target))) return;
            var helpBtn = document.getElementById('cs-perf-help-btn');
            if (helpBtn && helpBtn.contains(e.target)) return;
            togglePanel();
        });
        toggleBtn.addEventListener('click', function (e) { e.stopPropagation(); togglePanel(); });
        if (exportBtn) exportBtn.addEventListener('click', function (e) { e.stopPropagation(); exportJSON(); });

        var helpBtn   = document.getElementById('cs-perf-help-btn');
        var helpPanel = document.getElementById('cs-perf-help');
        var helpClose = document.getElementById('cs-perf-help-close');
        if (helpBtn && helpPanel) {
            helpBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                helpPanel.style.display = helpPanel.style.display === 'none' ? '' : 'none';
            });
            if (helpClose) helpClose.addEventListener('click', function (e) {
                e.stopPropagation();
                helpPanel.style.display = 'none';
            });
        }

        tabBtns.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                switchTab(btn.dataset.tab);
                if (!panel.classList.contains('cs-perf-open'))
                    openPanel(parseInt(localStorage.getItem(LS_HEIGHT), 10) || DEFAULT_H, true);
            });
        });

        [searchInput, pluginSel, speedSel].forEach(function (el) {
            el.addEventListener('input',  applyFilters);
            el.addEventListener('change', applyFilters);
        });
        dupeChk.addEventListener('change', applyFilters);

        filterBar.addEventListener('click', function (e) { e.stopPropagation(); });
        document.getElementById('cs-perf-tabs').addEventListener('click', function (e) { e.stopPropagation(); });

        // Log filters
        [logSearch, logLevel, logSource].forEach(function (el) {
            if (!el) return;
            el.addEventListener('input',  renderLogs);
            el.addEventListener('change', renderLogs);
            el.addEventListener('click',  function (e) { e.stopPropagation(); });
        });
        var logFiltersEl = document.querySelector('.cs-log-filters');
        if (logFiltersEl) logFiltersEl.addEventListener('click', function (e) { e.stopPropagation(); });

        // Debug toggle
        renderDebugBar();
        var debugToggleBtn = document.getElementById('cs-debug-toggle');
        var debugBar       = document.getElementById('cs-debug-bar');
        if (debugBar) debugBar.addEventListener('click', function (e) { e.stopPropagation(); });
        if (debugToggleBtn) {
            debugToggleBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                var enable   = !(meta.wp_debug && meta.wp_debug_log);
                var msgEl    = document.getElementById('cs-debug-msg');
                debugToggleBtn.disabled = true;
                debugToggleBtn.textContent = 'Saving…';
                var xhr = new XMLHttpRequest();
                xhr.open('POST', meta.ajax_url);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function () {
                    debugToggleBtn.disabled = false;
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (resp.success) {
                            meta.wp_debug     = resp.data.enabled;
                            meta.wp_debug_log = resp.data.enabled;
                            renderDebugBar();
                            if (msgEl) { msgEl.textContent = resp.data.message; setTimeout(function () { msgEl.textContent = ''; }, 4000); }
                        } else {
                            if (msgEl) msgEl.textContent = 'Error: ' + (resp.data || 'unknown');
                        }
                    } catch (err) {
                        if (msgEl) msgEl.textContent = 'Unexpected error';
                    }
                };
                xhr.onerror = function () { debugToggleBtn.disabled = false; if (msgEl) msgEl.textContent = 'Request failed'; };
                xhr.send('action=cs_perf_debug_toggle&nonce=' + encodeURIComponent(meta.debug_nonce) + '&enable=' + (enable ? '1' : '0'));
            });
        }

        bindResizeHandle();
        bindSortHeaders();

        document.addEventListener('keydown', function (e) {
            if (e.ctrlKey && e.shiftKey && (e.key === 'm' || e.key === 'M')) {
                e.preventDefault(); togglePanel();
            }
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    function clampHeight(h) { return Math.max(MIN_H, Math.min(Math.floor(window.innerHeight * MAX_H_PCT), h)); }

    function fmtMs(ms) {
        ms = +ms || 0;
        if (ms < 1)    return ms.toFixed(3) + 'ms';
        if (ms < 1000) return ms.toFixed(ms < 10 ? 2 : 1) + 'ms';
        return (ms / 1000).toFixed(2) + 's';
    }

    function speedClass(ms) {
        return ms >= T_CRITICAL ? 'critical' : ms >= T_SLOW ? 'slow' : ms >= T_MEDIUM ? 'medium' : 'fast';
    }
    function rowSpeedClass(ms) {
        return ms >= T_CRITICAL ? 'cs-row-critical' : ms >= T_SLOW ? 'cs-row-slow' : '';
    }
    function timeCell(ms, maxMs) {
        var cls = speedClass(ms), w = maxMs > 0 ? Math.max(2, Math.round((ms / maxMs) * 60)) : 2;
        return '<div class="cs-time-cell"><span class="cs-lat-bar cs-lat-' + cls + '" style="width:' + w + 'px"></span>'
            + '<span class="cs-time-val cs-tv-' + cls + '">' + fmtMs(ms) + '</span></div>';
    }
    function pluginChip(plugin) {
        var core = plugin === 'WordPress Core';
        return '<span class="cs-plugin-chip' + (core ? ' cs-plugin-core' : '') + '" title="' + esc(plugin) + '">' + esc(plugin) + '</span>';
    }
    function kwColour(kw) {
        switch (kw) {
            case 'SELECT': case 'SHOW': case 'DESCRIBE': return 'cs-kw-select';
            case 'INSERT': return 'cs-kw-insert';
            case 'UPDATE': return 'cs-kw-update';
            case 'DELETE': return 'cs-kw-delete';
            default:       return 'cs-kw-other';
        }
    }
    function methodBadge(method) {
        return '<span class="cs-method cs-method-' + (method||'get').toLowerCase() + '">' + esc(method) + '</span>';
    }
    function statusBadge(status, error) {
        if (error && !status) return '<span class="cs-status-err">ERR</span>';
        var cls = 'cs-status-' + (status >= 500 ? '5xx' : status >= 400 ? '4xx' : status >= 300 ? '3xx' : status >= 200 ? '2xx' : 'err');
        return '<span class="' + cls + '">' + (status || '—') + '</span>';
    }
    function truncate(str, max) {
        if (!str) return '';
        var s = str.replace(/\s+/g, ' ').trim();
        return s.length > max ? s.slice(0, max - 1) + '\u2026' : s;
    }
    function truncateUrl(url, max) {
        try {
            var u = new URL(url), out = u.hostname + u.pathname;
            if (u.search) out += u.search.slice(0, 20) + (u.search.length > 20 ? '\u2026' : '');
            return out.length > max ? out.slice(0, max - 1) + '\u2026' : out;
        } catch(e) { return truncate(url, max); }
    }
    function esc(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

}());
