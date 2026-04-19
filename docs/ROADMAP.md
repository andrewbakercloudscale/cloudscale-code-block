# CloudScale Cyber and Devtools — Feature Roadmap

**Strategic frame:** Stop competing with WordPress plugins. Become an autonomous operator for the site.

The endgame: "Tell it what you want. It does it." — natural language in, working site out.

---

## Tier 1 — Build next (high leverage, uses existing data)

### 1. Plugin Stack Scanner ★ QUICK WIN
**"You can remove 6 plugins. Here's what CloudScale already replaces."**

Scans installed/active plugins, maps them against CloudScale's capability set, outputs a concrete removal list with estimated performance gain (fewer HTTP requests, lighter page weight) and annual cost saving. Perfect conversion tool — runs as a one-click card on the Home dashboard.

- Already have: plugin list in security audit, capability knowledge
- Build complexity: low — mostly a matching table + UI card
- Differentiator: no one else tells you to uninstall their competition

---

### 2. AI Debugging Assistant ★ HIGH LEVERAGE
**"What's causing this PHP error and how do I fix it?"**

Reads error log, WordPress debug log, and recent request context. User pastes or selects an error; AI returns root cause, the plugin/hook responsible, and a specific fix. Optionally patches config automatically. Bonus: "explain this log" mode that summarises the last 100 lines with severity triage.

- Already have: server log viewer with full read access, log parsing
- Build complexity: medium — AI prompt engineering + log context window
- Differentiator: no debugging tool does root-cause AI analysis locally

---

### 3. AI Site Auditor ★ CENTREPIECE FEATURE
**"Scan my entire site and tell me what's wrong."**

Single button. Crawls all published pages and returns a prioritised issue list scored by impact:
- SEO: missing meta, thin content, duplicate titles, h1 problems
- Performance: render-blocking scripts, unoptimised images, page weight
- Security: existing audit integrated
- Broken links: internal 404s, missing images
- Plugin duplication: flags where two plugins do the same thing
- JS/CSS bloat: unused stylesheets, script count per page

Replaces: Screaming Frog lite, Ahrefs site audit lite, Yoast diagnostics.

- Already have: SQL access, perf monitor hooks, security audit engine
- Build complexity: high — crawler + AI synthesis layer
- Differentiator: fully local, privacy-first, no external service

*My addition: add a "re-audit this page" button on individual posts/pages for targeted checks.*

---

### 4. "Fix It For Me" — AI Actions Layer ★ GAME CHANGER
**Every finding gets a one-click Fix button.**

Extends the existing Quick Fixes framework from security into every audit type. AI suggests the fix; user clicks; it executes. Examples:
- Add/fix og:image
- Write meta description from page content
- Add missing alt text to images
- Compress and regenerate image sizes
- Add caching headers via .htaccess
- Fix CSP policy
- Remove unused CSS from a specific plugin

Why this matters: Yoast and Rank Math nag you about problems. This solves them.

- Already have: Quick Fixes infrastructure, PHP/SQL execution capability
- Build complexity: medium per fix type — implement incrementally
- Differentiator: execution, not just advice

---

### 5. Database Intelligence Engine ★ MY ADDITION
**"Your database has 47MB of bloat. Here's where it is and how to clean it."**

AI analyses autoloaded options (most common WordPress slowdown), expired transients, post revisions, orphaned metadata, and table fragmentation. Proposes and executes cleanup with a preview step and backup prompt. Scheduled weekly analysis with email digest.

- Already have: SQL query tool with all the relevant queries as built-ins
- Build complexity: low-medium — mostly scheduling + AI narrative layer
- Differentiator: combines diagnostics + execution + scheduling

---

## Tier 2 — Medium term

### 6. Natural Language Control *(from original brief)*
**"Why is my site slow?" → AI runs the tools and answers.**

User types a question or instruction in plain English. AI maps intent to internal tool calls, runs them, and returns an answer + action buttons. Examples:
- "Why is my homepage slow?" → runs perf monitor + DB query analysis → explains
- "Fix my SEO issues" → runs site auditor → presents fix queue
- "Show me what's broken" → runs broken link scan + error log → triage list

This is the UX transformation that moves the plugin from menu-driven to intent-driven. Build after the underlying tools (Tiers 1-2) are solid.

