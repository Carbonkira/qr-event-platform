import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { User, Mail, Lock, GraduationCap, UserPlus } from 'lucide-react'
import { Btn, Input, Select, Card } from '../../components/ui'
import PasswordChecklist from '../../components/shared/PasswordChecklist'
import { useApp } from '../../context/AppContext'
import { useOrgList } from '../../hooks/useApi'

// Organizations are admin-created now (see OrgController::store()) - an
// organizer picks one from this dropdown rather than typing a name that
// used to go nowhere (the old free-text field was silently dropped by the
// backend). Picking one doesn't grant membership immediately - it's held
// as requested_organization_id until the admin approves the account (see
// OrganizerApprovalController::approve()), same review every new organizer
// already goes through.
export default function RegisterOrganizer() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { createAccount, addToast } = useApp()
  const { data: orgsData } = useOrgList()
  const orgs = orgsData || []
  const [form, setForm] = useState({ organizationId: '', name: '', email: '', emailConfirmation: '', password: '', passwordConfirmation: '', institution: '' })
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
      await createAccount('organizer', { ...form, organizationId: form.organizationId || null })
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
          <Select
            label="Organization"
            value={form.organizationId}
            onChange={set('organizationId')}
            options={[{ value: '', label: "I don't have one yet" }, ...orgs.map(o => ({ value: String(o.id), label: o.name }))]}
          />
          {form.organizationId && <p className="text-[11px] text-slate-400 -mt-2">You'll be added once an admin approves your account.</p>}
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
          Already have an account? <Link to="/login" className="font-semibold text-[#1a1a2e] hover:text-[var(--accent)]">Log in</Link>
        </p>
      </Card>
    </div>
  )
}
