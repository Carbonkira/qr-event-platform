import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { User, Mail, Lock, GraduationCap, UserPlus } from 'lucide-react'
import { Btn, Input, Card } from '../../components/ui'
import PasswordChecklist from '../../components/shared/PasswordChecklist'
import { useApp } from '../../context/AppContext'

// Standalone participant signup - same entry point as organizer signup
// (both reachable from Login.jsx's toggle), but a genuinely separate flow:
// creates a Participant account, not an Organizer one. Distinct from
// Register.jsx's *embedded* account step (which also creates a Participant,
// just inline mid-event-registration) - this is for someone who wants an
// account before they've picked an event to register for.
export default function RegisterParticipant() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { createAccount, addToast } = useApp()
  const [form, setForm] = useState({ name: '', email: '', emailConfirmation: '', password: '', passwordConfirmation: '', institution: '' })
  const [errors, setErrors] = useState({})
  const [loading, setLoading] = useState(false)

  const set = (k) => (e) => setForm(f => ({ ...f, [k]: e.target.value }))

  const submit = async (e) => {
    e.preventDefault()
    if (form.email.trim().toLowerCase() !== form.emailConfirmation.trim().toLowerCase()) {
      setErrors({ emailConfirmation: ['Emails do not match'] })
      return
    }
    if (form.password !== form.passwordConfirmation) {
      setErrors({ password: ['Passwords do not match'] })
      return
    }
    setLoading(true)
    setErrors({})
    try {
      await createAccount('participant', form)
      const next = searchParams.get('next')
      navigate(next || `/participant/verify-email?email=${encodeURIComponent(form.email)}`)
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
        <h1 className="text-xl font-extrabold mb-1">Create Your Account</h1>
        <p className="text-[13px] text-slate-500 mb-6">Sign up to register for events and keep track of your passes.</p>
        <form onSubmit={submit} className="space-y-4">
          <Input label="Full Name" icon={User} value={form.name} onChange={set('name')} placeholder="Juan Dela Cruz" error={errors.name?.[0]} required />
          <Input label="Email" type="email" icon={Mail} value={form.email} onChange={set('email')} placeholder="juan@email.com" error={errors.email?.[0]} required />
          <Input label="Confirm Email" type="email" icon={Mail} value={form.emailConfirmation} onChange={set('emailConfirmation')} placeholder="juan@email.com" error={errors.emailConfirmation?.[0]} required />
          <Input label="Password" type="password" icon={Lock} value={form.password} onChange={set('password')} placeholder="••••••••" error={errors.password?.[0]} required />
          {form.password && <PasswordChecklist password={form.password} />}
          <Input label="Confirm Password" type="password" icon={Lock} value={form.passwordConfirmation} onChange={set('passwordConfirmation')} placeholder="••••••••" required />
          <Input label="Institution" icon={GraduationCap} value={form.institution} onChange={set('institution')} placeholder="Optional" error={errors.institution?.[0]} />
          <Btn type="submit" variant="accent" size="lg" full icon={UserPlus} loading={loading}>Create Account</Btn>
        </form>
        <p className="text-[13px] text-slate-500 text-center mt-6">
          Already have an account? <Link to="/login?type=participant" className="font-semibold text-[#1a1a2e] hover:text-[#e94560]">Log in</Link>
        </p>
      </Card>
    </div>
  )
}
