import { useRef, useState } from 'react'
import { Building2, Camera, Plus } from 'lucide-react'
import { Card, Btn, Input, Textarea, Badge } from '../../components/ui'
import { useApp } from '../../context/AppContext'
import { useMyOrgs } from '../../hooks/useApi'
import { createOrg, updateOrg, uploadOrgLogo } from '../../api/resources'

const MAX_LOGO_BYTES = 5 * 1024 * 1024 // 5MB — matches other image uploads

export default function Organizations() {
  const { addToast } = useApp()
  const { data: orgs, loading, refetch } = useMyOrgs()
  const [creating, setCreating] = useState(false)
  const [newName, setNewName] = useState('')
  const [savingNew, setSavingNew] = useState(false)

  const createNew = async (e) => {
    e.preventDefault()
    if (!newName.trim()) return
    setSavingNew(true)
    try {
      await createOrg({ name: newName })
      setNewName('')
      setCreating(false)
      addToast('Organization created', 'success')
      refetch()
    } catch (err) {
      addToast(err.message || 'Failed to create organization', 'error')
    } finally {
      setSavingNew(false)
    }
  }

  return (
    <div className="max-w-3xl space-y-5">
      <div className="flex items-center justify-between gap-3">
        <div><h1 className="text-2xl font-extrabold">My Organizations</h1><p className="text-[13px] text-slate-500">Every organization you belong to</p></div>
        <Btn variant="accent" icon={Plus} onClick={() => setCreating(o => !o)}>New Organization</Btn>
      </div>

      {creating && (
        <Card className="p-5">
          <form onSubmit={createNew} className="flex items-end gap-2">
            <div className="flex-1"><Input label="Organization name" value={newName} onChange={e => setNewName(e.target.value)} placeholder="e.g. Acme Robotics Club" autoFocus required /></div>
            <Btn type="submit" loading={savingNew}>Create</Btn>
          </form>
        </Card>
      )}

      {loading ? (
        <div className="text-center py-10 text-slate-400 text-[13px]">Loading…</div>
      ) : (orgs || []).length === 0 ? (
        <Card className="p-10 text-center text-[13px] text-slate-400">You don't belong to any organization yet.</Card>
      ) : (
        <div className="space-y-4">
          {orgs.map(org => <OrgCard key={org.id} org={org} onSaved={refetch} />)}
        </div>
      )}
    </div>
  )
}

function OrgCard({ org, onSaved }) {
  const { addToast } = useApp()
  const isOwner = org.pivot?.role === 'owner'
  const [form, setForm] = useState({ name: org.name, description: org.description || '', email: org.email || '' })
  const [saving, setSaving] = useState(false)
  const [uploading, setUploading] = useState(false)
  const fileRef = useRef(null)

  const update = (k) => (e) => setForm(f => ({ ...f, [k]: e.target.value }))

  const save = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      await updateOrg(org.id, form)
      addToast('Organization updated', 'success')
      onSaved()
    } catch (err) {
      addToast(err.message || 'Failed to update organization', 'error')
    } finally {
      setSaving(false)
    }
  }

  const onLogoPick = async (file) => {
    if (!file) return
    if (!file.type.startsWith('image/')) { addToast('Please upload an image', 'error'); return }
    if (file.size > MAX_LOGO_BYTES) { addToast('Image must be under 5MB', 'error'); return }
    setUploading(true)
    try {
      await uploadOrgLogo(org.id, file)
      addToast('Logo updated', 'success')
      onSaved()
    } catch (err) {
      addToast(err.message || 'Failed to upload logo', 'error')
    } finally {
      setUploading(false)
    }
  }

  return (
    <Card className="p-5 space-y-4">
      <div className="flex items-center gap-3">
        <div className="w-12 h-12 rounded-xl overflow-hidden bg-slate-100 flex items-center justify-center flex-shrink-0">
          {org.logo ? <img src={org.logo} alt="" className="w-full h-full object-cover" /> : <Building2 size={20} className="text-slate-400" />}
        </div>
        <div className="flex-1 min-w-0">
          <p className="font-bold text-[14px] truncate">{org.name}</p>
          <p className="text-[11px] text-slate-400">/org/{org.slug}</p>
        </div>
        <Badge color={isOwner ? 'dark' : 'slate'}>{isOwner ? 'Owner' : 'Member'}</Badge>
      </div>

      {isOwner ? (
        <form onSubmit={save} className="space-y-3">
          <div className="flex items-center gap-3">
            <Btn type="button" variant="secondary" size="sm" icon={Camera} loading={uploading} onClick={() => fileRef.current?.click()}>Change logo</Btn>
            <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={e => { onLogoPick(e.target.files?.[0]); e.target.value = '' }} />
          </div>
          <Input label="Name" value={form.name} onChange={update('name')} required />
          <Textarea label="Description" value={form.description} onChange={update('description')} rows={2} />
          <Input label="Contact email" type="email" value={form.email} onChange={update('email')} />
          <div className="flex justify-end"><Btn type="submit" variant="secondary" size="sm" loading={saving}>Save</Btn></div>
        </form>
      ) : (
        <p className="text-[12px] text-slate-400">Only an owner can edit this organization's profile.</p>
      )}
    </Card>
  )
}
