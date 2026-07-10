import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Mail, Search, Ticket } from 'lucide-react'
import { Btn, Input, Card } from '../../components/ui'
import { findPassesByEmail } from '../../api/resources'
import { useApp } from '../../context/AppContext'
import { fmtDate, fmtTime } from '../../lib/utils'

export default function FindPass() {
  const navigate = useNavigate()
  const { addToast } = useApp()
  const [email, setEmail] = useState('')
  const [results, setResults] = useState(null)
  const [loading, setLoading] = useState(false)

  const submit = async (e) => {
    e.preventDefault()
    setLoading(true)
    try {
      const regs = await findPassesByEmail(email)
      setResults(regs || [])
      if (!regs || regs.length === 0) addToast('No passes found for that email', 'info')
    } catch (err) {
      addToast(err.message, 'error')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="max-w-md mx-auto px-5 py-16">
      <Card className="p-8">
        <div className="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-4"><Ticket size={26} className="text-[#1a1a2e]" /></div>
        <h1 className="text-xl font-extrabold text-center mb-1">Find My Pass</h1>
        <p className="text-[13px] text-slate-500 text-center mb-6">Enter the email you used to register.</p>
        <form onSubmit={submit} className="flex gap-2">
          <div className="flex-1"><Input type="email" icon={Mail} value={email} onChange={e => setEmail(e.target.value)} placeholder="you@example.com" required /></div>
          <Btn type="submit" variant="accent" icon={Search} loading={loading}>Find</Btn>
        </form>
      </Card>

      {results && results.length > 0 && (
        <div className="mt-5 space-y-3">
          {results.map(reg => {
            // Support either a nested `event` relation or flattened fields
            // on the registration — see Pass.jsx for the same normalization.
            const ev = reg.event || reg
            return (
              <Card key={reg.id} hover className="p-4" onClick={() => navigate(`/pass/${reg.id}`, { state: reg })}>
                <p className="text-[14px] font-bold text-slate-800">{ev.title}</p>
                <p className="text-[12px] text-slate-500 mt-1">{fmtDate(ev.date)} · {fmtTime(ev.startTime)}</p>
              </Card>
            )
          })}
        </div>
      )}
    </div>
  )
}
