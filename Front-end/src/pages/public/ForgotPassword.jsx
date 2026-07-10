import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Mail, Send, CheckCircle2 } from 'lucide-react'
import { Btn, Input, Card } from '../../components/ui'
import { forgotPassword } from '../../api/resources'
import { useApp } from '../../context/AppContext'

export default function ForgotPassword() {
  const { addToast } = useApp()
  const [email, setEmail] = useState('')
  const [sent, setSent] = useState(false)
  const [loading, setLoading] = useState(false)

  const submit = async (e) => {
    e.preventDefault()
    setLoading(true)
    try {
      await forgotPassword(email)
      setSent(true)
    } catch (err) {
      addToast(err.message || 'Something went wrong', 'error')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="max-w-md mx-auto px-5 py-16">
      <Card className="p-8">
        {sent ? (
          <div className="text-center">
            <div className="w-16 h-16 rounded-2xl bg-emerald-50 flex items-center justify-center mx-auto mb-4"><CheckCircle2 size={30} className="text-emerald-600" /></div>
            <h1 className="text-xl font-extrabold mb-1">Check your inbox</h1>
            <p className="text-[13px] text-slate-500">If an account exists for {email}, a reset link is on its way.</p>
          </div>
        ) : (
          <>
            <h1 className="text-xl font-extrabold mb-1">Forgot your password?</h1>
            <p className="text-[13px] text-slate-500 mb-6">Enter your email and we'll send you a reset link.</p>
            <form onSubmit={submit} className="space-y-4">
              <Input label="Email" type="email" icon={Mail} value={email} onChange={e => setEmail(e.target.value)} placeholder="you@example.com" required />
              <Btn type="submit" variant="accent" size="lg" full icon={Send} loading={loading}>Send Reset Link</Btn>
            </form>
          </>
        )}
        <p className="text-[13px] text-slate-500 text-center mt-6">
          <Link to="/login" className="font-semibold text-[#1a1a2e] hover:text-[#e94560]">Back to login</Link>
        </p>
      </Card>
    </div>
  )
}
