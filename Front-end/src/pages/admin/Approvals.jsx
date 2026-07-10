import { Calendar, MapPin, Check, X, Hourglass, ShieldAlert } from 'lucide-react'
import { Card, Btn } from '../../components/ui'
import { useAdminEvents } from '../../hooks/useApi'
import { approveEvent, rejectEvent } from '../../api/resources'
import { useApp } from '../../context/AppContext'
import { fmtDate } from '../../lib/utils'

export default function Approvals() {
  const { user, addToast } = useApp()
  const { data, loading, refetch } = useAdminEvents()
  const pending = (data || []).filter(e => e.status === 'pending')

  // The nav item is already hidden for non-admins - this catches anyone who
  // hits the URL directly. The API enforces the same restriction either way.
  if (user?.role !== 'admin') {
    return (
      <Card className="p-14 text-center">
        <ShieldAlert size={32} className="mx-auto text-slate-300 mb-2" />
        <p className="text-[13px] font-semibold text-slate-600">Admins only</p>
        <p className="text-[13px] text-slate-400 mt-1">You don't have permission to review event approvals.</p>
      </Card>
    )
  }

  const onApprove = async (id) => {
    try { await approveEvent(id); refetch(); addToast('Event approved!', 'success') }
    catch (err) { addToast(err.message || 'Failed to approve event', 'error') }
  }
  const onReject = async (id) => {
    try { await rejectEvent(id); refetch(); addToast('Event rejected', 'info') }
    catch (err) { addToast(err.message || 'Failed to reject event', 'error') }
  }

  return (
    <div className="space-y-5">
      <div><h1 className="text-2xl font-extrabold">Approvals</h1><p className="text-[13px] text-slate-500">Events awaiting review before they go live</p></div>

      {loading ? (
        <p className="text-center text-slate-400 text-[13px] py-14">Loading…</p>
      ) : pending.length === 0 ? (
        <Card className="p-14 text-center">
          <Hourglass size={32} className="mx-auto text-slate-300 mb-2" />
          <p className="text-[13px] text-slate-400">No events awaiting approval.</p>
        </Card>
      ) : (
        <div className="space-y-3">
          {pending.map(e => (
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
                <Btn variant="secondary" size="sm" icon={X} onClick={() => onReject(e.id)}>Reject</Btn>
                <Btn variant="primary" size="sm" icon={Check} onClick={() => onApprove(e.id)}>Approve</Btn>
              </div>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}
