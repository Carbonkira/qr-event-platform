import { Link, useNavigate, useOutletContext } from 'react-router-dom'
import { CheckCircle2, Users, Calendar, Hourglass, ChevronRight, Star } from 'lucide-react'
import { Card, Badge, KPI, PriceTag } from '../components/ui'
import { useApp } from '../context/AppContext'
import { useAdminEvents, useAnalytics, useMyRegistrations, useMyOrgs } from '../hooks/useApi'
import { fmtDate, fmtTime } from '../lib/utils'

const STATUS_COLOR = { draft: 'slate', pending: 'amber', approved: 'green', rejected: 'rose', completed: 'slate', cancelled: 'rose' }

// A Participant account only ever attends; an Organizer account only ever
// hosts (genuinely separate account types, see Backend's Organizer/
// Participant models) - so this shows exactly one view, picked by
// accountType, rather than the old tab switcher between "both sides of the
// same account."
export default function MyEvents() {
  const { accountType, place } = useApp()
  return accountType === 'organizer' ? <HostingView place={place} /> : <AttendingView place={place} />
}

function AttendingView({ place }) {
  const navigate = useNavigate()
  const { user } = useApp()
  const { data: regsData } = useMyRegistrations(!!user)
  const registrations = regsData || []

  return (
    <div className="max-w-5xl mx-auto px-5 py-8">
      <div className="mb-6">
        <h1 className="text-2xl font-extrabold tracking-tight">My Events</h1>
        <p className="text-[13px] text-slate-500 mt-0.5">Everything you're attending{place?.city ? ` · ${place.city}` : ''}</p>
      </div>

      {registrations.length === 0 ? (
        <Card className="p-10 text-center text-[13px] text-slate-400">No registrations yet. <Link to="/" className="font-semibold text-[var(--accent)]">Browse events</Link></Card>
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
      )}
    </div>
  )
}

function HostingView({ place }) {
  const navigate = useNavigate()
  const { openCreate } = useOutletContext()
  const { user } = useApp()
  const { data: analytics } = useAnalytics(!!user)
  const { data: eventsData } = useAdminEvents(!!user)
  const { data: myOrgsData } = useMyOrgs(!!user)
  // adminIndex returns every event on the platform for an admin (they need
  // that for Approvals) - "hosting" here means events belonging to an
  // organization this account is actually a member of, same real scope as
  // any co-organizer sees, plus legacy org-less events they personally
  // created (those have no membership concept to check).
  const myOrgIds = new Set((myOrgsData || []).map(o => o.id))
  const hostedEvents = (eventsData || []).filter(e =>
    e.organizationId != null ? myOrgIds.has(e.organizationId) : e.organizerId === user?.id
  )
  const a = analytics || {}

  return (
    <div className="max-w-5xl mx-auto px-5 py-8">
      <div className="mb-6">
        <h1 className="text-2xl font-extrabold tracking-tight">My Events</h1>
        <p className="text-[13px] text-slate-500 mt-0.5">Everything you're hosting{place?.city ? ` · ${place.city}` : ''}</p>
      </div>

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
    </div>
  )
}
