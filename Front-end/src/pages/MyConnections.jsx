import { useState } from 'react'
import { Users2, Check, X, UserMinus } from 'lucide-react'
import { Card, Btn, Badge } from '../components/ui'
import { useApp } from '../context/AppContext'
import { useConnections } from '../hooks/useApi'
import { acceptConnection, declineConnection, removeConnection } from '../api/resources'

export default function MyConnections() {
  const { addToast } = useApp()
  const { data, loading, refetch } = useConnections()
  const [busyId, setBusyId] = useState(null)

  const run = async (id, fn, successMsg) => {
    setBusyId(id)
    try {
      await fn(id)
      addToast(successMsg, 'success')
      refetch()
    } catch (err) {
      addToast(err.message || 'Something went wrong', 'error')
    } finally {
      setBusyId(null)
    }
  }

  if (loading) return <div className="max-w-2xl mx-auto px-5 py-16 text-center text-slate-400 text-[13px]">Loading…</div>

  const { accepted = [], incoming = [], outgoing = [] } = data || {}

  return (
    <div className="max-w-2xl mx-auto px-5 py-8 space-y-8">
      <div>
        <h1 className="text-2xl font-extrabold tracking-tight">My Connections</h1>
        <p className="text-[13px] text-slate-500 mt-0.5">People you've met at events.</p>
      </div>

      {incoming.length > 0 && (
        <section>
          <h2 className="text-[12px] font-bold text-slate-700 uppercase tracking-wide mb-3">Requests <Badge size="xs" color="rose">{incoming.length}</Badge></h2>
          <div className="space-y-2">
            {incoming.map(({ id, user }) => (
              <Card key={id} className="p-4 flex items-center justify-between gap-3">
                <PersonRow user={user} />
                <div className="flex items-center gap-2 flex-shrink-0">
                  <Btn size="sm" variant="accent" icon={Check} loading={busyId === id} onClick={() => run(id, acceptConnection, 'Connected!')}>Accept</Btn>
                  <Btn size="sm" variant="ghost" icon={X} loading={busyId === id} onClick={() => run(id, declineConnection, 'Request declined')}>Decline</Btn>
                </div>
              </Card>
            ))}
          </div>
        </section>
      )}

      <section>
        <h2 className="text-[12px] font-bold text-slate-700 uppercase tracking-wide mb-3">Connections ({accepted.length})</h2>
        {accepted.length === 0 ? (
          <Card className="p-8 text-center text-[13px] text-slate-400"><Users2 size={22} className="mx-auto mb-2 text-slate-300" />No connections yet — connect with fellow attendees from your event pass.</Card>
        ) : (
          <div className="space-y-2">
            {accepted.map(({ id, user }) => (
              <Card key={id} className="p-4 flex items-center justify-between gap-3">
                <PersonRow user={user} />
                <button onClick={() => run(id, removeConnection, 'Connection removed')} className="p-1.5 text-slate-400 hover:text-rose-500 flex-shrink-0" title="Remove connection"><UserMinus size={15} /></button>
              </Card>
            ))}
          </div>
        )}
      </section>

      {outgoing.length > 0 && (
        <section>
          <h2 className="text-[12px] font-bold text-slate-700 uppercase tracking-wide mb-3">Pending ({outgoing.length})</h2>
          <div className="space-y-2">
            {outgoing.map(({ id, user }) => (
              <Card key={id} className="p-4 flex items-center justify-between gap-3">
                <PersonRow user={user} />
                <span className="text-[11px] text-slate-400 font-semibold flex-shrink-0">Waiting for response</span>
              </Card>
            ))}
          </div>
        </section>
      )}
    </div>
  )
}

function PersonRow({ user }) {
  return (
    <div className="flex items-center gap-3 min-w-0">
      <div className="w-10 h-10 rounded-full overflow-hidden bg-slate-100 flex items-center justify-center flex-shrink-0 font-bold text-slate-500">
        {user.avatar ? <img src={user.avatar} alt="" className="w-full h-full object-cover" /> : user.name?.[0]}
      </div>
      <p className="font-semibold text-[14px] text-slate-800 truncate">{user.name}</p>
    </div>
  )
}
