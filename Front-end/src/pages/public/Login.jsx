import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { Mail, Lock, LogIn } from 'lucide-react'
import { Btn, Input, Card } from '../../components/ui'
import { useApp } from '../../context/AppContext'

export default function Login() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { login, addToast } = useApp()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)

  const submit = async (e) => {
    e.preventDefault()
    setLoading(true)
    try {
      await login(email, password)
      navigate(searchParams.get('next') || '/my-events')
    } catch (err) {
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
        <form onSubmit={submit} className="space-y-4">
          <Input label="Email" type="email" icon={Mail} value={email} onChange={e => setEmail(e.target.value)} placeholder="you@organization.com" required />
          <Input label="Password" type="password" icon={Lock} value={password} onChange={e => setPassword(e.target.value)} placeholder="••••••••" required />
          <Btn type="submit" variant="accent" size="lg" full icon={LogIn} loading={loading}>Log In</Btn>
        </form>
        <p className="text-[13px] text-slate-500 text-center mt-4">
          <Link to="/forgot-password" className="font-semibold text-[#1a1a2e] hover:text-[#e94560]">Forgot your password?</Link>
        </p>
        <p className="text-[13px] text-slate-500 text-center mt-2">
          New here? <Link to="/organizer/register" className="font-semibold text-[#1a1a2e] hover:text-[#e94560]">Create an account</Link>
        </p>
      </Card>
    </div>
  )
}
