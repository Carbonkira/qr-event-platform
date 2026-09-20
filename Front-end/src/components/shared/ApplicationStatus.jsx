import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { Hourglass, XCircle, CheckCircle2, Circle, Phone, Building2, Mail, RefreshCw, MessageSquareText } from 'lucide-react'
import { Badge, Btn, Card } from '../ui'
import { useApp } from '../../context/AppContext'
import { useOrgList } from '../../hooks/useApi'
import { cn, fmtDateTime } from '../../lib/utils'

// Where an organizer's application stands, shown to the applicant themselves.
// Admins review every organizer account before it can host anything (see
// Backend's EnsureOrganizerApproved) - until now the only sign of that was the
// email, and the app itself just looked broken. Approved organizers and admins
// have no application to show, so this is null for them.
export function applicationStatusOf(user, accountType) {
  if (accountType !== 'organizer' || !user || user.role === 'admin') return null
  return user.approvalStatus === 'pending' || user.approvalStatus === 'rejected' ? user.approvalStatus : null
}

// Says so when a check finds the admin has decided. False while it's still pending.
function announceDecision(fresh, addToast) {
  if (fresh?.approvalStatus === 'approved') addToast('Your organizer application was approved - you can host events now.', 'success', 6000)
  else if (fresh?.approvalStatus === 'rejected') addToast('Your organizer application was reviewed - the details are on My Events.', 'info', 6000)
  else return false
  return true
}

// The admin decides in their own session, so a pending applicant's tab would
// keep saying "under review" until they logged out and back in. Re-check
// quietly every minute (and whenever they come back to the tab); once it
// flips, the rest of the app unlocks on its own.
export function useApplicationStatusWatch(status) {
  const { refreshUser, addToast } = useApp()
  useEffect(() => {
    if (status !== 'pending') return undefined
    const check = async () => {
      try {
        announceDecision(await refreshUser(), addToast)
      } catch { /* offline, or signed out - try again next time */ }
    }
    const timer = setInterval(check, 60_000)
    const onVisible = () => { if (document.visibilityState === 'visible') check() }
    document.addEventListener('visibilitychange', onVisible)
    return () => { clearInterval(timer); document.removeEventListener('visibilitychange', onVisible) }
  }, [status, refreshUser, addToast])
}

// The strip under the header, on every page - one line and a way to the details.
export function ApplicationStatusBanner({ status }) {
  const rejected = status === 'rejected'
  return (
    <div className={cn('px-5 py-2.5 border-b flex items-center justify-center gap-x-3 gap-y-1 flex-wrap', rejected ? 'bg-rose-50 border-rose-200' : 'bg-sky-50 border-sky-200')}>
      <p className={cn('text-[12px] flex items-center gap-1.5', rejected ? 'text-rose-700' : 'text-sky-800')}>
        {rejected ? <XCircle size={13} className="flex-shrink-0" /> : <Hourglass size={13} className="flex-shrink-0" />}
        {rejected
          ? "Your organizer application wasn't approved, so you can't host events."
          : "Your organizer application is being reviewed - you can't host events until an admin approves it."}
      </p>
      <Link to="/my-events" className={cn('text-[12px] font-semibold underline', rejected ? 'text-rose-800 hover:text-rose-900' : 'text-sky-900 hover:text-sky-950')}>View status</Link>
    </div>
  )
}

const STEP_ICON = {
  done: <CheckCircle2 size={18} className="text-emerald-500" />,
  current: <Hourglass size={18} className="text-sky-500" />,
  failed: <XCircle size={18} className="text-rose-500" />,
  todo: <Circle size={18} className="text-slate-300" />,
}

function Field({ icon: Icon, label, children }) {
  return (
    <div>
      <dt className="text-[10px] font-bold text-slate-400 uppercase tracking-wide flex items-center gap-1.5"><Icon size={11} />{label}</dt>
      <dd className="text-[13px] text-slate-700 mt-0.5 break-words">{children}</dd>
    </div>
  )
}

