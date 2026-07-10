import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import ReactMarkdown from 'react-markdown'
import remarkGfm from 'remark-gfm'
import { MessageSquare, Percent, Star, Sparkles, RefreshCw } from 'lucide-react'
import { Card, KPI, Btn } from '../../components/ui'
import { useAdminEvents, useFeedback, useAsync } from '../../hooks/useApi'
import { getRegistrations, getFeedbackSummary } from '../../api/resources'

// Gemini's feedback-summary report comes back as Markdown (headers, tables,
// bold) rather than the old plain-prose paragraph, so it needs an actual
// renderer - these overrides just keep it inside the app's own type scale
// and palette instead of the browser's unstyled h1/table defaults.
const SUMMARY_MARKDOWN_COMPONENTS = {
  h1: (p) => <h3 className="font-bold text-[15px] text-slate-800 mt-4 mb-2 first:mt-0" {...p} />,
  h2: (p) => <h4 className="font-bold text-[14px] text-slate-800 mt-4 mb-1.5 first:mt-0" {...p} />,
  h3: (p) => <h5 className="font-bold text-[13px] text-slate-700 mt-3 mb-1" {...p} />,
  p: (p) => <p className="text-[13px] text-slate-600 leading-relaxed mb-2.5 last:mb-0" {...p} />,
  ul: (p) => <ul className="list-disc pl-5 text-[13px] text-slate-600 leading-relaxed mb-2.5 space-y-1" {...p} />,
  ol: (p) => <ol className="list-decimal pl-5 text-[13px] text-slate-600 leading-relaxed mb-2.5 space-y-1" {...p} />,
  strong: (p) => <strong className="font-semibold text-slate-800" {...p} />,
  hr: () => <hr className="border-slate-100 my-3" />,
  table: (p) => <div className="overflow-x-auto mb-2.5"><table className="w-full text-[12px] border-collapse" {...p} /></div>,
  th: (p) => <th className="text-left font-semibold text-slate-500 border-b border-slate-200 px-2.5 py-1.5" {...p} />,
  td: (p) => <td className="text-slate-600 border-b border-slate-100 px-2.5 py-1.5" {...p} />,
}

