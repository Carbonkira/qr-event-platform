import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { Lock, KeyRound } from 'lucide-react'
import { Btn, Input, Card } from '../../components/ui'
import PasswordChecklist from '../../components/shared/PasswordChecklist'
import { resetPassword } from '../../api/resources'
import { useApp } from '../../context/AppContext'

export default function ResetPassword() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { addToast } = useApp()
  const token = searchParams.get('token') || ''
  const email = searchParams.get('email') || ''
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [errors, setErrors] = useState({})
  const [loading, setLoading] = useState(false)

  const submit = async (e) => {
    e.preventDefault()
    if (password !== passwordConfirmation) {
      setErrors({ password: ['Passwords do not match'] })
      return
    }
    setLoading(true)
    setErrors({})
    try {
      await resetPassword({ token, email, password, passwordConfirmation })
      addToast('Password reset — please log in', 'success')
      navigate('/login')
    } catch (err) {
      setErrors(err.errors || {})
      addToast(err.message || 'That reset link is invalid or expired', 'error')
    } finally {
      setLoading(false)
    }
  }

  if (!token || !email) {
    return (
      <div className="max-w-md mx-auto px-5 py-16 text-center">
        <Card className="p-8">
          <h1 className="text-xl font-extrabold mb-1">Invalid reset link</h1>
          <p className="text-[13px] text-slate-500 mb-6">This link is missing its token. Request a new one.</p>
          <Link to="/forgot-password"><Btn variant="secondary" full>Request a new link</Btn></Link>
        </Card>
      </div>
    )
  }

  return (
    <div className="max-w-md mx-auto px-5 py-16">
      <Card className="p-8">
        <h1 className="text-xl font-extrabold mb-1">Choose a new password</h1>
        <p className="text-[13px] text-slate-500 mb-6">Resetting password for {email}.</p>
        <form onSubmit={submit} className="space-y-4">
          <Input label="New Password" type="password" icon={Lock} value={password} onChange={e => setPassword(e.target.value)} placeholder="••••••••" error={errors.password?.[0]} required />
          {password && <PasswordChecklist password={password} />}
          <Input label="Confirm New Password" type="password" icon={Lock} value={passwordConfirmation} onChange={e => setPasswordConfirmation(e.target.value)} placeholder="••••••••" required />
          <Btn type="submit" variant="accent" size="lg" full icon={KeyRound} loading={loading}>Reset Password</Btn>
        </form>
      </Card>
    </div>
  )
}
