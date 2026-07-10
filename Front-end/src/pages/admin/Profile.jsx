import { useEffect, useState } from 'react'
import { Card, Btn, Input, Textarea } from '../../components/ui'
import { useOrganization } from '../../hooks/useApi'
import { updateOrganization } from '../../api/resources'
import { useApp } from '../../context/AppContext'

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

  if (loading || !form) return <div className="text-center py-20 text-slate-400 text-[13px]">Loading profile…</div>

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
      <div><h1 className="text-2xl font-extrabold">Organization Profile</h1><p className="text-[13px] text-slate-500">Shown to participants on your public event pages</p></div>

      <form onSubmit={handleSave} className="space-y-6">
        <Card className="p-5 space-y-4">
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
    </div>
  )
}
