import { useMemo, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import {
  Calendar, MapPin, Mail, Phone, Check, X, Hourglass, ShieldAlert, UserCog, Building2, ChevronDown,
  ExternalLink, MailCheck, MailWarning, Users, Ticket, Clock3, GraduationCap,
} from 'lucide-react'
import { Card, Btn, Badge, Avatar } from '../../components/ui'
import { useAdminEvents, usePendingOrganizers, useOrganizerHistory, useMyOrgs } from '../../hooks/useApi'
import { approveEvent, rejectEvent, approveOrganizer, rejectOrganizer } from '../../api/resources'
import { useApp } from '../../context/AppContext'
import { cn, fmtDateLong, fmtDateTime, fmtTime, monthLabel } from '../../lib/utils'

const TABS = [['events', 'Events'], ['organizers', 'Organizers']]
const VIEWS = [['pending', 'Pending'], ['approved', 'Approved'], ['rejected', 'Rejected']]

const byDateDesc = (key) => (a, b) => new Date(b[key] || 0) - new Date(a[key] || 0)

// Consecutive runs sharing a month label - the input is already sorted
// newest first, so this reads as a timeline: "September 2026", "August 2026"...
function groupByMonth(items, key) {
  const groups = []
  items.forEach(item => {
    const label = monthLabel(item[key])
    const last = groups[groups.length - 1]
    if (last && last.label === label) last.items.push(item)
    else groups.push({ label, items: [item] })
  })
  return groups
}

function Field({ label, children }) {
  return (
    <div className="min-w-0">
      <dt className="text-[10px] font-bold text-slate-400 uppercase tracking-wide">{label}</dt>
      <dd className="text-[13px] text-slate-700 mt-0.5 break-words">{children}</dd>
    </div>
  )
}

// One application: a header row you click to reveal the full details, with
// the decision buttons alongside it so a pending item can be decided without
// opening it - but everything the admin needs to decide well is a click away.
function ApplicationCard({ open, onToggle, avatar, title, subtitle, badge, actions, children }) {
  return (
    <Card className="overflow-hidden">
      <div className="flex flex-col sm:flex-row sm:items-center gap-3 p-4">
        <button type="button" onClick={onToggle} aria-expanded={open} className="flex-1 min-w-0 flex items-center gap-3 text-left">
          {avatar}
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2 min-w-0">
              <p className="font-bold text-[14px] text-slate-800 truncate">{title}</p>
              {badge}
            </div>
            <div className="mt-0.5 flex flex-wrap items-center gap-x-4 gap-y-0.5 text-[12px] text-slate-500">{subtitle}</div>
          </div>
          <ChevronDown size={16} className={cn('text-slate-400 transition-transform flex-shrink-0', open && 'rotate-180')} />
        </button>
        {actions && <div className="flex gap-2 flex-shrink-0">{actions}</div>}
      </div>
      {open && <div className="border-t border-slate-100 bg-slate-50/60 p-4 animate-fade">{children}</div>}
    </Card>
  )
}

function DecisionLine({ decision, by, at }) {
  if (!decision) return null
  const approved = decision === 'approved'
  return (
    <p className={cn('text-[12px] font-semibold flex items-center gap-1.5', approved ? 'text-emerald-700' : 'text-rose-600')}>
      {approved ? <Check size={13} /> : <X size={13} />}
      {approved ? 'Approved' : 'Rejected'}{by ? ` by ${by}` : ''}{at ? ` · ${fmtDateTime(at)}` : ''}
    </p>
  )
}

export default function Approvals() {
  const { user, addToast } = useApp()
  const [searchParams] = useSearchParams()
  const isAdmin = user?.role === 'admin'
  const [tab, setTab] = useState(searchParams.get('tab') === 'organizers' ? 'organizers' : 'events')
  const [view, setView] = useState('pending')
  const [openKey, setOpenKey] = useState(null)
  const [busyKey, setBusyKey] = useState(null)
  // Applicants who asked for an organization that doesn't exist yet: the
  // admin creates it while approving (on by default - that's the point of
  // them asking) unless they untick it in the details.
  const [skipCreateOrg, setSkipCreateOrg] = useState({})

  const { data: eventsData, loading: eventsLoading, refetch: refetchEvents } = useAdminEvents(isAdmin)
  const { data: pendingOrgs, loading: pendingLoading, refetch: refetchPending } = usePendingOrganizers(isAdmin)
  const { data: historyOrgs, loading: historyLoading, refetch: refetchHistory } = useOrganizerHistory(isAdmin)
  const { data: orgsData } = useMyOrgs(isAdmin)
  const orgName = useMemo(() => Object.fromEntries((orgsData || []).map(o => [o.id, o.name])), [orgsData])

  const events = eventsData || []
  const lists = {
    events: {
      pending: events.filter(e => e.status === 'pending').sort(byDateDesc('createdAt')),
      approved: events.filter(e => e.reviewDecision === 'approved').sort(byDateDesc('reviewedAt')),
      rejected: events.filter(e => e.reviewDecision === 'rejected').sort(byDateDesc('reviewedAt')),
    },
    organizers: {
      pending: [...(pendingOrgs || [])].sort(byDateDesc('createdAt')),
      approved: (historyOrgs || []).filter(o => o.approvalStatus === 'approved'),
      rejected: (historyOrgs || []).filter(o => o.approvalStatus === 'rejected'),
    },
  }
  const loading = tab === 'events' ? eventsLoading : (view === 'pending' ? pendingLoading : historyLoading)
  const items = lists[tab][view]

  // The nav item is already hidden for non-admins - this catches anyone who
  // hits the URL directly. The API enforces the same restriction either way.
  if (!isAdmin) {
    return (
      <Card className="p-14 text-center">
        <ShieldAlert size={32} className="mx-auto text-slate-300 mb-2" />
        <p className="text-[13px] font-semibold text-slate-600">Admins only</p>
        <p className="text-[13px] text-slate-400 mt-1">You don't have permission to review approvals.</p>
      </Card>
    )
  }

  const decide = async (key, action, done) => {
    setBusyKey(key)
    try { await action(); done() }
    catch (err) { addToast(err.message || 'That did not go through', 'error') }
    finally { setBusyKey(null) }
  }
  const onApproveEvent = (e) => decide(`e${e.id}`, () => approveEvent(e.id), () => { refetchEvents(); addToast('Event approved', 'success') })
  const onRejectEvent = (e) => decide(`e${e.id}`, () => rejectEvent(e.id), () => { refetchEvents(); addToast('Event rejected', 'info') })
  const onApproveOrganizer = (o) => {
    const createOrganization = !!o.requestedOrganizationName && !o.requestedOrganization && !skipCreateOrg[o.id]
    return decide(`o${o.id}`, () => approveOrganizer(o.id, { createOrganization }), () => {
      refetchPending(); refetchHistory()
      addToast(createOrganization ? `Organizer approved - "${o.requestedOrganizationName}" created` : 'Organizer approved', 'success')
    })
  }
  const onRejectOrganizer = (o) => decide(`o${o.id}`, () => rejectOrganizer(o.id), () => { refetchPending(); refetchHistory(); addToast('Organizer rejected', 'info') })

  const eventCard = (e) => {
    const key = `e${e.id}`
    const pending = e.status === 'pending'
    const creator = e.organizer
    return (
      <ApplicationCard
        key={key}
        open={openKey === key}
        onToggle={() => setOpenKey(k => k === key ? null : key)}
        avatar={<Avatar src={creator?.avatar} name={creator?.name || e.title} size={38} />}
        title={e.title}
        badge={!pending && <Badge color={e.reviewDecision === 'approved' ? 'green' : 'rose'} size="xs">{e.reviewDecision}</Badge>}
        subtitle={<>
          <span className="flex items-center gap-1.5"><Calendar size={12} />{fmtDateLong(e.date)}</span>
          {e.venue && <span className="flex items-center gap-1.5"><MapPin size={12} />{e.venue}</span>}
          <span>by {creator?.name || e.organizedBy || 'Unknown'}</span>
        </>}
        actions={pending && <>
          <Btn variant="secondary" size="sm" icon={X} loading={busyKey === key} onClick={() => onRejectEvent(e)}>Reject</Btn>
          <Btn variant="primary" size="sm" icon={Check} loading={busyKey === key} onClick={() => onApproveEvent(e)}>Approve</Btn>
        </>}
      >
        <div className="space-y-4">
          {!pending && <DecisionLine decision={e.reviewDecision} by={e.reviewer?.name} at={e.reviewedAt} />}
          {e.description && <p className="text-[13px] text-slate-600 leading-relaxed whitespace-pre-line">{e.description}</p>}
          <dl className="grid sm:grid-cols-2 gap-x-6 gap-y-3">
            <Field label="Date & time">{fmtDateLong(e.date)}<br />{fmtTime(e.startTime)} – {fmtTime(e.endTime)}</Field>
            <Field label="Venue">{e.venue}{e.location ? <><br /><span className="text-slate-500">{e.location}</span></> : null}</Field>
            <Field label="Organization">{e.organizationId ? (orgName[e.organizationId] || `#${e.organizationId}`) : 'None - not under an organization'}</Field>
            <Field label="Capacity"><span className="inline-flex items-center gap-1.5"><Users size={12} className="text-slate-400" />{e.capacity || 'Unlimited'}</span></Field>
            <Field label="Pricing"><span className="inline-flex items-center gap-1.5"><Ticket size={12} className="text-slate-400" />{e.pricing === 'paid' ? `Paid - ₱${e.price}` : e.pricing === 'walk-in' ? 'Walk-in' : 'Free'}</span></Field>
            <Field label="Visibility">{e.isPrivate ? 'Private (invite link only)' : 'Public'}</Field>
            <Field label="Submitted"><span className="inline-flex items-center gap-1.5"><Clock3 size={12} className="text-slate-400" />{fmtDateTime(e.createdAt)}</span></Field>
            {e.tags?.length > 0 && <Field label="Tags">{e.tags.join(', ')}</Field>}
          </dl>

          <div className="rounded-xl bg-white border border-slate-200 p-3">
            <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-2">Created by</p>
            {creator ? (
              <div className="flex items-start gap-3">
                <Avatar src={creator.avatar} name={creator.name} size={40} />
                <div className="min-w-0 text-[13px]">
                  <p className="font-semibold text-slate-800">{creator.name}</p>
                  {creator.institution && <p className="text-[12px] text-slate-500 flex items-center gap-1.5"><GraduationCap size={12} />{creator.institution}</p>}
                  {creator.email && <p className="text-[12px] text-slate-500 flex items-center gap-1.5 break-all"><Mail size={12} />{creator.email}</p>}
                  {creator.contactNumber && <p className="text-[12px] text-slate-500 flex items-center gap-1.5"><Phone size={12} />{creator.contactNumber}</p>}
                </div>
              </div>
            ) : <p className="text-[12px] text-slate-400">No account on record for this event.</p>}
          </div>

          <Link to={`/organizer/events/${e.id}`} className="inline-flex items-center gap-1.5 text-[12px] font-semibold text-[#1a1a2e] hover:text-[var(--accent)]">Open the full event <ExternalLink size={11} /></Link>
        </div>
      </ApplicationCard>
    )
  }

  const organizerCard = (o) => {
    const key = `o${o.id}`
    const pending = o.approvalStatus === 'pending'
    const wantsNewOrg = !!o.requestedOrganizationName && !o.requestedOrganization
    const requestedName = o.requestedOrganization?.name || o.requestedOrganizationName
    const willCreate = wantsNewOrg && !skipCreateOrg[o.id]
    return (
      <ApplicationCard
        key={key}
        open={openKey === key}
        onToggle={() => setOpenKey(k => k === key ? null : key)}
        avatar={<Avatar src={o.avatar} name={o.name} size={38} />}
        title={o.name}
        badge={!pending && <Badge color={o.approvalStatus === 'approved' ? 'green' : 'rose'} size="xs">{o.approvalStatus}</Badge>}
        subtitle={<>
          <span className="flex items-center gap-1.5 break-all"><Mail size={12} />{o.email}</span>
          {o.contactNumber && <span className="flex items-center gap-1.5"><Phone size={12} />{o.contactNumber}</span>}
          {requestedName && <span className="flex items-center gap-1.5"><Building2 size={12} />{requestedName}</span>}
        </>}
        actions={pending && <>
          <Btn variant="secondary" size="sm" icon={X} loading={busyKey === key} onClick={() => onRejectOrganizer(o)}>Reject</Btn>
          <Btn variant="primary" size="sm" icon={Check} loading={busyKey === key} onClick={() => onApproveOrganizer(o)}>{willCreate ? 'Approve & create org' : 'Approve'}</Btn>
        </>}
      >
        <div className="space-y-4">
          {!pending && <DecisionLine decision={o.approvalStatus} by={o.approver?.name} at={o.approvedAt} />}
          <dl className="grid sm:grid-cols-2 gap-x-6 gap-y-3">
            <Field label="Email">
              <span className="break-all">{o.email}</span><br />
              {o.emailVerifiedAt
                ? <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600"><MailCheck size={12} />Verified</span>
                : <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-600"><MailWarning size={12} />Not verified yet</span>}
            </Field>
            <Field label="Contact number">{o.contactNumber || <span className="text-slate-400">Not provided</span>}</Field>
            <Field label="Institution">{o.institution || <span className="text-slate-400">Not provided</span>}</Field>
            <Field label="Applied">{fmtDateTime(o.createdAt)}</Field>
            <Field label="Organization">
              {requestedName
                ? <>{requestedName}{wantsNewOrg && <span className="text-slate-400"> (new - not created yet)</span>}</>
                : <span className="text-slate-400">None requested</span>}
            </Field>
            {wantsNewOrg && <Field label="Organization address (for verification)">{o.requestedOrganizationAddress || <span className="text-slate-400">Not provided</span>}</Field>}
          </dl>

          {pending && wantsNewOrg && (
            <label className="flex items-start gap-2.5 rounded-xl bg-white border border-slate-200 p-3 cursor-pointer">
              <input type="checkbox" checked={willCreate} onChange={e => setSkipCreateOrg(s => ({ ...s, [o.id]: !e.target.checked }))} className="mt-0.5 accent-[#1a1a2e]" />
              <span className="text-[12px] text-slate-600"><b className="text-slate-800">Create "{o.requestedOrganizationName}" when approving</b> and make {o.name} its owner. Untick to approve the account only - you can create the organization later from Organizations.</span>
            </label>
          )}
        </div>
      </ApplicationCard>
    )
  }

  const renderList = () => {
    if (loading) return <p className="text-center text-slate-400 text-[13px] py-14">Loading…</p>
    if (items.length === 0) {
      const Icon = tab === 'events' ? Hourglass : UserCog
      const what = tab === 'events' ? 'events' : 'organizer applications'
      return (
        <Card className="p-14 text-center">
          <Icon size={32} className="mx-auto text-slate-300 mb-2" />
          <p className="text-[13px] text-slate-400">{view === 'pending' ? `No ${what} awaiting approval.` : `No ${view} ${what} yet.`}</p>
        </Card>
      )
    }
    const render = tab === 'events' ? eventCard : organizerCard
    if (view === 'pending') return <div className="space-y-3">{items.map(render)}</div>

    // Decided items read as a timeline: newest decision first, split by month.
    const dateKey = tab === 'events' ? 'reviewedAt' : 'approvedAt'
    return (
      <div className="space-y-6">
        {groupByMonth(items, dateKey).map(g => (
          <section key={g.label}>
            <div className="flex items-center gap-3 mb-2.5">
              <p className="text-[11px] font-bold text-slate-400 uppercase tracking-wide">{g.label}</p>
              <span className="text-[11px] text-slate-400">{g.items.length} {view}</span>
              <div className="h-px flex-1 bg-slate-200" />
            </div>
            <div className="space-y-3">{g.items.map(render)}</div>
          </section>
        ))}
      </div>
    )
  }

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-extrabold">Approvals</h1>
        <p className="text-[13px] text-slate-500">Applications for events and organizer accounts - what's waiting, and everything decided so far</p>
      </div>

      <div className="flex gap-1 p-1 rounded-xl bg-slate-100 w-fit">
        {TABS.map(([k, label]) => (
          <button key={k} onClick={() => { setTab(k); setOpenKey(null) }} className={cn('px-4 py-2 rounded-lg text-[13px] font-semibold transition-all', tab === k ? 'bg-white shadow-sm text-slate-800' : 'text-slate-500')}>
            {label}{lists[k].pending.length > 0 ? ` (${lists[k].pending.length})` : ''}
          </button>
        ))}
      </div>

      <div className="flex flex-wrap gap-2">
        {VIEWS.map(([k, label]) => (
          <button key={k} onClick={() => { setView(k); setOpenKey(null) }} className={cn('px-3.5 py-1.5 rounded-full text-[12px] font-semibold transition-all', view === k ? 'bg-[#1a1a2e] text-white' : 'bg-white border border-slate-200 text-slate-600 hover:border-slate-300')}>
            {label} <span className={view === k ? 'text-white/70' : 'text-slate-400'}>{lists[tab][k].length}</span>
          </button>
        ))}
      </div>

      {renderList()}
    </div>
  )
}
