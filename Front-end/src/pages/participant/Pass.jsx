import { useEffect, useState } from 'react'
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom'
import QRCode from 'qrcode'
import { ArrowLeft, Award, Calendar, MapPin, MessageSquare } from 'lucide-react'
import { Btn, Card } from '../../components/ui'
import FellowAttendees from '../../components/participant/FellowAttendees'
import { fmtDate, fmtTime } from '../../lib/utils'

export default function Pass() {
  const { regId } = useParams()
  const navigate = useNavigate()
  const location = useLocation()
  const registration = location.state
  const [qrDataUrl, setQrDataUrl] = useState(null)

  useEffect(() => {
    if (!registration?.qrCode) return
    QRCode.toDataURL(registration.qrCode, { width: 260, margin: 1, color: { dark: '#1a1a2e' } }).then(setQrDataUrl).catch(() => setQrDataUrl(null))
  }, [registration?.qrCode])

  if (!registration) {
    return (
      <div className="max-w-md mx-auto px-5 py-16 text-center">
        <Card className="p-8">
          <h2 className="text-xl font-extrabold mb-1">Pass not available</h2>
          <p className="text-[13px] text-slate-500 mb-6">We can't show your pass after a refresh from this link. Look it up by email instead.</p>
          <Link to="/find-pass"><Btn variant="secondary" full>Find my pass</Btn></Link>
        </Card>
      </div>
    )
  }

  const event = registration.event || registration
  const canGiveFeedback = registration.attended && !registration.feedbackSubmitted && (event.feedbackEnabled ?? true)

  return (
    <div className="max-w-sm mx-auto px-5 py-8 text-center">
      <button onClick={() => navigate('/')} className="flex items-center gap-1.5 text-[13px] text-slate-500 hover:text-slate-800 mb-4 font-medium mx-auto"><ArrowLeft size={15} />Events</button>
      <Card className="p-6 overflow-hidden">
        <div className="-mx-6 -mt-6 mb-5 h-1.5 bg-gradient-to-r from-[#1a1a2e] to-[#e94560]" />
        <p className="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1">QR Event Pass</p>
        <h3 className="font-extrabold text-[17px]">{registration.name}</h3>
        <p className="text-[12px] text-slate-500 mb-5">{event.title}</p>

        <div className="rounded-2xl bg-slate-50 border border-slate-200 p-4 mb-4">
          {qrDataUrl ? <img src={qrDataUrl} alt="QR pass" className="mx-auto w-[180px] h-[180px]" /> : <div className="w-[180px] h-[180px] mx-auto flex items-center justify-center text-slate-400 text-[12px]">Generating QR…</div>}
          <p className="text-[10px] font-mono text-slate-400 mt-2">{registration.qrCode}</p>
        </div>

        {(event.date || event.venue) && (
          <div className="rounded-xl bg-emerald-50 border border-emerald-100 p-3 text-left space-y-1.5 mb-4">
            {event.date && <div className="flex justify-between text-[11px]"><span className="text-emerald-600/70 flex items-center gap-1"><Calendar size={11} />Date</span><span className="font-semibold text-emerald-800 text-right ml-2">{fmtDate(event.date)}</span></div>}
            {event.startTime && <div className="flex justify-between text-[11px]"><span className="text-emerald-600/70">Time</span><span className="font-semibold text-emerald-800 text-right ml-2">{fmtTime(event.startTime)}</span></div>}
            {event.venue && <div className="flex justify-between text-[11px]"><span className="text-emerald-600/70 flex items-center gap-1"><MapPin size={11} />Venue</span><span className="font-semibold text-emerald-800 text-right ml-2">{event.venue}</span></div>}
            {registration.needsCertificate && <div className="pt-1.5 border-t border-emerald-100 flex items-center gap-1.5 text-[11px] text-violet-700 font-semibold"><Award size={11} />Certificate requested</div>}
          </div>
        )}

        {canGiveFeedback && (
          <Btn variant="accent" size="md" full icon={MessageSquare} onClick={() => navigate(`/feedback/${regId}`, { state: { ...registration, event } })}>Leave Feedback</Btn>
        )}
      </Card>

      {event.id && <FellowAttendees eventId={event.id} />}
    </div>
  )
}