export default function Feedback() {
  const [searchParams] = useSearchParams()
  const { data: eventsData } = useAdminEvents()
  const events = eventsData || []
  const [filter, setFilter] = useState(searchParams.get('event') || '')
  const { data: fbData } = useFeedback(filter || undefined)
  const fb = fbData || []
  const { data: attData } = useAsync(() => (filter ? getRegistrations(filter) : Promise.resolve([])), [filter])
  const att = (attData || []).filter(r => r.attended)

  const rate = filter && att.length ? ((fb.length / att.length) * 100).toFixed(0) : null
  const avg = fb.length ? (fb.reduce((s, f) => s + (f.q1 + f.q2 + f.q3 + f.q4 + f.q5) / 5, 0) / fb.length).toFixed(1) : "0"
  const highlighted = fb.filter(f => f.isHighlighted)
  const qLabels = ["Check-in experience", "Event organization", "Content quality", "Venue & facilities", "Overall satisfaction"]
  const qScores = ["q1", "q2", "q3", "q4", "q5"].map((k, i) => ({ label: qLabels[i], value: fb.length ? parseFloat((fb.reduce((s, f) => s + (f[k] || 0), 0) / fb.length).toFixed(1)) : 0 }))

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div><h1 className="text-2xl font-extrabold">Feedback</h1><p className="text-[13px] text-slate-500">Participant satisfaction insights</p></div>
        <select value={filter} onChange={e => setFilter(e.target.value)} className="px-3.5 py-2 rounded-xl border border-slate-200 bg-white text-[13px] outline-none"><option value="">All events</option>{events.map(e => <option key={e.id} value={e.id}>{e.title}</option>)}</select>
      </div>
      <div className="grid grid-cols-3 gap-4">
        <KPI icon={MessageSquare} label="Responses" value={fb.length} color="#1a1a2e" />
        <KPI icon={Percent} label="Response Rate" value={rate !== null ? `${rate}%` : "—"} sub={filter ? `${fb.length} of ${att.length} attendees` : undefined} color="#0f9d8f" />
        <KPI icon={Star} label="Avg Rating" value={`${avg}/5`} color="#f59e0b" />
      </div>
      {highlighted.length > 0 && (
        <Card className="p-5 bg-amber-50 border-amber-200">
          <p className="text-[13px] font-bold text-amber-800 mb-3">⭐ Important comments ({highlighted.length})</p>
          <div className="space-y-2">{highlighted.map(f => (
            <div key={f.id} className="p-3 rounded-xl bg-white border border-amber-200"><div className="flex justify-between mb-1"><span className="text-[12px] font-semibold">{f.badge || "Feedback"}</span></div><p className="text-[12px] text-slate-600">{f.comment}</p></div>
          ))}</div>
        </Card>
      )}
      <div className="grid lg:grid-cols-2 gap-5">
        <Card className="p-5">
          <p className="font-bold text-[14px] mb-4">Score by question</p>
          {fb.length === 0 ? <p className="text-center text-slate-400 text-[12px] py-8">No feedback yet</p> : (
            <div className="space-y-3.5">
              {qScores.map(q => (
                <div key={q.label}>
                  <div className="flex items-center justify-between mb-1"><span className="text-[12px] font-medium text-slate-600">{q.label}</span><span className="text-[12px] font-bold text-slate-800">{q.value.toFixed(1)}</span></div>
                  <div className="h-2 rounded-full bg-slate-100"><div className="h-full rounded-full transition-all" style={{ width: `${(q.value / 5) * 100}%`, background: q.value >= 4 ? "#0f9d8f" : q.value >= 3 ? "#f59e0b" : "#e94560" }} /></div>
                </div>
              ))}
            </div>
          )}
        </Card>

        {filter && <AiSummaryCard eventId={filter} />}

        <Card className="p-5">
          <p className="font-bold text-[14px] mb-3">All feedback</p>
          <div className="space-y-2 max-h-72 overflow-y-auto">
            {fb.map(f => { const av = ((f.q1 + f.q2 + f.q3 + f.q4 + f.q5) / 5).toFixed(1); return (
              <div key={f.id} className="p-3 rounded-xl bg-slate-50 border border-slate-200"><div className="flex justify-between"><span className="text-[12px] font-semibold">{f.badge || "Attendee"}</span><span className="text-[11px] font-bold text-amber-500">★ {av}</span></div>{f.comment && <p className="text-[11px] text-slate-500 mt-1 line-clamp-2">{f.comment}</p>}</div>
            )})}
            {fb.length === 0 && <p className="text-center text-slate-400 text-[12px] py-8">No feedback yet</p>}
          </div>
        </Card>
      </div>
    </div>
  )
}

// AI-generated synthesis of a single event's feedback comments — cached
// server-side on the event, "Regenerate" forces a fresh call (plan §4).
function AiSummaryCard({ eventId }) {
  const [summary, setSummary] = useState(null)
  const [loading, setLoading] = useState(true)
  const [regenerating, setRegenerating] = useState(false)

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    setSummary(null)
    getFeedbackSummary(eventId)
      .then(data => { if (!cancelled) setSummary(data) })
      .catch(() => { if (!cancelled) setSummary({ summary: null }) })
      .finally(() => { if (!cancelled) setLoading(false) })
    return () => { cancelled = true }
  }, [eventId])

  const regenerate = async () => {
    setRegenerating(true)
    try {
      const data = await getFeedbackSummary(eventId, true)
      setSummary(data)
    } catch {
      // keep showing the previous summary if regeneration fails
    } finally {
      setRegenerating(false)
    }
  }

  return (
    <Card className="p-5 lg:col-span-2">
      <div className="flex items-center justify-between mb-3">
        <p className="font-bold text-[14px] flex items-center gap-2"><Sparkles size={15} className="text-[#e94560]" />AI Summary</p>
        <Btn variant="ghost" size="sm" icon={RefreshCw} onClick={regenerate} disabled={loading || regenerating} loading={regenerating}>Regenerate</Btn>
      </div>
      {loading ? (
        <div className="flex items-center justify-center py-8"><span className="w-5 h-5 border-2 border-slate-300 border-t-[#1a1a2e] rounded-full animate-spin" /></div>
      ) : summary?.summary ? (
        <ReactMarkdown remarkPlugins={[remarkGfm]} components={SUMMARY_MARKDOWN_COMPONENTS}>{summary.summary}</ReactMarkdown>
      ) : (
        <p className="text-[13px] text-slate-600 leading-relaxed">Not enough feedback yet to summarize.</p>
      )}
    </Card>
  )
}
