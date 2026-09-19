import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { User, Mail, Lock, GraduationCap, UserPlus, Phone, Building2, MapPin } from 'lucide-react'
import { Btn, Input, Select, Card } from '../../components/ui'
import PasswordChecklist from '../../components/shared/PasswordChecklist'
import { useApp } from '../../context/AppContext'
import { useOrgList } from '../../hooks/useApi'

// Sentinel for the organization Select - not a real id, just "theirs isn't in
// the list, they're going to type it".
const NEW_ORG = '__new__'
const CONTACT_NUMBER = /^[0-9+\-()\s]{7,30}$/

// This is an application, not an instant signup: an admin reviews every
// organizer account before it can host anything. Organizations are admin-
// created, so the applicant either picks one from the list, or asks for a
// new one - name plus address, so the admin has something to verify it
// against (never shown publicly). Nothing is created or joined until the
// admin approves - see OrganizerApprovalController::approve().
export default function RegisterOrganizer() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { createAccount, addToast } = useApp()
  const { data: orgsData } = useOrgList()
  const orgs = orgsData || []
  const [form, setForm] = useState({
    name: '', email: '', emailConfirmation: '', contactNumber: '',
    organizationChoice: '', organizationName: '', organizationAddress: '',
    password: '', passwordConfirmation: '', institution: '',
  })
  const [errors, setErrors] = useState({})
  const [loading, setLoading] = useState(false)

  const set = (k) => (e) => setForm(f => ({ ...f, [k]: e.target.value }))
  const requestingNew = form.organizationChoice === NEW_ORG

  const submit = async (e) => {
    e.preventDefault()
    const errs = {}
    if (form.email.trim().toLowerCase() !== form.emailConfirmation.trim().toLowerCase()) errs.emailConfirmation = ['Emails do not match']
    if (!CONTACT_NUMBER.test(form.contactNumber.trim())) errs.contactNumber = ['Enter a valid contact number, e.g. 0917 123 4567']
    if (form.password !== form.passwordConfirmation) errs.password = ['Passwords do not match']
    if (requestingNew && !form.organizationName.trim()) errs.organizationName = ['Enter the organization name']
    if (requestingNew && !form.organizationAddress.trim()) errs.organizationAddress = ['Enter the organization address']
    if (Object.keys(errs).length) { setErrors(errs); return }

    setLoading(true)
    setErrors({})
    try {
      const { organizationChoice, organizationName, organizationAddress, ...rest } = form
      await createAccount('organizer', {
        ...rest,
        contactNumber: form.contactNumber.trim(),
        organizationId: organizationChoice && !requestingNew ? organizationChoice : null,
        organizationName: requestingNew ? organizationName.trim() : null,
        organizationAddress: requestingNew ? organizationAddress.trim() : null,
      })
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
        <h1 className="text-xl font-extrabold mb-1">Apply as an Organizer</h1>
        <p className="text-[13px] text-slate-500 mb-6">Tell us who you are - an admin reviews every application before you can host events, and we'll email you a confirmation right away.</p>
        <form onSubmit={submit} className="space-y-4">
          <Input label="Full Name" icon={User} value={form.name} onChange={set('name')} placeholder="Juan Dela Cruz" error={errors.name?.[0]} required />
          <Input label="Email" type="email" icon={Mail} value={form.email} onChange={set('email')} placeholder="you@organization.com" error={errors.email?.[0]} required />
          <Input label="Confirm Email" type="email" icon={Mail} value={form.emailConfirmation} onChange={set('emailConfirmation')} placeholder="you@organization.com" error={errors.emailConfirmation?.[0]} required />
          <Input label="Contact Number" type="tel" icon={Phone} value={form.contactNumber} onChange={set('contactNumber')} placeholder="0917 123 4567" error={errors.contactNumber?.[0]} hint="The admin may call to verify your application." required />

          <div className="space-y-3">
            <Select
              label="Company / Organization"
              value={form.organizationChoice}
              onChange={set('organizationChoice')}
              options={[
                { value: '', label: "I don't have one yet" },
                ...orgs.map(o => ({ value: String(o.id), label: o.name })),
                { value: NEW_ORG, label: "My organization isn't listed" },
              ]}
            />
            {form.organizationChoice && !requestingNew && <p className="text-[11px] text-slate-400 -mt-1">You'll be added once an admin approves your application.</p>}
            {requestingNew && (
              <div className="space-y-3 rounded-xl bg-slate-50 border border-slate-200 p-3">
                <Input label="Organization Name" icon={Building2} value={form.organizationName} onChange={set('organizationName')} placeholder="e.g. Acme Student Council" error={errors.organizationName?.[0]} required />
                <Input label="Organization Address" icon={MapPin} value={form.organizationAddress} onChange={set('organizationAddress')} placeholder="Street, city, province" error={errors.organizationAddress?.[0]} hint="Only the admin sees this - it's used to verify your organization, never shown publicly." required />
                <p className="text-[11px] text-slate-400">The admin creates the organization if they approve your application.</p>
              </div>
            )}
          </div>

          <Input label="Password" type="password" icon={Lock} value={form.password} onChange={set('password')} placeholder="••••••••" error={errors.password?.[0]} required />
          {form.password && <PasswordChecklist password={form.password} />}
          <Input label="Confirm Password" type="password" icon={Lock} value={form.passwordConfirmation} onChange={set('passwordConfirmation')} placeholder="••••••••" required />
          <Input label="Institution" icon={GraduationCap} value={form.institution} onChange={set('institution')} placeholder="Optional" error={errors.institution?.[0]} />
          <Btn type="submit" variant="accent" size="lg" full icon={UserPlus} loading={loading}>Submit Application</Btn>
        </form>
        <p className="text-[13px] text-slate-500 text-center mt-6">
          Already have an account? <Link to="/login" className="font-semibold text-[#1a1a2e] hover:text-[var(--accent)]">Log in</Link>
        </p>
      </Card>
    </div>
  )
}
