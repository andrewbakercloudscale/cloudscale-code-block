/**
 * CloudScale Performance Monitor — DevTools-style admin + frontend panel
 *
 * Tabs: DB Queries | HTTP/REST | PHP Errors | Summary
 * Features: call-chain trace, EXPLAIN on demand, N+1 detection,
 *           multi-column sort, colour-coded severity, export JSON.
 *
 * @since 1.8.6
 */
(function () {
    'use strict';

    var LS_OPEN   = 'cs_perf_open';
    var LS_HEIGHT = 'cs_perf_height';
    var LS_TAB    = 'cs_perf_tab';
    var DEFAULT_H = 340;
    var MIN_H     = 260;
    var MAX_H_PCT = 0.82;

    var T_MEDIUM   = 10;
    var T_SLOW     = 50;
    var T_CRITICAL = 200;
    var N1_THRESH  = 3;

    // ── State ─────────────────────────────────────────────────────────────────
    var data      = window.csPerfData || { queries: [], http: [], errors: [], logs: [], assets: { scripts: [], styles: [] }, cache: {}, hooks: [], meta: {}, request: {}, transients: [], template: { final: '', hierarchy: [] } };
    var meta      = data.meta || {};
    var sortCol   = 'time';
    var sortDir   = 'desc';
    var activeTab = localStorage.getItem(LS_TAB) || 'db';

    var filteredDB   = [];
    var filteredHTTP = [];
    var n1Patterns   = {};

    // Hooks sort state
    var hookSortCol = 'total_ms';
    var hookSortDir = 'desc';

    // ── DOM refs ──────────────────────────────────────────────────────────────
    var panel, toggleBtn, exportBtn, resizeHandle, footTxt, totalTxt, ctxStrip;
    var tabBtns, panes, filterBar;
    var searchInput, pluginSel, speedSel, dupeChk;
    var dbTbody, httpTbody, logList, summaryWrap;
    var dbCount, httpCount, logCount;
    var badgeDB, badgeHTTP, badgeLOG;
    var logSearch, logLevel, logSource;
    var assetsTbody, assetsCount, assetSearch, assetType, assetPlugin;
    var hooksTbody, hooksCount, hookSearch;
    var issuesWrap, requestWrap, templateWrap;
    var transTbody, transCount;

    var issuesList = [];

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
        assetsTbody  = document.getElementById('cs-assets-rows');
        assetsCount  = document.getElementById('cs-ptc-assets');
        assetSearch  = document.getElementById('cs-af-search');
        assetType    = document.getElementById('cs-af-type');
        assetPlugin  = document.getElementById('cs-af-plugin');
        hooksTbody   = document.getElementById('cs-hooks-rows');
        hooksCount   = document.getElementById('cs-ptc-hooks');
        hookSearch   = document.getElementById('cs-hkf-search');
        issuesWrap   = document.getElementById('cs-issues-wrap');
        requestWrap  = document.getElementById('cs-request-wrap');
        templateWrap = document.getElementById('cs-template-wrap');
        transTbody   = document.getElementById('cs-trans-rows');
        transCount   = document.getElementById('cs-ptc-trans');

        if (!panel) return;

        // Move the help panel to document.body so it's outside the fixed panel
        // hierarchy — avoids iOS Safari touch-blocking and overflow:hidden clipping.
        var helpPanel = document.getElementById('cs-perf-help');
        if (helpPanel) document.body.appendChild(helpPanel);

        computeN1Patterns();
        computeIssues();
        populatePluginFilter();
        populateAssetPluginFilter();
        updateBadges();
        updateTotalTime();
        renderPageContext();
        applyFilters();
        renderLogs();
        renderAssets();
        renderHooks();
        renderIssues();
        renderRequest();
        renderTemplate();
        renderTransients();
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
        if (h < MIN_H) { h = DEFAULT_H; localStorage.removeItem(LS_HEIGHT); }
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
        var logFiltersEl    = document.querySelector('.cs-log-filters');
        var assetsFiltersEl = document.querySelector('.cs-assets-filters');
        var hooksFiltersEl  = document.querySelector('.cs-hooks-filters');
        if (logFiltersEl)    logFiltersEl.style.display    = tab === 'logs'   ? '' : 'none';
        if (assetsFiltersEl) assetsFiltersEl.style.display = tab === 'assets' ? '' : 'none';
        if (hooksFiltersEl)  hooksFiltersEl.style.display  = tab === 'hooks'  ? '' : 'none';
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

    function populateAssetPluginFilter() {
        if (!assetPlugin) return;
        var seen = {};
        var assets = data.assets || {};
        (assets.scripts || []).forEach(function (a) { seen[a.plugin] = 1; });
        (assets.styles  || []).forEach(function (a) { seen[a.plugin] = 1; });
        Object.keys(seen).sort().forEach(function (name) {
            var opt   = document.createElement('option');
            opt.value = name; opt.text = name;
            assetPlugin.appendChild(opt);
        });
    }

    // ── Assets tab ────────────────────────────────────────────────────────────
    function renderAssets() {
        if (!assetsTbody) return;
        var assets  = data.assets || {};
        var scripts = assets.scripts || [];
        var styles  = assets.styles  || [];

        var typeFilter   = assetType   ? assetType.value   : '';
        var pluginFilter = assetPlugin ? assetPlugin.value : '';
        var search       = assetSearch ? assetSearch.value.toLowerCase().trim() : '';

        var rows = [];
        if (!typeFilter || typeFilter === 'scripts') {
            scripts.forEach(function (s) { rows.push({ type: 'JS', handle: s.handle, src: s.src, plugin: s.plugin, ver: s.ver }); });
        }
        if (!typeFilter || typeFilter === 'styles') {
            styles.forEach(function (s)  { rows.push({ type: 'CSS', handle: s.handle, src: s.src, plugin: s.plugin, ver: s.ver }); });
        }

        rows = rows.filter(function (r) {
            if (pluginFilter && r.plugin !== pluginFilter) return false;
            if (search && String(r.handle).toLowerCase().indexOf(search) === -1
                       && String(r.src).toLowerCase().indexOf(search) === -1
                       && String(r.plugin).toLowerCase().indexOf(search) === -1) return false;
            return true;
        });

        if (rows.length === 0) {
            assetsTbody.innerHTML = '<tr><td colspan="4" class="cs-empty">'
                + '<span class="cs-empty-icon">&#128190;</span>No assets match the filters.'
                + '</td></tr>';
            return;
        }

        // Sort: plugin then type then handle
        rows.sort(function (a, b) {
            var pc = a.plugin.localeCompare(b.plugin);
            if (pc !== 0) return pc;
            var tc = a.type.localeCompare(b.type);
            return tc !== 0 ? tc : a.handle.localeCompare(b.handle);
        });

        var html = '';
        rows.forEach(function (r) {
            var srcShort = r.src ? truncateUrl(r.src, 55) : '—';
            html += '<tr>'
                + '<td class="c-at"><span class="cs-asset-type-' + r.type.toLowerCase() + '">' + r.type + '</span></td>'
                + '<td class="c-ah" title="' + esc(r.handle) + '">' + esc(r.handle) + (r.ver ? '<span class="cs-asset-ver"> v' + esc(r.ver) + '</span>' : '') + '</td>'
                + '<td class="c-ap">' + pluginChip(r.plugin) + '</td>'
                + '<td class="c-au" title="' + esc(r.src) + '"><span class="cs-asset-src">' + esc(srcShort) + '</span></td>'
                + '</tr>';
        });
        assetsTbody.innerHTML = html;
    }

    // ── Hooks tab ─────────────────────────────────────────────────────────────
    function renderHooks() {
        if (!hooksTbody) return;
        var hooks  = data.hooks || [];
        var search = hookSearch ? hookSearch.value.toLowerCase().trim() : '';

        var filtered = hooks.filter(function (h) {
            return !search || h.hook.toLowerCase().indexOf(search) !== -1;
        });

        // Sort
        filtered = filtered.slice().sort(function (a, b) {
            var aVal = a[hookSortCol] !== undefined ? a[hookSortCol] : 0;
            var bVal = b[hookSortCol] !== undefined ? b[hookSortCol] : 0;
            if (typeof aVal === 'string') {
                var cmp = aVal.localeCompare(bVal);
                return hookSortDir === 'asc' ? cmp : -cmp;
            }
            return hookSortDir === 'desc' ? bVal - aVal : aVal - bVal;
        });

        if (filtered.length === 0) {
            hooksTbody.innerHTML = '<tr><td colspan="5" class="cs-empty">'
                + '<span class="cs-empty-icon">&#128279;</span>'
                + (hooks.length === 0 ? 'No hooks captured.' : 'No hooks match the filter.')
                + '</td></tr>';
            return;
        }

        var maxMs = filtered.length > 0 ? filtered[0].total_ms : 1;
        var html  = '';
        filtered.forEach(function (h) {
            var barW = maxMs > 0 ? Math.max(2, Math.round((h.total_ms / maxMs) * 60)) : 2;
            var cls  = speedClass(h.max_ms);
            html += '<tr>'
                + '<td class="c-hk" title="' + esc(h.hook) + '">' + esc(h.hook) + '</td>'
                + '<td class="c-hc" style="color:#888">' + h.count + '</td>'
                + '<td class="c-ht"><div class="cs-time-cell">'
                    + '<span class="cs-lat-bar cs-lat-' + cls + '" style="width:' + barW + 'px"></span>'
                    + '<span class="cs-time-val cs-tv-' + cls + '">' + fmtMs(h.total_ms) + '</span>'
                    + '</div></td>'
                + '<td class="c-hm cs-tv-' + speedClass(h.max_ms) + '">' + fmtMs(h.max_ms) + '</td>'
                + '</tr>';
        });
        hooksTbody.innerHTML = html;
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
        if (logCount)    logCount.textContent    = (data.logs || []).length;
        if (assetsCount) {
            var assets = data.assets || {};
            assetsCount.textContent = ((assets.scripts || []).length + (assets.styles || []).length);
        }
        if (hooksCount)  hooksCount.textContent  = (data.hooks       || []).length;
        if (transCount)  transCount.textContent  = (data.transients  || []).length;

        var issuesCntEl = document.getElementById('cs-ptc-issues');
        if (issuesCntEl) {
            var critCnt = issuesList.filter(function (i) { return i.sev === 'critical'; }).length;
            var warnCnt = issuesList.filter(function (i) { return i.sev === 'warning';  }).length;
            var shown   = critCnt + warnCnt;
            issuesCntEl.textContent = shown;
            issuesCntEl.className   = critCnt > 0 ? 'cs-issues-cnt-critical'
                                    : warnCnt > 0 ? 'cs-issues-cnt-warning'
                                    : '';
        }
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

    // ── Issues tab ────────────────────────────────────────────────────────────
    function computeIssues() {
        issuesList = [];

        // Critical & slow queries
        data.queries.forEach(function (q) {
            if (q.time_ms >= T_CRITICAL) {
                issuesList.push({ sev: 'critical', tab: 'db',
                    title: 'Critical query — ' + fmtMs(q.time_ms),
                    detail: truncate(q.sql, 110), plugin: q.plugin });
            } else if (q.time_ms >= T_SLOW) {
                issuesList.push({ sev: 'warning', tab: 'db',
                    title: 'Slow query — ' + fmtMs(q.time_ms),
                    detail: truncate(q.sql, 110), plugin: q.plugin });
            }
        });

        // N+1 patterns (already computed)
        Object.keys(n1Patterns).forEach(function (k) {
            var p = n1Patterns[k];
            issuesList.push({ sev: 'warning', tab: 'db',
                title: 'N+1 pattern — ' + p.count + ' identical calls — ' + fmtMs(p.total_ms) + ' total',
                detail: truncate(normalisePattern(p.example), 110), plugin: p.plugin });
        });

        // Duplicate queries — one entry per group
        var dupeMap = {};
        data.queries.forEach(function (q) {
            if (!q.is_dupe) return;
            var fp = q.sql.replace(/\s+/g, ' ').toLowerCase().trim();
            if (!dupeMap[fp]) dupeMap[fp] = { sql: q.sql, count: 1, plugin: q.plugin };
            else dupeMap[fp].count++;
        });
        Object.keys(dupeMap).forEach(function (k) {
            var g = dupeMap[k];
            issuesList.push({ sev: 'warning', tab: 'db',
                title: 'Duplicate query — ' + g.count + ' extra calls',
                detail: truncate(g.sql, 110), plugin: g.plugin });
        });

        // HTTP errors + slow HTTP
        data.http.forEach(function (h) {
            if (h.error || (h.status && h.status >= 400)) {
                issuesList.push({ sev: 'critical', tab: 'http',
                    title: 'HTTP error' + (h.status ? ' ' + h.status : '') + (h.error ? ' — ' + h.error : '') + ' — ' + fmtMs(h.time_ms),
                    detail: truncateUrl(h.url, 110), plugin: h.plugin });
            } else if (h.time_ms >= T_SLOW) {
                issuesList.push({ sev: 'warning', tab: 'http',
                    title: 'Slow HTTP — ' + fmtMs(h.time_ms),
                    detail: truncateUrl(h.url, 110), plugin: h.plugin });
            }
        });

        // PHP errors / log entries
        (data.logs || []).forEach(function (e) {
            var lvl = (e.level || '').toLowerCase();
            var isFatal = lvl.indexOf('fatal') !== -1;
            var isError = lvl.indexOf('error') !== -1;
            var isWarn  = lvl.indexOf('warn') !== -1 || lvl.indexOf('dep') !== -1;
            if (isFatal || (isError && e.source === 'php_handler')) {
                issuesList.push({ sev: 'critical', tab: 'logs',
                    title: (e.level || 'Error') + (e.source === 'php_handler' ? ' — this request' : ''),
                    detail: truncate(e.message, 110), plugin: '' });
            } else if (isError || isWarn) {
                issuesList.push({ sev: 'warning', tab: 'logs',
                    title: e.level || 'Warning',
                    detail: truncate(e.message, 110), plugin: '' });
            }
        });

        // Object cache health
        var cache = data.cache || {};
        if (cache.available && cache.hit_rate !== null) {
            if (cache.hit_rate < 30) {
                issuesList.push({ sev: 'critical', tab: 'summary',
                    title: 'Object cache hit rate ' + cache.hit_rate + '% — critically low',
                    detail: cache.hits + ' hits / ' + cache.misses + ' misses', plugin: '' });
            } else if (cache.hit_rate < 60) {
                issuesList.push({ sev: 'warning', tab: 'summary',
                    title: 'Object cache hit rate ' + cache.hit_rate + '% — below 60%',
                    detail: cache.hits + ' hits / ' + cache.misses + ' misses', plugin: '' });
            }
        }
        if (cache.available && !cache.persistent) {
            issuesList.push({ sev: 'info', tab: 'summary',
                title: 'No persistent object cache',
                detail: 'Redis or Memcached would reduce database load significantly', plugin: '' });
        }

        // Debug logging
        if (!meta.wp_debug || !meta.wp_debug_log) {
            issuesList.push({ sev: 'info', tab: 'logs',
                title: 'Debug logging is off',
                detail: 'Enable WP_DEBUG + WP_DEBUG_LOG in wp-config.php to capture PHP errors here', plugin: '' });
        }

        // Sort: critical → warning → info
        var order = { critical: 0, warning: 1, info: 2 };
        issuesList.sort(function (a, b) { return (order[a.sev] || 0) - (order[b.sev] || 0); });
    }

    function renderIssues() {
        if (!issuesWrap) return;

        if (issuesList.length === 0) {
            issuesWrap.innerHTML = '<div class="cs-empty" style="padding:24px 12px">'
                + '<span class="cs-empty-icon">&#10003;</span>'
                + 'No issues detected on this page load.</div>';
            return;
        }

        var html = '';
        var lastSev = null;
        var titles  = { critical: '&#128308;&nbsp;Critical', warning: '&#128993;&nbsp;Warnings', info: '&#128994;&nbsp;Info' };
        issuesList.forEach(function (issue) {
            if (issue.sev !== lastSev) {
                if (lastSev !== null) html += '</div>';
                html += '<div class="cs-issues-group"><div class="cs-issues-group-title">' + (titles[issue.sev] || issue.sev) + '</div>';
                lastSev = issue.sev;
            }
            html += '<div class="cs-issue-row cs-issue-' + esc(issue.sev) + '" data-tab="' + esc(issue.tab) + '">'
                + '<div class="cs-issue-top">'
                    + '<span class="cs-issue-title">' + esc(issue.title) + '</span>'
                    + (issue.plugin ? pluginChip(issue.plugin) : '')
                    + '<span class="cs-issue-arrow">&#8594;</span>'
                + '</div>'
                + (issue.detail ? '<div class="cs-issue-detail">' + esc(issue.detail) + '</div>' : '')
                + '</div>';
        });
        if (lastSev !== null) html += '</div>';

        issuesWrap.innerHTML = html;

        Array.prototype.forEach.call(issuesWrap.querySelectorAll('.cs-issue-row[data-tab]'), function (row) {
            row.addEventListener('click', function () { switchTab(row.getAttribute('data-tab')); });
        });
    }

    // ── Request tab ───────────────────────────────────────────────────────────
    function renderRequest() {
        if (!requestWrap) return;
        var req = data.request || {};

        var html = '<div class="cs-req-body">';

        // Method / URL / Rewrite
        html += '<div class="cs-req-section"><div class="cs-req-section-title">Request</div>'
            + '<div class="cs-req-kv"><span class="cs-req-k">Method</span><span class="cs-req-v">' + methodBadge(req.method || 'GET') + '</span></div>'
            + '<div class="cs-req-kv"><span class="cs-req-k">URL</span><span class="cs-req-v cs-req-mono">' + esc(meta.url || '—') + '</span></div>'
            + (req.matched_rule ? '<div class="cs-req-kv"><span class="cs-req-k">Rewrite rule</span><span class="cs-req-v cs-req-mono">' + esc(req.matched_rule) + '</span></div>' : '')
            + '</div>';

        // GET params
        var getKeys = Object.keys(req.get || {});
        html += '<div class="cs-req-section"><div class="cs-req-section-title">GET params (' + getKeys.length + ')</div>';
        if (getKeys.length === 0) {
            html += '<div class="cs-req-empty">None</div>';
        } else {
            getKeys.forEach(function (k) {
                html += '<div class="cs-req-kv"><span class="cs-req-k">' + esc(k) + '</span><span class="cs-req-v cs-req-mono">' + esc((req.get || {})[k]) + '</span></div>';
            });
        }
        html += '</div>';

        // POST keys only
        var postKeys = req.post_keys || [];
        html += '<div class="cs-req-section"><div class="cs-req-section-title">POST fields (' + postKeys.length + ') — keys only</div>';
        if (postKeys.length === 0) {
            html += '<div class="cs-req-empty">None</div>';
        } else {
            html += '<div class="cs-req-tags">'
                + postKeys.map(function (k) { return '<code class="cs-req-tag">' + esc(k) + '</code>'; }).join(' ')
                + '</div>';
        }
        html += '</div>';

        // WP query vars
        var qvKeys = Object.keys(req.query_vars || {});
        html += '<div class="cs-req-section"><div class="cs-req-section-title">WP Query Vars (' + qvKeys.length + ')</div>';
        if (qvKeys.length === 0) {
            html += '<div class="cs-req-empty">None — admin page, or parse_request has not run</div>';
        } else {
            qvKeys.forEach(function (k) {
                html += '<div class="cs-req-kv"><span class="cs-req-k">' + esc(k) + '</span><span class="cs-req-v cs-req-mono">' + esc((req.query_vars || {})[k]) + '</span></div>';
            });
        }
        html += '</div>';

        // Current user
        var roles = req.user_roles || [];
        html += '<div class="cs-req-section"><div class="cs-req-section-title">Current User</div>'
            + '<div class="cs-req-kv"><span class="cs-req-k">Roles</span><span class="cs-req-v">'
            + (roles.length
                ? roles.map(function (r) { return '<code class="cs-req-tag">' + esc(r) + '</code>'; }).join(' ')
                : '<em style="color:#666">None / not logged in</em>')
            + '</span></div></div>';

        html += '</div>';
        requestWrap.innerHTML = html;
    }

    // ── Template hierarchy tab ────────────────────────────────────────────────
    function renderTemplate() {
        if (!templateWrap) return;
        var tmpl = data.template || {};

        if (!tmpl.final && (!tmpl.hierarchy || tmpl.hierarchy.length === 0)) {
            templateWrap.innerHTML = '<div class="cs-empty" style="padding:20px 12px">'
                + '<span class="cs-empty-icon">&#128196;</span>'
                + 'Template hierarchy is only captured on frontend pages.</div>';
            return;
        }

        var html = '';

        if (tmpl.hierarchy && tmpl.hierarchy.length > 0) {
            tmpl.hierarchy.forEach(function (entry) {
                html += '<div class="cs-tmpl-group">'
                    + '<div class="cs-tmpl-type-hdr">' + esc(entry.type) + ' template</div>';
                entry.candidates.forEach(function (c) {
                    var cls  = c.active ? 'cs-tmpl-active' : c.found ? 'cs-tmpl-found' : 'cs-tmpl-miss';
                    var icon = c.active ? '&#9654;' : c.found ? '&#10003;' : '&mdash;';
                    var locTag = c.location
                        ? '<span class="cs-tmpl-loc cs-tmpl-loc-' + esc(c.location) + '">' + esc(c.location) + '</span>'
                        : '';
                    html += '<div class="cs-tmpl-row ' + cls + '">'
                        + '<span class="cs-tmpl-icon">' + icon + '</span>'
                        + '<span class="cs-tmpl-file">' + esc(c.file) + '</span>'
                        + locTag
                        + '</div>';
                });
                html += '</div>';
            });
        } else if (tmpl.final) {
            // Hierarchy not captured (e.g. full-page templates via template_include only)
            html = '<div class="cs-tmpl-group"><div class="cs-tmpl-type-hdr">Active template</div>'
                + '<div class="cs-tmpl-row cs-tmpl-active"><span class="cs-tmpl-icon">&#9654;</span>'
                + '<span class="cs-tmpl-file">' + esc(tmpl.final) + '</span></div></div>';
        }

        templateWrap.innerHTML = html;
    }

    // ── Transients tab ────────────────────────────────────────────────────────
    function renderTransients() {
        if (!transTbody) return;
        var transients = data.transients || [];

        if (transients.length === 0) {
            transTbody.innerHTML = '<tr><td colspan="7" class="cs-empty">'
                + '<span class="cs-empty-icon">&#9744;</span>'
                + 'No transients accessed or set on this page load.</td></tr>';
            return;
        }

        var html = '';
        transients.forEach(function (t) {
            var hr     = t.hit_rate !== null ? t.hit_rate : null;
            var hrCls  = hr === null ? '' : hr >= 80 ? 'cs-tv-fast' : hr >= 50 ? 'cs-tv-medium' : 'cs-tv-slow';
            var hrTxt  = hr !== null ? hr + '%' : '&mdash;';
            var missCls = t.misses > 0 ? 'cs-trans-miss' : '';
            html += '<tr>'
                + '<td class="c-tk" title="' + esc(t.key) + '">' + esc(t.key) + '</td>'
                + '<td class="c-tg">' + (t.gets    || 0) + '</td>'
                + '<td class="c-th" style="color:#4ec9b0">' + (t.hits    || 0) + '</td>'
                + '<td class="c-tm ' + missCls + '">' + (t.misses  || 0) + '</td>'
                + '<td class="c-ts" style="color:#9cdcfe">' + (t.sets    || 0) + '</td>'
                + '<td class="c-td" style="color:#888">' + (t.deletes || 0) + '</td>'
                + '<td class="c-tr"><span class="' + hrCls + '">' + hrTxt + '</span></td>'
                + '</tr>';
        });
        transTbody.innerHTML = html;
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

        // Environment card
        if (meta.php_version) {
            var memPct = 0;
            if (meta.memory_peak_mb && meta.memory_limit) {
                var limitMb = parseInt(meta.memory_limit, 10) || 0;
                if (limitMb > 0) memPct = Math.round((meta.memory_peak_mb / limitMb) * 100);
            }
            var memCls = memPct >= 90 ? ' cs-s-crit' : memPct >= 70 ? ' cs-s-warn' : '';
            html += '<div class="cs-sum-card cs-sum-card-env"><div class="cs-sum-card-title">&#9881; Environment</div>'
                + '<div class="cs-sum-card-sub">'
                + '<span>PHP&nbsp;' + esc(meta.php_version) + '</span>'
                + '<span>WP&nbsp;'  + esc(meta.wp_version  || '?') + '</span>'
                + (meta.mysql_version ? '<span>MySQL&nbsp;' + esc(meta.mysql_version) + '</span>' : '')
                + (meta.memory_peak_mb ? '<span class="' + memCls + '">Mem peak:&nbsp;' + meta.memory_peak_mb + 'MB&nbsp;/&nbsp;' + esc(meta.memory_limit || '?') + '</span>' : '')
                + (meta.active_theme   ? '<span>Theme:&nbsp;' + esc(meta.active_theme) + '</span>' : '')
                + (meta.is_multisite   ? '<span style="color:#9cdcfe">Multisite</span>' : '')
                + '</div></div>';
        }

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

        // Cache card
        var cache = data.cache || {};
        if (cache.available) {
            var hitRateStr = cache.hit_rate !== null ? cache.hit_rate + '%' : '–';
            var cacheClass = cache.hit_rate !== null ? (cache.hit_rate >= 80 ? 'cs-s-ok' : cache.hit_rate >= 50 ? 'cs-s-warn' : 'cs-s-crit') : '';
            html += '<div class="cs-sum-card cs-sum-card-cache"><div class="cs-sum-card-title">&#9889; Object Cache</div>'
                + '<div class="cs-sum-card-stat">' + hitRateStr + '</div>'
                + '<div class="cs-sum-card-sub">'
                + (cache.hit_rate !== null ? '<span class="' + cacheClass + '">&#9679; ' + hitRateStr + ' hit rate</span>' : '')
                + '<span>' + (cache.hits || 0) + ' hits &middot; ' + (cache.misses || 0) + ' misses</span>'
                + (cache.persistent ? '<span class="cs-s-ok">Persistent cache active</span>' : '<span style="color:#888">Non-persistent (no Redis/Memcache)</span>')
                + '</div></div>';
        }

        // Assets card
        var assets = data.assets || {};
        var jsCount  = (assets.scripts || []).length;
        var cssCount = (assets.styles  || []).length;
        html += '<div class="cs-sum-card cs-sum-card-assets"><div class="cs-sum-card-title">&#128190; Assets</div>'
            + '<div class="cs-sum-card-stat">' + (jsCount + cssCount) + '</div>'
            + '<div class="cs-sum-card-sub">'
            + '<span>' + jsCount + ' JS &middot; ' + cssCount + ' CSS</span>'
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

        // Top hooks
        var topHooks = (data.hooks || []).slice(0, 8);
        if (topHooks.length > 0) {
            var maxHookMs = topHooks[0].total_ms || 1;
            html += '<div><div class="cs-sum-section-title">Slowest Hooks (top 8 by total time)</div>';
            topHooks.forEach(function (h) {
                var bar = maxHookMs > 0 ? Math.max(2, Math.round((h.total_ms / maxHookMs) * 100)) : 2;
                html += '<div class="cs-sum-lb-row">'
                    + '<span class="cs-sum-lb-name" title="' + esc(h.hook) + '">' + esc(h.hook) + '</span>'
                    + '<div class="cs-sum-lb-bar-wrap"><div class="cs-sum-lb-bar cs-sum-lb-bar-hook" style="width:' + bar + '%"></div></div>'
                    + '<span class="cs-sum-lb-val">' + h.count + '× &middot; ' + fmtMs(h.total_ms) + ' total &middot; max ' + fmtMs(h.max_ms) + '</span>'
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

    // ── Copy current tab to clipboard ─────────────────────────────────────────
    function copyCurrentTab() {
        var tab   = activeTab;
        var lines = ['=== CS Monitor: ' + tab.toUpperCase() + ' ===', 'URL: ' + (meta.url || window.location.href), ''];

        switch (tab) {
            case 'issues':
                if (issuesList.length === 0) {
                    lines.push('No issues detected.');
                } else {
                    issuesList.forEach(function (issue) {
                        lines.push('[' + issue.sev.toUpperCase() + '] ' + issue.title
                            + (issue.detail ? ' — ' + issue.detail : '')
                            + ' (\u2192 ' + issue.tab + ')');
                    });
                }
                break;
            case 'db':
                lines.push('Queries: ' + filteredDB.length + ' / ' + data.queries.length);
                lines.push('');
                filteredDB.forEach(function (q, i) {
                    lines.push((i + 1) + '. [' + q.keyword + '] ' + q.sql.replace(/\s+/g, ' ').trim());
                    lines.push('   Plugin: ' + q.plugin + ' | Rows: ' + (q.rows >= 0 ? q.rows : '\u2013') + ' | Time: ' + fmtMs(q.time_ms));
                    if (q.is_dupe)   lines.push('   [DUPLICATE]');
                    if (isN1(q.sql)) lines.push('   [N+1 PATTERN]');
                });
                break;
            case 'http':
                lines.push('HTTP calls: ' + filteredHTTP.length);
                lines.push('');
                filteredHTTP.forEach(function (h, i) {
                    lines.push((i + 1) + '. [' + (h.method || 'GET') + '] ' + h.url);
                    lines.push('   Plugin: ' + h.plugin + ' | Status: ' + (h.status || 'ERR') + ' | Time: ' + fmtMs(h.time_ms));
                    if (h.error) lines.push('   Error: ' + h.error);
                });
                break;
            case 'logs':
                var logs = data.logs || [];
                lines.push('Log entries: ' + logs.length);
                lines.push('');
                logs.forEach(function (l) {
                    lines.push('[' + (l.level || 'info').toUpperCase() + '] ' + (l.message || '')
                        + (l.file ? ' (' + l.file + (l.line ? ':' + l.line : '') + ')' : ''));
                });
                break;
            case 'assets':
                var assets = data.assets || {};
                var scripts = assets.scripts || [], styles = assets.styles || [];
                lines.push('Scripts: ' + scripts.length + ' | Styles: ' + styles.length);
                lines.push('');
                lines.push('--- Scripts ---');
                scripts.forEach(function (a) { lines.push(a.handle + ' | ' + a.plugin + ' | ' + (a.src || '')); });
                lines.push('');
                lines.push('--- Styles ---');
                styles.forEach(function (a) { lines.push(a.handle + ' | ' + a.plugin + ' | ' + (a.src || '')); });
                break;
            case 'hooks':
                var hooks = data.hooks || [];
                lines.push('Hooks: ' + hooks.length);
                lines.push('');
                hooks.slice(0, 50).forEach(function (h) {
                    lines.push(h.hook + ' | ' + h.count + 'x | ' + fmtMs(h.total_ms) + ' total | max ' + fmtMs(h.max_ms));
                });
                break;
            case 'request':
                var req = data.request || {};
                if (req.method)        lines.push('Method: ' + req.method);
                if (req.url)           lines.push('Request URL: ' + req.url);
                if (req.matched_rule)  lines.push('Rewrite rule: ' + req.matched_rule);
                if (req.query_vars && Object.keys(req.query_vars).length) {
                    lines.push('Query vars:');
                    Object.keys(req.query_vars).forEach(function (k) { lines.push('  ' + k + ': ' + req.query_vars[k]); });
                }
                if (req.get && Object.keys(req.get).length) {
                    lines.push('GET:');
                    Object.keys(req.get).forEach(function (k) { lines.push('  ' + k + ': ' + req.get[k]); });
                }
                if (req.post && Object.keys(req.post).length) {
                    lines.push('POST:');
                    Object.keys(req.post).forEach(function (k) { lines.push('  ' + k + ': ' + req.post[k]); });
                }
                if (req.user_roles && req.user_roles.length) lines.push('Roles: ' + req.user_roles.join(', '));
                break;
            case 'template':
                var tmpl = data.template || {};
                lines.push('Active template: ' + (tmpl.final || '(unknown)'));
                lines.push('');
                (tmpl.hierarchy || []).forEach(function (f) {
                    lines.push((f.exists ? '[x] ' : '[ ] ') + f.file);
                });
                break;
            case 'transients':
                var trans = data.transients || [];
                lines.push('Transients: ' + trans.length);
                lines.push('');
                trans.forEach(function (t) {
                    lines.push(t.key + ' | ' + (t.hit ? 'HIT' : 'MISS')
                        + ' | gets: ' + t.gets + ' | sets: ' + t.sets + ' | deletes: ' + t.deletes);
                });
                break;
            case 'summary':
                var cQueries = data.queries || [], cHttp = data.http || [], cLogs = data.logs || [];

                // Environment
                if (meta.php_version) {
                    lines.push('Environment');
                    lines.push('  PHP: ' + meta.php_version + ' | WP: ' + (meta.wp_version || '?') + (meta.mysql_version ? ' | MySQL: ' + meta.mysql_version : ''));
                    if (meta.memory_peak_mb) lines.push('  Memory peak: ' + meta.memory_peak_mb + 'MB / ' + (meta.memory_limit || '?'));
                    if (meta.active_theme)   lines.push('  Theme: ' + meta.active_theme);
                    if (meta.is_multisite)   lines.push('  Multisite: yes');
                    lines.push('');
                }

                // DB card
                var cSlowQ = cQueries.filter(function (q) { return q.time_ms >= T_SLOW; }).length;
                var cCritQ = cQueries.filter(function (q) { return q.time_ms >= T_CRITICAL; }).length;
                var cDupeQ = cQueries.filter(function (q) { return q.is_dupe; }).length;
                var cN1Cnt = Object.keys(n1Patterns).length;
                lines.push('DB Queries: ' + meta.query_count + ' | ' + fmtMs(meta.query_total_ms) + ' total'
                    + (cCritQ ? ' | ' + cCritQ + ' critical' : cSlowQ ? ' | ' + cSlowQ + ' slow' : ' | no slow queries')
                    + (cDupeQ ? ' | ' + cDupeQ + ' dupes' : '')
                    + (cN1Cnt ? ' | ' + cN1Cnt + ' N+1 pattern' + (cN1Cnt > 1 ? 's' : '') : ''));

                // HTTP card
                var cSlowH = cHttp.filter(function (h) { return h.time_ms >= T_SLOW; }).length;
                var cCacH  = cHttp.filter(function (h) { return h.cached; }).length;
                var cErrH  = cHttp.filter(function (h) { return !!h.error; }).length;
                lines.push('HTTP / REST: ' + meta.http_count + ' calls | ' + fmtMs(meta.http_total_ms) + ' total'
                    + (cSlowH ? ' | ' + cSlowH + ' slow' : '')
                    + (cCacH  ? ' | ' + cCacH  + ' cached' : '')
                    + (cErrH  ? ' | ' + cErrH  + ' errors' : '')
                    + (cHttp.length === 0 ? ' | no outbound calls' : ''));

                // Logs card
                var cErrL  = cLogs.filter(function (e) { return (e.level || '').toLowerCase().indexOf('error') !== -1; }).length;
                var cWarnL = cLogs.filter(function (e) { return (e.level || '').toLowerCase().indexOf('warn')  !== -1; }).length;
                var cDepL  = cLogs.filter(function (e) { return (e.level || '').toLowerCase().indexOf('dep')   !== -1; }).length;
                lines.push('Logs: ' + cLogs.length
                    + (cErrL  ? ' | ' + cErrL  + ' errors' : '')
                    + (cWarnL ? ' | ' + cWarnL + ' warnings' : '')
                    + (cDepL  ? ' | ' + cDepL  + ' deprecated' : '')
                    + (cLogs.length === 0 ? ' | none' : ''));

                // Cache card
                var cCache = data.cache || {};
                if (cCache.available) {
                    var cHitStr = cCache.hit_rate !== null ? cCache.hit_rate + '%' : 'n/a';
                    lines.push('Object Cache: ' + cHitStr + ' hit rate | ' + (cCache.hits || 0) + ' hits, ' + (cCache.misses || 0) + ' misses'
                        + (cCache.persistent ? ' | persistent' : ' | non-persistent'));
                }

                // Assets card
                var cAssets = data.assets || {};
                lines.push('Assets: ' + ((cAssets.scripts || []).length + (cAssets.styles || []).length)
                    + ' | ' + (cAssets.scripts || []).length + ' JS, ' + (cAssets.styles || []).length + ' CSS');

                lines.push('');

                // Plugin leaderboard
                var cByP = {};
                cQueries.forEach(function (q) {
                    if (!cByP[q.plugin]) cByP[q.plugin] = { count: 0, total_ms: 0, slow: 0, n1: 0 };
                    cByP[q.plugin].count++; cByP[q.plugin].total_ms += q.time_ms;
                    if (q.time_ms >= T_SLOW) cByP[q.plugin].slow++;
                    if (isN1(q.sql))         cByP[q.plugin].n1++;
                });
                var cPluginList = Object.keys(cByP).map(function (p) {
                    return { plugin: p, count: cByP[p].count, total_ms: cByP[p].total_ms, slow: cByP[p].slow, n1: cByP[p].n1 };
                }).sort(function (a, b) { return b.total_ms - a.total_ms; });
                if (cPluginList.length > 0) {
                    lines.push('Plugin Leaderboard — DB query time');
                    cPluginList.slice(0, 8).forEach(function (p, i) {
                        lines.push('  ' + (i + 1) + '. ' + p.plugin + ' \u2014 ' + p.count + ' queries, ' + fmtMs(p.total_ms)
                            + (p.slow ? ', ' + p.slow + ' slow' : '')
                            + (p.n1   ? ', ' + p.n1   + ' N+1'  : ''));
                    });
                    lines.push('');
                }

                // Slowest queries (top 5)
                var cTop5Q = cQueries.slice().sort(function (a, b) { return b.time_ms - a.time_ms; }).slice(0, 5);
                if (cTop5Q.length > 0) {
                    lines.push('Slowest Queries');
                    cTop5Q.forEach(function (q) {
                        lines.push('  ' + fmtMs(q.time_ms) + '  ' + q.sql.replace(/\s+/g, ' ').trim().slice(0, 100) + '  [' + q.plugin + ']');
                    });
                    lines.push('');
                }

                // N+1 patterns
                var cN1List = Object.values(n1Patterns).sort(function (a, b) { return b.count - a.count; });
                if (cN1List.length > 0) {
                    lines.push('N+1 Query Patterns');
                    cN1List.forEach(function (p) {
                        lines.push('  x' + p.count + '  ' + normalisePattern(p.example).slice(0, 100) + '  [' + p.plugin + ']');
                    });
                    lines.push('');
                }

                // Slowest HTTP (top 5)
                var cTop5H = cHttp.slice().sort(function (a, b) { return b.time_ms - a.time_ms; }).slice(0, 5);
                if (cTop5H.length > 0) {
                    lines.push('Slowest HTTP Calls');
                    cTop5H.forEach(function (h) {
                        lines.push('  ' + fmtMs(h.time_ms) + '  [' + (h.method || 'GET') + '] ' + (h.url || '').slice(0, 100) + '  [' + h.plugin + ']');
                    });
                    lines.push('');
                }

                // Duplicate queries
                var cDupeGroups = {};
                cQueries.forEach(function (q) {
                    var fp = q.sql.replace(/\s+/g, ' ').toLowerCase().trim();
                    if (!cDupeGroups[fp]) cDupeGroups[fp] = { sql: q.sql, count: 0, total_ms: 0 };
                    cDupeGroups[fp].count++; cDupeGroups[fp].total_ms += q.time_ms;
                });
                var cDupeList = Object.values(cDupeGroups).filter(function (g) { return g.count > 1; })
                    .sort(function (a, b) { return b.count - a.count; }).slice(0, 8);
                if (cDupeList.length > 0) {
                    lines.push('Exact Duplicate Queries (' + cDupeList.length + ' groups)');
                    cDupeList.forEach(function (g) {
                        lines.push('  x' + g.count + '  ' + g.sql.replace(/\s+/g, ' ').trim().slice(0, 100)
                            + '  (' + fmtMs(g.count > 0 ? g.total_ms / g.count : 0) + ' avg)');
                    });
                    lines.push('');
                }

                // Slowest hooks (top 8)
                var cTopHooks = (data.hooks || []).slice(0, 8);
                if (cTopHooks.length > 0) {
                    lines.push('Slowest Hooks');
                    cTopHooks.forEach(function (h) {
                        lines.push('  ' + h.hook + ' | ' + h.count + 'x | ' + fmtMs(h.total_ms) + ' total | max ' + fmtMs(h.max_ms));
                    });
                }
                break;
        }

        var text   = lines.join('\n');
        var copyBtn = document.getElementById('cs-perf-copy');
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                flashCopyBtn(copyBtn, 'Copied!');
            }).catch(function () { fallbackCopy(text, copyBtn); });
        } else {
            fallbackCopy(text, copyBtn);
        }
    }

    function fallbackCopy(text, btn) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0';
        document.body.appendChild(ta);
        ta.focus(); ta.select();
        try { document.execCommand('copy'); flashCopyBtn(btn, 'Copied!'); }
        catch (e)                          { flashCopyBtn(btn, 'Failed'); }
        document.body.removeChild(ta);
    }

    function flashCopyBtn(btn, msg) {
        if (!btn) return;
        var orig = btn.textContent;
        btn.textContent = msg;
        setTimeout(function () { btn.textContent = orig; }, 1500);
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
        // iOS Safari fallback: touchend fires reliably even when parent has
        // overflow/fixed positioning quirks that can swallow click events.
        toggleBtn.addEventListener('touchend', function (e) {
            e.preventDefault(); // prevent the follow-up click from double-toggling
            e.stopPropagation();
            togglePanel();
        });
        if (exportBtn) exportBtn.addEventListener('click', function (e) { e.stopPropagation(); exportJSON(); });
        var copyBtn = document.getElementById('cs-perf-copy');
        if (copyBtn) copyBtn.addEventListener('click', function (e) { e.stopPropagation(); copyCurrentTab(); });

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

        // Assets filters
        [assetSearch, assetType, assetPlugin].forEach(function (el) {
            if (!el) return;
            el.addEventListener('input',  renderAssets);
            el.addEventListener('change', renderAssets);
            el.addEventListener('click',  function (e) { e.stopPropagation(); });
        });
        var assetsFiltersEl = document.querySelector('.cs-assets-filters');
        if (assetsFiltersEl) assetsFiltersEl.addEventListener('click', function (e) { e.stopPropagation(); });

        // Hooks filter
        if (hookSearch) {
            hookSearch.addEventListener('input',  renderHooks);
            hookSearch.addEventListener('click',  function (e) { e.stopPropagation(); });
        }
        var hooksFiltersEl = document.querySelector('.cs-hooks-filters');
        if (hooksFiltersEl) hooksFiltersEl.addEventListener('click', function (e) { e.stopPropagation(); });

        // Hooks sort headers
        Array.prototype.forEach.call(document.querySelectorAll('#cs-pp-hooks .cs-sortable'), function (th) {
            th.addEventListener('click', function (e) {
                e.stopPropagation();
                var col = th.dataset.sort;
                if (hookSortCol === col) hookSortDir = hookSortDir === 'desc' ? 'asc' : 'desc';
                else { hookSortCol = col; hookSortDir = 'desc'; }
                // Update hook sort header arrows
                Array.prototype.forEach.call(document.querySelectorAll('#cs-pp-hooks .cs-sortable'), function (h) {
                    var hCol = h.dataset.sort;
                    var labels = { total_ms: 'Total', count: 'Count', max_ms: 'Max' };
                    var arrow = hCol !== hookSortCol ? '&#8597;' : (hookSortDir === 'desc' ? '&#8595;' : '&#8593;');
                    h.innerHTML = (labels[hCol] || hCol) + '&nbsp;' + arrow;
                });
                renderHooks();
            });
        });

        bindResizeHandle();
        bindSortHeaders();

        document.addEventListener('keydown', function (e) {
            if (e.ctrlKey && e.shiftKey && (e.key === 'm' || e.key === 'M')) {
                e.preventDefault(); togglePanel();
            }
            if (e.key === 'Escape') {
                // Close help panel first if open
                var helpPanelEl = document.getElementById('cs-perf-help');
                if (helpPanelEl && helpPanelEl.style.display !== 'none') {
                    helpPanelEl.style.display = 'none';
                    return;
                }
                // Collapse any open EXPLAIN result divs and detail rows
                var hadOpen = false;
                Array.prototype.forEach.call(document.querySelectorAll('.cs-explain-result'), function (r) {
                    if (r.innerHTML) { r.innerHTML = ''; hadOpen = true; }
                });
                Array.prototype.forEach.call(document.querySelectorAll('.cs-row-detail'), function (d) {
                    if (d.style.display !== 'none') { d.style.display = 'none'; hadOpen = true; }
                });
                // Reset any disabled EXPLAIN buttons
                if (hadOpen) {
                    Array.prototype.forEach.call(document.querySelectorAll('.cs-explain-btn'), function (btn) {
                        btn.disabled = false; btn.textContent = 'EXPLAIN';
                    });
                }
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
        var s = String(str).replace(/\s+/g, ' ').trim();
        return s.length > max ? s.slice(0, max - 1) + '\u2026' : s;
    }
    function truncateUrl(url, max) {
        try {
            var u = new URL(String(url)), out = u.hostname + u.pathname;
            if (u.search) out += u.search.slice(0, 20) + (u.search.length > 20 ? '\u2026' : '');
            return out.length > max ? out.slice(0, max - 1) + '\u2026' : out;
        } catch(e) { return truncate(url, max); }
    }
    function esc(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

}());
