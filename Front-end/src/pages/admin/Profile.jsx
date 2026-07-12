import { useEffect, useRef, useState } from 'react'
import { User, Mail, GraduationCap, Lock, Camera } from 'lucide-react'
import { Card, Btn, Input, Textarea } from '../../components/ui'
import { useOrganization } from '../../hooks/useApi'
import { updateOrganization } from '../../api/resources'
import { useApp } from '../../context/AppContext'

const MAX_AVATAR_BYTES = 5 * 1024 * 1024 // 5MB — matches the backend's own limit

function AvatarUpload() {
  const { user, uploadAvatar, addToast } = useApp()
  const [uploading, setUploading] = useState(false)
  const fileRef = useRef(null)

  const onPick = async (file) => {
    if (!file) return
    if (!file.type.startsWith('image/')) { addToast('Please upload an image', 'error'); return }
    if (file.size > MAX_AVATAR_BYTES) { addToast('Image must be under 5MB', 'error'); return }
    setUploading(true)
    try {
      await uploadAvatar(file)
      addToast('Profile photo updated', 'success')
    } catch (err) {
      addToast(err.message || 'Failed to upload photo', 'error')
    } finally {
      setUploading(false)
    }
  }

  return (
    <div className="flex items-center gap-4">
      <div className="w-16 h-16 rounded-full overflow-hidden bg-gradient-to-br from-[#e94560] to-[#6d28d9] flex items-center justify-center text-white font-bold text-2xl flex-shrink-0">
        {user?.avatar ? <img src={user.avatar} alt="" className="w-full h-full object-cover" /> : (user?.name?.[0]?.toUpperCase() || '·')}
      </div>
      <div>
        <Btn variant="secondary" size="sm" icon={Camera} loading={uploading} onClick={() => fileRef.current?.click()}>Change photo</Btn>
        <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={e => { onPick(e.target.files?.[0]); e.target.value = '' }} />
      </div>
    </div>
  )
}

function AccountCard() {
  const { user, updateProfile, addToast } = useApp()
  const [form, setForm] = useState({ name: user?.name || '', email: user?.email || '', institution: user?.institution || '' })
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const update = (k) => (e) => { setForm(f => ({ ...f, [k]: e.target.value })); setErrors(er => ({ ...er, [k]: undefined })) }

  const save = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    try {
      await updateProfile(form)
      addToast(form.email !== user?.email ? 'Account updated — check your new email to verify it' : 'Account updated', 'success')
    } catch (err) {
      setErrors(err.errors || {})
      addToast(err.message || 'Failed to update account', 'error')
    } finally {
      setSaving(false)
    }
  }

  return (
    <Card className="p-5 space-y-4">
      <div>
        <p className="font-bold text-[14px]">My Account</p>
        <p className="text-[11px] text-slate-400">Your own login — separate from the organization info below</p>
      </div>
      <AvatarUpload />
      <form onSubmit={save} className="space-y-4">
        <Input label="Full name" value={form.name} onChange={update('name')} icon={User} error={errors.name?.[0]} required />
        <Input label="Email" type="email" value={form.email} onChange={update('email')} icon={Mail} error={errors.email?.[0]} required />
        <Input label="Institution" value={form.institution} onChange={update('institution')} icon={GraduationCap} error={errors.institution?.[0]} />
        <div className="flex justify-end">
          <Btn variant="secondary" type="submit" loading={saving}>Save Account</Btn>
        </div>
      </form>
    </Card>
  )
}