// The full picture, on My Events: what was asked for, how far along it is,
// and - if it was turned down - why, and who to talk to.
export function ApplicationStatusCard() {
  const { user, refreshUser, addToast } = useApp()
  const { data: orgsData } = useOrgList()
  const [checking, setChecking] = useState(false)

  const status = applicationStatusOf(user, 'organizer')
  if (!status) return null
  const rejected = status === 'rejected'
  const verified = !!user.emailVerifiedAt
  const number = user.adminContactNumber
  const requestedOrg = user.requestedOrganizationName || (orgsData || []).find(o => o.id === user.requestedOrganizationId)?.name

  const steps = [
    { state: 'done', title: 'Application submitted', detail: fmtDateTime(user.createdAt) },
    verified
      ? { state: 'done', title: 'Email verified', detail: user.email }
      : { state: 'current', title: 'Verify your email', detail: `Click the link we sent to ${user.email}. Most actions stay locked until you do.` },
    rejected
      ? { state: 'done', title: 'Reviewed by an admin', detail: user.approvedAt ? fmtDateTime(user.approvedAt) : null }
      : { state: 'current', title: 'Admin review', detail: 'In progress - an admin looks at every application before an account can host events.' },
    rejected
      ? { state: 'failed', title: 'Not approved', detail: null }
      : { state: 'todo', title: 'Decision', detail: `We'll email ${user.email} as soon as there is one.` },
  ]

  const check = async () => {
    setChecking(true)
    try {
      if (!announceDecision(await refreshUser(), addToast)) addToast('Still under review - nothing has changed yet.', 'info')
    } catch (err) {
      addToast(err.message || 'Could not check right now', 'error')
    } finally {
      setChecking(false)
    }
  }

  return (
    <Card className="p-6">
      <div className="flex items-start gap-3.5">
        <div className={cn('w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0', rejected ? 'bg-rose-50 text-rose-600' : 'bg-sky-50 text-sky-600')}>
          {rejected ? <XCircle size={22} /> : <Hourglass size={22} />}
        </div>
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-2 flex-wrap">
            <h2 className="text-[17px] font-extrabold tracking-tight">{rejected ? "Your organizer application wasn't approved" : 'Your organizer application is under review'}</h2>
            <Badge color={rejected ? 'rose' : 'blue'} size="xs">{rejected ? 'Not approved' : 'Pending review'}</Badge>
          </div>
          <p className="text-[13px] text-slate-500 mt-1">
            {rejected
              ? "An admin reviewed your application and didn't approve it, so this account can't host events."
              : "You can browse and register for events in the meantime. Hosting unlocks the moment an admin approves you - you don't need to do anything else."}
          </p>
        </div>
      </div>

      {rejected && user.rejectionReason && (
        <div className="mt-5 rounded-xl bg-rose-50/60 border border-rose-100 p-4">
          <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1 flex items-center gap-1.5"><MessageSquareText size={11} />Reason from the admin</p>
          <p className="text-[13px] text-slate-700 whitespace-pre-line">{user.rejectionReason}</p>
        </div>
      )}

      <ol className="mt-6 space-y-4">
        {steps.map((s, i) => (
          <li key={s.title} className="flex gap-3">
            <div className="flex flex-col items-center">
              <span className="mt-0.5">{STEP_ICON[s.state]}</span>
              {i < steps.length - 1 && <span className="w-px flex-1 bg-slate-200 mt-1" />}
            </div>
            <div className="pb-1 min-w-0">
              <p className={cn('text-[13px] font-semibold', s.state === 'todo' ? 'text-slate-400' : s.state === 'failed' ? 'text-rose-700' : 'text-slate-800')}>{s.title}</p>
              {s.detail && <p className="text-[12px] text-slate-500 break-words">{s.detail}</p>}
            </div>
          </li>
        ))}
      </ol>

      <dl className="mt-6 pt-5 border-t border-slate-100 grid sm:grid-cols-3 gap-x-6 gap-y-3">
        <Field icon={Phone} label="Your contact number">{user.contactNumber || <span className="text-slate-400">Not provided</span>}</Field>
        <Field icon={Building2} label="Organization">{requestedOrg || <span className="text-slate-400">None requested</span>}</Field>
        <Field icon={Mail} label="Applied with">{user.email}</Field>
      </dl>

      <div className="mt-6 flex flex-wrap items-center justify-between gap-3">
        <p className="text-[12px] text-slate-500">
          {number
            ? <>{rejected ? 'Think it should be reconsidered? ' : 'Questions? '}Contact the admin at <a href={`tel:${number.replace(/[^\d+]/g, '')}`} className="font-semibold text-slate-700 hover:text-[var(--accent)]">{number}</a>.</>
            : rejected ? 'Think it should be reconsidered? Reply to the email we sent you.' : 'Questions? Reply to the email we sent you.'}
        </p>
        {!rejected && <Btn variant="secondary" size="sm" icon={RefreshCw} loading={checking} onClick={check}>Check status</Btn>}
      </div>
    </Card>
  )
}
