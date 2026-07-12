import { useState } from 'react'
import { Users, UserPlus, Check, Clock } from 'lucide-react'
import { Card, Btn } from '../ui'
import { useApp } from '../../context/AppContext'
import { useFellowAttendees } from '../../hooks/useApi'
import { sendConnectionRequest, acceptConnection } from '../../api/resources'

// Discovery surface for real connections (request/accept) - only shown to
// people who are themselves registered for this event (server-enforced).
export default function FellowAttendees({ eventId }) {
  const { addToast } = useApp()
  const { data, loading, refetch } = useFellowAttendees(eventId)
  const attendees = data || []
  const [busyId, setBusyId] = useState(null)

  if (loading || attendees.length === 0) return null

  const connect = async (userId) => {
    setBusyId(userId)
    try {
      await sendConnectionRequest(userId)
      addToast('Connection request sent', 'success')
      refetch()
    } catch (err) {
      addToast(err.message || 'Failed to send request', 'error')
    } finally {
      setBusyId(null)
    }
  }

  const accept = async (connectionId, userId) => {
    setBusyId(userId)
    try {
      await acceptConnection(connectionId)
      addToast('Connected!', 'success')
      refetch()
    } catch (err) {
      addToast(err.message || 'Failed to accept', 'error')
    } finally {
      setBusyId(null)
    }
  }

  return (
    <Card className="p-5 text-left mt-4">
      <p className="text-[12px] font-bold text-slate-700 uppercase tracking-wide mb-3 flex items-center gap-1.5"><Users size={13} />Fellow attendees</p>
      <div className="space-y-2">
        {attendees.map(a => (
          <div key={a.id} className="flex items-center justify-between gap-2">
            <div className="flex items-center gap-2.5 min-w-0">
              <div className="w-8 h-8 rounded-full overflow-hidden bg-slate-100 flex items-center justify-center flex-shrink-0 text-[12px] font-bold text-slate-500">
                {a.avatar ? <img src={a.avatar} alt="" className="w-full h-full object-cover" /> : a.name?.[0]}
              </div>
              <span className="text-[13px] font-medium text-slate-700 truncate">{a.name}</span>
            </div>
            {a.connectionStatus === 'connected' ? (
              <span className="text-[11px] font-semibold text-emerald-600 flex items-center gap-1 flex-shrink-0"><Check size={12} />Connected</span>
            ) : a.connectionStatus === 'pendingSent' ? (
              <span className="text-[11px] font-semibold text-slate-400 flex items-center gap-1 flex-shrink-0"><Clock size={12} />Pending</span>
            ) : a.connectionStatus === 'pendingReceived' ? (
              <Btn size="sm" variant="secondary" loading={busyId === a.id} onClick={() => accept(a.connectionId, a.id)}>Accept</Btn>
            ) : (
              <Btn size="sm" variant="secondary" icon={UserPlus} loading={busyId === a.id} onClick={() => connect(a.id)}>Connect</Btn>
            )}
          </div>
        ))}
      </div>
    </Card>
  )
}