- Build complexity: high — requires tool-calling architecture and intent routing

---

### 7. Real-Time Threat and Anomaly Detection
**Continuous monitoring, not just on-demand scans.**

Extends the existing SSH brute-force monitor (already reads auth.log on a 60s cron) into a broader anomaly engine:
- Unusual login time/location patterns
- Sudden traffic spikes on specific endpoints
- Repeated 403s on non-standard paths (probe patterns)
- Admin account creation outside business hours
- File modification events on core WordPress files

Alert channels: dashboard badge, email, ntfy.sh push (already wired up).

- Already have: cron infrastructure, auth log reader, email/push alerts
- Build complexity: medium — pattern rules + AI anomaly scoring
- Differentiator: no external WAF, no data leaving server, lower latency

---

### 8. Auto-Update Risk Scoring ★ MY ADDITION
**"This plugin update is high risk. Here's why."**

Before updating a plugin, AI assesses the update: is it a security patch (update immediately), a minor feature release (low risk), or a major version with breaking change potential (review first)? Reads the plugin's changelog, checks if the plugin touches your specific config, and gives a risk rating.

- Build complexity: medium — changelog parsing + conflict heuristics
- Differentiator: no WordPress tool does pre-update AI risk assessment

---

### 9. Automated Performance Tuning
**Detects and fixes the most common WordPress performance killers.**

- Identifies render-blocking scripts and suggests defer/async flags
- Detects slow queries (>100ms) and suggests query optimisation or transient caching
- Flags plugins adding the most page weight
- Applies lazy loading to images not already covered
- Suggests/adds caching headers via .htaccess for static assets

Competes with: WP Rocket, NitroPack — but runs locally with no black box.

- Build complexity: high — spans server config, PHP, and SQL
- Ship incrementally: start with detection-only, add fixes per category

---

## Tier 3 — Later

### 10. Internal Linking and Content Intelligence *(from original brief)*
Semantic graph of all posts, orphan page detection, internal link gap analysis, anchor text suggestions. Competes with Surfer SEO and Frase. High value but big project — only viable once the core audit/action loop is solid.

### 11. Plugin Conflict Predictor ⭐ *Andrew's addition*
When a new plugin is activated, AI assesses likely conflicts with the existing stack based on known hook/filter patterns and the new plugin's code. Pre-emptive warning before things break. No other WordPress tool does pre-activation conflict prediction.

### 12. Uptime and Core Web Vitals Monitor ⭐ *Andrew's addition*
Local polling (no external service, no Pingdom dependency) for page availability and LCP/FCP regression. Alerts when a deploy or plugin update breaks performance. Pairs with the performance tuning engine.

### 13. Git-Style Config Snapshots ⭐ *Andrew's addition*
Version control for wp-config.php, .htaccess, and key wp_options values. Diff before/after any Quick Fix or hardening change. One-click rollback. Especially valuable before database prefix rename or any destructive hardening operation.

### 14. Auto-Update Risk Scoring ⭐ *Andrew's addition*
Before updating a plugin, AI reads the changelog and assesses update risk: security patch (update now), minor feature (low risk), major version with breaking changes (review first). Checks if the plugin touches your specific config. No WordPress tool does pre-update AI risk assessment.

### 15. AI Content Ops *(from original brief — lowest priority)*
Meta description generation, heading optimisation, content rewrite suggestions. Crowded space with low moat — only worth building if it integrates tightly with the audit/fix loop (e.g., "fix meta descriptions on all 47 posts missing them" as a bulk action).

---

## What NOT to build

- Generic chatbot UI embedded in the plugin — everyone has one, no differentiation
- External API-dependent features — kills the local/private core advantage
- Standalone AI writing assistant — low moat, wrong audience, competes with Jasper/ChatGPT directly

---

## The 3-feature sprint that changes everything

If only 3 ship next:

1. **Plugin Stack Scanner** — immediate conversion impact, low effort, marketing story in product
2. **AI Debugging Assistant** — developer killer feature, most of the data already exists
3. **"Fix It For Me" actions** — turns the audit from a report into an operator

That combination: scan → understand → fix. Autonomous loop. That's the moment the plugin stops being a toolbox and starts being an operator.

---

*Last updated: 2026-04-19*
