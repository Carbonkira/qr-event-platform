import { Link, useNavigate } from 'react-router-dom'
import { Ticket, CheckCircle2 } from 'lucide-react'
import { Card, Badge } from '../../components/ui'
import { useApp } from '../../context/AppContext'
import { useMyRegistrations } from '../../hooks/useApi'
import { fmtDate, fmtTime } from '../../lib/utils'

// The account-based counterpart to FindPass.jsx (which looks passes up by
// email for guests without an account) - every event this logged-in
// account has registered for, across any organizer.
export default function MyTickets() {
  const navigate = useNavigate()
  const { user, authReady } = useApp()
  const { data, loading } = useMyRegistrations(!!user)
  const registrations = data || []

  if (!authReady || loading) return <div className="text-center py-20 text-slate-400 text-[13px]">Loading…</div>

  if (!user) {
    return (
      <div className="max-w-md mx-auto px-5 py-16 text-center">
        <Card className="p-8">
          <h1 className="text-xl font-extrabold mb-1">Log in to see your tickets</h1>
          <p className="text-[13px] text-slate-500 mb-6">Every event you've registered for lives on your account.</p>
          <Link to="/login" className="font-semibold text-[#e94560]">Log in</Link>
        </Card>
      </div>
    )
  }

  return (
    <div className="max-w-md mx-auto px-5 py-10">
      <div className="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-4"><Ticket size={26} className="text-[#1a1a2e]" /></div>
      <h1 className="text-xl font-extrabold text-center mb-1">My Tickets</h1>
      <p className="text-[13px] text-slate-500 text-center mb-6">Every event registered under {user.email}.</p>

      {registrations.length === 0 && <Card className="p-8 text-center text-[13px] text-slate-400">No registrations yet. <Link to="/" className="font-semibold text-[#e94560]">Browse events</Link></Card>}

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
    </div>
  )
}
