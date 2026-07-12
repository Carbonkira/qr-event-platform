// Vercel serverless function - only reached by known social-media crawlers
// (see vercel.json's "has" user-agent match on /events/:slug), never by real
// visitors. This is a static SPA with no server-side rendering, so a
// crawler hitting the normal index.html would only ever see the generic
// static <title>/description - it can't run the JS that fetches the actual
// event. Returning a tiny crawler-only HTML page with the real event's
// title/description/image as Open Graph tags is the standard fix for this
// on any client-rendered app.
const API_BASE = 'https://api.qrmeets.net/api'
const SITE_URL = 'https://www.qrmeets.net'
const DEFAULT_IMAGE = 'https://api.qrmeets.net/logo-email.png'

function esc(value) {
  return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))
}

export default async function handler(req, res) {
  const slug = req.query.slug
  let event = null

  try {
    const r = await fetch(`${API_BASE}/events/${encodeURIComponent(slug)}`)
    if (r.ok) event = await r.json()
  } catch {
    // Best-effort - falls back to generic QRMeets copy below if this fails.
  }

  const title = event ? `${event.title} | QRMeets` : 'QRMeets | Event Management Platform'
  const description = event?.description
    ? event.description.slice(0, 160)
    : 'QR-based event registration, check-in, and feedback, automated.'
  const image = event?.image || DEFAULT_IMAGE
  const url = `${SITE_URL}/events/${encodeURIComponent(slug)}`

  const html = `<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>${esc(title)}</title>
<meta name="description" content="${esc(description)}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="QRMeets">
<meta property="og:title" content="${esc(title)}">
<meta property="og:description" content="${esc(description)}">
<meta property="og:image" content="${esc(image)}">
<meta property="og:url" content="${esc(url)}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="${esc(title)}">
<meta name="twitter:description" content="${esc(description)}">
<meta name="twitter:image" content="${esc(image)}">
</head>
<body></body>
</html>`

  res.setHeader('Content-Type', 'text/html; charset=utf-8')
  res.setHeader('Cache-Control', 's-maxage=600, stale-while-revalidate=3600')
  res.status(200).send(html)
}
