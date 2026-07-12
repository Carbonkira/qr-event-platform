import { Link, useParams } from 'react-router-dom'
import { Building2, Instagram, Linkedin, Facebook, Twitter, Globe, Calendar } from 'lucide-react'
import { Card } from '../../components/ui'
import { usePublicOrg } from '../../hooks/useApi'
import { SuggestCard } from './Home'

const SOC = [['instagram', Instagram], ['linkedin', Linkedin], ['facebook', Facebook], ['twitter', Twitter], ['website', Globe]]

export default function OrgPublic() {
  const { slug } = useParams()
  const { data, loading } = usePublicOrg(slug)

  if (loading) return <div className="text-center py-20 text-slate-400 text-[13px]">Loading…</div>
  if (!data) return <div className="text-center py-20 text-slate-500">Organization not found.</div>

  const { organization: org, upcomingEvents, pastEvents } = data

  return (
    <div className="max-w-4xl mx-auto px-5 py-8">
      <div className="flex items-center gap-4 mb-3">
        <div className="w-16 h-16 rounded-2xl overflow-hidden bg-slate-100 flex items-center justify-center flex-shrink-0">
          {org.logo ? <img src={org.logo} alt="" className="w-full h-full object-cover" /> : <Building2 size={26} className="text-slate-400" />}
        </div>
        <div className="flex-1 min-w-0">
          <h1 className="text-2xl font-extrabold tracking-tight truncate">{org.name}</h1>
          {org.description && <p className="text-[13px] text-slate-500 mt-0.5">{org.description}</p>}
        </div>
      </div>

      {SOC.some(([k]) => org[k]) && (
        <div className="flex items-center gap-2 mb-8">
          {SOC.map(([k, Icon]) => org[k] ? <a key={k} href="#" onClick={e => e.preventDefault()} className="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-600 transition-all"><Icon size={15} /></a> : null)}
        </div>
      )}

      <section className="mb-10">
        <h2 className="text-[13px] font-bold text-slate-700 uppercase tracking-wide mb-3">Upcoming events</h2>
        {upcomingEvents.length === 0 ? (
          <Card className="p-8 text-center text-[13px] text-slate-400"><Calendar size={22} className="mx-auto mb-2 text-slate-300" />No upcoming events right now.</Card>
        ) : (
          <div className="grid sm:grid-cols-3 gap-4">
            {upcomingEvents.map(e => <SuggestCard key={e.id} event={e} />)}
          </div>
        )}
      </section>

      {pastEvents.length > 0 && (
        <section>
          <h2 className="text-[13px] font-bold text-slate-700 uppercase tracking-wide mb-3">Past events</h2>
          <div className="grid sm:grid-cols-3 gap-4">
            {pastEvents.map(e => <SuggestCard key={e.id} event={e} />)}
          </div>
        </section>
      )}

      <p className="text-[12px] text-slate-400 mt-10"><Link to="/" className="font-semibold text-[#1a1a2e] hover:text-[#e94560]">Back to all events</Link></p>
    </div>
  )
}
