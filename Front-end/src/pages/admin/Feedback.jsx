import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { MessageSquare, Percent, Star, Download } from 'lucide-react'
import { Card, KPI, Btn } from '../../components/ui'
import AiSummaryCard from '../../components/admin/AiSummaryCard'
import { useAdminEvents, useFeedback, useAsync } from '../../hooks/useApi'
import { getRegistrations } from '../../api/resources'

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

  const csvEscape = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`
  const downloadCsv = () => {
    const eventTitleById = Object.fromEntries(events.map(e => [e.id, e.title]))
    const header = ['Name', 'Email', ...(filter ? [] : ['Event']), ...qLabels, 'Average', 'Comment', 'Submitted'].map(csvEscape).join(',')
    const rows = fb.map(f => {
      const average = ((f.q1 + f.q2 + f.q3 + f.q4 + f.q5) / 5).toFixed(1)
      const cells = [
        f.registration?.name || '', f.registration?.email || '',
        ...(filter ? [] : [eventTitleById[f.eventId] || '']),
        f.q1, f.q2, f.q3, f.q4, f.q5, average, f.comment || '',
        f.createdAt ? new Date(f.createdAt).toLocaleString() : '',
      ]
      return cells.map(csvEscape).join(',')
    })
    const blob = new Blob([[header, ...rows].join('\n')], { type: 'text/csv' })
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = filter ? `${events.find(e => String(e.id) === String(filter))?.slug || filter}-feedback.csv` : 'all-events-feedback.csv'
    a.click()
    URL.revokeObjectURL(a.href)
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div><h1 className="text-2xl font-extrabold">Feedback</h1><p className="text-[13px] text-slate-500">Participant satisfaction insights</p></div>
        <div className="flex items-center gap-2">
          <select value={filter} onChange={e => setFilter(e.target.value)} className="px-3.5 py-2 rounded-xl border border-slate-200 bg-white text-[13px] outline-none"><option value="">All events</option>{events.map(e => <option key={e.id} value={e.id}>{e.title}</option>)}</select>
          <Btn variant="secondary" size="sm" icon={Download} onClick={downloadCsv} disabled={fb.length === 0}>CSV</Btn>
        </div>
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

        {filter && <AiSummaryCard eventId={filter} className="lg:col-span-2" />}

        <Card className="p-5">
          <p className="font-bold text-[14px] mb-3">All feedback</p>
          <div className="space-y-2 max-h-72 overflow-y-auto">
            {fb.map(f => { const av = ((f.q1 + f.q2 + f.q3 + f.q4 + f.q5) / 5).toFixed(1); return (
              <div key={f.id} className="p-3 rounded-xl bg-slate-50 border border-slate-200"><div className="flex justify-between"><span className="text-[12px] font-semibold">{f.badge || "Feedback"}</span><span className="text-[11px] font-bold text-amber-500">★ {av}</span></div>{f.comment && <p className="text-[11px] text-slate-500 mt-1 line-clamp-2">{f.comment}</p>}</div>
            )})}
            {fb.length === 0 && <p className="text-center text-slate-400 text-[12px] py-8">No feedback yet</p>}
          </div>
        </Card>
      </div>
    </div>
  )
}
