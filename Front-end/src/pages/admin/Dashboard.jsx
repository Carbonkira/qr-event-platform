import { useNavigate, useOutletContext } from 'react-router-dom'
import { Users, CheckCircle2, MessageSquare, Star, Calendar, Hourglass, ChevronRight, Plus } from 'lucide-react'
import { Card, Badge, Btn, KPI, PriceTag } from '../../components/ui'
import { useAdminEvents, useAnalytics } from '../../hooks/useApi'
import { fmtDate, locale } from '../../lib/utils'

const STATUS_COLOR = { draft: 'slate', pending: 'amber', approved: 'green', rejected: 'rose', completed: 'slate' }

// The landing page for organizers: a KPI strip for a quick pulse, then every
// event as a clickable card - everything about a specific event (editing,
// guests, checklist, scanner, feedback, report) lives on that event's own
// page (EventDetail), reached by clicking its card here.
export default function Dashboard() {
  const navigate = useNavigate()
  const { openCreate } = useOutletContext()
  const { data: analytics } = useAnalytics()
  const { data: eventsData } = useAdminEvents()
  const events = eventsData || []
  const a = analytics || {}

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div><h1 className="text-2xl font-extrabold">Dashboard</h1><p className="text-[13px] text-slate-500">Your events at a glance · {locale.flag} {locale.city}</p></div>
        <Btn variant="accent" icon={Plus} onClick={openCreate}>Create Event</Btn>
      </div>

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

      <div>
        <div className="flex items-center justify-between mb-3"><h3 className="font-bold text-[14px]">Your Events</h3></div>
        {events.length === 0 ? (
          <button onClick={openCreate} className="w-full py-12 rounded-2xl border-2 border-dashed border-slate-200 text-slate-400 text-[13px] hover:border-slate-300 hover:text-slate-500">+ Create your first event</button>
        ) : (
          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {events.map(e => {
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
    </div>
  )
}
