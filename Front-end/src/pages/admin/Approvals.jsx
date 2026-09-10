import { useState } from 'react'
import { Calendar, MapPin, Mail, Check, X, Hourglass, ShieldAlert, UserCog, Building2 } from 'lucide-react'
import { Card, Btn } from '../../components/ui'
import { useAdminEvents, usePendingOrganizers } from '../../hooks/useApi'
import { approveEvent, rejectEvent, approveOrganizer, rejectOrganizer } from '../../api/resources'
import { useApp } from '../../context/AppContext'
import { cn, fmtDate } from '../../lib/utils'

const TABS = ['Events', 'Organizers']

export default function Approvals() {
  const { user, addToast } = useApp()
  const [tab, setTab] = useState('Events')
  const { data: eventsData, loading: eventsLoading, refetch: refetchEvents } = useAdminEvents()
  const { data: organizersData, loading: organizersLoading, refetch: refetchOrganizers } = usePendingOrganizers()
  const pendingEvents = (eventsData || []).filter(e => e.status === 'pending')
  const pendingOrganizers = organizersData || []

  // The nav item is already hidden for non-admins - this catches anyone who
  // hits the URL directly. The API enforces the same restriction either way.
  if (user?.role !== 'admin') {
    return (
      <Card className="p-14 text-center">
        <ShieldAlert size={32} className="mx-auto text-slate-300 mb-2" />
        <p className="text-[13px] font-semibold text-slate-600">Admins only</p>
        <p className="text-[13px] text-slate-400 mt-1">You don't have permission to review approvals.</p>
      </Card>
    )
  }

  const onApproveEvent = async (id) => {
    try { await approveEvent(id); refetchEvents(); addToast('Event approved!', 'success') }
    catch (err) { addToast(err.message || 'Failed to approve event', 'error') }
  }
  const onRejectEvent = async (id) => {
    try { await rejectEvent(id); refetchEvents(); addToast('Event rejected', 'info') }
    catch (err) { addToast(err.message || 'Failed to reject event', 'error') }
  }
  const onApproveOrganizer = async (id) => {
    try { await approveOrganizer(id); refetchOrganizers(); addToast('Organizer approved!', 'success') }
    catch (err) { addToast(err.message || 'Failed to approve organizer', 'error') }
  }
  const onRejectOrganizer = async (id) => {
    try { await rejectOrganizer(id); refetchOrganizers(); addToast('Organizer rejected', 'info') }
    catch (err) { addToast(err.message || 'Failed to reject organizer', 'error') }
  }

  return (
    <div className="space-y-5">
      <div><h1 className="text-2xl font-extrabold">Approvals</h1><p className="text-[13px] text-slate-500">Events and organizer accounts awaiting review</p></div>

      <div className="flex gap-1 p-1 rounded-xl bg-slate-100 w-fit">
        {TABS.map(t => (
          <button key={t} onClick={() => setTab(t)} className={cn('px-4 py-2 rounded-lg text-[13px] font-semibold transition-all', tab === t ? 'bg-white shadow-sm text-slate-800' : 'text-slate-500')}>
            {t}{t === 'Organizers' && pendingOrganizers.length > 0 ? ` (${pendingOrganizers.length})` : ''}
          </button>
        ))}
      </div>

      {tab === 'Events' && (
        eventsLoading ? (
          <p className="text-center text-slate-400 text-[13px] py-14">Loading…</p>
        ) : pendingEvents.length === 0 ? (
          <Card className="p-14 text-center">
            <Hourglass size={32} className="mx-auto text-slate-300 mb-2" />
            <p className="text-[13px] text-slate-400">No events awaiting approval.</p>
          </Card>
        ) : (
          <div className="space-y-3">
            {pendingEvents.map(e => (
              <Card key={e.id} className="p-4 flex items-center justify-between gap-4">
                <div className="min-w-0">
                  <p className="font-bold text-[14px] text-slate-800 truncate">{e.title}</p>
                  <div className="mt-1 flex items-center gap-4 text-[12px] text-slate-500">
                    <span className="flex items-center gap-1.5"><Calendar size={12} />{fmtDate(e.date)}</span>
                    <span className="flex items-center gap-1.5"><MapPin size={12} />{e.venue}</span>
                    <span>by {e.organizedBy}</span>
                  </div>
                </div>
                <div className="flex gap-2 flex-shrink-0">
                  <Btn variant="secondary" size="sm" icon={X} onClick={() => onRejectEvent(e.id)}>Reject</Btn>
                  <Btn variant="primary" size="sm" icon={Check} onClick={() => onApproveEvent(e.id)}>Approve</Btn>
                </div>
              </Card>
            ))}
          </div>
        )
      )}

      {tab === 'Organizers' && (
        organizersLoading ? (
          <p className="text-center text-slate-400 text-[13px] py-14">Loading…</p>
        ) : pendingOrganizers.length === 0 ? (
          <Card className="p-14 text-center">
            <UserCog size={32} className="mx-auto text-slate-300 mb-2" />
            <p className="text-[13px] text-slate-400">No organizer accounts awaiting approval.</p>
          </Card>
        ) : (
          <div className="space-y-3">
            {pendingOrganizers.map(o => (
              <Card key={o.id} className="p-4 flex items-center justify-between gap-4">
                <div className="min-w-0">
                  <p className="font-bold text-[14px] text-slate-800 truncate">{o.name}</p>
                  <div className="mt-1 flex items-center gap-4 text-[12px] text-slate-500">
                    <span className="flex items-center gap-1.5"><Mail size={12} />{o.email}</span>
                    {o.institution && <span>{o.institution}</span>}
                    <span>Signed up {fmtDate(o.createdAt)}</span>
                  </div>
                  {o.requestedOrganization && (
                    <p className="mt-1 text-[12px] text-slate-500 flex items-center gap-1.5"><Building2 size={12} />Requested to join <span className="font-semibold text-slate-700">{o.requestedOrganization.name}</span></p>
                  )}
                </div>
                <div className="flex gap-2 flex-shrink-0">
                  <Btn variant="secondary" size="sm" icon={X} onClick={() => onRejectOrganizer(o.id)}>Reject</Btn>
                  <Btn variant="primary" size="sm" icon={Check} onClick={() => onApproveOrganizer(o.id)}>Approve</Btn>
                </div>
              </Card>
            ))}
          </div>
        )
      )}
    </div>
  )
}
