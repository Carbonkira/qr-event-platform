import { Link } from 'react-router-dom'
import { Building2, Calendar } from 'lucide-react'
import { Card } from '../../components/ui'
import { useOrgDirectory } from '../../hooks/useApi'

export default function OrgDirectory() {
  const { data, loading } = useOrgDirectory()
  const orgs = data || []

  return (
    <div className="max-w-5xl mx-auto px-5 py-8">
      <div className="mb-7">
        <h1 className="text-2xl font-extrabold tracking-tight">Organizations</h1>
        <p className="text-[13px] text-slate-500 mt-0.5">Clubs, teams, and communities hosting events on QRMeets.</p>
      </div>

      {loading ? (
        <div className="text-center py-20 text-slate-400 text-[13px]">Loading…</div>
      ) : orgs.length === 0 ? (
        <div className="text-center py-20">
          <Building2 size={40} className="mx-auto text-slate-300 mb-3" />
          <p className="font-semibold text-slate-500">No organizations yet</p>
          <p className="text-[13px] text-slate-400 mt-1">Once an organization publishes an event, it'll show up here.</p>
        </div>
      ) : (
        <div className="grid sm:grid-cols-2 md:grid-cols-3 gap-4">
          {orgs.map(org => (
            <Link key={org.id} to={`/org/${org.slug}`}>
              <Card hover className="p-5">
                <div className="w-12 h-12 rounded-xl overflow-hidden bg-slate-100 flex items-center justify-center mb-3">
                  {org.logo ? <img src={org.logo} alt="" className="w-full h-full object-cover" /> : <Building2 size={20} className="text-slate-400" />}
                </div>
                <p className="font-bold text-[14px] text-slate-800 truncate">{org.name}</p>
                {org.description && <p className="text-[12px] text-slate-500 mt-1 line-clamp-2">{org.description}</p>}
                <p className="text-[11px] text-slate-400 mt-2.5 flex items-center gap-1.5">
                  <Calendar size={11} />{org.upcomingEventsCount} upcoming event{org.upcomingEventsCount === 1 ? '' : 's'}
                </p>
              </Card>
            </Link>
          ))}
        </div>
      )}
    </div>
  )
}
