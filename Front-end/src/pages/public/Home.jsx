import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Calendar, Clock, MapPin, Users, Search, MapPinned, Sparkles, LocateFixed } from 'lucide-react'
import { Card, Badge, PriceTag } from '../../components/ui'
import LandingHero from '../../components/public/LandingHero'
import { cn, fmtDate, fmtTime, monthDay } from '../../lib/utils'
import { usePublicEvents } from '../../hooks/useApi'
import { useApp } from '../../context/AppContext'

export default function Home() {
  const [q, setQ] = useState('')
  const [filter, setFilter] = useState('upcoming')
  const { user, coords, place, locationStatus: geoStatus } = useApp()

  const { data: all, loading } = usePublicEvents(coords ? { lat: coords.lat, lng: coords.lng } : {})
  const events0 = all || []
  const now = new Date()

  const query = q.trim().toLowerCase()
  let events = events0.filter(e => {
    if (!query) return true
    const haystack = [e.title, e.description, e.venue, e.location, e.organizedBy, e.industry, e.type, ...(e.tags || [])]
      .filter(Boolean).join(' ').toLowerCase()
    return haystack.includes(query)
  })
  if (filter === 'upcoming') events = events.filter(e => new Date(e.date) >= new Date(now.toDateString()))
  events = events.sort((a, b) => new Date(a.date) - new Date(b.date))

  const groups = {}
  events.forEach(e => { (groups[e.date] = groups[e.date] || []).push(e) })
  const suggested = events0.slice(0, 3)

  return (
    <div className="max-w-5xl mx-auto px-5 py-8">
      {!user && <LandingHero events={events0} onSelectCategory={setQ} />}

      <div id="event-listing" className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-7 scroll-mt-6">
        <div>
          <h1 className="text-2xl font-extrabold tracking-tight">Events</h1>
          {geoStatus === 'granted' && place?.city ? (
            <p className="text-[13px] text-slate-500 mt-0.5 flex items-center gap-1.5"><LocateFixed size={13} className="text-[#0f9d8f]" />Sorted by distance from {place.city}</p>
          ) : geoStatus === 'granted' ? (
            <p className="text-[13px] text-slate-500 mt-0.5 flex items-center gap-1.5"><LocateFixed size={13} className="text-[#0f9d8f]" />Sorted by distance from you</p>
          ) : (
            <p className="text-[13px] text-slate-500 mt-0.5 flex items-center gap-1.5"><MapPinned size={13} />Discover upcoming events</p>
          )}
        </div>
        <div className="flex items-center gap-2">
          <div className="relative flex-1 sm:flex-none">
            <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input value={q} onChange={e => setQ(e.target.value)} placeholder="Search events, venues, topics…" className="w-full sm:w-64 pl-9 pr-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm outline-none focus:border-[#1a1a2e] focus:ring-2 focus:ring-slate-100" />
          </div>
        </div>
      </div>

      <div className="flex items-center gap-2 mb-7">
        {[['upcoming', 'Upcoming'], ['all', 'All Events']].map(([k, l]) => (
          <button key={k} onClick={() => setFilter(k)} className={cn('px-4 py-2 rounded-full text-[13px] font-semibold transition-all', filter === k ? 'bg-[#1a1a2e] text-white' : 'bg-white border border-slate-200 text-slate-600 hover:border-slate-300')}>{l}</button>
        ))}
      </div>

      {!q && filter === 'upcoming' && suggested.length > 0 && (
        <div className="mb-9">
          <div className="flex items-center gap-2 mb-3">
            <Sparkles size={15} className="text-[#e94560]" />
            <p className="text-[13px] font-bold text-slate-700">Suggested for you</p>
            <span className="text-[11px] text-slate-400">· {geoStatus === 'granted' ? 'nearest to you' : 'recently added'}</span>
          </div>
          <div className="grid sm:grid-cols-3 gap-4">
            {suggested.map(e => <SuggestCard key={e.id} event={e} />)}
          </div>
        </div>
      )}

      {loading ? (
        <div className="text-center py-20 text-slate-400 text-[13px]">Loading events…</div>
      ) : Object.keys(groups).length === 0 ? (
        <div className="text-center py-20">
          <Calendar size={40} className="mx-auto text-slate-300 mb-3" />
          <p className="font-semibold text-slate-500">No events found</p>
          <p className="text-[13px] text-slate-400 mt-1">Try a different search or check back soon.</p>
        </div>
      ) : (
        <div className="space-y-8">
          {Object.entries(groups).map(([date, evs]) => {
            const md = monthDay(date)
            return (
              <div key={date} className="flex gap-5">
                <div className="flex flex-col items-center pt-1 w-14 flex-shrink-0">
                  <span className="text-[11px] font-bold text-slate-400">{md.month}</span>
                  <span className="text-2xl font-extrabold text-slate-800 leading-none">{md.day}</span>
                  <span className="text-[11px] text-slate-400 mt-0.5">{md.weekday}</span>
                  <div className="w-px flex-1 bg-slate-200 mt-3" />
                </div>
                <div className="flex-1 space-y-4 pb-2">
                  {evs.map(e => <EventRow key={e.id} event={e} />)}
                </div>
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}

export function SuggestCard({ event }) {
  return (
    <Link to={`/events/${event.slug}`}>
      <Card hover className="overflow-hidden">
        <div className="h-28 bg-slate-100 relative">
          {event.image && <img src={event.image} alt="" className="w-full h-full object-cover" onError={e => e.target.style.display = 'none'} />}
          <div className="absolute top-2 left-2"><PriceTag event={event} /></div>
        </div>
        <div className="p-3">
          <p className="text-[13px] font-bold text-slate-800 line-clamp-2 leading-snug">{event.title}</p>
          <p className="text-[11px] text-slate-500 mt-1.5 flex items-center gap-1"><Calendar size={10} />{fmtDate(event.date)} · {fmtTime(event.startTime)}</p>
          {event.distanceKm != null && <p className="text-[11px] text-[#0f9d8f] font-semibold mt-1 flex items-center gap-1"><LocateFixed size={10} />{event.distanceKm} km away</p>}
        </div>
      </Card>
    </Link>
  )
}

function EventRow({ event }) {
  return (
    <Link to={`/events/${event.slug}`}>
      <Card hover className="p-4 flex gap-4">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 mb-1.5">
            <PriceTag event={event} />
            <Badge color="slate" size="xs">{event.type}</Badge>
          </div>
          <h3 className="font-bold text-[16px] text-slate-800 leading-snug line-clamp-2">{event.title}</h3>
          <div className="mt-2 space-y-1">
            <p className="text-[12px] text-slate-500 flex items-center gap-1.5"><Clock size={12} />{fmtTime(event.startTime)} – {fmtTime(event.endTime)}</p>
            <p className="text-[12px] text-slate-500 flex items-center gap-1.5"><MapPin size={12} />{event.venue}{event.distanceKm != null && <span className="text-[#0f9d8f] font-semibold"> · {event.distanceKm} km away</span>}</p>
            <p className="text-[12px] text-slate-400 flex items-center gap-1.5"><Users size={12} />{event.registrations_count ?? 0} going · by {event.organizedBy}</p>
          </div>
        </div>
        <div className="w-28 sm:w-36 flex-shrink-0 rounded-xl overflow-hidden bg-slate-100 self-stretch min-h-[96px]">
          {event.image && <img src={event.image} alt="" className="w-full h-full object-cover" onError={e => e.target.style.display = 'none'} />}
        </div>
      </Card>
    </Link>
  )
}
