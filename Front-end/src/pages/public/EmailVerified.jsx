import { useSearchParams, useNavigate, Link } from 'react-router-dom'
import { CheckCircle2, XCircle } from 'lucide-react'
import { Btn, Card } from '../../components/ui'
import { useApp } from '../../context/AppContext'

// Landing page after clicking the real verification link in the email -
// the backend (AuthController::verify) already marked the account verified
// (or not, if the link was bad/expired) before redirecting here; this page
// just reports the outcome.
export default function EmailVerified() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { user } = useApp()
  const verified = searchParams.get('verified') === '1'

  return (
    <div className="max-w-md mx-auto px-5 py-16 text-center">
      <Card className="p-8">
        {verified ? (
          <>
            <div className="w-16 h-16 rounded-2xl bg-emerald-50 flex items-center justify-center mx-auto mb-4"><CheckCircle2 size={30} className="text-emerald-600" /></div>
            <h1 className="text-xl font-extrabold mb-1">You're verified!</h1>
            <p className="text-[13px] text-slate-500 mb-6">Your email is confirmed. You're all set.</p>
          </>
        ) : (
          <>
            <div className="w-16 h-16 rounded-2xl bg-rose-50 flex items-center justify-center mx-auto mb-4"><XCircle size={30} className="text-rose-600" /></div>
            <h1 className="text-xl font-extrabold mb-1">That link didn't work</h1>
            <p className="text-[13px] text-slate-500 mb-6">It may have expired. Log in and resend the verification email from your dashboard.</p>
          </>
        )}
        <Btn variant="accent" size="lg" full onClick={() => navigate(user ? '/organizer' : '/login')}>
          {user ? 'Go to Dashboard' : 'Log In'}
        </Btn>
        {!user && <p className="text-[13px] text-slate-500 text-center mt-4"><Link to="/" className="font-semibold text-[#1a1a2e] hover:text-[#e94560]">Back to home</Link></p>}
      </Card>
    </div>
  )
}
