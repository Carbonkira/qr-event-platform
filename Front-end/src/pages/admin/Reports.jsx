import { useEffect, useState } from 'react'
import { Download, Users, ClipboardCheck, MessageSquare, Star, MapPinned } from 'lucide-react'
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend } from 'recharts'
import { Card, Btn, KPI } from '../../components/ui'
import { useAdminEvents, useAnalytics } from '../../hooks/useApi'
import { useApp } from '../../context/AppContext'
import { getRegistrations, getFeedback } from '../../api/resources'
import { locale } from '../../lib/utils'

export default function Reports() {
  const { addToast } = useApp()
  const { data: eventsData } = useAdminEvents()
  const { data: analytics } = useAnalytics()
  const events = eventsData || []
  const [stats, setStats] = useState({}) // eventId -> { registered, attended, feedback }
  const [downloadingId, setDownloadingId] = useState(null)

  useEffect(() => {
    if (events.length === 0) return
    let cancelled = false
    Promise.all(events.map(e =>
      Promise.all([getRegistrations(e.id), getFeedback(e.id)])
        .then(([regs, fb]) => [e.id, { registered: regs.length, attended: regs.filter(r => r.attended).length, feedback: fb.length }])
        .catch(() => [e.id, { registered: 0, attended: 0, feedback: 0 }])
    )).then(pairs => { if (!cancelled) setStats(Object.fromEntries(pairs)) })
    return () => { cancelled = true }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [events.length])

  const chartData = events.map(e => ({
    name: e.title.length > 16 ? e.title.slice(0, 16) + '…' : e.title,
    registered: stats[e.id]?.registered ?? 0,
    attended: stats[e.id]?.attended ?? 0,
  }))

  const downloadCsv = async (event) => {
    setDownloadingId(event.id)
    try {
      const regs = await getRegistrations(event.id)
      let csv = 'Name,Email,Attended,Certificate,Feedback\n'
      regs.forEach(r => {
        csv += `"${r.name}","${r.email}","${r.attended ? 'Yes' : 'No'}","${r.needsCertificate ? 'Yes' : 'No'}","${r.feedbackSubmitted ? 'Yes' : 'No'}"\n`
      })
      const blob = new Blob([csv], { type: 'text/csv' })
      const a = document.createElement('a')
      a.href = URL.createObjectURL(blob)
      a.download = `${event.slug || event.id}-registrations.csv`
      a.click()
      URL.revokeObjectURL(a.href)
    } catch (err) {
      addToast(err.message, 'error')
    } finally {
      setDownloadingId(null)
    }
  }

  return (
    <div className="space-y-5">
      <div><h1 className="text-2xl font-extrabold">Reports</h1><p className="text-[13px] text-slate-500">Attendance and feedback across all events</p></div>

      <Card className="p-5 !bg-[#1a1a2e] text-white">
        <p className="text-[13px] font-bold mb-1 flex items-center gap-1.5"><MapPinned size={14} />Locale metrics for HQ</p>
        <p className="text-[12px] text-slate-300">{locale.city}, {locale.region}, {locale.country} {locale.flag} · Overall attendance {analytics?.attendanceRate ?? 0}% · Avg satisfaction {analytics?.avgSatisfaction ?? 0}/5 · {analytics?.totalFeedback ?? 0} reviews</p>
      </Card>

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <KPI icon={Users} label="Registrations" value={analytics?.totalRegistrations ?? 0} color="#1a1a2e" />
        <KPI icon={ClipboardCheck} label="Attendance Rate" value={`${analytics?.attendanceRate ?? 0}%`} color="#0f9d8f" />
        <KPI icon={MessageSquare} label="Feedback Rate" value={`${analytics?.feedbackRate ?? 0}%`} color="#6d28d9" />
        <KPI icon={Star} label="Avg Satisfaction" value={`${analytics?.avgSatisfaction ?? 0}/5`} color="#f59e0b" />
      </div>

      <Card className="p-5">
        <p className="font-bold text-[14px] mb-1">Registrations vs. attendance</p>
        <p className="text-[12px] text-slate-400 mb-4">Per event</p>
        {events.length === 0 ? (
          <p className="text-center text-slate-400 text-[12px] py-10">No events yet.</p>
        ) : (
          <ResponsiveContainer width="100%" height={260}>
            <BarChart data={chartData}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
              <XAxis dataKey="name" tick={{ fontSize: 11, fill: '#94a3b8' }} axisLine={false} tickLine={false} />
              <YAxis tick={{ fontSize: 11, fill: '#94a3b8' }} axisLine={false} tickLine={false} allowDecimals={false} />
              <Tooltip contentStyle={{ borderRadius: 12, border: '1px solid #e2e8f0', fontSize: 12 }} />
              <Legend wrapperStyle={{ fontSize: 12 }} />
              <Bar dataKey="registered" name="Registered" fill="#1a1a2e" radius={[6, 6, 0, 0]} barSize={16} />
              <Bar dataKey="attended" name="Attended" fill="#0f9d8f" radius={[6, 6, 0, 0]} barSize={16} />
            </BarChart>
          </ResponsiveContainer>
        )}
      </Card>

      <Card className="overflow-hidden">
        <p className="font-bold text-[14px] p-5 pb-0">Per-event breakdown</p>
        {events.length === 0 ? (
          <p className="text-center text-slate-400 text-[12px] py-10">No events yet.</p>
        ) : (
          <table className="w-full text-[13px] mt-3">
            <thead>
              <tr className="border-b border-slate-100 text-left">
                <th className="px-5 py-2.5 font-semibold text-slate-500 text-[11px] uppercase tracking-wide">Event</th>
                <th className="px-3 py-2.5 font-semibold text-slate-500 text-[11px] uppercase tracking-wide">Registered</th>
                <th className="px-3 py-2.5 font-semibold text-slate-500 text-[11px] uppercase tracking-wide">Attended</th>
                <th className="px-3 py-2.5 font-semibold text-slate-500 text-[11px] uppercase tracking-wide">Rate</th>
                <th className="px-3 py-2.5 font-semibold text-slate-500 text-[11px] uppercase tracking-wide">Feedback</th>
                <th className="px-5 py-2.5" />
              </tr>
            </thead>
            <tbody>
              {events.map(e => {
                const s = stats[e.id] || { registered: 0, attended: 0, feedback: 0 }
                const rate = s.registered ? Math.round((s.attended / s.registered) * 100) : 0
                return (
                  <tr key={e.id} className="border-b border-slate-50 last:border-0">
                    <td className="px-5 py-3 font-semibold text-slate-700 truncate max-w-[220px]">{e.title}</td>
                    <td className="px-3 py-3 text-slate-500">{s.registered}</td>
                    <td className="px-3 py-3 text-slate-500">{s.attended}</td>
                    <td className="px-3 py-3 font-semibold text-[#0f9d8f]">{rate}%</td>
                    <td className="px-3 py-3 text-slate-500">{s.feedback}</td>
                    <td className="px-5 py-3 text-right">
                      <Btn variant="secondary" size="sm" icon={Download} loading={downloadingId === e.id} onClick={() => downloadCsv(e)}>CSV</Btn>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        )}
      </Card>
    </div>
  )
}
