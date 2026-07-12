import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { Building2, User, Mail, Lock, GraduationCap, UserPlus } from 'lucide-react'
import { Btn, Input, Card } from '../../components/ui'
import PasswordChecklist from '../../components/shared/PasswordChecklist'
import { useApp } from '../../context/AppContext'

export default function RegisterOrganizer() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { createAccount, addToast } = useApp()
  const [form, setForm] = useState({ organization: '', name: '', email: '', emailConfirmation: '', password: '', passwordConfirmation: '', institution: '' })
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
      await createAccount(form)
      const next = searchParams.get('next')
      navigate(next || `/organizer/verify-email?email=${encodeURIComponent(form.email)}`)
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
        <h1 className="text-xl font-extrabold mb-1">Register as Organizer</h1>
        <p className="text-[13px] text-slate-500 mb-6">Create an account to start hosting events.</p>
        <form onSubmit={submit} className="space-y-4">
          <Input label="Organization Name" icon={Building2} value={form.organization} onChange={set('organization')} placeholder="e.g. Acme Student Council" error={errors.organization?.[0]} required />
          <Input label="Your Name" icon={User} value={form.name} onChange={set('name')} placeholder="Full name" error={errors.name?.[0]} required />
          <Input label="Email" type="email" icon={Mail} value={form.email} onChange={set('email')} placeholder="you@organization.com" error={errors.email?.[0]} required />
          <Input label="Confirm Email" type="email" icon={Mail} value={form.emailConfirmation} onChange={set('emailConfirmation')} placeholder="you@organization.com" error={errors.emailConfirmation?.[0]} required />
          <Input label="Password" type="password" icon={Lock} value={form.password} onChange={set('password')} placeholder="••••••••" error={errors.password?.[0]} required />
          {form.password && <PasswordChecklist password={form.password} />}
          <Input label="Confirm Password" type="password" icon={Lock} value={form.passwordConfirmation} onChange={set('passwordConfirmation')} placeholder="••••••••" required />
          <Input label="Institution" icon={GraduationCap} value={form.institution} onChange={set('institution')} placeholder="Optional" error={errors.institution?.[0]} />
          <Btn type="submit" variant="accent" size="lg" full icon={UserPlus} loading={loading}>Create Account</Btn>
        </form>
        <p className="text-[13px] text-slate-500 text-center mt-6">
          Already have an account? <Link to="/login" className="font-semibold text-[#1a1a2e] hover:text-[#e94560]">Log in</Link>
        </p>
      </Card>
    </div>
  )
}
