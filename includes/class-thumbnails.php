<?php
/**
 * Social preview diagnostics and thumbnails for WordPress posts.
 *
 * @package CloudScale_DevTools
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CSDT_Thumbnails {

    /* ==================================================================
       17. THUMBNAILS — Social Preview Diagnostics
       ================================================================== */

    // ─── Nonce / constants ──────────────────────────────────────────────
    private const THUMB_NONCE = 'csdt_devtools_thumbnails';

    private const SOCIAL_PLATFORMS = [
        // target_kb = optimum file size to aim for during generation.
        // max_kb    = platform's hard limit (used only for compatibility warnings).
        'facebook'  => [ 'label' => 'Facebook',    'w' => 1200, 'h' => 630,  'target_kb' => 400, 'max_kb' => 8000 ],
        'twitter'   => [ 'label' => 'X / Twitter', 'w' => 1200, 'h' => 628,  'target_kb' => 400, 'max_kb' => 5000 ],
        'whatsapp'  => [ 'label' => 'WhatsApp',    'w' => 1200, 'h' => 630,  'target_kb' => 200, 'max_kb' => 300  ],
        'linkedin'  => [ 'label' => 'LinkedIn',    'w' => 1200, 'h' => 627,  'target_kb' => 400, 'max_kb' => 5000 ],
        'instagram' => [ 'label' => 'Instagram',   'w' => 1080, 'h' => 1080, 'target_kb' => 400, 'max_kb' => 8000 ],
    ];

    private const SOCIAL_UAS = [
        'WhatsApp'            => 'WhatsApp/2.23.24.82 A',
        'Facebook'            => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
        'Facebot'             => 'Facebot',
        'LinkedInBot'         => 'LinkedInBot/1.0 (compatible; Mozilla/5.0; Apache-HttpClient +http://www.linkedin.com)',
        'Twitterbot'          => 'Twitterbot/1.0',
    ];

    // ─── Panel render ────────────────────────────────────────────────────

    public static function render_thumbnails_panel(): void {
        $cf_zone  = get_option( 'csdt_devtools_cf_zone_id', '' );
        $cf_token = get_option( 'csdt_devtools_cf_api_token', '' );
        $cf_zone_masked  = $cf_zone  ? str_repeat( '•', 28 ) . substr( $cf_zone,  -4 ) : '';
        $cf_token_masked = $cf_token ? str_repeat( '•', 12 ) . substr( $cf_token, -4 ) : '';
        ?>
        <div class="cs-panel" id="cs-panel-thumbs-checker">
            <div class="cs-section-header" style="background:linear-gradient(135deg,#1565c0,#0d47a1);">
                <span>🔍 URL SOCIAL PREVIEW CHECKER</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Run a full social-preview diagnostic on any URL', 'cloudscale-devtools' ); ?></span>
                <?php CloudScale_DevTools::render_explain_btn( 'social-checker', 'URL Social Preview Checker', [
                    [ 'name' => 'HTTPS',                'rec' => 'Required',     'html' => 'Social crawlers refuse to load preview images served over <code>http://</code>. The URL must use <code>https://</code>.' ],
                    [ 'name' => 'HTTP Response',        'rec' => 'Required',     'html' => 'The page must return <code>HTTP 200</code> for the crawler\'s User-Agent. A <code>403</code> or bot-block will prevent any preview from loading.' ],
                    [ 'name' => 'Response Time',        'rec' => 'Recommended',  'html' => 'Crawlers time out after ~<code>3 seconds</code>. Pages that take longer to respond will show no preview — check for slow plugins or uncached pages.' ],
                    [ 'name' => 'og:title',             'rec' => 'Required',     'html' => 'The title shown in the social card. Without <code>og:title</code>, platforms fall back to the page <code>&lt;title&gt;</code> tag or show nothing.' ],
                    [ 'name' => 'og:description',       'rec' => 'Recommended',  'html' => 'The summary text shown under the title. Recommended for all platforms; Twitter/X truncates to ~<code>200</code> chars.' ],
                    [ 'name' => 'og:image',             'rec' => 'Required',     'html' => 'The preview image. Must be an absolute <code>https://</code> URL. Recommended size: <code>1200×630 px</code>, max <code>8 MB</code>. Facebook enforces a minimum of <code>200×200 px</code>.' ],
                    [ 'name' => 'og:image dimensions',  'rec' => 'Recommended',  'html' => '<code>og:image:width</code> and <code>og:image:height</code> tell crawlers the size without downloading the image. Speeds up card rendering and avoids layout shifts.' ],
                    [ 'name' => 'robots.txt',           'rec' => 'Info',         'html' => 'Checks that <code>robots.txt</code> does not block <code>Googlebot</code>, <code>Twitterbot</code>, <code>facebookexternalhit</code>, or other social crawlers from accessing the page.' ],
                    [ 'name' => 'Crawler UA test',      'rec' => 'Info',         'html' => 'Re-fetches the page using each platform\'s real crawler User-Agent string to confirm the page is not blocked by a WAF, Cloudflare rule, or bot-protection plugin.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">
                <p class="cs-hint" style="margin-bottom:10px"><?php esc_html_e( 'Checks OG tags, og:image size/dimensions, HTTPS, robots.txt, and verifies each platform crawler can actually read the page.', 'cloudscale-devtools' ); ?></p>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                    <?php
                    $recent_post = get_posts( [ 'numberposts' => 1, 'post_status' => 'publish', 'post_type' => 'post' ] );
                    $checker_default_url = ! empty( $recent_post ) ? get_permalink( $recent_post[0] ) : home_url( '/' );
                    ?>
                    <input type="url" id="cs-thumb-check-url" class="cs-input" style="max-width:520px;flex:1"
                           placeholder="<?php echo esc_attr( $checker_default_url ); ?>"
                           value="<?php echo esc_attr( $checker_default_url ); ?>">
                    <button type="button" class="cs-btn-primary" id="cs-thumb-check-btn">🔍 <?php esc_html_e( 'Run Diagnostic', 'cloudscale-devtools' ); ?></button>
                </div>
                <div id="cs-thumb-check-results" style="margin-top:14px;display:none"></div>
            </div>
        </div>

        <?php
        $csdi_id      = (int) get_option( 'cloudscale_default_image_id', 0 );
        $csdi_preview = $csdi_id ? wp_get_attachment_image_url( $csdi_id, 'medium' ) : '';
        ?>
        <div class="cs-panel" id="cs-panel-thumbs-default-image">
            <div class="cs-section-header" style="background:linear-gradient(135deg,#1565c0,#0d47a1);">
                <span>🖼️ DEFAULT FEATURED IMAGE</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Fallback featured image used when a post has no thumbnail set', 'cloudscale-devtools' ); ?></span>
                <?php CloudScale_DevTools::render_explain_btn( 'default-image', 'Default Featured Image', [
                    [ 'name' => 'What it does',       'rec' => 'Overview',     'html' => 'When a post has no featured image set, WordPress normally shows nothing. This plugin intercepts the <code>post_thumbnail_html</code> and <code>has_post_thumbnail</code> filters to return your chosen fallback image instead — in theme loops, archive pages, and as the <code>og:image</code> fallback for social sharing.' ],
                    [ 'name' => 'Recommended size',   'rec' => 'Required',     'html' => 'Use a <strong>1200 × 630 px</strong> image (JPEG or PNG, under 300 KB). This is the optimal size for WhatsApp, LinkedIn, Facebook, and X/Twitter cards. Smaller images may be cropped or rejected by social crawlers.' ],
                    [ 'name' => 'og:image fallback',  'rec' => 'Important',    'html' => 'Without a default image, posts shared on social media with no featured image will show no preview card — significantly reducing click-through rates. Setting a branded default ensures every post looks professional when shared.' ],
                    [ 'name' => 'Change vs Remove',   'rec' => 'Info',         'html' => '<strong>Change Image</strong> — opens the WordPress Media Library to select a new fallback image.<br><strong>Remove</strong> — clears the fallback. Posts without a featured image will revert to showing no thumbnail.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">
                <p class="cs-hint" style="margin-bottom:14px;"><?php esc_html_e( 'When a post has no featured image, this image is shown in theme loops and used as the og:image fallback for social sharing. Choose a branded 1200×630 px image.', 'cloudscale-devtools' ); ?></p>
                <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap;">
                    <div id="csdt-defimg-preview" style="flex-shrink:0;">
                        <?php if ( $csdi_preview ) : ?>
                            <img src="<?php echo esc_url( $csdi_preview ); ?>" style="max-width:240px;height:auto;border:1px solid #ddd;border-radius:4px;display:block;" />
                        <?php else : ?>
                            <div style="width:240px;height:126px;background:#f0f0f0;border:1px dashed #ccc;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:12px;">No image selected</div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <input type="hidden" id="csdt-defimg-id" value="<?php echo esc_attr( $csdi_id ); ?>" />
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
                            <button type="button" class="cs-btn-primary" id="csdt-defimg-select"><?php echo $csdi_id ? esc_html__( 'Change Image', 'cloudscale-devtools' ) : esc_html__( 'Select Image', 'cloudscale-devtools' ); ?></button>
                            <?php if ( $csdi_id ) : ?>
                            <button type="button" class="cs-btn-secondary" id="csdt-defimg-remove" style="color:#dc2626;border-color:#dc2626;"><?php esc_html_e( 'Remove', 'cloudscale-devtools' ); ?></button>
                            <?php endif; ?>
                        </div>
                        <p id="csdt-defimg-status" style="font-size:12px;color:#4b5563;margin:0;"></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="cs-panel" id="cs-panel-thumbs-cloudflare">
            <div class="cs-section-header" style="background:linear-gradient(135deg,#e65100,#bf360c);">
                <span>☁️ CLOUDFLARE SETUP &amp; DIAGNOSTICS</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Configure WAF bypass rules and test cache behaviour', 'cloudscale-devtools' ); ?></span>
                <?php CloudScale_DevTools::render_explain_btn( 'cloudflare', 'Cloudflare Setup & Diagnostics', [
                    [ 'name' => 'Bot Fight Mode fix',    'rec' => 'Critical',     'html' => 'Cloudflare\'s Bot Fight Mode blocks social crawler user agents (WhatsApp, Facebook, LinkedIn, X/Twitter). This prevents them from reading your OG tags, meaning no preview card when links are shared. The fix is a <strong>WAF Custom Rule</strong> that skips Bot Fight Mode for those specific user agents only.' ],
                    [ 'name' => 'WAF rule setup',        'rec' => 'Recommended',  'html' => 'In Cloudflare Dashboard → Security → WAF → Custom Rules: create a rule with <em>User Agent contains</em> (facebookexternalhit OR LinkedInBot OR WhatsApp OR Twitterbot) → Action: <strong>Skip</strong> → Bot Fight Mode. Place it above any block rules.' ],
                    [ 'name' => 'Cache purge',           'rec' => 'Optional',     'html' => 'After fixing OG tags or images, Cloudflare may serve stale cached versions to crawlers for hours. The Cache Purge tool lets you clear a specific URL or your entire zone instantly. Requires a Cloudflare API Token with Cache Purge permission and your Zone ID.' ],
                    [ 'name' => 'Crawler test',          'rec' => 'Info',         'html' => 'The URL Social Preview Checker (panel above) simulates each crawler\'s user agent and reports exactly what OG tags they see — including whether Cloudflare is blocking them. Use it to verify your WAF rule is working correctly.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">

                <!-- CF Setup Guide -->
                <div class="cs-thumb-cf-guide">
                    <h3 style="margin-top:0;font-size:14px;color:#333"><?php esc_html_e( 'Why social previews fail with Cloudflare', 'cloudscale-devtools' ); ?></h3>
                    <p class="cs-hint"><?php esc_html_e( 'Cloudflare\'s Bot Fight Mode and Super Bot Fight Mode block social crawler user agents (WhatsApp, Facebook, LinkedIn, X/Twitter) before they can read your page\'s OG tags. The fix is a WAF Custom Rule that skips Bot Fight Mode for those specific UAs.', 'cloudscale-devtools' ); ?></p>

                    <div class="cs-thumb-cf-steps">
                        <div class="cs-thumb-cf-step">
                            <span class="cs-thumb-cf-step-num">1</span>
                            <div>
                                <strong><?php esc_html_e( 'Open Cloudflare Dashboard', 'cloudscale-devtools' ); ?></strong>
                                <p class="cs-hint"><?php esc_html_e( 'Go to your Cloudflare dashboard → select your domain → Security → WAF → Custom Rules.', 'cloudscale-devtools' ); ?></p>
                            </div>
                        </div>
                        <div class="cs-thumb-cf-step">
                            <span class="cs-thumb-cf-step-num">2</span>
                            <div>
                                <strong><?php esc_html_e( 'Create a Custom Rule: "Allow Social Crawlers"', 'cloudscale-devtools' ); ?></strong>
                                <p class="cs-hint"><?php esc_html_e( 'Use the Expression Editor and paste this expression:', 'cloudscale-devtools' ); ?></p>
                                <pre class="cs-thumb-cf-code">(http.user_agent contains "WhatsApp") or (http.user_agent contains "facebookexternalhit") or (http.user_agent contains "Facebot") or (http.user_agent contains "LinkedInBot") or (http.user_agent contains "Twitterbot")</pre>
                                <p class="cs-hint"><?php esc_html_e( 'Set the Action to "Skip" and tick "Bot Fight Mode" and "Super Bot Fight Mode".', 'cloudscale-devtools' ); ?></p>
                            </div>
                        </div>
                        <div class="cs-thumb-cf-step">
                            <span class="cs-thumb-cf-step-num">3</span>
                            <div>
                                <strong><?php esc_html_e( 'Deploy and verify', 'cloudscale-devtools' ); ?></strong>
                                <p class="cs-hint"><?php esc_html_e( 'Save the rule, then use the "Test Crawler Access" button below to confirm each crawler UA gets a 200 response with OG tags present.', 'cloudscale-devtools' ); ?></p>
                            </div>
                        </div>
                        <div class="cs-thumb-cf-step">
                            <span class="cs-thumb-cf-step-num">4</span>
                            <div>
                                <strong><?php esc_html_e( 'Cache note', 'cloudscale-devtools' ); ?></strong>
                                <p class="cs-hint"><?php esc_html_e( 'If social platforms have already cached a failed preview, purge the Cloudflare cache for that URL and then use each platform\'s debug tool to force a re-scrape (Facebook Sharing Debugger, LinkedIn Post Inspector, etc.).', 'cloudscale-devtools' ); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CF Cache Purge -->
                <div style="margin-top:18px;padding-top:16px;border-top:1px solid #e0e0e0">
                    <h3 style="font-size:14px;color:#333;margin-top:0"><?php esc_html_e( 'Cloudflare Cache Purge', 'cloudscale-devtools' ); ?></h3>
                    <p class="cs-hint"><?php esc_html_e( 'After fixing OG tags or image issues, purge the Cloudflare cache to force crawlers to re-fetch the page. Requires a Cloudflare API Token with Cache Purge permission and your Zone ID.', 'cloudscale-devtools' ); ?></p>

                    <div class="cs-field-row" style="flex-wrap:wrap;gap:12px">
                        <div class="cs-field" style="min-width:240px">
                            <label class="cs-label" for="cs-cf-zone-id"><?php esc_html_e( 'Zone ID', 'cloudscale-devtools' ); ?></label>
                            <div style="display:flex;gap:6px;align-items:center">
                                <input type="text" id="cs-cf-zone-id" class="cs-input"
                                       value="<?php echo esc_attr( $cf_zone_masked ?: '' ); ?>"
                                       data-real="<?php echo esc_attr( $cf_zone ); ?>"
                                       data-masked="<?php echo $cf_zone ? '1' : '0'; ?>"
                                       <?php if ( $cf_zone ) : ?>readonly<?php endif; ?>
                                       placeholder="<?php esc_attr_e( '32-character hex string', 'cloudscale-devtools' ); ?>">
                                <button type="button" id="cs-cf-zone-eye" title="<?php esc_attr_e( 'Show / hide Zone ID', 'cloudscale-devtools' ); ?>" style="flex-shrink:0;background:none;border:1px solid #ccc;border-radius:4px;padding:5px 9px;cursor:pointer;font-size:13px;line-height:1">&#x1F441;</button>
                            </div>
                        </div>
                        <div class="cs-field" style="min-width:280px">
                            <label class="cs-label" for="cs-cf-api-token"><?php esc_html_e( 'API Token (Cache Purge permission)', 'cloudscale-devtools' ); ?></label>
                            <div style="display:flex;gap:6px;align-items:center">
                                <input type="password" id="cs-cf-api-token" class="cs-input" value=""
                                       placeholder="<?php echo esc_attr( $cf_token_masked ?: __( 'Paste token here', 'cloudscale-devtools' ) ); ?>">
                                <button type="button" id="cs-cf-token-eye" title="<?php esc_attr_e( 'Show / hide token', 'cloudscale-devtools' ); ?>" style="flex-shrink:0;background:none;border:1px solid #ccc;border-radius:4px;padding:5px 9px;cursor:pointer;font-size:13px;line-height:1">&#x1F441;</button>
                            </div>
                            <span class="cs-hint"><?php esc_html_e( 'Leave blank to keep the saved token. Clear and save to remove.', 'cloudscale-devtools' ); ?></span>
                        </div>
                    </div>

                    <div style="margin:12px 0;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                        <input type="url" id="cs-cf-purge-url" class="cs-input cs-input-light-placeholder" style="max-width:420px;flex:1"
                               placeholder="<?php esc_attr_e( 'https://yoursite.com/your-post/ (leave blank to purge everything)', 'cloudscale-devtools' ); ?>">
                        <button type="button" class="cs-btn-primary" id="cs-cf-purge-btn">🗑️ <?php esc_html_e( 'Purge Cache', 'cloudscale-devtools' ); ?></button>
                        <button type="button" class="cs-btn-secondary" id="cs-cf-save-btn" style="background:#555;color:#fff;padding:7px 14px;border:none;border-radius:4px;cursor:pointer;font-size:13px">💾 <?php esc_html_e( 'Save Settings', 'cloudscale-devtools' ); ?></button>
                    </div>
                    <div id="cs-cf-purge-result" style="display:none;margin-top:8px"></div>
                    <span class="cs-settings-saved" id="cs-cf-saved">✓ <?php esc_html_e( 'CF Settings Saved', 'cloudscale-devtools' ); ?></span>
                </div>
            </div>
        </div>

        <div class="cs-panel" id="cs-panel-thumbs-media">
            <div class="cs-section-header" style="background:linear-gradient(135deg,#6a1b9a,#4a148c);">
                <span>📋 POST SOCIAL PREVIEW SCAN</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Check the last 50 posts — will their images work on each social platform?', 'cloudscale-devtools' ); ?></span>
                <?php CloudScale_DevTools::render_explain_btn( 'post-social-scan', 'Post Social Preview Scan', [
                    [ 'name' => 'Facebook',      'rec' => 'Min 200×200',    'html' => 'Checks the WordPress featured image file directly — no live HTTP fetch. Recommended <code>1200×630 px</code>, max <code>8 MB</code>. Optimised versions are auto-generated to <code>/wp-content/uploads/social-formats/</code> when you publish or update a post.' ],
                    [ 'name' => 'X / Twitter',   'rec' => 'Min 280×150',    'html' => '<code>summary_large_image</code> card format. Recommended <code>1200×628 px</code>, max <code>5 MB</code>. Auto-generated at the correct crop on every post save with a new featured image.' ],
                    [ 'name' => 'WhatsApp',      'rec' => 'Max 300 KB',     'html' => 'Strict <code>300 KB</code> hard limit — images over this are <strong>silently hidden</strong> with no error message. The plugin automatically compresses the image at lower JPEG quality until it fits, so your WhatsApp preview will always appear.' ],
                    [ 'name' => 'LinkedIn',      'rec' => 'Min 200×110',    'html' => 'Recommended <code>1200×627 px</code>, max <code>5 MB</code>. Auto-generated with the correct crop. Portrait-oriented or very small images often display poorly in LinkedIn feed cards.' ],
                    [ 'name' => 'Instagram',     'rec' => '1080×1080 sq',   'html' => 'Square <code>1:1</code> format for direct feed post uploads. Min <code>320×320</code>, recommended <code>1080×1080</code>, max <code>8 MB</code>.<br><br><strong>Note:</strong> Instagram does not scrape OG tags for link previews — this format is for direct uploads only.' ],
                    [ 'name' => 'Auto-generate', 'rec' => 'Automatic',      'html' => 'Every time you publish or update a post with a new featured image, the plugin automatically generates correctly sized and compressed images for each enabled platform. Nothing changes if the featured image hasn\'t changed.' ],
                    [ 'name' => 'Fix',           'rec' => 'Manual action',  'html' => 'Manually triggers generation for a single post. Use this to regenerate after changing platform settings, or for posts that existed before auto-generation was enabled.' ],
                    [ 'name' => 'Fix all',            'rec' => 'Manual action',  'html' => 'Runs <strong>Fix</strong> for every post in the current scan results (up to 50). Useful for quickly fixing the posts you just scanned.' ],
                    [ 'name' => 'Fix All Posts on Site', 'rec' => 'Bulk action', 'html' => 'Processes every published post on the entire site in batches of <code>10</code>, generating platform formats for each. Shows live progress (e.g. <code>Fixing 45 / 320</code>). Posts without a featured image are skipped automatically.' ],
                    [ 'name' => 'Refresh Stale',  'rec' => 'Targeted bulk action', 'html' => 'Scans every published post and regenerates only the ones where the featured image has changed since the last generation — either because a different attachment was selected, or because the media file was replaced in the Media Library (same attachment ID, new file). Shows a live log of how many posts were found and fixed, with a clickable link to each one. Much faster than <em>Fix All Posts on Site</em> when only a handful of posts need updating.' ],
                    [ 'name' => 'Re-check',      'rec' => 'Diagnostic',     'html' => 'Runs the full live URL diagnostic (OG tags, robots.txt, crawler UA test) on this specific post URL and scrolls to the URL checker results above.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">
                <p class="cs-hint" style="margin-bottom:10px"><?php esc_html_e( 'Checks the featured image of your last 50 published posts and shows per-platform compatibility. Uses local file data — no live HTTP requests.', 'cloudscale-devtools' ); ?></p>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <button type="button" class="cs-btn-primary" id="cs-thumb-audit-btn" data-mode="recent">📋 <?php esc_html_e( 'Scan Last 50 Posts', 'cloudscale-devtools' ); ?></button>
                    <button type="button" class="cs-btn-primary" id="cs-thumb-audit-top-btn" data-mode="top" style="background:#1565c0">🔥 <?php esc_html_e( 'Scan Top 50 Posts', 'cloudscale-devtools' ); ?></button>
                    <button type="button" class="cs-btn-secondary" id="cs-thumb-fix-all-btn" style="display:none;background:#2271b1;color:#fff;padding:7px 14px;border:none;border-radius:4px;cursor:pointer;font-size:13px">🔧 <?php esc_html_e( 'Fix all', 'cloudscale-devtools' ); ?></button>
                    <button type="button" class="cs-btn-secondary" id="cs-thumb-fix-site-btn" style="background:#6a1b9a;color:#fff;padding:7px 14px;border:none;border-radius:4px;cursor:pointer;font-size:13px">🌐 <?php esc_html_e( 'Fix All Posts on Site', 'cloudscale-devtools' ); ?></button>
                    <button type="button" class="cs-btn-secondary" id="cs-thumb-refresh-stale-btn" style="background:#1565c0;color:#fff;padding:7px 14px;border:none;border-radius:4px;cursor:pointer;font-size:13px">🔄 <?php esc_html_e( 'Refresh Stale', 'cloudscale-devtools' ); ?></button>
                    <span id="cs-thumb-audit-progress" style="font-size:12px;color:#888"></span>
                </div>
                <div id="cs-thumb-stale-log" style="display:none;margin-top:14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:12px;font-size:13px;"></div>
                <div id="cs-thumb-audit-results" style="margin-top:14px;display:none"></div>
            </div>
        </div>

        <?php
        $enabled_platforms = get_option( 'csdt_devtools_social_platforms', array_keys( self::SOCIAL_PLATFORMS ) );
        ?>
        <div class="cs-panel" id="cs-panel-thumbs-platforms">
            <div class="cs-section-header" style="background:linear-gradient(135deg,#00695c,#004d40);">
                <span>🎨 SOCIAL FORMAT SETTINGS</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Auto-generates on every post save — select which platforms to prepare images for', 'cloudscale-devtools' ); ?></span>
                <?php CloudScale_DevTools::render_explain_btn( 'social-formats', 'Social Format Settings', [
                    [ 'name' => 'Facebook 1200×630',    'rec' => 'Optimum ~400 KB',  'html' => 'Optimum: <code>1200×630 px</code> at under <code>400 KB</code> JPEG. Hard limit: <code>8 MB</code>. Minimum: <code>200×200 px</code>. Landscape <code>1.91:1</code> ratio — the plugin auto-crops to this exact frame so Facebook always shows your image, not a random one from the page.' ],
                    [ 'name' => 'X / Twitter 1200×628', 'rec' => 'Optimum ~400 KB',  'html' => 'Optimum: <code>1200×628 px</code> at under <code>400 KB</code> JPEG. Hard limit: <code>5 MB</code>. Minimum for large card: <code>280×150 px</code>. Slightly shorter than Facebook — a separate dedicated crop prevents the subject being letterboxed or clipped.' ],
                    [ 'name' => 'WhatsApp 1200×630',    'rec' => 'Optimum ~200 KB',  'html' => 'Optimum: <code>1200×630 px</code> at under <code>200 KB</code> JPEG. Hard limit: <strong><code>300 KB</code></strong> — images over this are <strong>silently dropped</strong> with no error message. The plugin targets <code>200 KB</code> for a safe margin, retrying at lower quality until the file fits.' ],
                    [ 'name' => 'LinkedIn 1200×627',    'rec' => 'Optimum ~400 KB',  'html' => 'Optimum: <code>1200×627 px</code> at under <code>400 KB</code> JPEG. Hard limit: <code>5 MB</code>. Minimum: <code>200×110 px</code>. Landscape cards perform best — portrait images are cropped awkwardly or shown very small in the LinkedIn feed.' ],
                    [ 'name' => 'Instagram 1080×1080',  'rec' => 'Optimum ~400 KB',  'html' => 'Optimum: <code>1080×1080 px</code> square at under <code>400 KB</code> JPEG. Hard limit: <code>8 MB</code>. Minimum: <code>320×320 px</code>. Square <code>1:1</code> crop for direct feed uploads — Instagram does not scrape OG tags for link preview cards.' ],
                    [ 'name' => 'Auto-generation',      'rec' => 'How it works',     'html' => 'Every time you publish or update a post with a new featured image, the plugin automatically generates all enabled platform formats at the optimum size and quality — not just within the hard limit. Unchanged images are skipped. <strong>Originals are never modified.</strong>' ],
                    [ 'name' => 'Save Settings',        'rec' => 'Required once',    'html' => 'Saves which platforms are enabled. Only checked platforms are generated on post save. Changes take effect on the next publish or update.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">
                <p class="cs-hint" style="margin-bottom:14px"><?php esc_html_e( 'Social format images are generated automatically every time you publish or update a post with a new featured image. You can also generate them manually using the Fix button in the scan above. Original images are never modified.', 'cloudscale-devtools' ); ?></p>
                <div class="cs-platform-grid">
                    <?php foreach ( self::SOCIAL_PLATFORMS as $key => $p ) :
                        $checked   = in_array( $key, $enabled_platforms, true );
                        $opt_kb    = $p['target_kb'];
                        $size_note = $opt_kb >= 1000 ? 'optimum ~' . ( $opt_kb / 1000 ) . ' MB' : 'optimum ~' . $opt_kb . ' KB';
                        ?>
                        <label class="cs-platform-card <?php echo $checked ? 'cs-platform-checked' : ''; ?>">
                            <input type="checkbox" name="cs_social_platform[]" value="<?php echo esc_attr( $key ); ?>"
                                   class="cs-platform-cb" <?php checked( $checked ); ?>>
                            <div class="cs-platform-card-body">
                                <span class="cs-platform-name"><?php echo esc_html( $p['label'] ); ?></span>
                                <span class="cs-platform-dims"><?php echo esc_html( $p['w'] . '×' . $p['h'] . 'px' ); ?></span>
                                <span class="cs-platform-limit"><?php echo esc_html( $size_note ); ?></span>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:14px;display:flex;align-items:center;gap:10px">
                    <button type="button" class="cs-btn-primary" id="cs-platform-save-btn">💾 <?php esc_html_e( 'Save Settings', 'cloudscale-devtools' ); ?></button>
                    <span class="cs-settings-saved" id="cs-platform-saved">✓ <?php esc_html_e( 'Saved', 'cloudscale-devtools' ); ?></span>
                </div>
            </div>
        </div>

        <?php
        // CSS for this tab is injected via wp_add_inline_style() in enqueue_admin_assets().
        // See get_thumbnails_admin_css() for the ruleset.
    }

    /**
     * Validates that a URL does not point to a private/reserved IP address.
     *
     * Prevents SSRF attacks from admin-initiated URL checks. Only allows
     * publicly routable destinations; rejects localhost, RFC-1918 ranges,
     * link-local, and other reserved blocks.
     *
     * @param  string $url The URL to validate.
     * @return bool         True if safe to fetch, false if internal/reserved.
     */
    private static function is_safe_external_url( string $url ): bool {
        if ( ! wp_http_validate_url( $url ) ) {
            return false;
        }
        $parsed = wp_parse_url( $url );
        $host   = $parsed['host'] ?? '';
        if ( ! $host ) {
            return false;
        }
        // Resolve hostname to IP.
        $ip = gethostbyname( $host );
        // gethostbyname returns the original hostname unchanged on failure.
        if ( $ip === $host && ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            // Could not resolve — reject.
            return false;
        }
        // Reject private/reserved ranges.
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    // ─── AJAX: check a single URL ────────────────────────────────────────

    public static function ajax_social_check_url(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
        if ( ! $url ) {
            wp_send_json_error( [ 'message' => 'No URL provided.' ] );
        }
        if ( ! self::is_safe_external_url( $url ) ) {
            wp_send_json_error( [ 'message' => 'URL must be a publicly accessible address.' ] );
        }
        wp_send_json_success( self::social_diagnose_url( $url ) );
    }

    // ─── AJAX: scan last 10 posts ────────────────────────────────────────

    public static function ajax_social_scan_posts(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $posts = get_posts( [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [ [ 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ] ],
        ] );
        $results = [];
        foreach ( $posts as $post ) {
            $url      = get_permalink( $post );
            $diag     = self::social_diagnose_url( $url );
            $thumb_id = get_post_thumbnail_id( $post->ID );
            $attach_id = $thumb_id ? (int) $thumb_id : null;
            $can_fix   = false;
            if ( $attach_id ) {
                $file    = get_attached_file( $attach_id );
                $can_fix = $file && file_exists( $file );
            }
            $results[] = [
                'id'        => $post->ID,
                'title'     => get_the_title( $post ),
                'url'       => $url,
                'totals'    => $diag['totals'],
                'og_image'  => $diag['og_image'] ?? '',
                'img_kb'    => $diag['img_kb'] ?? null,
                'img_w'     => $diag['img_w'] ?? null,
                'img_h'     => $diag['img_h'] ?? null,
                'attach_id' => $attach_id,
                'can_fix'   => $can_fix,
            ];
        }
        wp_send_json_success( $results );
    }

    // ─── Per-platform compatibility check ────────────────────────────────

    private static function check_platform_compat( int $width, int $height, float $kb, bool $https ): array {
        $r = [];

        // Facebook — optimum ~400 KB, hard limit 8 MB, min 200×200, ideal 1200×630
        if ( ! $https ) {
            $r['facebook'] = [ 'status' => 'fail', 'msg' => 'Image must be HTTPS' ];
        } elseif ( $width < 200 || $height < 200 ) {
            $r['facebook'] = [ 'status' => 'fail', 'msg' => 'Too small — minimum 200×200 px' ];
        } elseif ( $kb > 8000 ) {
            $r['facebook'] = [ 'status' => 'fail', 'msg' => 'Too large — hard limit 8 MB' ];
        } elseif ( $width < 1200 || $height < 630 ) {
            $r['facebook'] = [ 'status' => 'warn', 'msg' => 'Below optimum 1200×630 — Fix will crop and resize to the ideal size' ];
        } elseif ( $kb > 400 ) {
            $r['facebook'] = [ 'status' => 'warn', 'msg' => "{$kb} KB — above optimum ~400 KB. Fix will compress to the ideal size" ];
        } else {
            $r['facebook'] = [ 'status' => 'pass', 'msg' => 'Ready — optimum size and quality' ];
        }

        // X / Twitter — optimum ~400 KB, hard limit 5 MB, min 280×150, ideal 1200×628
        if ( ! $https ) {
            $r['twitter'] = [ 'status' => 'fail', 'msg' => 'Image must be HTTPS' ];
        } elseif ( $width < 280 || $height < 150 ) {
            $r['twitter'] = [ 'status' => 'fail', 'msg' => 'Too small — minimum 280×150 px for large card' ];
        } elseif ( $kb > 5000 ) {
            $r['twitter'] = [ 'status' => 'fail', 'msg' => 'Too large — hard limit 5 MB' ];
        } elseif ( $width < 1200 || $height < 628 ) {
            $r['twitter'] = [ 'status' => 'warn', 'msg' => 'Below optimum 1200×628 — Fix will crop and resize to the ideal size' ];
        } elseif ( $kb > 400 ) {
            $r['twitter'] = [ 'status' => 'warn', 'msg' => "{$kb} KB — above optimum ~400 KB. Fix will compress to the ideal size" ];
        } else {
            $r['twitter'] = [ 'status' => 'pass', 'msg' => 'Ready — optimum size and quality' ];
        }

        // WhatsApp — optimum ~200 KB, hard limit 300 KB (images over this are silently hidden)
        if ( ! $https ) {
            $r['whatsapp'] = [ 'status' => 'fail', 'msg' => 'Image must be HTTPS' ];
        } elseif ( $kb > 300 ) {
            $r['whatsapp'] = [ 'status' => 'fail', 'msg' => "{$kb} KB — over 300 KB hard limit. Preview will be silently hidden. Fix will compress below the limit" ];
        } elseif ( $width < 1200 || $height < 630 ) {
            $r['whatsapp'] = [ 'status' => 'warn', 'msg' => 'Below optimum 1200×630 — Fix will crop, resize, and compress to under 200 KB' ];
        } elseif ( $kb > 200 ) {
            $r['whatsapp'] = [ 'status' => 'warn', 'msg' => "{$kb} KB — above optimum ~200 KB (close to 300 KB hard limit). Fix will compress to the safe target" ];
        } else {
            $r['whatsapp'] = [ 'status' => 'pass', 'msg' => 'Ready — within optimum 200 KB target' ];
        }

        // LinkedIn — optimum ~400 KB, hard limit 5 MB, min 200×110, ideal 1200×627
        if ( ! $https ) {
            $r['linkedin'] = [ 'status' => 'fail', 'msg' => 'Image must be HTTPS' ];
        } elseif ( $width < 200 || $height < 110 ) {
            $r['linkedin'] = [ 'status' => 'fail', 'msg' => 'Too small — minimum 200×110 px' ];
        } elseif ( $kb > 5000 ) {
            $r['linkedin'] = [ 'status' => 'fail', 'msg' => 'Too large — hard limit 5 MB' ];
        } elseif ( $width < 1200 || $height < 627 ) {
            $r['linkedin'] = [ 'status' => 'warn', 'msg' => 'Below optimum 1200×627 — Fix will crop and resize to the ideal size' ];
        } elseif ( $kb > 400 ) {
            $r['linkedin'] = [ 'status' => 'warn', 'msg' => "{$kb} KB — above optimum ~400 KB. Fix will compress to the ideal size" ];
        } else {
            $r['linkedin'] = [ 'status' => 'pass', 'msg' => 'Ready — optimum size and quality' ];
        }

        // Instagram — optimum ~400 KB, hard limit 8 MB, min 320×320, ideal 1080×1080 square
        if ( $width < 320 || $height < 320 ) {
            $r['instagram'] = [ 'status' => 'fail', 'msg' => 'Too small — minimum 320×320 px' ];
        } elseif ( $kb > 8000 ) {
            $r['instagram'] = [ 'status' => 'fail', 'msg' => 'Too large — hard limit 8 MB' ];
        } elseif ( $width < 1080 || $height < 1080 ) {
            $r['instagram'] = [ 'status' => 'warn', 'msg' => 'Below optimum 1080×1080 square — Fix will crop to a square and resize to the ideal size' ];
        } elseif ( $kb > 400 ) {
            $r['instagram'] = [ 'status' => 'warn', 'msg' => "{$kb} KB — above optimum ~400 KB. Fix will compress to the ideal size" ];
        } else {
            $r['instagram'] = [ 'status' => 'pass', 'msg' => 'Ready — optimum size and quality' ];
        }

        return $r;
    }

    // ─── AJAX: scan featured images for last 50 posts ─────────────────────

    public static function ajax_social_scan_media(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $mode = isset( $_POST['mode'] ) && $_POST['mode'] === 'top'
            ? 'top' : 'recent';

        // "Top posts" — order by view count meta if available, else comment count.
        $view_meta_keys = [ 'post_views_count', 'views', '_post_views', 'wpb_post_views_count', 'jetpack-views' ];
        $view_meta_found = false;
        if ( $mode === 'top' ) {
            foreach ( $view_meta_keys as $mk ) {
                $sample = get_posts( [ 'post_type' => 'post', 'posts_per_page' => 1, 'meta_key' => $mk, 'orderby' => 'meta_value_num', 'order' => 'DESC', 'fields' => 'ids' ] );
                if ( ! empty( $sample ) ) {
                    $view_meta_found = $mk;
                    break;
                }
            }
        }

        $query_args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'fields'         => 'ids',
        ];

        if ( $mode === 'top' ) {
            if ( $view_meta_found ) {
                $query_args['meta_key'] = $view_meta_found;
                $query_args['orderby']  = 'meta_value_num';
                $query_args['order']    = 'DESC';
            } else {
                // Fall back to comment count as a proxy for popularity.
                $query_args['orderby'] = 'comment_count';
                $query_args['order']   = 'DESC';
            }
        } else {
            $query_args['orderby'] = 'date';
            $query_args['order']   = 'DESC';
        }

        $posts = get_posts( $query_args );

        $results = [];

        foreach ( $posts as $post_id ) {
            $post_url  = get_permalink( $post_id );
            $thumb_id  = get_post_thumbnail_id( $post_id );

            if ( ! $thumb_id ) {
                // No featured image — all platforms fail.
                $all_fail = [];
                foreach ( array_keys( self::SOCIAL_PLATFORMS ) as $key ) {
                    $all_fail[ $key ] = [ 'status' => 'fail', 'msg' => 'No featured image set' ];
                }
                $results[] = [
                    'post_id'   => $post_id,
                    'title'     => get_the_title( $post_id ),
                    'post_url'  => $post_url,
                    'img_url'   => '',
                    'attach_id' => null,
                    'width'     => 0,
                    'height'    => 0,
                    'size_kb'   => null,
                    'status'    => 'fail',
                    'no_image'  => true,
                    'platforms' => $all_fail,
                    'can_fix'   => false,
                ];
                continue;
            }

            $attach_id = (int) $thumb_id;
            $img_url   = wp_get_attachment_url( $attach_id );
            $meta      = wp_get_attachment_metadata( $attach_id );
            $width     = (int) ( $meta['width']  ?? 0 );
            $height    = (int) ( $meta['height'] ?? 0 );
            $kb        = null;
            $can_fix   = false;
            $https     = $img_url && str_starts_with( $img_url, 'https://' );

            $file = get_attached_file( $attach_id );
            if ( $file && file_exists( $file ) ) {
                $bytes   = (int) filesize( $file );
                $kb      = round( $bytes / 1024, 1 );
                $ext     = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
                $can_fix = in_array( $ext, [ 'jpg', 'jpeg', 'png', 'webp' ], true );
            }

            $platforms = self::check_platform_compat( $width, $height, (float) ( $kb ?? 0 ), $https );

            // Derive overall status from worst platform result.
            $status = 'pass';
            foreach ( $platforms as $pc ) {
                if ( $pc['status'] === 'fail' )      { $status = 'fail'; break; }
                if ( $pc['status'] === 'warn' )       { $status = 'warn'; }
            }

            $results[] = [
                'post_id'   => $post_id,
                'title'     => get_the_title( $post_id ),
                'post_url'  => $post_url,
                'img_url'   => $img_url,
                'attach_id' => $attach_id,
                'width'     => $width,
                'height'    => $height,
                'size_kb'   => $kb,
                'status'    => $status,
                'no_image'  => false,
                'platforms' => $platforms,
                'can_fix'   => $can_fix,
            ];
        }

        $counts    = array_count_values( array_column( $results, 'status' ) );
        $sort_note = '';
        if ( $mode === 'top' ) {
            $sort_note = $view_meta_found
                ? sprintf( __( 'sorted by view count (%s)', 'cloudscale-devtools' ), $view_meta_found )
                : __( 'sorted by comment count (no view-count plugin detected)', 'cloudscale-devtools' );
        }
        wp_send_json_success( [
            'total_scanned' => count( $results ),
            'pass'          => $counts['pass'] ?? 0,
            'warn'          => $counts['warn'] ?? 0,
            'fail'          => $counts['fail'] ?? 0,
            'mode'          => $mode,
            'sort_note'     => $sort_note,
            'posts'         => $results,
        ] );
    }

    // ─── AJAX: recompress an oversized image ─────────────────────────────

    public static function ajax_social_fix_image(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $attachment_id = absint( $_POST['attachment_id'] ?? 0 );
        if ( ! $attachment_id ) {
            wp_send_json_error( [ 'message' => 'No attachment ID.' ] );
        }
        $result = self::social_recompress_image( $attachment_id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }
        wp_send_json_success( $result );
    }

    // ─── AJAX: save platform settings ────────────────────────────────────

    public static function ajax_social_platform_save(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $raw      = isset( $_POST['platforms'] ) ? (array) $_POST['platforms'] : [];
        $allowed  = array_keys( self::SOCIAL_PLATFORMS );
        $filtered = array_values( array_intersect( $raw, $allowed ) );
        update_option( 'csdt_devtools_social_platforms', $filtered );
        wp_send_json_success( [ 'saved' => $filtered ] );
    }

    // ─── Shared: generate per-platform social format images ─────────────

    private static function generate_social_formats_for_post( int $post_id ): ?array {
        $thumb_id = get_post_thumbnail_id( $post_id );
        if ( ! $thumb_id ) return null;

        $source_file = get_attached_file( (int) $thumb_id );
        if ( ! $source_file || ! file_exists( $source_file ) ) return null;

        $enabled = get_option( 'csdt_devtools_social_platforms', array_keys( self::SOCIAL_PLATFORMS ) );
        if ( empty( $enabled ) ) return null;

        $upload   = wp_upload_dir();
        $dest_dir = trailingslashit( $upload['basedir'] ) . 'social-formats/' . $post_id;
        $dest_url = trailingslashit( $upload['baseurl'] ) . 'social-formats/' . $post_id;
        wp_mkdir_p( $dest_dir );

        $ext = strtolower( pathinfo( $source_file, PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, [ 'jpg', 'jpeg', 'png', 'webp' ], true ) ) {
            $ext = 'jpg';
        }
        // Convert PNG/WebP to JPEG so lossy quality reduction can actually shrink the file.
        if ( in_array( $ext, [ 'png', 'webp' ], true ) ) {
            $ext = 'jpg';
        }

        $results = [];

        foreach ( self::SOCIAL_PLATFORMS as $key => $platform ) {
            if ( ! in_array( $key, $enabled, true ) ) continue;

            $filename = "{$dest_dir}/{$key}.{$ext}";
            $file_url = "{$dest_url}/{$key}.{$ext}";
            $quality  = 90;

            for ( $attempt = 0; $attempt < 4; $attempt++ ) {
                $editor = wp_get_image_editor( $source_file );
                if ( is_wp_error( $editor ) ) {
                    $results[ $key ] = [ 'success' => false, 'label' => $platform['label'], 'error' => $editor->get_error_message() ];
                    continue 2;
                }
                $editor->resize( $platform['w'], $platform['h'], true );
                $editor->set_quality( $quality );
                $saved = $editor->save( $filename );
                if ( is_wp_error( $saved ) ) {
                    $results[ $key ] = [ 'success' => false, 'label' => $platform['label'], 'error' => $saved->get_error_message() ];
                    continue 2;
                }
                $kb = round( (int) filesize( $filename ) / 1024, 1 );
                if ( $kb <= $platform['target_kb'] || $quality <= 55 ) break;
                $quality -= 10;
            }

            $kb          = round( (int) filesize( $filename ) / 1024, 1 );
            $under_limit = $kb <= $platform['target_kb'];

            $results[ $key ] = [
                'success'     => true,
                'label'       => $platform['label'],
                'w'           => $platform['w'],
                'h'           => $platform['h'],
                'kb'          => $kb,
                'max_kb'      => $platform['max_kb'],
                'under_limit' => $under_limit,
                'url'         => $file_url,
                'preview_url' => $file_url . '?v=' . time(),
            ];
        }

        update_post_meta( $post_id, '_csdt_social_formats', $results );
        return $results;
    }

    // ─── Hook: auto-generate on post publish / update ────────────────────

    public static function on_post_saved( int $post_id, \WP_Post $post, bool $update ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) )                 return;
        if ( $post->post_status !== 'publish' )                return;

        $thumb_id = (int) get_post_thumbnail_id( $post_id );
        if ( ! $thumb_id ) return;

        // Skip if the thumbnail ID and file are both unchanged since last generation.
        $last_thumb    = (int) get_post_meta( $post_id, '_csdt_social_formats_thumb_id', true );
        $last_gen_time = (int) get_post_meta( $post_id, '_csdt_social_formats_gen_time', true );
        $thumb_post    = get_post( $thumb_id );
        $thumb_mtime   = $thumb_post ? strtotime( $thumb_post->post_modified_gmt ) : 0;
        if ( $last_thumb === $thumb_id && $last_gen_time >= $thumb_mtime ) return;

        $results = self::generate_social_formats_for_post( $post_id );
        if ( $results === null ) return;

        update_post_meta( $post_id, '_csdt_social_formats_thumb_id', $thumb_id );
        update_post_meta( $post_id, '_csdt_social_formats_gen_time', time() );

        // Purge Cloudflare cache for the post URL so crawlers get fresh og:image.
        $post_url = get_permalink( $post_id );
        if ( $post_url ) {
            self::cf_purge_urls( [ $post_url ] );
        }

        // Store for admin notice on next page load.
        $user_id = get_current_user_id();
        set_transient( "cs_sfmt_{$user_id}_{$post_id}", $results, 120 );
    }

    // ─── Shared: purge one or more URLs from Cloudflare cache ───────────

    private static function cf_purge_urls( array $urls ): bool {
        $zone_id = (string) get_option( 'csdt_devtools_cf_zone_id', '' );
        if ( ! $zone_id || empty( $urls ) ) return false;

        $token    = (string) get_option( 'csdt_devtools_cf_api_token', '' );
        $cf_key   = (string) get_option( 'cloudflare_api_key', '' );
        $cf_email = (string) get_option( 'cloudflare_api_email', '' );
        if ( ! $token && ( ! $cf_key || ! $cf_email ) ) return false;

        $headers = [ 'Content-Type' => 'application/json' ];
        if ( $token ) {
            $headers['Authorization'] = 'Bearer ' . $token;
        } else {
            $headers['X-Auth-Key']   = $cf_key;
            $headers['X-Auth-Email'] = $cf_email;
        }

        $response = wp_remote_post(
            "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache",
            [
                'timeout' => 8,
                'headers' => $headers,
                'body'    => wp_json_encode( [ 'files' => array_values( $urls ) ] ),
            ]
        );

        if ( is_wp_error( $response ) ) return false;
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return ! empty( $data['success'] );
    }

    // ─── Hook: Cloudflare cache purge on post publish/update ────────────
    public static function on_post_status_change( string $new_status, string $old_status, \WP_Post $post ): void {
        if ( $new_status !== 'publish' ) return;
        if ( ! in_array( $post->post_type, [ 'post', 'page' ], true ) ) return;
        self::cf_purge_urls( array_filter( [ get_permalink( $post->ID ), home_url( '/' ) ] ) );
    }

    // ─── Admin notice: shown after auto-generation ───────────────────────

    public static function social_format_admin_notice(): void {
        $screen = get_current_screen();
        if ( ! $screen || $screen->base !== 'post' ) return;

        $post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
        if ( ! $post_id ) return;

        $user_id = get_current_user_id();
        $results = get_transient( "cs_sfmt_{$user_id}_{$post_id}" );
        if ( ! $results ) return;
        delete_transient( "cs_sfmt_{$user_id}_{$post_id}" );

        $ok_labels = [];
        $fail_labels = [];
        foreach ( $results as $r ) {
            if ( ! empty( $r['success'] ) ) {
                $size_note = $r['under_limit'] ? '' : ' ⚠';
                $ok_labels[] = $r['label'] . ' (' . $r['w'] . '×' . $r['h'] . ', ' . $r['kb'] . ' KB' . $size_note . ')';
            } else {
                $fail_labels[] = $r['label'];
            }
        }
        if ( empty( $ok_labels ) ) return;

        echo '<div class="notice notice-success is-dismissible" style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px">';
        echo '<span style="font-size:20px;line-height:1.4">🎨</span>';
        echo '<div><strong>' . esc_html__( 'Social format images generated automatically', 'cloudscale-devtools' ) . '</strong><br>';
        echo '<span style="font-size:12px;color:#50575e">' . esc_html( implode( ' &nbsp;·&nbsp; ', $ok_labels ) ) . '</span>';
        if ( ! empty( $fail_labels ) ) {
            echo '<br><span style="font-size:12px;color:#8c2020">✘ Failed: ' . esc_html( implode( ', ', $fail_labels ) ) . '</span>';
        }
        echo '</div></div>';
    }

    // ─── AJAX: generate per-platform social formats ───────────────────────

    public static function ajax_social_generate_formats(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => 'No post ID.' ] );
        }
        if ( ! get_post_thumbnail_id( $post_id ) ) {
            wp_send_json_error( [ 'message' => 'No featured image set for this post.' ] );
        }
        $results = self::generate_social_formats_for_post( $post_id );
        if ( $results === null ) {
            wp_send_json_error( [ 'message' => 'Could not generate formats — check the featured image file and platform settings.' ] );
        }
        // Mark as up-to-date so the save hook won't re-run for this thumbnail.
        update_post_meta( $post_id, '_csdt_social_formats_thumb_id', (int) get_post_thumbnail_id( $post_id ) );

        // Purge Cloudflare cache so WhatsApp/social crawlers get the fresh og:image immediately.
        $post_url  = get_permalink( $post_id );
        $cf_purged = $post_url ? self::cf_purge_urls( [ $post_url ] ) : false;

        wp_send_json_success( array_merge( $results, [ 'cf_purged' => $cf_purged ] ) );
    }

    // ─── AJAX: diagnose social formats for a post ────────────────────────
    // Checks: (1) what's stored in meta, (2) whether image files exist on disk,
    // (3) whether image URLs are reachable by each crawler UA.

    public static function ajax_social_diagnose_formats(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => 'No post ID.' ] );
        }

        $result = [];

        // ── 1. Meta state ────────────────────────────────────────────────
        $formats   = get_post_meta( $post_id, '_csdt_social_formats', true );
        $old_formats = get_post_meta( $post_id, '_cs_social_formats', true );
        $thumb_id  = (int) get_post_meta( $post_id, '_csdt_social_formats_thumb_id', true );
        $current_thumb = (int) get_post_thumbnail_id( $post_id );

        $result['meta'] = [
            'has_new_key'    => ! empty( $formats ),
            'has_old_key'    => ! empty( $old_formats ),
            'thumb_id_saved' => $thumb_id,
            'thumb_id_now'   => $current_thumb,
            'thumb_stale'    => $thumb_id !== $current_thumb,
            'no_thumbnail'   => ! $current_thumb,
        ];

        if ( empty( $formats ) && ! empty( $old_formats ) ) {
            $formats = $old_formats;
            $result['meta']['using_old_key'] = true;
        }

        // ── 2. Per-platform: file existence + URL reachability ───────────
        $upload   = wp_upload_dir();
        $dest_dir = trailingslashit( $upload['basedir'] ) . 'social-formats/' . $post_id;

        $test_uas = [
            'LinkedInBot' => 'LinkedInBot/1.0 (compatible; Mozilla/5.0; Apache-HttpClient +http://www.linkedin.com)',
            'Facebook'    => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
            'Twitterbot'  => 'Twitterbot/1.0',
        ];

        $platforms_out = [];
        foreach ( self::SOCIAL_PLATFORMS as $key => $p ) {
            $meta_entry = $formats[ $key ] ?? null;
            $entry = [
                'label'       => $p['label'],
                'meta_status' => 'missing',
                'url'         => null,
                'file_exists' => false,
                'ua_results'  => [],
            ];

            if ( $meta_entry !== null ) {
                if ( ! empty( $meta_entry['success'] ) ) {
                    $entry['meta_status'] = 'ok';
                    $entry['url']         = $meta_entry['url'] ?? null;
                    $entry['kb']          = $meta_entry['kb'] ?? null;
                    $entry['w']           = $meta_entry['w'] ?? null;
                    $entry['h']           = $meta_entry['h'] ?? null;
                } else {
                    $entry['meta_status'] = 'failed';
                    $entry['error']       = $meta_entry['error'] ?? 'Unknown error';
                }
            }

            // Check file on disk (try .jpg and .png).
            foreach ( [ 'jpg', 'png', 'webp' ] as $ext ) {
                $path = "{$dest_dir}/{$key}.{$ext}";
                if ( file_exists( $path ) ) {
                    $entry['file_exists'] = true;
                    $entry['file_path']   = $path;
                    $entry['file_kb']     = round( filesize( $path ) / 1024, 1 );
                    break;
                }
            }

            // Test URL reachability with crawler UAs (only if a URL is stored).
            if ( ! empty( $entry['url'] ) ) {
                foreach ( $test_uas as $ua_label => $ua_string ) {
                    $resp = wp_remote_head( $entry['url'], [
                        'user-agent'  => $ua_string,
                        'timeout'     => 8,
                        'redirection' => 3,
                    ] );
                    if ( is_wp_error( $resp ) ) {
                        $entry['ua_results'][ $ua_label ] = [ 'code' => 0, 'ok' => false, 'error' => $resp->get_error_message() ];
                    } else {
                        $code = (int) wp_remote_retrieve_response_code( $resp );
                        $entry['ua_results'][ $ua_label ] = [ 'code' => $code, 'ok' => $code === 200 ];
                    }
                }
            }

            $platforms_out[ $key ] = $entry;
        }

        $result['platforms'] = $platforms_out;

        // ── 3. What og:image would each crawler see ──────────────────────
        $post_url = get_permalink( $post_id );
        $og_seen = [];
        foreach ( $test_uas as $ua_label => $ua_string ) {
            $resp = wp_remote_get( $post_url, [
                'user-agent'  => $ua_string,
                'timeout'     => 10,
                'redirection' => 5,
            ] );
            if ( is_wp_error( $resp ) ) {
                $og_seen[ $ua_label ] = [ 'ok' => false, 'error' => $resp->get_error_message() ];
                continue;
            }
            $code = (int) wp_remote_retrieve_response_code( $resp );
            $body = wp_remote_retrieve_body( $resp );
            preg_match( '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']|<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/', $body, $m );
            $og_url = $m[1] ?? $m[2] ?? null;
            $og_seen[ $ua_label ] = [
                'ok'     => $code === 200,
                'code'   => $code,
                'og_url' => $og_url,
                'has_og' => ! empty( $og_url ),
            ];
        }
        $result['og_seen'] = $og_seen;

        wp_send_json_success( $result );
    }

    // ─── AJAX: batch fix all posts ────────────────────────────────────────

    public static function ajax_social_fix_all_batch(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $offset     = absint( $_POST['offset']     ?? 0 );
        $batch_size = 10; // process 10 per request to avoid timeouts

        $total = (int) wp_count_posts( 'post' )->publish;

        $posts = get_posts( [
            'post_type'        => 'post',
            'post_status'      => 'publish',
            'posts_per_page'   => $batch_size,
            'offset'           => $offset,
            'orderby'          => 'ID',
            'order'            => 'ASC',
            'fields'           => 'ids',
            'suppress_filters' => false,
        ] );

        $batch_results = [];
        foreach ( $posts as $post_id ) {
            $thumb_id = get_post_thumbnail_id( $post_id );
            if ( ! $thumb_id ) {
                $batch_results[] = [ 'post_id' => $post_id, 'skipped' => true, 'reason' => 'no_thumbnail' ];
                continue;
            }
            $file = get_attached_file( (int) $thumb_id );
            if ( ! $file || ! file_exists( $file ) ) {
                $batch_results[] = [ 'post_id' => $post_id, 'skipped' => true, 'reason' => 'file_missing' ];
                continue;
            }
            $results = self::generate_social_formats_for_post( $post_id );
            if ( $results ) {
                update_post_meta( $post_id, '_csdt_social_formats_thumb_id', (int) $thumb_id );
            }
            $batch_results[] = [ 'post_id' => $post_id, 'skipped' => false, 'ok' => $results !== null ];
        }

        $next_offset = $offset + count( $posts );
        wp_send_json_success( [
            'total'        => $total,
            'offset'       => $offset,
            'next_offset'  => $next_offset,
            'has_more'     => $next_offset < $total,
            'batch'        => $batch_results,
        ] );
    }

    public static function ajax_social_refresh_stale_batch(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $offset     = absint( $_POST['offset'] ?? 0 );
        $batch_size = 10;

        $total = (int) wp_count_posts( 'post' )->publish;

        $posts = get_posts( [
            'post_type'        => 'post',
            'post_status'      => 'publish',
            'posts_per_page'   => $batch_size,
            'offset'           => $offset,
            'orderby'          => 'ID',
            'order'            => 'ASC',
            'fields'           => 'ids',
            'suppress_filters' => false,
        ] );

        $stale_posts = [];
        foreach ( $posts as $post_id ) {
            $thumb_id = (int) get_post_thumbnail_id( $post_id );
            if ( ! $thumb_id ) continue;

            $last_thumb    = (int) get_post_meta( $post_id, '_csdt_social_formats_thumb_id', true );
            $last_gen_time = (int) get_post_meta( $post_id, '_csdt_social_formats_gen_time', true );
            $thumb_post    = get_post( $thumb_id );
            $thumb_mtime   = $thumb_post ? strtotime( $thumb_post->post_modified_gmt ) : 0;

            $is_stale = ( $last_thumb !== $thumb_id ) || ( $last_gen_time < $thumb_mtime );
            if ( ! $is_stale ) continue;

            $results = self::generate_social_formats_for_post( $post_id );
            $ok      = $results !== null;
            if ( $ok ) {
                update_post_meta( $post_id, '_csdt_social_formats_thumb_id', $thumb_id );
                update_post_meta( $post_id, '_csdt_social_formats_gen_time', time() );
            }

            $stale_posts[] = [
                'post_id' => $post_id,
                'title'   => get_the_title( $post_id ),
                'url'     => get_permalink( $post_id ),
                'reason'  => $last_thumb !== $thumb_id ? 'thumb_id_changed' : 'file_replaced',
                'ok'      => $ok,
            ];
        }

        $next_offset = $offset + count( $posts );
        wp_send_json_success( [
            'total'       => $total,
            'next_offset' => $next_offset,
            'has_more'    => $next_offset < $total,
            'checked'     => count( $posts ),
            'stale'       => $stale_posts,
        ] );
    }

    // ─── Always output social og:image early (priority 1) ───────────────
    // UA-based serving breaks CDN caches — Cloudflare caches the first response
    // and all subsequent visitors (including bots) get the same cached HTML.
    // Instead, always output the best available 1200×630 social image for all
    // visitors so the CDN-cached page has the correct og:image embedded.

    public static function output_crawler_og_image(): void {
        if ( ! is_singular( 'post' ) ) return;

        $post_id = get_the_ID();
        if ( ! $post_id ) return;

        $formats = get_post_meta( $post_id, '_csdt_social_formats', true );
        if ( empty( $formats ) ) {
            $formats = get_post_meta( $post_id, '_cs_social_formats', true );
        }
        if ( empty( $formats ) ) return;

        // Pick the best available format: prefer whatsapp (1200×630, ≤200 KB),
        // fall back through other 1200×630 formats.
        $order = [ 'whatsapp', 'facebook', 'linkedin', 'instagram', 'twitter' ];
        $platform = null;
        foreach ( $order as $p ) {
            if ( ! empty( $formats[ $p ]['url'] ) ) {
                $platform = $p;
                break;
            }
        }
        if ( ! $platform ) return;

        $img_url = esc_url( $formats[ $platform ]['url'] );
        $dims    = self::SOCIAL_PLATFORMS[ $platform ];

        // Output before SEO plugin tags (priority 1). First og:image wins for most crawlers.
        echo "\n<!-- CloudScale: social og:image ({$platform}) -->\n";
        echo '<meta property="og:image" content="' . $img_url . '" />' . "\n";
        echo '<meta property="og:image:width" content="' . (int) $dims['w'] . '" />' . "\n";
        echo '<meta property="og:image:height" content="' . (int) $dims['h'] . '" />' . "\n";
    }

    // ─── AJAX: Cloudflare crawler UA test ────────────────────────────────

    public static function ajax_social_cf_test(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : home_url( '/' );
        if ( ! self::is_safe_external_url( $url ) ) {
            wp_send_json_error( [ 'message' => 'URL must be a publicly accessible address.' ] );
        }
        wp_send_json_success( self::social_test_crawlers( $url ) );
    }

    // ─── AJAX: Cloudflare cache purge ────────────────────────────────────

    public static function ajax_cf_purge(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $zone_id = get_option( 'csdt_devtools_cf_zone_id', '' );
        $token   = get_option( 'csdt_devtools_cf_api_token', '' );
        if ( ! $zone_id || ! $token ) {
            wp_send_json_error( [ 'message' => __( 'Cloudflare Zone ID and API Token are required. Please save them above.', 'cloudscale-devtools' ) ] );
        }
        $purge_url = isset( $_POST['purge_url'] ) ? esc_url_raw( wp_unslash( $_POST['purge_url'] ) ) : '';
        // Ensure purge_url belongs to this site — prevents purging arbitrary Cloudflare-cached URLs.
        if ( $purge_url && strpos( $purge_url, home_url() ) !== 0 ) {
            wp_send_json_error( [ 'message' => __( 'URL must belong to this site.', 'cloudscale-devtools' ) ] );
        }
        $cf_api    = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache";
        $body      = $purge_url
            ? wp_json_encode( [ 'files' => [ $purge_url ] ] )
            : wp_json_encode( [ 'purge_everything' => true ] );
        $response = wp_remote_post( $cf_api, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => $body,
            'timeout' => 15,
        ] );
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! empty( $data['success'] ) ) {
            wp_send_json_success( [
                'message' => $purge_url
                    ? sprintf( __( 'Cache purged for: %s', 'cloudscale-devtools' ), $purge_url )
                    : __( 'Entire Cloudflare cache purged successfully.', 'cloudscale-devtools' ),
            ] );
        } else {
            $errors = isset( $data['errors'] ) ? wp_json_encode( $data['errors'] ) : __( 'Unknown error', 'cloudscale-devtools' );
            wp_send_json_error( [ 'message' => $errors ] );
        }
    }

    // ─── AJAX: save CF credentials ───────────────────────────────────────

    public static function ajax_cf_save(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $zone_id = isset( $_POST['zone_id'] ) ? sanitize_text_field( wp_unslash( $_POST['zone_id'] ) ) : '';
        $token   = isset( $_POST['api_token'] ) ? sanitize_text_field( wp_unslash( $_POST['api_token'] ) ) : '';
        update_option( 'csdt_devtools_cf_zone_id', $zone_id );
        if ( $token !== '' ) {
            update_option( 'csdt_devtools_cf_api_token', $token );
        }
        wp_send_json_success( [ 'message' => __( 'Cloudflare settings saved.', 'cloudscale-devtools' ) ] );
    }

    // ─── Default Featured Image ───────────────────────────────────────────

    public static function ajax_save_default_image(): void {
        check_ajax_referer( 'csdt_defimg', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Unauthorized', 403 ); }
        $id = absint( $_POST['image_id'] ?? 0 );
        update_option( 'cloudscale_default_image_id', $id );
        wp_send_json_success( [ 'id' => $id ] );
    }

    public static function default_image_html( string $html, int $post_id, $post_thumbnail_id, $size, $attr ): string {
        if ( ! empty( $html ) ) { return $html; }
        if ( get_post_type( $post_id ) !== 'post' ) { return $html; }
        $default_id = (int) get_option( 'cloudscale_default_image_id', 0 );
        if ( ! $default_id ) { return $html; }
        return wp_get_attachment_image( $default_id, $size, false, (array) $attr );
    }

    public static function default_image_has_thumbnail( bool $has, $post, $thumbnail_id ): bool {
        if ( $has ) { return $has; }
        $post_obj = get_post( $post );
        if ( ! $post_obj || $post_obj->post_type !== 'post' ) { return $has; }
        return (int) get_option( 'cloudscale_default_image_id', 0 ) > 0;
    }

    // ─── Hero image: swap to 1200×630 social format on single posts ──────

    public static function hero_image_html( string $html, int $post_id, $post_thumbnail_id, $size, $attr ): string {
        if ( empty( $html ) || ! is_singular( 'post' ) ) { return $html; }
        $formats = get_post_meta( $post_id, '_csdt_social_formats', true );
        if ( empty( $formats['facebook']['success'] ) || empty( $formats['facebook']['url'] ) ) {
            return $html;
        }
        $url = esc_url( $formats['facebook']['url'] );
        $w   = (int) $formats['facebook']['w'];
        $h   = (int) $formats['facebook']['h'];
        $html = preg_replace( '/\ssrc="[^"]*"/',    ' src="' . $url . '"', $html );
        $html = preg_replace( '/\ssrcset="[^"]*"/', '',                     $html );
        $html = preg_replace( '/\ssizes="[^"]*"/',  '',                     $html );
        $html = preg_replace( '/\swidth="[^"]*"/',  ' width="' . $w . '"', $html );
        $html = preg_replace( '/\sheight="[^"]*"/', ' height="' . $h . '"', $html );
        return $html;
    }

    public static function enqueue_hero_styles(): void {
        if ( ! is_singular( 'post' ) ) { return; }
        wp_register_style( 'csdt-hero-styles', false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
        wp_enqueue_style( 'csdt-hero-styles' );
        wp_add_inline_style( 'csdt-hero-styles', '.single .wp-post-image{aspect-ratio:1200/630;object-fit:cover;width:100%;display:block;height:auto}' );
    }

    // ─── Private: full URL diagnostic ────────────────────────────────────

    /**
     * Runs all social-preview checks against a URL and returns a structured
     * result array with sections (title + result items) and summary totals.
     */
    private static function social_diagnose_url( string $url ): array {
        $sections = [];
        $og_image = '';
        $img_kb   = null;
        $img_w    = null;
        $img_h    = null;

        $wa_ua = self::SOCIAL_UAS['WhatsApp'];

        // 1. HTTPS
        $pass = str_starts_with( $url, 'https://' );
        $sections[] = [
            'title'   => 'HTTPS',
            'results' => [ $pass
                ? [ 'type' => 'pass', 'msg' => 'URL uses HTTPS' ]
                : [ 'type' => 'fail', 'msg' => 'URL uses HTTP — WhatsApp requires HTTPS for link previews.', 'fix' => 'Install an SSL certificate (Let\'s Encrypt is free) and update your WordPress Address and Site Address in Settings → General to use https://. Add a redirect rule to force HTTP → HTTPS.' ] ],
        ];

        // 2. HTTP response (WhatsApp UA)
        $head = wp_remote_head( $url, [ 'user-agent' => $wa_ua, 'redirection' => 5, 'timeout' => 12, 'sslverify' => true ] );
        $http_ok = false;
        if ( is_wp_error( $head ) ) {
            $sections[] = [ 'title' => 'HTTP Response', 'results' => [ [ 'type' => 'fail', 'msg' => 'Could not connect: ' . $head->get_error_message() ] ] ];
        } else {
            $code = wp_remote_retrieve_response_code( $head );
            $http_ok = ( $code === 200 );
            $is_redirect = in_array( $code, [ 301, 302, 307, 308 ], true );
            $sections[] = [ 'title' => 'HTTP Response (WhatsApp UA)', 'results' => [ $code === 200
                ? [ 'type' => 'pass', 'msg' => 'HTTP 200 OK' ]
                : [ 'type' => $is_redirect ? 'warn' : 'fail',
                    'msg'  => "HTTP $code — " . ( $is_redirect ? 'Redirect (crawlers follow, but adds latency)' : 'Non-200 response; crawler may not read OG tags' ),
                    'fix'  => $is_redirect
                        ? 'Update og:url and your canonical tag to point directly to the final URL to avoid the redirect chain.'
                        : 'Check your WAF, Cloudflare firewall rules, or bot-protection plugins — they may be blocking the WhatsApp crawler User-Agent. Add facebookexternalhit, WhatsApp, Twitterbot, and LinkedInBot to your allowlist.' ] ] ];
        }

        // 3. Fetch HTML + measure response time
        $start = microtime( true );
        $resp  = wp_remote_get( $url, [ 'user-agent' => $wa_ua, 'redirection' => 5, 'timeout' => 18 ] );
        $elapsed = round( microtime( true ) - $start, 2 );
        $html = is_wp_error( $resp ) ? '' : wp_remote_retrieve_body( $resp );

        $sections[] = [ 'title' => 'Response Time', 'results' => [ $elapsed < 3.0
            ? [ 'type' => 'pass', 'msg' => "{$elapsed}s — within 3s crawler timeout" ]
            : ( $elapsed < 5.0
                ? [ 'type' => 'warn', 'msg' => "{$elapsed}s — approaching WhatsApp 3–5s timeout", 'fix' => 'Enable a page caching plugin (e.g. WP Super Cache or W3 Total Cache) and enable Cloudflare\'s HTML caching. Crawlers hit cold pages — caching ensures they get a fast response every time.' ]
                : [ 'type' => 'fail', 'msg' => "{$elapsed}s — exceeds 5s; crawler will likely abort before reading OG tags", 'fix' => 'Enable full-page caching immediately. Check for slow database queries using the CS Monitor DB tab, deactivate heavy plugins, and enable Cloudflare in front of the origin server.' ] ) ] ];

        // 4. OG tags
        $og_results = [];
        $og_fixes = [
            'og:title'       => 'Add <meta property="og:title" content="Your Page Title"> to the <head>. Use an SEO plugin like Yoast or Rank Math — they generate this automatically from your post title.',
            'og:description' => 'Add <meta property="og:description" content="Your description (max ~200 chars)">. Most SEO plugins set this from the meta description field on each post/page.',
            'og:image'       => 'Add <meta property="og:image" content="https://yoursite.com/image.jpg">. Use a 1200×630px JPEG/PNG under 300 KB. Set a site-wide fallback in your SEO plugin settings.',
            'og:url'         => 'Add <meta property="og:url" content="https://yoursite.com/this-page/"> using the canonical URL of this page.',
            'og:type'        => 'Add <meta property="og:type" content="website"> (or "article" for blog posts). Most SEO plugins set this automatically.',
        ];
        foreach ( [ 'og:title', 'og:description', 'og:image', 'og:url', 'og:type' ] as $prop ) {
            $val = self::social_extract_property( $html, $prop );
            $og_results[] = $val
                ? [ 'type' => 'pass', 'msg' => "$prop: " . mb_substr( $val, 0, 80 ) ]
                : [ 'type' => 'fail', 'msg' => "$prop is missing", 'fix' => $og_fixes[ $prop ] ?? '' ];
        }
        foreach ( [ 'twitter:card', 'twitter:image' ] as $name ) {
            $val = self::social_extract_name( $html, $name );
            $og_results[] = $val
                ? [ 'type' => 'pass', 'msg' => "$name: " . mb_substr( $val, 0, 80 ) ]
                : [ 'type' => 'warn', 'msg' => "$name missing — X/Twitter may not render large card",
                    'fix'  => $name === 'twitter:card'
                        ? 'Add <meta name="twitter:card" content="summary_large_image"> to show the full-width image card on X/Twitter. Most SEO plugins have a Twitter Card setting.'
                        : 'Add <meta name="twitter:image" content="https://yoursite.com/image.jpg">. X/Twitter uses this over og:image if present.' ];
        }
        $sections[] = [ 'title' => 'Open Graph Tags', 'results' => $og_results ];

        // 5. og:image analysis
        $og_image = self::social_extract_property( $html, 'og:image' );
        $img_results = [];
        if ( ! $og_image ) {
            $img_results[] = [ 'type' => 'fail', 'msg' => 'og:image is missing — cannot analyse image.', 'fix' => 'Set a featured image on this post/page and ensure your SEO plugin is configured to use it as og:image. Add a site-wide fallback image in your SEO plugin settings.' ];
        } else {
            $img_head = wp_remote_head( $og_image, [ 'user-agent' => $wa_ua, 'timeout' => 10, 'redirection' => 3 ] );
            if ( is_wp_error( $img_head ) ) {
                $img_results[] = [ 'type' => 'fail', 'msg' => 'og:image URL unreachable: ' . $img_head->get_error_message(), 'fix' => 'Verify the image URL is publicly accessible. Check that the file exists in your Media Library and that no security plugin or Cloudflare rule is blocking direct image access.' ];
            } else {
                $img_code = wp_remote_retrieve_response_code( $img_head );
                $img_results[] = $img_code === 200
                    ? [ 'type' => 'pass', 'msg' => 'og:image URL returns HTTP 200' ]
                    : [ 'type' => 'fail', 'msg' => "og:image URL returns HTTP $img_code — image inaccessible", 'fix' => "The image file returned HTTP $img_code. Re-upload the image to your Media Library, update the og:image URL to the new path, and confirm the file is publicly readable (check file permissions and any WAF rules blocking image requests)." ];
                $ct = wp_remote_retrieve_header( $img_head, 'content-type' );
                $img_results[] = str_contains( (string) $ct, 'image/' )
                    ? [ 'type' => 'pass', 'msg' => "Content-Type: $ct" ]
                    : [ 'type' => 'warn', 'msg' => "Unexpected Content-Type: '$ct'", 'fix' => "The URL is not serving an image file. Verify the og:image URL points directly to a JPEG, PNG, or WebP file (not a page or redirect). If using a CDN, check that it is not transforming the response Content-Type." ];
            }
            $img_results[] = str_starts_with( $og_image, 'https://' )
                ? [ 'type' => 'pass', 'msg' => 'og:image uses HTTPS' ]
                : [ 'type' => 'fail', 'msg' => 'og:image uses HTTP — WhatsApp requires HTTPS images', 'fix' => 'Update the og:image URL to use https://. If your site has SSL, the image URL should automatically use HTTPS — check your SEO plugin settings or the post\'s custom OG image field.' ];

            $img_resp = wp_remote_get( $og_image, [ 'user-agent' => $wa_ua, 'timeout' => 20, 'redirection' => 3 ] );
            if ( ! is_wp_error( $img_resp ) ) {
                $img_body = wp_remote_retrieve_body( $img_resp );
                $img_bytes = strlen( $img_body );
                $img_kb    = round( $img_bytes / 1024, 1 );
                if ( $img_bytes > 307200 ) {
                    $img_results[] = [ 'type' => 'fail', 'msg' => "Image is {$img_kb} KB — exceeds WhatsApp's 300 KB silent-failure threshold. Compress to under 250 KB.", 'fix' => "Use the Media Library Audit below to recompress this image, or use squoosh.app / TinyPNG to manually compress it to under 250 KB. Then re-upload and update the og:image URL." ];
                } elseif ( $img_bytes > 204800 ) {
                    $img_results[] = [ 'type' => 'warn', 'msg' => "Image is {$img_kb} KB — approaching 300 KB WhatsApp limit. Consider optimising.", 'fix' => "Compress the image to under 200 KB using TinyPNG or the Media Library Audit recompress tool below. JPEG at 80% quality typically achieves good compression without visible quality loss." ];
                } else {
                    $img_results[] = [ 'type' => 'pass', 'msg' => "Image is {$img_kb} KB — within the 300 KB WhatsApp limit." ];
                }
                if ( function_exists( 'imagecreatefromstring' ) ) {
                    $res = @imagecreatefromstring( $img_body ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                    if ( $res ) {
                        $img_w = imagesx( $res );
                        $img_h = imagesy( $res );
                        imagedestroy( $res );
                        if ( $img_w >= 1200 && $img_h >= 630 ) {
                            $img_results[] = [ 'type' => 'pass', 'msg' => "Dimensions: {$img_w}×{$img_h}px — meets 1200×630 minimum" ];
                        } elseif ( $img_w >= 600 ) {
                            $img_results[] = [ 'type' => 'warn', 'msg' => "Dimensions: {$img_w}×{$img_h}px — below recommended 1200×630", 'fix' => "Resize or recreate the image at 1200×630px. This is the optimal size for Facebook, LinkedIn, and WhatsApp. Use Canva or your image editor to export at exactly 1200×630px." ];
                        } else {
                            $img_results[] = [ 'type' => 'fail', 'msg' => "Dimensions: {$img_w}×{$img_h}px — too small for reliable social previews", 'fix' => "Replace this image with one at least 1200×630px. Images smaller than 600px wide are often ignored by social crawlers entirely. Create a new featured image at 1200×630px." ];
                        }
                    }
                }
            }
        }
        $sections[] = [ 'title' => 'og:image Analysis', 'results' => $img_results ];

        // 6. robots.txt
        $base_url    = preg_replace( '#(https?://[^/]+).*#', '$1', $url );
        $robots_resp = wp_remote_get( "$base_url/robots.txt", [ 'timeout' => 8 ] );
        $rb_results  = [];
        if ( is_wp_error( $robots_resp ) || wp_remote_retrieve_response_code( $robots_resp ) !== 200 ) {
            $rb_results[] = [ 'type' => 'warn', 'msg' => 'robots.txt not found — ensure crawlers are not blocked elsewhere', 'fix' => 'Create a robots.txt at your domain root. In WordPress, go to Settings → Reading and ensure "Discourage search engines" is unchecked. CloudScale SEO AI auto-generates robots.txt — enable it if available.' ];
        } else {
            $rb_body = wp_remote_retrieve_body( $robots_resp );
            foreach ( [ 'facebookexternalhit', 'WhatsApp', 'Facebot', 'LinkedInBot', 'Twitterbot' ] as $bot ) {
                if ( preg_match( '/User-agent:\s*' . preg_quote( $bot, '/' ) . '.*?Disallow:\s*\//si', $rb_body ) ) {
                    $rb_results[] = [ 'type' => 'fail', 'msg' => "robots.txt blocks $bot — this prevents all previews from that platform", 'fix' => "Remove the Disallow rule for $bot from your robots.txt. Add \"User-agent: $bot\\nDisallow:\" (empty Disallow = allow all) to explicitly permit this crawler. Edit robots.txt via your SEO plugin or directly in the site root." ];
                } else {
                    $rb_results[] = [ 'type' => 'pass', 'msg' => "robots.txt does not block $bot" ];
                }
            }
        }
        $sections[] = [ 'title' => 'robots.txt', 'results' => $rb_results ];

        // 7. Cloudflare detection
        $cf_results = [];
        $cf_ray = wp_remote_retrieve_header( is_wp_error( $head ) ? [] : $head, 'cf-ray' );
        if ( $cf_ray ) {
            $cf_cache = wp_remote_retrieve_header( is_wp_error( $head ) ? [] : $head, 'cf-cache-status' );
            $cf_results[] = [ 'type' => 'pass', 'msg' => "Cloudflare active: cf-ray $cf_ray" . ( $cf_cache ? " | Cache: $cf_cache" : '' ) ];
            $cf_results[] = [ 'type' => 'info', 'msg' => 'If any crawler UA test failed, set up a WAF Skip rule in Cloudflare for social crawler user agents — see the Cloudflare Setup panel.' ];
        } else {
            $cf_results[] = [ 'type' => 'pass', 'msg' => 'No Cloudflare detected — WAF skip rule not required' ];
        }
        $sections[] = [ 'title' => 'Cloudflare', 'results' => $cf_results ];

        // Totals
        $pass = $warn = $fail = 0;
        foreach ( $sections as $s ) {
            foreach ( $s['results'] as $r ) {
                match ( $r['type'] ) { 'pass' => $pass++, 'warn' => $warn++, 'fail' => $fail++, default => null };
            }
        }

        return [
            'url'      => $url,
            'sections' => $sections,
            'totals'   => [ 'pass' => $pass, 'warn' => $warn, 'fail' => $fail ],
            'og_image' => $og_image,
            'img_kb'   => $img_kb,
            'img_w'    => $img_w,
            'img_h'    => $img_h,
        ];
    }

    /** Runs the five social crawler UA tests against a URL — used by the CF test button. */
    private static function social_test_crawlers( string $url ): array {
        $results = [];
        foreach ( self::SOCIAL_UAS as $label => $ua ) {
            $resp = wp_remote_get( $url, [ 'user-agent' => $ua, 'redirection' => 5, 'timeout' => 15 ] );
            if ( is_wp_error( $resp ) ) {
                $results[ $label ] = [ 'type' => 'fail', 'code' => 0, 'og' => false, 'msg' => $resp->get_error_message() ];
                continue;
            }
            $code = wp_remote_retrieve_response_code( $resp );
            $body = wp_remote_retrieve_body( $resp );
            $has_og = (bool) preg_match( '/property=["\']og:image["\']/', $body );
            $challenged = str_contains( $body, 'challenge-platform' );
            if ( $code === 200 && $has_og ) {
                $results[ $label ] = [ 'type' => 'pass', 'code' => $code, 'og' => true, 'msg' => $challenged ? 'HTTP 200, og:image present (Cloudflare challenge script detected — WAF skip rule is working)' : 'HTTP 200, og:image present' ];
            } elseif ( $code === 200 && ! $has_og ) {
                $results[ $label ] = [ 'type' => 'fail', 'code' => $code, 'og' => false, 'msg' => $challenged ? 'HTTP 200 but og:image absent — Bot Fight Mode is blocking this crawler. WAF skip rule needed.' : 'HTTP 200 but og:image absent in response' ];
            } else {
                $results[ $label ] = [ 'type' => 'fail', 'code' => $code, 'og' => false, 'msg' => "HTTP $code — crawler is being blocked" ];
            }
        }
        return $results;
    }

    /** Helper: extract og:meta property content. */
    private static function social_extract_property( string $html, string $prop ): string {
        if ( preg_match( '/property=["\']' . preg_quote( $prop, '/' ) . '["\'][^>]+content=["\']([^"\']+)["\']/', $html, $m ) ) {
            return trim( $m[1] );
        }
        if ( preg_match( '/content=["\']([^"\']+)["\'][^>]+property=["\']' . preg_quote( $prop, '/' ) . '["\']/', $html, $m ) ) {
            return trim( $m[1] );
        }
        return '';
    }

    /** Helper: extract meta name content. */
    private static function social_extract_name( string $html, string $name ): string {
        if ( preg_match( '/name=["\']' . preg_quote( $name, '/' ) . '["\'][^>]+content=["\']([^"\']+)["\']/', $html, $m ) ) {
            return trim( $m[1] );
        }
        if ( preg_match( '/content=["\']([^"\']+)["\'][^>]+name=["\']' . preg_quote( $name, '/' ) . '["\']/', $html, $m ) ) {
            return trim( $m[1] );
        }
        return '';
    }

    /**
     * Helper: recompress an attachment to under 300 KB using WP_Image_Editor.
     *
     * @return array|\WP_Error
     */
    private static function social_recompress_image( int $attachment_id ) {
        $file_path = get_attached_file( $attachment_id );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return new \WP_Error( 'not_found', __( 'Attachment file not found on disk.', 'cloudscale-devtools' ) );
        }
        $ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, [ 'jpg', 'jpeg', 'png' ], true ) ) {
            return new \WP_Error( 'unsupported', __( 'Only JPEG and PNG images can be recompressed.', 'cloudscale-devtools' ) );
        }
        // Backup the original.
        $backup = $file_path . '.cs-backup';
        if ( ! copy( $file_path, $backup ) ) {
            return new \WP_Error( 'backup_failed', __( 'Could not create backup — aborting to protect original.', 'cloudscale-devtools' ) );
        }
        $editor = wp_get_image_editor( $file_path );
        if ( is_wp_error( $editor ) ) {
            wp_delete_file( $backup );
            return $editor;
        }
        // Resize if larger than 1200×630 (maintaining aspect ratio, no upscaling).
        $size = $editor->get_size();
        if ( $size['width'] > 1200 || $size['height'] > 630 ) {
            $editor->resize( 1200, 630, false );
        }
        $editor->set_quality( 80 );
        $saved = $editor->save( $file_path );
        if ( is_wp_error( $saved ) ) {
            copy( $backup, $file_path );
            wp_delete_file( $backup );
            return $saved;
        }
        $new_bytes = filesize( $file_path );
        // Still over 300 KB? Try quality 65.
        if ( $new_bytes > 307200 ) {
            $e2 = wp_get_image_editor( $backup );
            if ( ! is_wp_error( $e2 ) ) {
                $e2->set_quality( 65 );
                $e2->save( $file_path );
                $new_bytes = filesize( $file_path );
            }
        }
        $new_kb = round( $new_bytes / 1024, 1 );
        // Regenerate attachment metadata.
        $meta = wp_generate_attachment_metadata( $attachment_id, $file_path );
        wp_update_attachment_metadata( $attachment_id, $meta );
        return [
            'attachment_id' => $attachment_id,
            'new_size_kb'   => $new_kb,
            'backup'        => basename( $backup ),
            'under_limit'   => $new_bytes <= 307200,
            'message'       => $new_bytes <= 307200
                ? sprintf( __( 'Recompressed to %s KB — within the WhatsApp 300 KB threshold.', 'cloudscale-devtools' ), $new_kb )
                : sprintf( __( 'Recompressed to %s KB — still above threshold. Manual intervention needed.', 'cloudscale-devtools' ), $new_kb ),
        ];
    }

    /* ==================================================================
       Security tab helpers + render
       ================================================================== */

    public static function strip_asset_ver( string $src ): string {
        if ( strpos( $src, 'ver=' ) !== false ) {
            $src = remove_query_arg( 'ver', $src );
        }
        return $src;
    }

}
