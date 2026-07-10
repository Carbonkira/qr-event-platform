import { Link, useLocation, useNavigate, useParams } from 'react-router-dom'
import { CheckCircle2, ListPlus, QrCode, CalendarPlus, Clock3 } from 'lucide-react'
import { Btn, Card } from '../../components/ui'
import { useEvent } from '../../hooks/useApi'
import { downloadICS, fmtDate, googleCalUrl } from '../../lib/utils'

export default function Confirm() {
  const { slug, regId } = useParams()
  const navigate = useNavigate()
  const location = useLocation()
  const registration = location.state
  const { data: fetchedEvent } = useEvent(slug)
  const event = registration?.event || fetchedEvent

  if (!registration) {
    return (
      <div className="max-w-md mx-auto px-5 py-16 text-center">
        <Card className="p-8">
          <h2 className="text-xl font-extrabold mb-1">Registration Confirmed</h2>
          <p className="text-[13px] text-slate-500 mb-6">Your pass details aren't available on this page after a refresh — view your pass instead.</p>
          <Link to="/find-pass"><Btn variant="secondary" full>View my pass</Btn></Link>
        </Card>
      </div>
    )
  }

  const rows = [
    ['Name', registration.name],
    ['Event', event?.title],
    ['Date', event ? fmtDate(event.date) : null],
    ['Pass', registration.qrCode],
    ...(registration.paymentRef ? [['Payment Ref', registration.paymentRef]] : []),
    ...(registration.needsCertificate ? [['Certificate', 'Requested ✓']] : []),
  ].filter(([, v]) => v)

  return (
    <div className="max-w-md mx-auto px-5 py-10 text-center">
      <Card className="p-8">
        {registration.waitlisted ? (
          <>
            <div className="w-16 h-16 rounded-2xl bg-violet-50 flex items-center justify-center mx-auto mb-4"><ListPlus size={32} className="text-violet-600" /></div>
            <h1 className="text-xl font-extrabold mb-1">You're on the waitlist</h1>
            <p className="text-[13px] text-slate-500 mb-6">This event is full. We'll email you if a spot opens up — hold onto your pass below.</p>
          </>
        ) : (
          <>
            <div className="w-16 h-16 rounded-2xl bg-emerald-50 flex items-center justify-center mx-auto mb-4"><CheckCircle2 size={32} className="text-emerald-500" /></div>
            <h1 className="text-xl font-extrabold mb-1">You're in!</h1>
            <p className="text-[13px] text-slate-500 mb-6">{registration.isWalkIn ? 'Walk-in registered & checked in.' : 'Your spot is confirmed.'}</p>
          </>
        )}

        {registration.paymentStatus === 'pending' && (
          <div className="rounded-xl bg-amber-50 border border-amber-200 p-3 mb-4 flex items-start gap-2 text-left">
            <Clock3 size={15} className="text-amber-600 flex-shrink-0 mt-0.5" />
            <p className="text-[12px] text-amber-700">Payment under review. Your pass works now — the organizer will confirm your payment shortly.</p>
          </div>
        )}

        <div className="rounded-xl bg-slate-50 border border-slate-200 p-4 text-left space-y-2 mb-6">
          {rows.map(([k, v]) => (
            <div key={k} className="flex justify-between text-[12px]"><span className="text-slate-400">{k}</span><span className="font-semibold text-slate-700 text-right ml-3">{v}</span></div>
          ))}
        </div>

        <div className="space-y-2">
          <Btn variant="primary" size="lg" full icon={QrCode} onClick={() => navigate(`/pass/${regId}`, { state: { ...registration, event } })}>View QR Pass</Btn>
          {event && (
            <div className="grid grid-cols-2 gap-2">
              <a href={googleCalUrl(event)} target="_blank" rel="noreferrer" className="flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-white border border-slate-200 hover:border-slate-300 text-[12px] font-semibold text-slate-700 transition-all"><CalendarPlus size={14} />Google Cal</a>
              <button onClick={() => downloadICS(event)} className="flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-white border border-slate-200 hover:border-slate-300 text-[12px] font-semibold text-slate-700 transition-all"><CalendarPlus size={14} />.ics file</button>
            </div>
          )}
          <Link to="/"><Btn variant="ghost" full>Back to Events</Btn></Link>
        </div>
      </Card>
    </div>
  )
}
