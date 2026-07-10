import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { MailCheck, RefreshCw } from 'lucide-react'
import { Btn, Card } from '../../components/ui'
import { useApp } from '../../context/AppContext'

// Shown right after account creation, before the user has clicked the real
// verification link sent to their inbox (see Backend's AuthController -
// the link itself is a signed URL that hits the backend directly and
// redirects to /email-verified, not this page).
export default function VerifyEmail() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { user, resendVerificationEmail, addToast } = useApp()
  const email = searchParams.get('email') || user?.email
  const [resending, setResending] = useState(false)

  const resend = async () => {
    setResending(true)
    try {
      await resendVerificationEmail()
      addToast('Verification email sent!', 'success')
    } catch (err) {
      addToast(err.message || 'Could not resend right now', 'error')
    } finally {
      setResending(false)
    }
  }

  return (
    <div className="max-w-md mx-auto px-5 py-16 text-center">
      <Card className="p-8">
        <div className="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-4"><MailCheck size={30} className="text-[#1a1a2e]" /></div>
        <h1 className="text-xl font-extrabold mb-1">Check your inbox</h1>
        <p className="text-[13px] text-slate-500 mb-6">We sent a verification link to {email ? <b className="text-slate-700">{email}</b> : 'your email'}. Click it to verify your account — you can keep using the app in the meantime.</p>
        <div className="space-y-2">
          <Btn variant="secondary" size="lg" full icon={RefreshCw} loading={resending} onClick={resend}>Resend email</Btn>
          <Btn variant="accent" size="lg" full onClick={() => navigate('/organizer')}>Go to Dashboard</Btn>
        </div>
      </Card>
    </div>
  )
}
