import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import {
  ArrowLeft, Lock, Instagram, Linkedin, Facebook, Twitter, Globe, Briefcase, Award,
  MapPin, Shield, ExternalLink, Ticket, UserCheck, CalendarPlus, Share2, CheckCircle2,
} from 'lucide-react'
import { Btn, Card, Badge, PriceTag } from '../../components/ui'
import EventMap from '../../components/shared/EventMap'
import { useApp } from '../../context/AppContext'
import { useEvent, useMyRegistrations } from '../../hooks/useApi'
import { fmtDateLong, fmtTime, monthDay, downloadICS, googleCalUrl, shareEvent } from '../../lib/utils'

const SOC = [['instagram', Instagram], ['linkedin', Linkedin], ['facebook', Facebook], ['twitter', Twitter], ['website', Globe]]

export default function EventDetail() {
  const { slug } = useParams()
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const { user, addToast: toast } = useApp()
  const { data: event, loading } = useEvent(slug)
  // Was fetching /events/:id/registrations for a count - that endpoint is
  // organizer-only, so an anonymous or non-organizer visitor silently got
  // 0 back regardless of real attendance. The event itself now carries its
  // own confirmed-registration count (see EventController::show).
  const registeredCount = event?.registrationsCount ?? 0
  // This page always showed "Register" even to someone who already had a
  // pass - reported live: close the tab, come back, and it looks like the
  // site "forgot" you registered (harmless server-side - store() just
  // returns the existing registration - but confusing since nothing here
  // said you already had one).
  const { data: myRegs } = useMyRegistrations(!!user)
  const myRegistration = (myRegs || []).find(r => r.event?.id === event?.id)

  if (loading) return <div className="text-center py-20 text-slate-400 text-[13px]">Loading event…</div>
  if (!event) return <div className="text-center py-20 text-slate-500">Event not found.</div>

  // Private event gating: require matching access token (passed via ?access=)
  if (event.isPrivate && searchParams.get('access') !== event.privateLink) {
    return (
      <div className="max-w-md mx-auto px-5 py-16 text-center">
        <Card className="p-8">
          <div className="w-16 h-16 rounded-2xl bg-rose-50 flex items-center justify-center mx-auto mb-4"><Lock size={30} className="text-[#e94560]" /></div>
          <h2 className="text-xl font-extrabold mb-1">Private Event</h2>
          <p className="text-[13px] text-slate-500 mb-6">This event is invite-only. You need the access link from the organizer to view and register.</p>
          <Btn variant="secondary" full icon={ArrowLeft} onClick={() => navigate('/')}>Back to Events</Btn>
        </Card>
      </div>
    )
  }

  const isFull = registeredCount >= event.capacity
  const isPast = event.status === 'completed' || new Date(event.date) < new Date(new Date().toDateString())

  return (
    <div className="max-w-3xl mx-auto px-5 py-6">
      <button onClick={() => navigate('/')} className="flex items-center gap-1.5 text-[13px] text-slate-500 hover:text-slate-800 mb-4 font-medium"><ArrowLeft size={15} />Events</button>

      <div className="rounded-2xl overflow-hidden bg-slate-100 aspect-[2/1] mb-5 relative">
        {event.image && <img src={event.image} alt={event.title} className="w-full h-full object-cover" onError={e => e.target.style.display = 'none'} />}
        <div className="absolute top-3 left-3 flex gap-2">
          <PriceTag event={event} />
          {event.isPrivate && <Badge color="rose"><Lock size={10} />Private</Badge>}
        </div>
      </div>

      <div className="grid md:grid-cols-[1fr_300px] gap-6 items-start">
        <div>
          <div className="flex items-center gap-2 mb-3"><Badge color="slate">{event.type}</Badge><Badge color="blue"><Briefcase size={10} />{event.industry}</Badge>{event.requiresCertificate && <Badge color="violet"><Award size={10} />Certificate</Badge>}</div>
          <h1 className="text-[26px] font-extrabold tracking-tight leading-tight mb-4">{event.title}</h1>

          <div className="space-y-3 mb-6">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-slate-100 flex flex-col items-center justify-center flex-shrink-0">
                <span className="text-[9px] font-bold text-slate-400 leading-none">{monthDay(event.date).month}</span>
                <span className="text-[15px] font-extrabold leading-none">{monthDay(event.date).day}</span>
              </div>
              <div><p className="text-[14px] font-semibold text-slate-800">{fmtDateLong(event.date)}</p><p className="text-[12px] text-slate-500">{fmtTime(event.startTime)} – {fmtTime(event.endTime)}</p></div>
            </div>
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center flex-shrink-0"><MapPin size={17} className="text-slate-500" /></div>
              <div><p className="text-[14px] font-semibold text-slate-800">{event.venue}</p><p className="text-[12px] text-slate-500">{event.location}</p></div>
            </div>
          </div>

          <div className="rounded-xl border border-slate-200 p-4 mb-6">
            <p className="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-2">Organized by</p>
            {event.organization ? (
              <Link to={`/org/${event.organization.slug}`} className="flex items-center gap-3 group">
                <div className="w-9 h-9 rounded-full overflow-hidden bg-gradient-to-br from-[#1a1a2e] to-[#e94560] flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                  {event.organization.logo ? <img src={event.organization.logo} alt="" className="w-full h-full object-cover" /> : event.organizedBy?.[0]}
                </div>
                <div className="flex-1"><p className="text-[14px] font-semibold text-slate-800 group-hover:text-[#e94560]">{event.organizedBy}</p></div>
              </Link>
            ) : (
              <div className="flex items-center gap-3">
                <div className="w-9 h-9 rounded-full bg-gradient-to-br from-[#1a1a2e] to-[#e94560] flex items-center justify-center text-white font-bold text-sm flex-shrink-0">{event.organizedBy?.[0]}</div>
                <div className="flex-1"><p className="text-[14px] font-semibold text-slate-800">{event.organizedBy}</p></div>
              </div>
            )}
            {Object.values(event.socials || {}).some(Boolean) && <div className="flex items-center gap-2 mt-3 pt-3 border-t border-slate-100">
              {SOC.map(([k, Icon]) => event.socials?.[k] ? <a key={k} href="#" onClick={e => e.preventDefault()} className="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-600 transition-all"><Icon size={14} /></a> : null)}
            </div>}
          </div>

          <div className="prose-sm">
            <p className="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-2">About this event</p>
            <p className="text-[14px] text-slate-600 leading-relaxed whitespace-pre-line">{event.description}</p>
          </div>

          {event.privacyPolicyUrl && <a href={event.privacyPolicyUrl} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 text-[12px] text-slate-500 hover:text-slate-800 mt-5 font-medium"><Shield size={12} />Privacy Policy <ExternalLink size={11} /></a>}
        </div>

        <div className="md:sticky md:top-20">
          <Card className="p-5 shadow-sm">
            {event.status === 'cancelled' ? <div className="text-center py-3"><Badge color="rose">This event has been cancelled</Badge></div> : isPast ? <div className="text-center py-3"><Badge color="slate">This event has ended</Badge></div> : (
              <>
                <div className="flex items-baseline justify-between mb-3">
                  <p className="text-[13px] font-semibold text-slate-500">Registration</p>
                  {event.pricing === 'paid' ? <span className="text-xl font-extrabold">₱{event.price}</span> : <span className="text-[15px] font-bold text-emerald-600">Free</span>}
                </div>
                <div className="mb-4">
                  <div className="flex justify-between text-[11px] mb-1.5"><span className="text-slate-500">{registeredCount} registered</span><span className="font-semibold">{event.capacity} cap</span></div>
                  <div className="h-1.5 rounded-full bg-slate-100"><div className="h-full rounded-full bg-[#1a1a2e] transition-all" style={{ width: `${Math.min(100, (registeredCount / event.capacity) * 100)}%` }} /></div>
                </div>
                {myRegistration ? (
                  <div className="space-y-2">
                    <div className="text-center py-2 rounded-xl bg-emerald-50 text-emerald-700 text-[13px] font-semibold flex items-center justify-center gap-1.5"><CheckCircle2 size={14} />You're registered</div>
                    <Btn variant="secondary" size="lg" full icon={Ticket} onClick={() => navigate(`/pass/${myRegistration.id}`)}>View your pass</Btn>
                  </div>
                ) : isFull ? <div className="text-center py-2 rounded-xl bg-amber-50 text-amber-700 text-[13px] font-semibold">Event is full</div> : (
                  <div className="space-y-2">
                    <Btn variant="accent" size="lg" full icon={Ticket} onClick={() => navigate(`/events/${event.slug}/register`)}>{event.pricing === 'paid' ? 'Register & Pay' : 'Register'}</Btn>
                    {event.allowWalkIns && <Btn variant="secondary" size="md" full icon={UserCheck} onClick={() => navigate(`/events/${event.slug}/register?walkIn=1`)}>Walk-in (skip form)</Btn>}
                  </div>
                )}
                {event.isPrivate && <p className="text-[10px] text-slate-400 text-center mt-3 flex items-center justify-center gap-1"><Lock size={10} />Private · invite-only link</p>}
                {!event.allowWalkIns && !isFull && <p className="text-[10px] text-slate-400 text-center mt-3">Walk-ins not accepted for this event</p>}
              </>
            )}
          </Card>

          <div className="mt-4">
            <p className="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-2">Location</p>
            <div className="rounded-2xl overflow-hidden aspect-video bg-slate-100 border border-slate-200">
              <EventMap event={event} />
            </div>
            <p className="text-[12px] text-slate-500 mt-2">{event.venue}, {event.location}</p>
          </div>

          <div className="mt-3 space-y-2">
            <div className="grid grid-cols-2 gap-2">
              <a href={googleCalUrl(event)} target="_blank" rel="noreferrer" className="flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-white border border-slate-200 hover:border-slate-300 text-[12px] font-semibold text-slate-700 transition-all"><CalendarPlus size={14} />Google</a>
              <button onClick={() => downloadICS(event)} className="flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-white border border-slate-200 hover:border-slate-300 text-[12px] font-semibold text-slate-700 transition-all"><CalendarPlus size={14} />.ics</button>
            </div>
            <button onClick={() => shareEvent(event, toast)} className="w-full flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-white border border-slate-200 hover:border-slate-300 text-[12px] font-semibold text-slate-700 transition-all"><Share2 size={14} />Share event</button>
          </div>
        </div>
      </div>
    </div>
  )
}
