import { useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { Building2, Camera, Plus, Mail, X, UserMinus, ExternalLink } from 'lucide-react'
import { Card, Btn, Input, Textarea, Badge } from '../../components/ui'
import { useApp } from '../../context/AppContext'
import { useMyOrgs, useOrgMembers, useOrgInvites } from '../../hooks/useApi'
import { createOrg, updateOrg, uploadOrgLogo, removeOrgMember, inviteToOrg, revokeOrgInvite } from '../../api/resources'

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
  const { addToast, user } = useApp()
  const isOwner = org.pivot?.role === 'owner'
  const [form, setForm] = useState({ name: org.name, description: org.description || '', email: org.email || '' })
  const [saving, setSaving] = useState(false)
  const [uploading, setUploading] = useState(false)
  const fileRef = useRef(null)
  const { data: membersData, refetch: refetchMembers } = useOrgMembers(org.id)
  const { data: invitesData, refetch: refetchInvites } = useOrgInvites(org.id, isOwner)
  const members = membersData || []
  const invites = invitesData || []
  const [inviteEmail, setInviteEmail] = useState('')
  const [inviting, setInviting] = useState(false)

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

  const sendInvite = async (e) => {
    e.preventDefault()
    if (!inviteEmail.trim()) return
    setInviting(true)
    try {
      await inviteToOrg(org.id, inviteEmail.trim())
      addToast(`Invite sent to ${inviteEmail.trim()}`, 'success')
      setInviteEmail('')
      refetchInvites()
    } catch (err) {
      addToast(err.message || 'Failed to send invite', 'error')
    } finally {
      setInviting(false)
    }
  }

  const cancelInvite = async (inviteId) => {
    try {
      await revokeOrgInvite(org.id, inviteId)
      addToast('Invite revoked', 'success')
      refetchInvites()
    } catch (err) {
      addToast(err.message || 'Failed to revoke invite', 'error')
    }
  }

  const kickMember = async (memberId) => {
    try {
      await removeOrgMember(org.id, memberId)
      addToast('Member removed', 'success')
      refetchMembers()
    } catch (err) {
      addToast(err.message || 'Failed to remove member', 'error')
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
          <Link to={`/org/${org.slug}`} className="text-[11px] text-slate-400 hover:text-[#e94560] inline-flex items-center gap-1">/org/{org.slug}<ExternalLink size={10} /></Link>
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

      <div className="border-t border-slate-100 pt-4 space-y-2">
        <p className="text-[12px] font-bold text-slate-700">Members ({members.length})</p>
        <div className="space-y-1.5">
          {members.map(m => (
            <div key={m.id} className="flex items-center justify-between gap-2 text-[13px] py-1">
              <span className="truncate">{m.name}{m.id === user?.id && ' (you)'}</span>
              <div className="flex items-center gap-2 flex-shrink-0">
                <Badge color={m.pivot?.role === 'owner' ? 'dark' : 'slate'} size="xs">{m.pivot?.role}</Badge>
                {isOwner && m.pivot?.role !== 'owner' && (
                  <button type="button" onClick={() => kickMember(m.id)} className="p-1 text-slate-400 hover:text-rose-500" title="Remove member"><UserMinus size={13} /></button>
                )}
              </div>
            </div>
          ))}
        </div>
      </div>

      {isOwner && (
        <div className="border-t border-slate-100 pt-4 space-y-2">
          <p className="text-[12px] font-bold text-slate-700">Invite a member</p>
          <form onSubmit={sendInvite} className="flex items-end gap-2">
            <div className="flex-1"><Input value={inviteEmail} onChange={e => setInviteEmail(e.target.value)} type="email" icon={Mail} placeholder="teammate@example.com" /></div>
            <Btn type="submit" variant="secondary" size="sm" loading={inviting}>Send invite</Btn>
          </form>
          {invites.length > 0 && (
            <div className="space-y-1.5 pt-1">
              {invites.map(inv => (
                <div key={inv.id} className="flex items-center justify-between gap-2 text-[12px] py-1">
                  <span className="truncate text-slate-500">{inv.email} <span className="text-slate-400">· pending</span></span>
                  <button type="button" onClick={() => cancelInvite(inv.id)} className="p-1 text-slate-400 hover:text-rose-500 flex-shrink-0" title="Revoke invite"><X size={13} /></button>
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </Card>
  )
}
