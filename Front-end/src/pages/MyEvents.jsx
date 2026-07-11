import { useState } from 'react'
import { Link, useNavigate, useOutletContext } from 'react-router-dom'
import { CheckCircle2, Users, Calendar, Hourglass, ChevronRight, Star } from 'lucide-react'
import { Card, Badge, KPI, PriceTag } from '../components/ui'
import { useApp } from '../context/AppContext'
import { useAdminEvents, useAnalytics, useMyRegistrations } from '../hooks/useApi'
import { cn, fmtDate, fmtTime, locale } from '../lib/utils'

const STATUS_COLOR = { draft: 'slate', pending: 'amber', approved: 'green', rejected: 'rose', completed: 'slate' }

// Replaces the old split between MyTickets.jsx (public, attending-only) and
// Dashboard.jsx (organizer-only, hosting-only) - one account can do both, so
// this shows both, as tabs of the same page instead of two different shells.
export default function MyEvents() {
  const navigate = useNavigate()
  const { openCreate } = useOutletContext()
  const { user } = useApp()
  const [tab, setTab] = useState('attending')

  const { data: regsData } = useMyRegistrations(!!user)
  const registrations = regsData || []

  const { data: analytics } = useAnalytics(!!user)
  const { data: eventsData } = useAdminEvents(!!user)
  // adminIndex returns every event for an admin (they need that for
  // Approvals/Manage) - "hosting" here means events *this* account created,
  // regardless of role, so it's filtered client-side either way.
  const hostedEvents = (eventsData || []).filter(e => e.userId === user?.id)
  const a = analytics || {}

  return (
    <div className="max-w-5xl mx-auto px-5 py-8">
      <div className="mb-6">
        <h1 className="text-2xl font-extrabold tracking-tight">My Events</h1>
        <p className="text-[13px] text-slate-500 mt-0.5">Everything you're attending and hosting · {locale.flag} {locale.city}</p>
      </div>

      <div className="flex items-center gap-2 mb-7">
        {[['attending', 'Attending'], ['hosting', 'Hosting']].map(([k, l]) => (
          <button key={k} onClick={() => setTab(k)} className={cn('px-4 py-2 rounded-full text-[13px] font-semibold transition-all', tab === k ? 'bg-[#1a1a2e] text-white' : 'bg-white border border-slate-200 text-slate-600 hover:border-slate-300')}>{l}</button>
        ))}
      </div>

      {tab === 'attending' && (
        registrations.length === 0 ? (
          <Card className="p-10 text-center text-[13px] text-slate-400">No registrations yet. <Link to="/" className="font-semibold text-[#e94560]">Browse events</Link></Card>
        ) : (
          <div className="space-y-3">
            {registrations.map(reg => (
              <Card key={reg.id} hover className="p-4" onClick={() => navigate(`/pass/${reg.id}`, { state: reg })}>
                <div className="flex items-center justify-between">
                  <div className="min-w-0">
                    <p className="text-[14px] font-bold text-slate-800 truncate">{reg.event?.title}</p>
                    <p className="text-[12px] text-slate-500 mt-1">{fmtDate(reg.event?.date)} · {fmtTime(reg.event?.startTime)}</p>
                  </div>
                  {reg.attended && <Badge color="green"><CheckCircle2 size={10} />Checked in</Badge>}
                </div>
              </Card>
            ))}
          </div>
        )
      )}

      {tab === 'hosting' && (
        <div className="space-y-6">
          {a.pendingApprovals > 0 && (
            <Card className="p-4 flex items-center gap-3 bg-amber-50 border-amber-200" hover onClick={() => navigate('/organizer/approvals')}>
              <div className="w-9 h-9 rounded-xl bg-amber-400 flex items-center justify-center text-white"><Hourglass size={17} /></div>
              <div className="flex-1"><p className="text-[13px] font-bold text-amber-800">{a.pendingApprovals} event{a.pendingApprovals > 1 ? 's' : ''} awaiting approval</p><p className="text-[11px] text-amber-600">Review and publish them to go live.</p></div>
              <ChevronRight size={18} className="text-amber-500" />
            </Card>
          )}

          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <KPI icon={Calendar} label="Live Events" value={a.totalEvents ?? 0} color="#1a1a2e" />
            <KPI icon={Users} label="Registrations" value={a.totalRegistrations ?? 0} color="#0f9d8f" />
            <KPI icon={CheckCircle2} label="Attendance" value={`${a.attendanceRate ?? 0}%`} sub={`${a.totalAttended ?? 0} checked in`} color="#6d28d9" />
            <KPI icon={Star} label="Satisfaction" value={`${a.avgSatisfaction ?? 0}/5`} sub={`${a.totalFeedback ?? 0} reviews`} color="#f59e0b" />
          </div>

          {hostedEvents.length === 0 ? (
            <button onClick={openCreate} className="w-full py-12 rounded-2xl border-2 border-dashed border-slate-200 text-slate-400 text-[13px] hover:border-slate-300 hover:text-slate-500">+ Create your first event</button>
          ) : (
            <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
              {hostedEvents.map(e => {
                const tasks = e.tasks || []
                const done = tasks.filter(t => t.done).length
                return (
                  <Card key={e.id} hover className="overflow-hidden" onClick={() => navigate(`/organizer/events/${e.id}`)}>
                    <div className="h-28 bg-slate-100">{e.image && <img src={e.image} alt="" className="w-full h-full object-cover" />}</div>
                    <div className="p-4">
                      <div className="flex items-center gap-1.5 mb-1.5">
                        <Badge color={STATUS_COLOR[e.status] || 'slate'} size="xs">{e.status}</Badge>
                        <PriceTag event={e} />
                      </div>
                      <p className="text-[13px] font-bold truncate">{e.title}</p>
                      <p className="text-[11px] text-slate-400 mt-0.5">{fmtDate(e.date)}{tasks.length > 0 && ` · ${done}/${tasks.length} tasks`}</p>
                    </div>
                  </Card>
                )
              })}
            </div>
          )}
        </div>
      )}
    </div>
  )
}
