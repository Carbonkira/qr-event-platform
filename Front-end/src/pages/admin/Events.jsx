import { Link } from 'react-router-dom'
import { Calendar } from 'lucide-react'
import { Card, Badge, PriceTag } from '../../components/ui'
import { useAdminEvents } from '../../hooks/useApi'
import { fmtDate } from '../../lib/utils'

const STATUS_COLOR = { draft: 'slate', pending: 'amber', approved: 'green', rejected: 'rose', completed: 'slate' }

export default function Events() {
  const { data, loading } = useAdminEvents()
  const events = data || []

  return (
    <div className="space-y-5">
      <div><h1 className="text-2xl font-extrabold">Events</h1><p className="text-[13px] text-slate-500">All events across your organization</p></div>

      <Card className="overflow-hidden">
        {loading ? (
          <p className="text-center text-slate-400 text-[13px] py-14">Loading events…</p>
        ) : events.length === 0 ? (
          <div className="text-center py-14">
            <Calendar size={32} className="mx-auto text-slate-300 mb-2" />
            <p className="text-[13px] text-slate-400">No events yet — create your first one.</p>
          </div>
        ) : (
          <table className="w-full text-[13px]">
            <thead>
              <tr className="border-b border-slate-100 text-left">
                <th className="px-5 py-3 font-semibold text-slate-500 text-[11px] uppercase tracking-wide">Event</th>
                <th className="px-3 py-3 font-semibold text-slate-500 text-[11px] uppercase tracking-wide">Type</th>
                <th className="px-3 py-3 font-semibold text-slate-500 text-[11px] uppercase tracking-wide">Date</th>
                <th className="px-3 py-3 font-semibold text-slate-500 text-[11px] uppercase tracking-wide">Status</th>
                <th className="px-3 py-3 font-semibold text-slate-500 text-[11px] uppercase tracking-wide">Capacity</th>
                <th className="px-5 py-3" />
              </tr>
            </thead>
            <tbody>
              {events.map(e => (
                <tr key={e.id} className="border-b border-slate-50 last:border-0 hover:bg-slate-50/60 transition-colors">
                  <td className="px-5 py-3.5">
                    <p className="font-semibold text-slate-800 truncate max-w-[240px]">{e.title}</p>
                    <div className="mt-0.5"><PriceTag event={e} /></div>
                  </td>
                  <td className="px-3 py-3.5 text-slate-500">{e.type}</td>
                  <td className="px-3 py-3.5 text-slate-500 whitespace-nowrap">{fmtDate(e.date)}</td>
                  <td className="px-3 py-3.5"><Badge color={STATUS_COLOR[e.status] || 'slate'} size="xs">{e.status}</Badge></td>
                  <td className="px-3 py-3.5 text-slate-500">{e.capacity}</td>
                  <td className="px-5 py-3.5 text-right">
                    <Link to={`/organizer/events/${e.id}`}><Btn variant="secondary" size="sm">Manage</Btn></Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
    </div>
  )
}
