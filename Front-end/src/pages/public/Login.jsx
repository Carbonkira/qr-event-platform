import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { Mail, Lock, LogIn } from 'lucide-react'
import { Btn, Input, Card } from '../../components/ui'
import { useApp } from '../../context/AppContext'
import { cn } from '../../lib/utils'

const REMEMBERED_EMAIL_KEY = 'qr_remembered_email'

// Genuinely separate organizer/participant accounts (see Backend's
// Organizer/Participant models) means a login page reached with no other
// context has to ask which one - Register.jsx's own embedded login (mid
// event-registration) already knows the answer is "participant" and never
// lands here.
export default function Login() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { login, addToast } = useApp()
  const [type, setType] = useState(searchParams.get('type') === 'participant' ? 'participant' : 'organizer')
  const [email, setEmail] = useState(() => localStorage.getItem(REMEMBERED_EMAIL_KEY) || '')
  const [password, setPassword] = useState('')
  const [errors, setErrors] = useState({})
  const [loading, setLoading] = useState(false)

  const submit = async (e) => {
    e.preventDefault()
    setLoading(true)
    setErrors({})
    try {
      await login(type, email, password)
      localStorage.setItem(REMEMBERED_EMAIL_KEY, email)
      navigate(searchParams.get('next') || '/my-events')
    } catch (err) {
      setErrors(err.errors || {})
      addToast(err.message, 'error')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="max-w-md mx-auto px-5 py-16">
      <Card className="p-8">
        <h1 className="text-xl font-extrabold mb-1">Log In</h1>
        <p className="text-[13px] text-slate-500 mb-6">Sign in to your account.</p>
        <div className="flex gap-1.5 p-1 rounded-xl bg-slate-100 mb-5">
          <button type="button" onClick={() => setType('organizer')} className={cn('flex-1 py-2 rounded-lg text-[12px] font-semibold transition-all', type === 'organizer' ? 'bg-white shadow-sm text-slate-800' : 'text-slate-500')}>Organizer</button>
          <button type="button" onClick={() => setType('participant')} className={cn('flex-1 py-2 rounded-lg text-[12px] font-semibold transition-all', type === 'participant' ? 'bg-white shadow-sm text-slate-800' : 'text-slate-500')}>Participant</button>
        </div>
        <form onSubmit={submit} className="space-y-4">
          <Input label="Email" type="email" icon={Mail} value={email} onChange={e => setEmail(e.target.value)} placeholder="you@organization.com" error={errors.email?.[0]} required />
          <Input label="Password" type="password" icon={Lock} value={password} onChange={e => setPassword(e.target.value)} placeholder="••••••••" error={errors.password?.[0]} required />
          <Btn type="submit" variant="accent" size="lg" full icon={LogIn} loading={loading}>Log In</Btn>
        </form>
        <p className="text-[13px] text-slate-500 text-center mt-4">
          <Link to="/forgot-password" className="font-semibold text-[#1a1a2e] hover:text-[#e94560]">Forgot your password?</Link>
        </p>
        {type === 'organizer' && (
          <p className="text-[13px] text-slate-500 text-center mt-2">
            New here? <Link to="/organizer/register" className="font-semibold text-[#1a1a2e] hover:text-[#e94560]">Create an account</Link>
          </p>
        )}
      </Card>
    </div>
  )
}