function PasswordCard() {
  const { updateProfile, addToast } = useApp()
  const [form, setForm] = useState({ currentPassword: '', password: '', passwordConfirm: '' })
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const update = (k) => (e) => { setForm(f => ({ ...f, [k]: e.target.value })); setErrors(er => ({ ...er, [k]: undefined })) }

  const save = async (e) => {
    e.preventDefault()
    if (form.password !== form.passwordConfirm) {
      setErrors({ password: ['New password and confirmation do not match'] })
      return
    }
    setSaving(true)
    setErrors({})
    try {
      await updateProfile({ currentPassword: form.currentPassword, password: form.password })
      addToast('Password updated', 'success')
      setForm({ currentPassword: '', password: '', passwordConfirm: '' })
    } catch (err) {
      setErrors(err.errors || {})
      addToast(err.message || 'Failed to update password', 'error')
    } finally {
      setSaving(false)
    }
  }

  return (
    <Card className="p-5 space-y-4">
      <p className="font-bold text-[14px]">Change Password</p>
      <form onSubmit={save} className="space-y-4">
        <Input label="Current password" type="password" value={form.currentPassword} onChange={update('currentPassword')} icon={Lock} error={errors.currentPassword?.[0]} required />
        <div className="grid sm:grid-cols-2 gap-3">
          <Input label="New password" type="password" value={form.password} onChange={update('password')} icon={Lock} error={errors.password?.[0]} required />
          <Input label="Confirm new password" type="password" value={form.passwordConfirm} onChange={update('passwordConfirm')} icon={Lock} required />
        </div>
        <div className="flex justify-end">
          <Btn variant="secondary" type="submit" loading={saving}>Update Password</Btn>
        </div>
      </form>
    </Card>
  )
}

export default function Profile() {
  const { addToast } = useApp()
  const { data: org, loading } = useOrganization()
  const [form, setForm] = useState(null)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (org && !form) {
      setForm({
        name: org.name || '', description: org.description || '', organizedBy: org.organizedBy || '',
        email: org.email || '', industry: org.industry || '', instagram: org.instagram || '',
        linkedin: org.linkedin || '', facebook: org.facebook || '', website: org.website || '',
        twitter: org.twitter || '', privacyPolicyUrl: org.privacyPolicyUrl || '',
      })
    }
  }, [org, form])

  const update = (k, v) => setForm(f => ({ ...f, [k]: v }))

  const handleSave = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      await updateOrganization(form)
      addToast('Profile updated', 'success')
    } catch (err) {
      addToast(err.message || 'Failed to update profile', 'error')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="max-w-2xl space-y-5">
      <div><h1 className="text-2xl font-extrabold">Profile</h1><p className="text-[13px] text-slate-500">Your account and your organization's public info</p></div>

      <AccountCard />
      <PasswordCard />

      {loading || !form ? (
        <div className="text-center py-10 text-slate-400 text-[13px]">Loading organization profile…</div>
      ) : (
        <form onSubmit={handleSave} className="space-y-6">
          <Card className="p-5 space-y-4">
            <p className="font-bold text-[14px]">Organization Profile</p>
            <p className="text-[11px] text-slate-400 -mt-3">Shown to participants on your public event pages</p>
            <Input label="Organization name" value={form.name} onChange={e => update('name', e.target.value)} required />
            <Textarea label="Description" value={form.description} onChange={e => update('description', e.target.value)} rows={3} />
            <div className="grid sm:grid-cols-2 gap-3">
              <Input label="Organized by" value={form.organizedBy} onChange={e => update('organizedBy', e.target.value)} />
              <Input label="Industry" value={form.industry} onChange={e => update('industry', e.target.value)} />
            </div>
            <Input label="Contact email" type="email" value={form.email} onChange={e => update('email', e.target.value)} />
          </Card>

          <Card className="p-5 space-y-4">
            <p className="text-[12px] font-bold text-slate-400 uppercase tracking-wide">Socials & links</p>
            <div className="grid sm:grid-cols-2 gap-3">
              <Input label="Instagram" value={form.instagram} onChange={e => update('instagram', e.target.value)} placeholder="@handle" />
              <Input label="LinkedIn" value={form.linkedin} onChange={e => update('linkedin', e.target.value)} placeholder="Page URL" />
              <Input label="Facebook" value={form.facebook} onChange={e => update('facebook', e.target.value)} placeholder="Page URL" />
              <Input label="Twitter / X" value={form.twitter} onChange={e => update('twitter', e.target.value)} placeholder="@handle" />
              <Input label="Website" value={form.website} onChange={e => update('website', e.target.value)} placeholder="https://…" />
              <Input label="Privacy policy URL" value={form.privacyPolicyUrl} onChange={e => update('privacyPolicyUrl', e.target.value)} placeholder="https://…" />
            </div>
          </Card>

          <div className="flex justify-end">
            <Btn variant="accent" type="submit" loading={saving}>Save Changes</Btn>
          </div>
        </form>
      )}
    </div>
  )
}
