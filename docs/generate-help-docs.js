'use strict';
const helpLib = require('REPO_BASE/shared-help-docs/help-lib.js');

helpLib.run({
    baseUrl:    process.env.WP_BASE_URL,
    cookies:    process.env.WP_COOKIES,
    restUser:   process.env.WP_REST_USER,
    restPass:   process.env.WP_REST_PASS,
    docsDir:    process.env.WP_DOCS_DIR,

    pluginName: 'CloudScale Cyber and Devtools',
    pluginDesc: 'Free WordPress security plugin with AI cyber audit using Claude &amp; Gemini, two-factor authentication, passkeys, login URL protection, one-click hardening, server logs, and code blocks.',
    seoTitle:  'CloudScale Cyber & Devtools | Free WordPress Security Plugin',
    seoDesc:   'Free WordPress security plugin with AI cyber audit (Claude & Gemini), 2FA, passkeys, one-click hardening, server logs, and code blocks. No subscription.',

    schema: {
        '@context': 'https://schema.org',
        '@type': 'SoftwareApplication',
        'name': 'CloudScale Cyber and Devtools',
        'applicationCategory': 'SecurityApplication',
        'operatingSystem': 'WordPress',
        'offers': { '@type': 'Offer', 'price': '0', 'priceCurrency': 'USD' },
        'description': 'Free WordPress security plugin powered by Anthropic Claude and Google Gemini AI. Features: AI cyber audit, two-factor authentication, passkeys (WebAuthn), hide login URL, brute-force protection, CSP builder, server logs, SQL tool, and syntax-highlighted code blocks.',
        'url': 'https://your-wordpress-site.example.com/wordpress-plugin-help/cloudscale-cyber-devtools-help/',
        'downloadUrl': 'https://your-s3-bucket.s3.af-south-1.amazonaws.com/cloudscale-devtools.zip',
        'softwareVersion': '1.9.117',
        'author': { '@type': 'Person', 'name': 'Andrew Baker', 'url': 'https://your-wordpress-site.example.com' },
        'isAccessibleForFree': true,
        'license': 'https://www.gnu.org/licenses/gpl-2.0.html',
    },
    pageTitle:  'CloudScale Cyber and Devtools',
    pageSlug:   'cloudscale-cyber-devtools-help',
    downloadUrl: 'https://your-s3-bucket.s3.af-south-1.amazonaws.com/cloudscale-devtools.zip',
    repoUrl:     'https://github.com/andrewbakercloudscale/cloudscale-cyber-devtools',
    adminUrl:   `${process.env.WP_BASE_URL}/wp-admin/tools.php?page=cloudscale-devtools`,

    pluginFile: `${__dirname}/../cs-code-block.php`,

    pluginIntro: `

<div style="background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 60%,#1e3a5f 100%);border-radius:12px;padding:40px 36px 36px;margin:0 0 36px;color:#fff;position:relative;overflow:hidden;">
<div style="position:absolute;top:-40px;right:-40px;width:260px;height:260px;background:rgba(99,102,241,.15);border-radius:50%;pointer-events:none;"></div>
<div style="position:relative;">
<p style="margin:0 0 10px;font-size:.85em;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#818cf8;">Free &amp; Open Source · No Subscription · Your Own API Key</p>
<h1 style="margin:0 0 18px;font-size:2em;font-weight:900;line-height:1.2;color:#fff;">43% of the Internet Runs WordPress.<br>Most of It Is Dangerously Exposed.</h1>
<p style="margin:0 0 22px;font-size:1.1em;line-height:1.75;color:#cbd5e1;max-width:700px;">CloudScale Cyber and Devtools brings the same AI-powered security intelligence used by enterprise security teams directly into your WordPress dashboard. Completely free, open-source, and running on your own server. Powered by <strong style="color:#fff;">Anthropic Claude 4</strong> and <strong style="color:#fff;">Google Gemini 2.5 Pro</strong>.</p>
<div style="display:flex;flex-wrap:wrap;gap:12px;">
<a href="#download" style="display:inline-block;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;text-decoration:none;font-weight:700;font-size:.95em;padding:12px 28px;border-radius:8px;">Download Free Plugin</a>
<a href="#cs-section-security" style="display:inline-block;background:rgba(255,255,255,.12);color:#fff;text-decoration:none;font-weight:600;font-size:.95em;padding:12px 28px;border-radius:8px;border:1px solid rgba(255,255,255,.2);">See the AI Audit →</a>
</div>
</div>
</div>

<h2 style="font-size:1.5em;font-weight:800;color:#0f172a;margin:0 0 14px;">The WordPress Security Reality No One Talks About</h2>

<p style="font-size:1.05em;color:#374151;margin:0 0 16px;line-height:1.75;">WordPress powers <strong>43% of every website on the internet</strong>, over 810 million sites. That extraordinary market dominance makes it the single most targeted platform in the history of the web. Automated attack bots don't discriminate by site size or traffic. Your personal blog, your agency client's e-commerce store, your company's marketing site: they are all being probed right now, regardless of how small or "not worth hacking" you think they are.</p>

<p style="font-size:1.05em;color:#374151;margin:0 0 16px;line-height:1.75;">The numbers are stark. Approximately <strong>90,000 WordPress sites are attacked every single minute</strong>. Over 97% of those attacks are fully automated: bots running credential-stuffing scripts, plugin vulnerability scanners, and file-injection exploits around the clock, targeting millions of sites simultaneously. The bots don't care who you are. They care that you're running WordPress.</p>

<p style="font-size:1.05em;color:#374151;margin:0 0 24px;line-height:1.75;">And here is the uncomfortable truth about the typical WordPress security posture: it's almost always inadequate, and the owner almost never knows it. Debug mode left on in production, leaking PHP errors to every visitor. WordPress version number advertised in page source and RSS feeds, letting attackers search for known CVEs before you've had a chance to patch. <code>/wp-login.php</code> answering requests from every IP on earth, soaking up thousands of brute-force attempts per day. Plugins installed years ago, never updated, carrying unpatched vulnerabilities that have been in public CVE databases for months. A single administrator account with a password reused from a site that breached two years ago. None of this is unusual. All of it is standard.</p>

<p style="font-size:1.05em;color:#374151;margin:0 0 24px;line-height:1.75;">The consequences are binary and brutal. An unprotected login page or an SSH port open to the internet with no brute-force protection will either get your server recruited into a DDoS botnet (taking your site offline and potentially getting your IP blacklisted), or it hands attackers the keys to your admin dashboard. Servers with open SSH and no fail2ban are found by automated scanners within minutes of going online. Once inside, they don't just deface your site. They install backdoors, steal customer data, send spam through your mail server, and use your infrastructure to attack other targets. You often won't know for weeks.</p>

<div style="background:#fff5f5;border-left:4px solid #dc2626;border-radius:0 8px 8px 0;padding:20px 24px;margin:0 0 28px;">
<h3 style="margin:0 0 10px;font-size:1.05em;font-weight:700;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">The Checklist Security Myth</h3>
<p style="margin:0;color:#374151;line-height:1.7;">For years, WordPress security advice has come in the form of checklists: "enable these constants in wp-config.php, install a firewall plugin, keep plugins updated." This advice is correct but woefully incomplete. A checklist tells you <em>what</em> to check. It cannot tell you what your specific configuration actually means from a risk perspective, whether a combination of settings creates an exposure that no individual setting would reveal, or whether one of your installed plugins contains obfuscated code that bypasses every firewall rule written. Checklists treat all sites as identical. Your site is not identical to anyone else's.</p>
</div>

<h2 style="font-size:1.5em;font-weight:800;color:#0f172a;margin:0 0 14px;">Why the Existing Security Tools Fall Short</h2>

<div style="background:#f1f5f9;border-radius:8px;padding:20px 24px;margin:0 0 24px;">
<h3 style="margin:0 0 12px;font-size:1.02em;font-weight:700;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">Understanding the Terminology</h3>
<p style="margin:0 0 10px;color:#374151;line-height:1.7;"><strong>CVE (Common Vulnerabilities and Exposures)</strong> is a public database of known security flaws in software. Each one gets a unique ID like CVE-2024-1234. When a researcher discovers a bug in a WordPress plugin that could let an attacker take over a site, they file a CVE report. It gets added to the database. Security tools scan your plugins against this list.</p>
<p style="margin:0 0 10px;color:#374151;line-height:1.7;"><strong>CVSS score</strong> (Common Vulnerability Scoring System) rates the severity of each CVE on a scale of 0–10. The four bands you'll see in CloudScale's reports: <strong>Critical (9–10):</strong> remote code execution, full site takeover, mass data theft possible with no user interaction. <strong>High (7–8.9):</strong> significant data exposure or privilege escalation. <strong>Medium (4–6.9):</strong> real risk but requires specific conditions. <strong>Low (0.1–3.9):</strong> minimal practical impact. Any Critical finding on a live site should be treated as a fire drill.</p>
<p style="margin:0;color:#374151;line-height:1.7;"><strong>Zero-day</strong> refers to a vulnerability that is being actively exploited before a patch exists or before it has been added to any CVE database. The name comes from the fact that developers have had zero days to fix it. Zero-days are the most dangerous class of vulnerability because every signature-based scanner in the world is blind to them. The attacker knows about the flaw. The defenders don't. The only way to catch them is through code analysis and behavioural reasoning. That is exactly what CloudScale's AI Code Triage does.</p>
</div>

<p style="font-size:1.05em;color:#374151;margin:0 0 16px;line-height:1.75;"><strong>Wordfence</strong> ($119/year for premium), <strong>Sucuri</strong> ($199/year), and <strong>WPScan</strong> ($25–$75/month) are the tools most security professionals will point you to. They are legitimate products that do real things: malware signature scanning, firewall rules, IP reputation blocking. But they share a fundamental architectural limitation. They are <em>signature-based</em>. They match what they see on your site against a database of known bad patterns. If the malware or misconfiguration isn't in their database yet, they don't flag it. They are inherently reactive; they require someone to be compromised first, for the attack pattern to be captured, analysed, and written into a rule. By definition they cannot identify novel threats, unusual configuration combinations, or the specific risk profile of your particular setup.</p>

<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:22px 24px;margin:0 0 20px;">
<h3 style="margin:0 0 14px;font-size:1.05em;font-weight:700;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">CloudScale vs WPScan: Side by Side</h3>
<table style="width:100%;border-collapse:collapse;font-size:.92em;color:#374151;">
<thead><tr style="background:#f1f5f9;"><th style="padding:10px 14px;text-align:left;font-weight:700;border-bottom:2px solid #e2e8f0;">Capability</th><th style="padding:10px 14px;text-align:center;font-weight:700;border-bottom:2px solid #e2e8f0;">WPScan</th><th style="padding:10px 14px;text-align:center;font-weight:700;color:#6366f1;border-bottom:2px solid #e2e8f0;">CloudScale</th></tr></thead>
<tbody>
<tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:10px 14px;">Cost</td><td style="padding:10px 14px;text-align:center;color:#dc2626;">$25–$75/month</td><td style="padding:10px 14px;text-align:center;color:#16a34a;font-weight:600;">Free</td></tr>
<tr style="background:#f8fafc;border-bottom:1px solid #f1f5f9;"><td style="padding:10px 14px;">Analysis method</td><td style="padding:10px 14px;text-align:center;">CVE database lookup</td><td style="padding:10px 14px;text-align:center;color:#16a34a;font-weight:600;">Frontier AI reasoning</td></tr>
<tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:10px 14px;">Novel / zero-day threats</td><td style="padding:10px 14px;text-align:center;color:#dc2626;">✗ Must be in database first</td><td style="padding:10px 14px;text-align:center;color:#16a34a;font-weight:600;">✓ Reasons from first principles</td></tr>
<tr style="background:#f8fafc;border-bottom:1px solid #f1f5f9;"><td style="padding:10px 14px;">Context-aware findings</td><td style="padding:10px 14px;text-align:center;color:#dc2626;">✗</td><td style="padding:10px 14px;text-align:center;color:#16a34a;font-weight:600;">✓ Your specific config</td></tr>
<tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:10px 14px;">PHP code static analysis</td><td style="padding:10px 14px;text-align:center;color:#dc2626;">✗</td><td style="padding:10px 14px;text-align:center;color:#16a34a;font-weight:600;">✓ AI-triaged</td></tr>
<tr style="background:#f8fafc;border-bottom:1px solid #f1f5f9;"><td style="padding:10px 14px;">SSH / server hardening checks</td><td style="padding:10px 14px;text-align:center;color:#dc2626;">✗</td><td style="padding:10px 14px;text-align:center;color:#16a34a;font-weight:600;">✓</td></tr>
<tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:10px 14px;">DNS / SPF / DMARC analysis</td><td style="padding:10px 14px;text-align:center;color:#dc2626;">✗</td><td style="padding:10px 14px;text-align:center;color:#16a34a;font-weight:600;">✓</td></tr>
<tr style="background:#f8fafc;border-bottom:1px solid #f1f5f9;"><td style="padding:10px 14px;">Remediation steps</td><td style="padding:10px 14px;text-align:center;">Generic</td><td style="padding:10px 14px;text-align:center;color:#16a34a;font-weight:600;">Specific to your site</td></tr>
<tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:10px 14px;">One-click quick fixes</td><td style="padding:10px 14px;text-align:center;color:#dc2626;">✗</td><td style="padding:10px 14px;text-align:center;color:#16a34a;font-weight:600;">✓</td></tr>
<tr style="background:#f8fafc;border-bottom:1px solid #f1f5f9;"><td style="padding:10px 14px;">Your data passes through vendor</td><td style="padding:10px 14px;text-align:center;color:#dc2626;">Yes</td><td style="padding:10px 14px;text-align:center;color:#16a34a;font-weight:600;">No. Direct to AI provider.</td></tr>
<tr><td style="padding:10px 14px;">Login security (2FA, passkeys)</td><td style="padding:10px 14px;text-align:center;color:#dc2626;">✗</td><td style="padding:10px 14px;text-align:center;color:#16a34a;font-weight:600;">✓ Included free</td></tr>
</tbody>
</table>
</div>

<p style="font-size:1.05em;color:#374151;margin:0 0 16px;line-height:1.75;">The premium price also filters out the vast majority of WordPress site owners. There are 810 million WordPress sites and a fraction of them pay for premium security tooling. Everyone else: the personal bloggers, small business owners, freelancers building sites for local clients. They are either running free tools with heavily restricted capabilities, or running nothing at all.</p>

<div style="background:#fefce8;border-left:4px solid #d97706;border-radius:0 8px 8px 0;padding:20px 24px;margin:0 0 28px;">
<h3 style="margin:0 0 10px;font-size:1.05em;font-weight:700;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">The "AI Security" Marketing Trap</h3>
<p style="margin:0 0 10px;color:#374151;line-height:1.7;">Since ChatGPT became mainstream, the WordPress plugin directory has filled with plugins claiming "AI-powered security." Look closely at almost all of them and you find one of two things: either a bolt-on GPT-4 API call wrapped around the same signature-based scan output that existed before (the AI doesn't do the analysis, it just summarises it), or a marketing page full of AI language that describes what the plugin <em>could</em> detect with AI, without actually using AI to do it.</p>
<p style="margin:0;color:#374151;line-height:1.7;">Real AI security analysis means sending your actual configuration, your actual plugin list, your actual code (not a pre-processed summary) to a frontier model and asking it to reason about the specific risk profile. It means the AI can identify that <em>your combination</em> of an outdated caching plugin, a relaxed CORS policy, and a public-facing REST API endpoint creates an exposure that no individual component would trigger on its own. That requires genuine frontier intelligence, not pattern-matching dressed up with AI branding.</p>
</div>

<h2 style="font-size:1.5em;font-weight:800;color:#0f172a;margin:0 0 14px;">What Frontier AI Actually Changes</h2>

<p style="font-size:1.05em;color:#374151;margin:0 0 16px;line-height:1.75;">Anthropic Claude Opus 4 and Google Gemini 2.5 Pro are not chatbots with a security FAQ. They are frontier reasoning systems with deep knowledge of CVEs, OWASP vulnerabilities, PHP exploitation techniques, WordPress internals, and the full threat landscape. A professional security consultant doing a WordPress audit is doing fundamentally the same thing: reading your configuration, reasoning about what it means, cross-referencing known vulnerability patterns, and applying judgement about real-world risk. The audit a consultant would charge $500–$5,000 for and take days to schedule? The AI does it in under 60 seconds, on your specific site.</p>

<p style="font-size:1.05em;color:#374151;margin:0 0 24px;line-height:1.75;">The critical difference from signature-based tools: the AI doesn't need your vulnerability to be in a database first. It reasons from first principles. When it reads your sshd_config and sees that <code>PasswordAuthentication yes</code> is set with no fail2ban equivalent running and port 22 open to the internet, it knows from its training on real-world security incidents that this configuration actively gets servers recruited into DDoS botnets. Not because that specific combination is in a signature database. Because it understands what that configuration means.</p>

<h2 style="font-size:1.5em;font-weight:800;color:#0f172a;margin:0 0 14px;">The Mythology of AI Security</h2>

<p style="font-size:1.05em;color:#374151;margin:0 0 16px;line-height:1.75;">There is a prevailing mythology in the security industry that AI is a magic layer you bolt onto existing tools to make them better. Vendors who spent the last decade building signature databases rebranded overnight. The product didn't change. The marketing did. "AI-powered" became the new "cloud-enabled": a phrase that means everything and nothing at once.</p>

<p style="font-size:1.05em;color:#374151;margin:0 0 16px;line-height:1.75;">The mythology is seductive because it's partly true. Adding an AI summary to a Wordfence scan report does make it easier to read. Adding a chatbot that explains CVEs is marginally useful. But these are cosmetic improvements to a fundamentally reactive architecture. The underlying problem is unchanged: you can only detect what you've already catalogued.</p>

<p style="font-size:1.05em;color:#374151;margin:0 0 16px;line-height:1.75;">What frontier AI actually enables is something qualitatively different. Not a better summary of existing scan results. A different kind of analysis altogether. Claude Opus 4 has read more security research, CVE disclosures, penetration testing write-ups, and malware analyses than any human security team ever could. When it looks at your WordPress configuration, it is drawing on that entire body of knowledge simultaneously, applying it to your specific situation, and reasoning about what it actually means for you. That's not a better wrapper around signature matching. That's a different tool entirely.</p>

<div style="background:linear-gradient(135deg,#0f172a,#1e293b);border-radius:8px;padding:24px 28px;margin:0 0 24px;color:#fff;">
<h3 style="margin:0 0 14px;font-size:1.1em;font-weight:700;color:#e2e8f0;background:transparent!important;padding:0!important;border:none!important;">Where This Goes Next</h3>
<p style="margin:0 0 12px;color:#94a3b8;line-height:1.7;">We are at the beginning of a capability curve, not the middle. The models available today (Claude Sonnet 4.6, Claude Opus 4.7, Gemini 2.5 Pro) already outperform the security analysis you'd get from most paid consultants. The models coming in the next 12–24 months will make these look primitive.</p>
<p style="margin:0 0 12px;color:#94a3b8;line-height:1.7;">Claude 5 and its successors will be capable of autonomous security research: actively probing your infrastructure, reasoning about multi-step attack chains, writing and testing proposed fixes, and explaining the second and third-order consequences of every configuration decision. The gap between "AI that helps you understand a scan" and "AI that autonomously hardens your infrastructure" is closing fast.</p>
<p style="margin:0;color:#94a3b8;line-height:1.7;">CloudScale is built to absorb every new model the day it launches. No migration, no upgrade fee, no waiting. Your plugin gets smarter as the underlying AI gets smarter. The architecture was designed specifically for this: your site, your API key, your direct relationship with the provider. When the next breakthrough model drops, you flip a dropdown and you're on it.</p>
</div>

<h2 style="font-size:1.5em;font-weight:800;color:#0f172a;margin:0 0 14px;">CloudScale Cyber and Devtools: The Breakthrough</h2>

<p style="font-size:1.05em;color:#374151;margin:0 0 16px;line-height:1.75;">CloudScale Cyber and Devtools is a <strong>free, open-source WordPress security and developer toolkit</strong> that gives every WordPress site owner access to exactly this level of analysis. No premium tier. No "upgrade to see your full results." No monthly subscription. You bring your own API key (Google Gemini has a <strong>free tier that requires no credit card</strong>), and the plugin runs on your own server. Your data never goes anywhere except directly to the AI provider you choose.</p>

<p style="font-size:1.05em;color:#374151;margin:0 0 20px;line-height:1.75;">The result is a full security audit that would normally cost hundreds of dollars from a consultant, available in your WordPress dashboard, for free, any time you want to run it. Set up daily or weekly scheduled scans and you'll get an email alert when new issues appear, so you know about problems before your users or Google do.</p>

<div style="background:linear-gradient(135deg,#ecfdf5,#f0f9ff);border:1px solid #a7f3d0;border-radius:8px;padding:22px 24px;margin:0 0 28px;">
<h3 style="margin:0 0 12px;font-size:1.1em;font-weight:700;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">No Middleman. No Data Risk. Always the Latest Models.</h3>
<p style="margin:0 0 12px;color:#374151;line-height:1.7;">Most "AI-powered" WordPress security products send your site's data to their own servers first, where it gets logged, processed, and potentially used to train their models, before eventually forwarding it to an AI provider. You're paying for a middleman who adds latency, a new privacy risk, and a business model dependency. When that vendor changes their pricing, gets acquired, or goes offline, your security tooling goes with it.</p>
<p style="margin:0 0 12px;color:#374151;line-height:1.7;">CloudScale works differently. <strong>Your WordPress data goes directly from your server to the AI provider's API</strong> (Anthropic or Google) with no intermediary, no CloudScale server, no third-party logging. You supply your own API key, so you have a direct relationship with the provider and full control over your data. CloudScale never sees your site data at all.</p>
<p style="margin:0;color:#374151;line-height:1.7;">When Anthropic releases Claude Opus 5 or Google ships Gemini 3, <strong>you get it immediately.</strong> No waiting for a plugin vendor to integrate it, no being held on an older model to protect their infrastructure margins. CloudScale ships support for the latest frontier models as soon as they launch. You choose your model, you own the key, you get the best intelligence available from day one.</p>
</div>

<h2 style="font-size:1.5em;font-weight:800;color:#0f172a;margin:0 0 14px;">Installing the Plugin: Step by Step</h2>

<p style="font-size:1.05em;color:#374151;margin:0 0 20px;line-height:1.75;">The plugin isn't in the WordPress.org directory yet, so installation takes one extra step compared to a typical plugin. It's still under five minutes from download to your first security scan.</p>

<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:24px 28px;margin:0 0 12px;counter-reset:step;">

<div style="display:flex;gap:16px;align-items:flex-start;margin:0 0 20px;">
<div style="flex-shrink:0;width:36px;height:36px;background:#6366f1;color:#fff;font-weight:800;font-size:1em;border-radius:50%;display:flex;align-items:center;justify-content:center;">1</div>
<div>
<p style="margin:0 0 6px;font-weight:700;color:#0f172a;font-size:1.02em;">Download the plugin zip</p>
<p style="margin:0;color:#374151;line-height:1.65;">Click the <strong>Download Free Plugin</strong> button at the top of this page. Your browser will save a file called <code>cloudscale-devtools.zip</code>. Leave it zipped; WordPress handles the extraction.</p>
</div>
</div>

<div style="display:flex;gap:16px;align-items:flex-start;margin:0 0 20px;">
<div style="flex-shrink:0;width:36px;height:36px;background:#6366f1;color:#fff;font-weight:800;font-size:1em;border-radius:50%;display:flex;align-items:center;justify-content:center;">2</div>
<div>
<p style="margin:0 0 6px;font-weight:700;color:#0f172a;font-size:1.02em;">Open your WordPress dashboard</p>
<p style="margin:0;color:#374151;line-height:1.65;">Log in to your WordPress site and go to <strong>Plugins</strong> in the left sidebar. At the top of the page, click <strong>Add New Plugin</strong>, then click the <strong>Upload Plugin</strong> button that appears near the top of the screen.</p>
</div>
</div>

<div style="display:flex;gap:16px;align-items:flex-start;margin:0 0 20px;">
<div style="flex-shrink:0;width:36px;height:36px;background:#6366f1;color:#fff;font-weight:800;font-size:1em;border-radius:50%;display:flex;align-items:center;justify-content:center;">3</div>
<div>
<p style="margin:0 0 6px;font-weight:700;color:#0f172a;font-size:1.02em;">Upload and install</p>
<p style="margin:0;color:#374151;line-height:1.65;">Click <strong>Choose File</strong>, select the <code>cloudscale-devtools.zip</code> file you just downloaded, then click <strong>Install Now</strong>. WordPress uploads and unpacks the plugin in a few seconds.</p>
</div>
</div>

<div style="display:flex;gap:16px;align-items:flex-start;margin:0 0 20px;">
<div style="flex-shrink:0;width:36px;height:36px;background:#6366f1;color:#fff;font-weight:800;font-size:1em;border-radius:50%;display:flex;align-items:center;justify-content:center;">4</div>
<div>
<p style="margin:0 0 6px;font-weight:700;color:#0f172a;font-size:1.02em;">Activate</p>
<p style="margin:0;color:#374151;line-height:1.65;">After installation, WordPress shows you a success screen with an <strong>Activate Plugin</strong> button. Click it. The plugin is now running.</p>
</div>
</div>

<div style="display:flex;gap:16px;align-items:flex-start;margin:0 0 20px;">
<div style="flex-shrink:0;width:36px;height:36px;background:#6366f1;color:#fff;font-weight:800;font-size:1em;border-radius:50%;display:flex;align-items:center;justify-content:center;">5</div>
<div>
<p style="margin:0 0 6px;font-weight:700;color:#0f172a;font-size:1.02em;">Open the plugin</p>
<p style="margin:0;color:#374151;line-height:1.65;">In the WordPress sidebar, go to <strong>Tools → Cyber and Devtools</strong>. You'll land on the Home dashboard showing your current security posture at a glance.</p>
</div>
</div>

<div style="display:flex;gap:16px;align-items:flex-start;">
<div style="flex-shrink:0;width:36px;height:36px;background:#16a34a;color:#fff;font-weight:800;font-size:1em;border-radius:50%;display:flex;align-items:center;justify-content:center;">6</div>
<div>
<p style="margin:0 0 6px;font-weight:700;color:#0f172a;font-size:1.02em;">Run your first security scan</p>
<p style="margin:0;color:#374151;line-height:1.65;">Click the <strong>Security</strong> tab. If you don't have an API key yet, click the link to get a free Google Gemini key (see the AI setup guide in this page's Security section). Paste it in, click Save, then hit <strong>Run AI Cyber Audit</strong>. Your first report appears in about 30 seconds.</p>
</div>
</div>

</div>

<p style="font-size:.92em;color:#64748b;margin:0 0 16px;"><strong>Requirements:</strong> WordPress 6.0 or later, PHP 7.4 or later. Works on shared hosting, VPS, and managed WordPress hosting (WP Engine, Kinsta, Cloudways, etc.). Does not require SSH access or command-line tools.</p>

<div style="background:#fff7ed;border-left:4px solid #ea580c;border-radius:0 8px 8px 0;padding:16px 20px;margin:0 0 32px;">
<p style="margin:0;color:#374151;font-size:.95em;line-height:1.65;"><strong>Before you start hardening anything: take a backup.</strong> The Quick Fixes in this plugin modify wp-config.php, database tables, and server configuration. In the unlikely event something goes wrong, you want a restore point. The free <a href="https://your-wordpress-site.example.com/wordpress-plugin-help/cloudscale-backup-restore-help/" target="_blank" rel="noopener"><strong>CloudScale Backup and Restore plugin</strong></a> does one-click full-site backups (database + files) to local storage or cloud. Five minutes now saves hours later.</p>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin:0 0 32px;">
<div style="background:#fff5f5;border-left:4px solid #e53e3e;border-radius:0 8px 8px 0;padding:18px 20px;">
<h3 style="margin:0 0 10px;font-size:1em;font-weight:700;color:#1a202c;text-transform:uppercase;letter-spacing:.05em;background:transparent!important;padding:0!important;border:none!important;">🛡️ Security</h3>
<ul style="margin:0;padding-left:18px;color:#374151;font-size:.95em;line-height:1.8;">
<li><strong>AI Cyber Audit:</strong> scored security report in under 60 seconds using Claude or Gemini</li>
<li><strong>Deep Dive Scan:</strong> HTTP probes, DNS checks, TLS, PHP code analysis</li>
<li><strong>Quick Fixes:</strong> one-click hardening for common misconfigurations</li>
<li><strong>SSH Brute-Force Monitor:</strong> reads auth.log every 60 seconds, alerts on 10+ failures</li>
<li><strong>Scheduled Scans:</strong> daily/weekly background scans with email &amp; push alerts</li>
<li><strong>Server Logs:</strong> read PHP, WordPress and web server logs in-browser</li>
</ul>
</div>
<div style="background:#f0f9ff;border-left:4px solid #0e6b8f;border-radius:0 8px 8px 0;padding:18px 20px;">
<h3 style="margin:0 0 10px;font-size:1em;font-weight:700;color:#1a202c;text-transform:uppercase;letter-spacing:.05em;background:transparent!important;padding:0!important;border:none!important;">🔐 Login Security</h3>
<ul style="margin:0;padding-left:18px;color:#374151;font-size:.95em;line-height:1.8;">
<li><strong>Hide Login URL:</strong> move /wp-login.php to a secret slug</li>
<li><strong>Two-Factor Authentication:</strong> email OTP, TOTP (authenticator app), or passkeys</li>
<li><strong>Passkeys (WebAuthn):</strong> Face ID, Touch ID, Windows Hello, YubiKey</li>
<li><strong>Brute-Force Protection:</strong> per-account lockout after N failed attempts</li>
<li><strong>Force 2FA for admins:</strong> block dashboard access until 2FA is set up</li>
<li><strong>Test Account Manager:</strong> temporary accounts for Playwright / CI pipelines</li>
</ul>
</div>
<div style="background:#f0fdf4;border-left:4px solid #16a34a;border-radius:0 8px 8px 0;padding:18px 20px;">
<h3 style="margin:0 0 10px;font-size:1em;font-weight:700;color:#1a202c;text-transform:uppercase;letter-spacing:.05em;background:transparent!important;padding:0!important;border:none!important;">🛠️ Developer Tools</h3>
<ul style="margin:0;padding-left:18px;color:#374151;font-size:.95em;line-height:1.8;">
<li><strong>Syntax-highlighted Code Block:</strong> 190+ languages, 14 themes, bundled locally</li>
<li><strong>Code Block Migrator:</strong> batch-convert blocks from other plugins</li>
<li><strong>SQL Query Tool:</strong> read-only SELECT queries in-browser</li>
<li><strong>SMTP Mail:</strong> replace PHP mail() with authenticated SMTP</li>
<li><strong>Performance Monitor:</strong> overlay showing queries, hooks, assets per page</li>
<li><strong>Custom 404 Page:</strong> branded 404 with 7 playable mini-games and leaderboard</li>
</ul>
</div>
<div style="background:#fafafa;border-left:4px solid #6366f1;border-radius:0 8px 8px 0;padding:18px 20px;">
<h3 style="margin:0 0 10px;font-size:1em;font-weight:700;color:#1a202c;text-transform:uppercase;letter-spacing:.05em;background:transparent!important;padding:0!important;border:none!important;">What's Covered Below</h3>
<ul style="margin:0;padding-left:18px;color:#374151;font-size:.95em;line-height:1.8;">
<li><a href="#cs-section-hide-login" style="color:#6366f1;">Hide Login URL</a> setup and how it works</li>
<li><a href="#cs-section-2fa" style="color:#6366f1;">Two-Factor Authentication</a> and enforcement</li>
<li><a href="#cs-section-passkeys" style="color:#6366f1;">Passkeys</a> registration and browser support</li>
<li><a href="#cs-section-security" style="color:#6366f1;">AI Cyber Audit</a> with full API key setup guides</li>
<li><a href="#cs-section-code-block" style="color:#6366f1;">Code Block</a> themes, languages, and usage</li>
<li><a href="#cs-section-sql-tool" style="color:#6366f1;">SQL Query Tool</a> and built-in queries</li>
<li><a href="#cs-section-server-logs" style="color:#6366f1;">Server Logs</a> viewer and tail mode</li>
</ul>
</div>
</div>`,

    sections: [
        { id: 'hide-login', label: 'Hide Login URL',        file: 'panel-hide-login.png',  tabSelector: 'a[href*="tab=login"]',    elementSelector: '#cs-panel-hide-login',
          altText: 'WordPress Hide Login URL settings panel — move wp-login.php to a secret URL to block automated bot attacks',
          jsBeforeShot: () => {
            var s = document.getElementById('cs-login-slug');
            if (s) s.value = 'your-secret-slug';
            var u = document.getElementById('cs-current-login-url');
            if (u) u.textContent = window.location.origin + '/your-secret-slug/';
          } },
        { id: '2fa',        label: 'Two-Factor Auth',       file: 'panel-2fa.png',         tabSelector: 'a[href*="tab=login"]',    elementSelector: '#cs-panel-2fa',
          altText: 'WordPress two-factor authentication settings with email OTP, TOTP authenticator app, and passkeys' },
        { id: 'passkeys',   label: 'Passkeys (WebAuthn)',   file: 'panel-passkeys.png',    tabSelector: 'a[href*="tab=login"]',    elementSelector: '#cs-panel-passkeys',
          altText: 'WordPress passkeys WebAuthn registration supporting Face ID, Touch ID and hardware security key login' },
        { id: 'security',   label: 'AI Cyber Audit',        file: 'panel-security.png',    tabSelector: 'a[href*="tab=security"]', elementSelector: '#cs-vuln-results',
          altText: 'WordPress AI security audit showing a perfect score with Claude 4 and Gemini 2.5 Pro on a free security plugin',
          jsBeforeShot: () => {
            // Inject demo data: score 100, no real findings
            var r = document.getElementById('cs-vuln-results');
            if (r) {
                r.style.display = 'block';
                r.innerHTML =
                    '<div class="cs-audit-header">' +
                    '<div class="cs-audit-score-circle cs-audit-score-excellent">' +
                    '<span class="cs-audit-score-num">100</span>' +
                    '<span class="cs-audit-score-lbl">Excellent</span>' +
                    '</div>' +
                    '<div class="cs-audit-meta">' +
                    '<p class="cs-audit-summary-text">Your WordPress installation demonstrates exceptional security. All critical controls are in place: security headers, 2FA, hidden login URL, disabled file editing, and no vulnerable plugins. Nothing to remediate.</p>' +
                    '<span class="cs-audit-meta-line">Model: claude-sonnet-4-6 · Auto AI Model</span>' +
                    '</div></div>' +
                    '<div class="cs-audit-section cs-audit-sec-good">' +
                    '<h4 class="cs-audit-section-title">Good Practices (8)</h4>' +
                    '<div class="cs-audit-good-item"><span class="cs-audit-good-check">✓</span><div><strong>Security headers configured:</strong> X-Content-Type-Options, X-Frame-Options, Referrer-Policy, and Permissions-Policy all set.</div></div>' +
                    '<div class="cs-audit-good-item"><span class="cs-audit-good-check">✓</span><div><strong>WordPress auto-updates enabled:</strong> Core security patches applied automatically.</div></div>' +
                    '<div class="cs-audit-good-item"><span class="cs-audit-good-check">✓</span><div><strong>File editing disabled:</strong> DISALLOW_FILE_EDIT is set in wp-config.php.</div></div>' +
                    '<div class="cs-audit-good-item"><span class="cs-audit-good-check">✓</span><div><strong>Debug mode off in production:</strong> WP_DEBUG and display_errors are disabled.</div></div>' +
                    '<div class="cs-audit-good-item"><span class="cs-audit-good-check">✓</span><div><strong>Strong administrator credentials:</strong> No default or weak passwords detected.</div></div>' +
                    '<div class="cs-audit-good-item"><span class="cs-audit-good-check">✓</span><div><strong>Login URL hidden:</strong> Custom login path protects against automated brute-force attempts.</div></div>' +
                    '<div class="cs-audit-good-item"><span class="cs-audit-good-check">✓</span><div><strong>Two-factor authentication active:</strong> All administrator accounts protected with 2FA.</div></div>' +
                    '<div class="cs-audit-good-item"><span class="cs-audit-good-check">✓</span><div><strong>XML-RPC disabled:</strong> Endpoint blocked to prevent credential-stuffing attacks.</div></div>' +
                    '</div>';
            }
            // Make all quick fixes show as fixed
            document.querySelectorAll('#cs-quick-fixes-list [data-fix-id]').forEach(function(btn) {
                var wrap = btn.closest('div[style*="flex-shrink"]') || btn.parentElement;
                if (wrap) wrap.innerHTML = '<span style="font-size:12px;color:#16a34a;font-weight:600;">Fixed \u2713</span>';
            });
            // Hide AI settings form (scope to security panel — .cs-sec-settings appears in other panels too)
            var panel = document.getElementById('cs-panel-security');
            if (panel) {
                var ctrl = panel.querySelector('.cs-sec-settings');
                if (ctrl) ctrl.style.display = 'none';
                var intro = panel.querySelector('.cs-tab-intro');
                if (intro) intro.style.display = 'none';
            }
            // Also scrub any API key inputs that might be visible anywhere
            document.querySelectorAll('input[id*="key"], input[id*="api"], input[type="password"]').forEach(function(el) {
                el.value = '';
            });
          }
        },
        { id: 'code-block', label: 'Code Block',             file: 'panel-code-block.png',  tabSelector: 'a[href*="tab=migrate"]', elementSelector: '#cs-panel-code-settings',
          altText: 'WordPress syntax-highlighted code block settings with 190 languages, 14 themes, no CDN, completely free' },
        { id: 'migrator',   label: 'Code Block Migrator',   file: 'panel-migrator.png',    tabSelector: 'a[href*="tab=migrate"]', elementSelector: '#cs-panel-migrator',
          altText: 'WordPress code block migrator for batch converting from Enlighter, SyntaxHighlighter, and other plugins' },
        { id: 'sql-tool',   label: 'SQL Query Tool',        file: 'panel-sql-tool.png',    tabSelector: 'a[href*="tab=sql"]',     elementSelector: '#cs-panel-sql',
          altText: 'WordPress read-only SQL query tool for safe database inspection inside wp-admin without phpMyAdmin' },
        { id: 'server-logs',label: 'Server Logs',           file: 'panel-server-logs.png', tabSelector: 'a[href*="tab=logs"]',    elementSelector: '#cs-panel-logs',
          altText: 'WordPress server log viewer for PHP error logs, debug logs, and web server logs without SSH access' },
    ],

    docs: {
        'hide-login': `
<div style="background:#f0fdf4;border-left:4px solid #16a34a;padding:18px 22px;border-radius:0 8px 8px 0;margin-bottom:24px;">
<h2 style="margin:0 0 8px;font-size:1.25em;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">🔐 Stop Bots Before They Even See Your Login Page</h2>
<p style="margin:0 0 10px;color:#374151;">Every WordPress site on the internet is hammered by bots probing <code>/wp-login.php</code> every hour. These aren't targeted attacks; they're automated scanners running 24/7, trying thousands of password combinations. If they can reach your login page, they will keep trying. Hide Login URL makes your login page invisible to them: bots get a 404 and move on. No login form means no brute-force attack.</p>
<p style="margin:0;color:#374151;"><strong>Competing plugins charge $49–$99/year</strong> for this feature (iThemes Security Pro, All-in-One Security Premium). CloudScale includes it free, bundled with 2FA and Passkeys in the same plugin, so there's no juggling three separate security plugins.</p>
</div>
<p>When enabled, a WordPress <code>init</code> hook (priority 1) intercepts requests to your chosen secret slug and serves the login form transparently: no redirect, no URL change, the form just loads. Direct requests to <code>/wp-login.php</code> return a clean 404. All internal WordPress links (password reset emails, logout URLs) automatically update to use your secret URL.</p>
<p><strong>Setup takes 30 seconds:</strong></p>
<ol>
<li>Toggle <em>Enable Hide Login</em> on.</li>
<li>Enter your secret slug (e.g. <code>team-portal</code>). Avoid <code>login</code>, <code>admin</code>, or <code>dashboard</code>; bots know those too.</li>
<li>Click <em>Save</em> and bookmark the new URL immediately.</li>
<li>If you ever lose the URL, <code>wp option get csdt_devtools_login_slug</code> via WP-CLI will retrieve it.</li>
</ol>
<p><strong>What stays unaffected:</strong> WP-CLI, XML-RPC, REST API, and WP Cron all bypass the login URL check entirely, so nothing breaks.</p>`,

        '2fa': `
<div style="background:#fdf4ff;border-left:4px solid #9333ea;padding:18px 22px;border-radius:0 8px 8px 0;margin-bottom:24px;">
<h2 style="margin:0 0 8px;font-size:1.25em;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">🔑 A Stolen Password Should Never Be Enough to Break In</h2>
<p style="margin:0 0 10px;color:#374151;">Passwords get leaked in data breaches, reused across sites, and phished out of users. Two-factor authentication (2FA) means an attacker who has your password still cannot log in. They also need physical access to your phone, email inbox, or hardware key. For WordPress admins, 2FA is the single most effective account protection you can add.</p>
<p style="margin:0;color:#374151;"><strong>WP 2FA Pro charges $79/year.</strong> Wordfence Premium (which includes 2FA) charges $119/year. CloudScale gives you email OTP, TOTP authenticator apps, and Passkeys (all three methods) completely free, in the same plugin you use for everything else.</p>
</div>
<p><strong>Three methods, one plugin:</strong></p>
<ul>
<li><strong>Email OTP:</strong> a 6-digit code sent to the user's email after login. No app needed. Code expires in 10 minutes. Best for non-technical users.</li>
<li><strong>Authenticator app (TOTP):</strong> standard RFC 6238 algorithm. Works with Google Authenticator, Authy, 1Password, Bitwarden, or any TOTP app. Generates a new code every 30 seconds, works offline, immune to email interception.</li>
<li><strong>Passkey (WebAuthn):</strong> replaces the code prompt with Face ID, Touch ID, Windows Hello, or a hardware security key. The fastest and most phishing-resistant option available. See the Passkeys section below.</li>
</ul>
<p><strong>Admin enforcement:</strong> Enable <em>Force 2FA for administrators</em> and any admin who hasn't configured their second factor gets blocked at the dashboard until they do. They can't skip it. A configurable grace period lets existing admins set up 2FA before enforcement kicks in.</p>
<p><strong>Brute-Force Protection</strong> is built into the same tab: lock accounts after N failed attempts (default: 5 attempts, 5-minute lockout). Both thresholds are yours to configure.</p>
<p><strong>Session Duration</strong> lets you override WordPress's default session length. When set, persistent cookies keep sessions alive across browser closes, which is useful for teams who find constant re-authentication disruptive.</p>`,

        'passkeys': `
<div style="background:#fff7ed;border-left:4px solid #ea580c;padding:18px 22px;border-radius:0 8px 8px 0;margin-bottom:24px;">
<h2 style="margin:0 0 8px;font-size:1.25em;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">🪪 The Most Secure WordPress Login Method Available. And It's Free.</h2>
<p style="margin:0 0 10px;color:#374151;">Even TOTP codes can be phished: a fake login page captures your password and OTP code in real time and replays them instantly. Passkeys cannot be phished this way. They are cryptographically bound to your site's exact domain; a fake domain simply cannot trigger your passkey. This is the authentication standard used by Apple, Google, and Microsoft for their own products, now available for your WordPress site at no cost.</p>
<p style="margin:0;color:#374151;"><strong>Most WordPress passkey plugins don't exist as free products.</strong> The handful that do charge $50–$100/year for a commercial FIDO2 implementation. CloudScale's passkey support is a full WebAuthn/FIDO2 implementation, open-source, and completely free.</p>
</div>
<p><strong>How it works:</strong> When you register a passkey, your device generates a public/private key pair. The private key never leaves your device. At login, your server sends a random challenge; your device signs it with the private key; the server verifies the signature against your stored public key. No secret is ever transmitted over the network.</p>
<p><strong>Supported authenticators:</strong> Face ID (iPhone, iPad, Mac), Touch ID (MacBook), Windows Hello (fingerprint, face, PIN), Android biometrics, and hardware security keys (YubiKey 5 series, Google Titan, etc.).</p>
<p><strong>Registering a passkey:</strong></p>
<ol>
<li>Click <em>+ Add Passkey</em> and give it a label (e.g. "iPhone 16 Pro", "YubiKey").</li>
<li>Click <em>Register</em> and your browser will prompt for biometric confirmation or a hardware key tap.</li>
<li>The passkey is saved to your account. Register one per device you log in from.</li>
</ol>
<p><strong>Browser support:</strong> Chrome 108+, Safari 16+, Edge 108+, Firefox 122+. If a browser doesn't support passkeys, the login flow falls back to email OTP automatically, so no user is ever locked out.</p>`,

        'security': `
<div style="background:#fff5f5;border-left:4px solid #c0392b;padding:18px 22px;border-radius:0 8px 8px 0;margin-bottom:24px;">
<h2 style="margin:0 0 8px;font-size:1.25em;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">🛡️ A Security Consultant in Your WordPress Dashboard, for Free</h2>
<p style="margin:0 0 10px;color:#374151;">A professional WordPress security audit costs $500–$5,000 and takes days to schedule. Generic security checklists from free plugins tell you what to check but not what it means for your specific site. CloudScale connects directly to the world's most capable AI models: <strong>Anthropic Claude 4</strong> and <strong>Google Gemini 2.5 Pro</strong>. It analyses your entire WordPress installation and delivers a scored, prioritised report with specific remediation steps in under 60 seconds. The same class of AI used by enterprise security teams, working on your site.</p>
<p style="margin:0;color:#374151;"><strong>Wordfence Premium costs $119/year. Sucuri costs $199/year. WPScan costs $25–$75/month.</strong> These tools run signature-based scans; they match known patterns against a database. They cannot identify novel threats, unusual configuration combinations, or the specific risk profile of your setup. CloudScale's AI audit reasons from first principles: it reads your actual configuration, your actual code, and delivers findings that are specific to you, not generic checklist items.</p>
</div>

<p><strong>Standard Scan</strong> audits WordPress core settings, active plugins and themes, user accounts, file permissions, and wp-config.php hardening constants. The AI scores each finding Critical / High / Medium / Low and gives you specific steps to fix it: not generic advice, but instructions for your exact configuration.</p>
<p><strong>Deep Dive Scan</strong> adds live probes your site's security team would run manually:</p>
<ul>
<li><strong>Static PHP code analysis</strong> of every active plugin, flagging <code>eval()</code>, shell execution functions, code obfuscation, and suspicious patterns that malware authors use</li>
<li><strong>Live HTTP probes:</strong> open directory listing, weak TLS (SSLv3, TLS 1.0), CORS misconfigurations, server version header leaks</li>
<li><strong>DNS security checks:</strong> SPF strictness, DMARC policy strength, DKIM probes (skipped entirely for domains with no MX records, so there are no false positives for non-email sites)</li>
<li><strong>CSP quality analysis:</strong> flags <code>unsafe-inline</code>, <code>unsafe-eval</code>, wildcard sources, and missing directives in your Content Security Policy</li>
<li><strong>SSH hardening:</strong> probes port 22, reads sshd_config, checks for fail2ban; unprotected SSH is marked CRITICAL because it is actively used to recruit servers into DDoS botnets</li>
<li><strong>AI Code Triage:</strong> the 10 highest-risk static findings are sent to the AI with surrounding code context; each is classified as Confirmed Threat / False Positive / Needs Review before the main audit runs</li>
</ul>
<p><strong>Quick Fixes</strong> appear above the scan results, providing one-click remediations for the most common misconfigurations. Each shows green (done) or amber (needs attention) at a glance.</p>
<p><strong>Scheduled Scans</strong> run automatically on a daily or weekly schedule with email and push notifications (ntfy.sh supported), so you know about problems before your users or Google do.</p>

<hr style="border:none;border-top:1px solid #e2e8f0;margin:28px 0;">

<h2 style="font-size:1.3em;font-weight:800;color:#0f172a;margin:0 0 6px;">Setting Up Your AI Provider</h2>
<p style="color:#64748b;margin:0 0 20px;font-size:.95em;">You need one API key to use the AI Cyber Audit. Google Gemini has a free tier with no credit card needed. Anthropic Claude requires a credit card but delivers the deepest analysis. Either works; both are excellent.</p>

<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:22px 24px;margin:0 0 24px;">
<h3 style="margin:0 0 16px;font-size:1.1em;font-weight:700;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">Option A: Google Gemini (Free, No Credit Card)</h3>
<p style="margin:0 0 14px;color:#374151;line-height:1.7;">Google AI Studio's free tier gives you access to Gemini 2.0 Flash with generous daily limits, more than enough for daily WordPress security scans. No billing setup required. This is the recommended starting point if you've never used an AI API before.</p>
<ol style="margin:0 0 14px;padding-left:20px;color:#374151;line-height:1.9;">
<li>Go to <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener"><strong>aistudio.google.com/app/apikey</strong></a></li>
<li>Sign in with your Google account</li>
<li>Click <strong>"Create API key"</strong> and select any Google Cloud project (or create a new one)</li>
<li>Copy the key; it looks like <code>AIzaSy...</code></li>
<li>In WordPress: <strong>Tools → Cyber and Devtools → Security tab → AI Settings</strong></li>
<li>Select <strong>Google Gemini</strong> as provider, paste your key, select model, click <strong>Save</strong></li>
</ol>
<p style="margin:0 0 8px;color:#374151;"><strong>Free tier limits:</strong> Gemini 2.0 Flash gives you 15 requests/minute, 1,500 requests/day, and 1 million tokens/day. A standard WordPress scan uses approximately 3,000–8,000 tokens. You can run dozens of scans per day at no cost.</p>
<p style="margin:0;color:#374151;"><strong>Want Gemini 2.5 Pro?</strong> That model requires a paid Google AI Studio account. Go to <a href="https://aistudio.google.com" target="_blank" rel="noopener">aistudio.google.com</a>, click your account, then <strong>Billing</strong>, and enable pay-as-you-go. Gemini 2.5 Pro costs approximately $0.01–0.03 per scan.</p>
</div>

<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:22px 24px;margin:0 0 24px;">
<h3 style="margin:0 0 16px;font-size:1.1em;font-weight:700;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">Option B: Anthropic Claude (Deepest Analysis, Credit Card Required)</h3>
<p style="margin:0 0 14px;color:#374151;line-height:1.7;">Claude Sonnet 4.6 and Opus 4.7 deliver the most thorough security reasoning available. Anthropic does not offer a free tier, but the cost is minimal: a deep dive audit with Claude Opus 4.7 typically costs $0.05–0.15. An entire month of daily scans with Claude Sonnet 4.6 costs under $1.</p>
<ol style="margin:0 0 14px;padding-left:20px;color:#374151;line-height:1.9;">
<li>Go to <a href="https://console.anthropic.com" target="_blank" rel="noopener"><strong>console.anthropic.com</strong></a> and create an account</li>
<li>Go to <strong>Settings → Billing</strong> and add a credit card</li>
<li>Add an initial credit (<strong>$5 is plenty to get started</strong> and covers hundreds of standard scans)</li>
<li>Go to <strong>Settings → API Keys</strong> and click <strong>"Create Key"</strong></li>
<li>Give it a name like "WordPress Security" and copy the key; it looks like <code>sk-ant-api03-...</code></li>
<li>In WordPress: <strong>Tools → Cyber and Devtools → Security tab → AI Settings</strong></li>
<li>Select <strong>Anthropic Claude</strong> as provider, paste your key, select model, click <strong>Save</strong></li>
</ol>
<p style="margin:0;color:#374151;"><strong>Model guide:</strong> <em>claude-sonnet-4-6</em> is fast and excellent for standard scans and daily scheduling. <em>claude-opus-4-7</em> is the most capable model available and is recommended for deep dive scans and critical sites. Use <em>Auto</em> mode in the plugin to let it pick the right model for each scan type.</p>
</div>

<div style="background:#fefce8;border:1px solid #fde68a;border-radius:8px;padding:22px 24px;margin:0 0 24px;">
<h3 style="margin:0 0 12px;font-size:1.05em;font-weight:700;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">⚡ Setting Up Automatic Top-Ups (Anthropic)</h3>
<p style="margin:0 0 12px;color:#374151;line-height:1.7;">If you use scheduled daily scans with Claude, your credit balance will gradually decrease. Automatic top-ups ensure your scans never fail due to an empty balance. Anthropic recharges your account automatically when it drops below a threshold you set.</p>
<ol style="margin:0;padding-left:20px;color:#374151;line-height:1.9;">
<li>Go to <a href="https://console.anthropic.com/settings/billing" target="_blank" rel="noopener"><strong>console.anthropic.com/settings/billing</strong></a></li>
<li>Scroll to <strong>"Automatic recharge"</strong></li>
<li>Toggle it on</li>
<li>Set <strong>"Recharge when balance falls below"</strong> to $2 (works well for moderate usage)</li>
<li>Set <strong>"Recharge amount"</strong> to $10 (covers several months of daily scans)</li>
<li>Click <strong>Save</strong></li>
</ol>
<p style="margin:12px 0 0;color:#92400e;font-size:.9em;"><strong>Tip:</strong> Anthropic sends email receipts for each top-up. Set a usage budget alert at <strong>Settings → Limits</strong> (e.g. $5/month) so you get notified if usage spikes unexpectedly.</p>
</div>

<div style="background:#fefce8;border:1px solid #fde68a;border-radius:8px;padding:22px 24px;margin:0 0 4px;">
<h3 style="margin:0 0 12px;font-size:1.05em;font-weight:700;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">⚡ Setting Up Spend Alerts (Google Paid Tier)</h3>
<p style="margin:0 0 12px;color:#374151;line-height:1.7;">If you upgrade to Gemini 2.5 Pro on Google's pay-as-you-go tier, Google bills your card automatically as you use the API, with no manual top-up process. Usage is charged to your linked payment method at the end of each billing period.</p>
<ol style="margin:0;padding-left:20px;color:#374151;line-height:1.9;">
<li>Go to <a href="https://console.cloud.google.com/billing" target="_blank" rel="noopener"><strong>console.cloud.google.com/billing</strong></a></li>
<li>Select your project, then click <strong>Budgets &amp; Alerts</strong></li>
<li>Click <strong>"Create Budget"</strong></li>
<li>Set a monthly budget (e.g. $5) and email alert thresholds at 50%, 90%, and 100%</li>
<li>Click <strong>Save</strong> and Google will email you if spend approaches your limit</li>
</ol>
<p style="margin:12px 0 0;color:#92400e;font-size:.9em;"><strong>Note:</strong> Google does not cut off API access when a budget alert fires; it only sends a notification. To hard-cap spend, enable the <em>"Actions"</em> option in the budget and select "Disable billing" (use cautiously, as this will break any Google Cloud services in the project).</p>
</div>`,

        'code-block': `
<div style="background:#f0f9ff;border-left:4px solid #0e6b8f;padding:18px 22px;border-radius:0 8px 8px 0;margin-bottom:24px;">
<h2 style="margin:0 0 8px;font-size:1.25em;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">💻 Beautiful Code Blocks Without Paying $50/Year or Slowing Your Site Down</h2>
<p style="margin:0 0 10px;color:#374151;">Most WordPress code highlighting plugins have one of two problems: they load scripts from an external CDN (adding 100–300ms to every page load, hurting your Core Web Vitals score, and breaking if the CDN goes down), or they charge $30–$50/year for features that should be free. <strong>Enlighter</strong> loads from their own servers. <strong>SyntaxHighlighter Evolved</strong> loads from WordPress.com's CDN. <strong>Prismatic</strong> charges $29/year for a theme switcher.</p>
<p style="margin:0;color:#374151;">CloudScale bundles highlight.js 11.11.1 <strong>entirely on your own server</strong>: zero external HTTP requests, zero CDN dependency, zero annual fee. Your pages load faster, your cache hit rates improve, and your syntax highlighting works even when third-party services are down.</p>
</div>
<p>The Code Block is a native Gutenberg block (<code>cloudscale/code</code>) and a <code>[cs_code]</code> shortcode. It works everywhere WordPress renders content.</p>
<p><strong>190+ languages with auto-detection.</strong> CloudScale detects the language automatically from the code content. Override it manually in the block sidebar when detection picks the wrong one.</p>
<p><strong>14 professional colour themes:</strong> Atom One Dark/Light, GitHub, Monokai, Nord, Dracula, Tokyo Night, VS Code, VS 2015, Stack Overflow, Night Owl, Gruvbox, Solarized, Panda, Shades of Purple. A toggle button switches between dark and light variants, storing the preference in <code>localStorage</code> so it follows the reader across pages.</p>
<p><strong>Copy to clipboard</strong> with one click. Line numbers are rendered via CSS counter so they are never included when someone copies the code.</p>
<p><strong>INI/TOML auto-repair:</strong> Gutenberg breaks INI and TOML files at bare <code>[section]</code> headers by treating them as block delimiters. CloudScale detects this silently and reassembles the fragments, showing a brief toast so you know it happened.</p>`,

        'migrator': `
<div style="background:#fefce8;border-left:4px solid #ca8a04;padding:18px 22px;border-radius:0 8px 8px 0;margin-bottom:24px;">
<h2 style="margin:0 0 8px;font-size:1.25em;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">🔄 Switch Plugins Without Touching 100 Posts by Hand</h2>
<p style="margin:0 0 10px;color:#374151;">Switching code highlighting plugins normally means opening every post, finding the old block or shortcode, deleting it, re-inserting the new one, and republishing, for every single post on your site. On a blog with 100 posts, that's hours of tedious work with plenty of room for mistakes.</p>
<p style="margin:0;color:#374151;">No other free WordPress plugin offers automated batch migration from multiple source formats with a preview step before committing. CloudScale does it in three clicks: Scan, Preview, Migrate All.</p>
</div>
<p>The Migrator scans your database for posts and pages using any supported legacy format, shows you a precise before/after diff, and converts them all to CloudScale blocks in a single operation.</p>
<p><strong>Supported source formats:</strong></p>
<ul>
<li>WordPress core <code>&lt;!-- wp:code --&gt;</code> and <code>&lt;!-- wp:preformatted --&gt;</code> blocks</li>
<li>Code Syntax Block plugin (<code>&lt;!-- wp:code-syntax-block/code --&gt;</code>)</li>
<li>Legacy shortcodes: <code>[code]</code>, <code>[sourcecode]</code>, and common variants</li>
</ul>
<p><strong>Workflow:</strong></p>
<ol>
<li><strong>Scan:</strong> finds every post and page with supported blocks. Shows title, status, date, and block count.</li>
<li><strong>Preview:</strong> shows the exact before/after content diff per post. Nothing is written to the database at this stage.</li>
<li><strong>Migrate:</strong> convert one post at a time, or migrate everything in a single click.</li>
</ol>
<p>⚠ The migrator writes directly to <code>post_content</code>. Always take a database backup first. Use the CloudScale Backup &amp; Restore plugin for a one-click snapshot before you begin.</p>`,

        'sql-tool': `
<div style="background:#f8fafc;border-left:4px solid #64748b;padding:18px 22px;border-radius:0 8px 8px 0;margin-bottom:24px;">
<h2 style="margin:0 0 8px;font-size:1.25em;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">🗄️ Query Your Live Database Safely, Without phpMyAdmin or SSH</h2>
<p style="margin:0 0 10px;color:#374151;">phpMyAdmin is powerful but complex to install securely, and leaving it exposed is a serious vulnerability. Adminer is a single PHP file that attackers actively scan for. Desktop tools like TablePlus require you to open a database port to your laptop. For WordPress administrators who just need to check table sizes, find orphaned data, or troubleshoot a slow query, those options are overkill or a security liability.</p>
<p style="margin:0;color:#374151;">CloudScale's SQL tool lives inside wp-admin, accessible only to administrators, and is <strong>read-only by design</strong>. It is architecturally impossible to delete or modify data through it. No separate installation, no open ports, no exposed files.</p>
</div>
<p><strong>Read-only enforcement:</strong> Every query passes through <code>is_safe_query()</code> which strips comments, rejects semicolons (blocking statement stacking), blocks <code>INTO OUTFILE</code> and <code>LOAD_FILE</code>, and only permits <code>SELECT</code>, <code>SHOW</code>, <code>DESCRIBE</code>, <code>EXPLAIN</code>. Even if an administrator tries to run a destructive query, it is rejected before reaching the database.</p>
<p><strong>14 built-in quick queries</strong> cover the most common diagnostic tasks without writing a single line of SQL:</p>
<ul>
<li><em>Health &amp; Diagnostics:</em> database status, site options, table sizes and row counts</li>
<li><em>Content Summary:</em> posts by type and status, latest published content</li>
<li><em>Bloat &amp; Cleanup:</em> orphaned postmeta, expired transients, revisions, largest autoloaded options (the most common cause of slow WordPress admin)</li>
<li><em>URL &amp; Migration Helpers:</em> HTTP references (for HTTP→HTTPS migrations), posts with old IP references, posts missing meta descriptions</li>
</ul>
<p><strong>Keyboard shortcuts:</strong> <kbd>Enter</kbd> or <kbd>Ctrl+Enter</kbd> runs the query. <kbd>Shift+Enter</kbd> inserts a newline for multi-line queries.</p>`,

        'server-logs': `
<div style="background:#f0fdf4;border-left:4px solid #15803d;padding:18px 22px;border-radius:0 8px 8px 0;margin-bottom:24px;">
<h2 style="margin:0 0 8px;font-size:1.25em;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">📋 Read Your Server Logs Without Leaving WordPress</h2>
<p style="margin:0 0 10px;color:#374151;">When something breaks on a WordPress site, the answer is almost always in a log file. But accessing logs normally means SSH access (which many hosting plans don't provide), navigating a cPanel file manager, or asking your hosting provider to email you a file. For agency developers, that means waiting. For site owners on shared hosting, that means never seeing the logs at all.</p>
<p style="margin:0;color:#374151;"><strong>Query Monitor</strong> shows database queries and hooks but not server-level PHP or Nginx/Apache logs. <strong>Debug Bar</strong> only surfaces WP_DEBUG output. Neither replaces direct log access. CloudScale gives you the actual log files (PHP errors, WordPress debug output, and web server logs) in a clean, searchable interface inside wp-admin, with no SSH required.</p>
</div>
<p><strong>All your log sources in one place:</strong> The source picker lists every available log file with a live status indicator (readable, not found, permission denied, or empty). Switch between PHP error log, WordPress debug log, and web server access/error logs with a single click.</p>
<p><strong>Live search</strong> filters entries as you type with highlighted matches, which is essential for finding a specific error in a log with thousands of lines.</p>
<p><strong>Severity filter</strong> narrows results to Emergency, Alert, Critical, Error, Warning, Notice, Info, or Debug. Cuts through noise on busy production sites where Info and Debug lines dominate.</p>
<p><strong>Auto-refresh tail mode</strong> polls for new entries every 30 seconds. Reproduce a bug in one browser tab while watching the log update in real time in another. It's the fastest way to trace an intermittent error.</p>
<p><strong>Custom log paths:</strong> add any file path (Nginx error log, a custom application log, a cron output file). Paths persist across sessions.</p>
<p><strong>One-click PHP error logging setup:</strong> if PHP error logging isn't configured on the server, a button writes the required <code>php.ini</code> directives automatically. No server configuration knowledge required.</p>`,
    },
}).catch(err => { console.error('ERROR:', err.message); process.exit(1); });
