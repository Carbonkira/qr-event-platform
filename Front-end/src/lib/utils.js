import { clsx } from 'clsx'

export function cn(...inputs) {
  return clsx(inputs)
}

export const PIE_COLORS = ['#1a1a2e', '#e94560', '#f59e0b', '#0f9d8f', '#6d28d9']
export const CHART_COLORS = PIE_COLORS

// The fixed set of event industries/categories - one source of truth for
// the create/edit forms' Select and the public category browsing cards.
export const INDUSTRIES = ['Technology', 'Education', 'Design', 'Finance', 'Healthcare', 'Marketing', 'Real Estate', 'Other']

export const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { weekday: 'short', month: 'short', day: 'numeric' }) : ''
export const fmtDateLong = (d) => d ? new Date(d).toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }) : ''
export const fmtTime = (t) => { if (!t) return ''; const [h, m] = t.split(':'); const ampm = h >= 12 ? 'PM' : 'AM'; return `${h % 12 || 12}:${m} ${ampm}` }
export const monthDay = (d) => { const dt = new Date(d); return { month: dt.toLocaleDateString('en-US', { month: 'short' }).toUpperCase(), day: dt.getDate(), weekday: dt.toLocaleDateString('en-US', { weekday: 'short' }) } }

export function getUserLocale() {
  const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''
  if (tz.includes('Manila')) return { city: 'Metro Manila', region: 'NCR', country: 'Philippines', flag: '🇵🇭' }
  if (tz.includes('Singapore')) return { city: 'Singapore', region: 'Central', country: 'Singapore', flag: '🇸🇬' }
  return { city: 'Your Area', region: 'Local', country: 'Global', flag: '🌏' }
}
export const locale = getUserLocale()

// Calendar (.ics) + share helpers
const pad2 = (n) => String(n).padStart(2, '0')
const toICSDate = (date, time) => {
  const [h, m] = (time || '00:00').split(':')
  const d = new Date(date)
  return `${d.getFullYear()}${pad2(d.getMonth() + 1)}${pad2(d.getDate())}T${pad2(+h)}${pad2(+m)}00`
}
export function downloadICS(event) {
  const ics = `BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:-//QRMeets//EN\nBEGIN:VEVENT\nUID:${event.id}@qrmeets.net\nDTSTAMP:${toICSDate(event.date, event.startTime)}\nDTSTART:${toICSDate(event.date, event.startTime)}\nDTEND:${toICSDate(event.date, event.endTime || event.startTime)}\nSUMMARY:${event.title}\nDESCRIPTION:${(event.description || '').replace(/\n/g, ' ')}\nLOCATION:${event.venue}, ${event.location}\nEND:VEVENT\nEND:VCALENDAR`
  const blob = new Blob([ics], { type: 'text/calendar' })
  const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = `${event.slug}.ics`; a.click()
}
export function googleCalUrl(event) {
  const dates = `${toICSDate(event.date, event.startTime)}/${toICSDate(event.date, event.endTime || event.startTime)}`
  return `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(event.title)}&dates=${dates}&details=${encodeURIComponent(event.description || '')}&location=${encodeURIComponent(event.venue + ', ' + event.location)}`
}
export async function shareEvent(event, toast) {
  const url = `${window.location.origin}/events/${event.slug}`
  try { if (navigator.share) { await navigator.share({ title: event.title, text: `Check out ${event.title}`, url }); return } }
  catch (e) { return }
  try { await navigator.clipboard.writeText(url); toast?.('Link copied to clipboard', 'success') }
  catch { toast?.("Couldn't share — copy the URL manually", 'error') }
}
