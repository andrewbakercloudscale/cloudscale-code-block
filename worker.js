// CloudScale Uptime Monitor — readiness probe
// Deployed via: Deploy Worker to Cloudflare button in WP Admin → Tools → CloudScale DevTools → Optimizer tab
//
// Required environment bindings (set in Cloudflare Worker → Settings → Variables):
//   SITE_URL   — your WordPress site URL (e.g. https://your-wordpress-site.example.com)
//   PING_URL   — WordPress admin-ajax.php URL (receives ping data)
//   READY_URL  — readiness endpoint URL (e.g. https://your-wordpress-site.example.com/wp-json/csdt/v1/ready/abc123)
//   PING_TOKEN — shared secret token (generated in WP Admin)
//   NTFY_URL   — ntfy.sh topic URL for down alerts (e.g. https://ntfy.sh/yourtopic)
//
// Cron trigger: * * * * *  (every minute)
// HTTP trigger: POST / with Authorization: Bearer <PING_TOKEN> — for manual test from WP Admin

async function runProbe(env, ctx) {
  const start = Date.now();
  let statusCode = 0, responseMs = 0, isUp = false;
  try {
    const res = await fetch(env.READY_URL, {
      method: 'GET',
      headers: {
        'Authorization': 'Bearer ' + env.PING_TOKEN,
        'User-Agent': 'CloudScale-Uptime/1.0',
        'Cache-Control': 'no-store',
      },
      signal: AbortSignal.timeout(15000),
    });
    statusCode = res.status;
    responseMs = Date.now() - start;
    isUp = statusCode === 200;
  } catch(e) { responseMs = Date.now() - start; }

  if (!isUp && env.NTFY_URL) {
    ctx.waitUntil(fetch(env.NTFY_URL, {
      method: 'POST',
      headers: {'Title': 'Site Down: ' + env.SITE_URL, 'Priority': 'urgent', 'Tags': 'rotating_light'},
      body: 'Readiness probe: ' + (statusCode ? 'HTTP ' + statusCode : 'timeout') + ' — ' + responseMs + 'ms',
    }).catch(() => {}));
  }

  ctx.waitUntil(fetch(env.PING_URL, {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=csdt_uptime_ping&token=' + encodeURIComponent(env.PING_TOKEN) + '&status_code=' + statusCode + '&response_ms=' + responseMs,
    signal: AbortSignal.timeout(10000),
  }).catch(() => {}));

  return { statusCode, responseMs, isUp };
}

export default {
  async scheduled(event, env, ctx) {
    ctx.waitUntil(runProbe(env, ctx));
  },

  async fetch(request, env, ctx) {
    // Only accept POST with valid Bearer token — used by WP Admin "Test Endpoint" button
    if (request.method !== 'POST') {
      return new Response('Method Not Allowed', { status: 405 });
    }
    const auth = request.headers.get('Authorization') || '';
    if (auth !== 'Bearer ' + env.PING_TOKEN) {
      return new Response('Unauthorized', { status: 401 });
    }
    const result = await runProbe(env, ctx);
    return new Response(JSON.stringify({ ok: result.isUp, status_code: result.statusCode, response_ms: result.responseMs, triggered: true }), {
      headers: { 'Content-Type': 'application/json' },
    });
  },
};
