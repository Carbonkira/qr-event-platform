import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { Plus, Trash2, ArrowLeft, MapPin, MapPinned, Image as ImageIcon, Users, Ticket, DollarSign, UserCheck, Lock, Award, MessageSquare, Tag, Send, Upload, X } from 'lucide-react'
import { Btn, Card, Input, Select, Textarea, Toggle } from '../../components/ui'
import LocationPicker from '../../components/shared/LocationPicker'
import { useAdminEvents, useMyOrgs } from '../../hooks/useApi'
import { updateEvent, submitEvent, uploadEventImage } from '../../api/resources'
import { useApp } from '../../context/AppContext'
import { cn, INDUSTRIES } from '../../lib/utils'

const DEFAULT_FEEDBACK_QUESTIONS = [
  { id: 'q1', label: 'Check-in experience', type: 'rating', required: true },
  { id: 'q2', label: 'Event organization', type: 'rating', required: true },
  { id: 'q3', label: 'Content quality', type: 'rating', required: true },
  { id: 'q4', label: 'Venue & facilities', type: 'rating', required: true },
  { id: 'q5', label: 'Overall satisfaction', type: 'rating', required: true },
]

const MAX_IMAGE_BYTES = 5 * 1024 * 1024 // 5MB — matches the backend's own limit

const TYPE_OPTIONS = ['Meetup', 'Conference', 'Workshop', 'Seminar', 'Networking', 'Training'].map(v => ({ value: v, label: v }))
const INDUSTRY_OPTIONS = INDUSTRIES.map(v => ({ value: v, label: v }))
const FIELD_TYPE_OPTIONS = [
  { value: 'text', label: 'Text' },
  { value: 'number', label: 'Number' },
  { value: 'select', label: 'Select' },
  { value: 'checkbox', label: 'Checkbox' },
]

function formFromEvent(event) {
  return {
    title: event.title || '', type: event.type || 'Conference', isPrivate: !!event.isPrivate, description: event.description || '',
    venue: event.venue || '', location: event.location || '', lat: event.lat ?? null, lng: event.lng ?? null,
    date: event.date || '', startTime: event.startTime || '', endTime: event.endTime || '',
    organizedBy: event.organizedBy || '', industry: event.industry || 'Technology', capacity: String(event.capacity ?? ''),
    feedbackEnabled: event.feedbackEnabled ?? true, image: event.image || '', requiresCertificate: !!event.requiresCertificate,
    pricing: event.pricing || 'free', price: String(event.price || ''), allowWalkIns: !!event.allowWalkIns,
    privacyPolicyUrl: event.privacyPolicyUrl || '',
    customFields: (event.customFields || []).map((f, i) => ({ id: f.id || `cf-${i}`, ...f })),
    feedbackQuestions: (event.feedbackQuestions?.length ? event.feedbackQuestions : DEFAULT_FEEDBACK_QUESTIONS).map(q => ({ ...q })),
    tags: (event.tags || []).join(', '),
  }
}

export default function EditEvent() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { user, addToast } = useApp()
  const { data: eventsData } = useAdminEvents()
  const event = (eventsData || []).find(e => String(e.id) === id)
  const { data: myOrgs } = useMyOrgs()
  // Matches EventController::authorizeOrgMember: any member of the event's
  // organization can edit it, not just whoever originally created it.
  const canManage = event && (event.organizationId == null || (myOrgs || []).some(o => o.id === event.organizationId))

  const [form, setForm] = useState(null)
  const [errors, setErrors] = useState({})
  const [loading, setLoading] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [imgUploading, setImgUploading] = useState(false)

  useEffect(() => { if (event && !form) setForm(formFromEvent(event)) }, [event, form])

  if (event && !canManage) {
    return (
      <div className="max-w-md mx-auto py-20">
        <Card className="p-8 text-center">
          <h2 className="text-lg font-bold mb-1">Not your event</h2>
          <p className="text-[13px] text-slate-500 mb-6">Only a member of this event's organization can edit it.</p>
          <Btn variant="secondary" full onClick={() => navigate(`/organizer/events/${event.id}`)}>Back to event</Btn>
        </Card>
      </div>
    )
  }

  if (!form) return <div className="text-center py-20 text-slate-400 text-[13px]">Loading event…</div>

  const up = (k, v) => { setForm(f => ({ ...f, [k]: v })); setErrors(e => ({ ...e, [k]: undefined })) }
  const onImageFile = async (file) => {
    if (!file) return
    if (!file.type.startsWith('image/')) { addToast('Please upload an image', 'error'); return }
    if (file.size > MAX_IMAGE_BYTES) { addToast('Image must be under 5MB', 'error'); return }
    setImgUploading(true)
    try {
      const url = await uploadEventImage(file)
      up('image', url)
    } catch (err) {
      addToast(err.message || 'Failed to upload image', 'error')
    } finally {
      setImgUploading(false)
    }
  }
  // No hyphen/underscore: see the note in CreateEventModal.jsx — this id
  // doubles as a customData dictionary key and must survive the API's
  // camelCase<->snake_case re-casing unchanged.
  const addCustomField = () => setForm(f => ({ ...f, customFields: [...f.customFields, { id: `cf${Date.now()}`, label: '', type: 'text', required: false }] }))
  const updateCustomField = (i, patch) => setForm(f => ({ ...f, customFields: f.customFields.map((c, idx) => idx === i ? { ...c, ...patch } : c) }))
  const removeCustomField = (i) => setForm(f => ({ ...f, customFields: f.customFields.filter((_, idx) => idx !== i) }))

  // First 5 (q1-q5) are the fixed rating columns - relabel only.
  const updQuestion = (id, k, v) => setForm(f => ({ ...f, feedbackQuestions: f.feedbackQuestions.map(q => q.id === id ? { ...q, [k]: v } : q) }))
  const addQuestion = () => setForm(f => ({ ...f, feedbackQuestions: [...f.feedbackQuestions, { id: `fq${Date.now()}`, label: '', type: 'text', required: false }] }))
  const delQuestion = (id) => setForm(f => ({ ...f, feedbackQuestions: f.feedbackQuestions.filter(q => q.id !== id) }))

  const submitForApproval = async () => {
    setSubmitting(true)
    try {
      await submitEvent(event.id)
      addToast('Submitted for approval', 'success')
      navigate(`/organizer/events/${event.id}`)
    } catch (err) {
      const firstError = err.errors ? Object.values(err.errors)[0]?.[0] : null
      addToast(firstError || err.message || 'Failed to submit event', 'error')
    } finally {
      setSubmitting(false)
    }
  }

  const save = async () => {
    if (!form.title.trim() || !form.venue.trim() || !form.date) {
      addToast('Title, venue and date are required', 'error')
      return
    }
    setLoading(true)
    try {
      const payload = {
        ...form,
        capacity: parseInt(form.capacity) || 50,
        price: form.pricing === 'paid' ? (parseInt(form.price) || 0) : 0,
        customFields: form.customFields.filter(f => f.label.trim()),
        tags: form.tags.split(',').map(t => t.trim()).filter(Boolean),
      }
      await updateEvent(event.id, payload)
      addToast('Event updated!', 'success')
      navigate(`/organizer/events/${event.id}`)
    } catch (err) {
      if (err.errors) {
        const fieldErrs = {}
        Object.entries(err.errors).forEach(([k, v]) => { fieldErrs[k] = Array.isArray(v) ? v[0] : v })
        setErrors(fieldErrs)
      }
      addToast(err.message || 'Failed to update event', 'error')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="space-y-5 max-w-3xl">
      <div className="flex items-center gap-3">
        <button onClick={() => navigate(`/organizer/events/${event.id}`)} className="p-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-50"><ArrowLeft size={16} /></button>
        <div><h1 className="text-xl font-extrabold">Edit Event</h1><p className="text-[13px] text-slate-500">Update details — changes go live immediately</p></div>
      </div>

      <div className="bg-white rounded-2xl border border-slate-200/70 p-6 space-y-4">
        <p className="font-bold text-[13px]">Details</p>
        <Input label="Title" value={form.title} onChange={e => up('title', e.target.value)} error={errors.title} required />
        <div className="grid grid-cols-2 gap-3">
          <Select label="Type" value={form.type} onChange={e => up('type', e.target.value)} options={TYPE_OPTIONS} />
          <Select label="Industry" value={form.industry} onChange={e => up('industry', e.target.value)} options={INDUSTRY_OPTIONS} />
        </div>
        <Textarea label="Description" value={form.description} onChange={e => up('description', e.target.value)} rows={4} />

        <div className="space-y-2">
          <p className="text-[12px] font-semibold text-slate-600">Venue & Location <span className="text-rose-500">*</span></p>
          <LocationPicker venue={form.venue} location={form.location} lat={form.lat} lng={form.lng} onChange={patch => setForm(f => ({ ...f, ...patch }))} />
          {errors.location && <p className="text-[11px] text-rose-600">{errors.location}</p>}
        </div>

        <div className="grid grid-cols-3 gap-3">
          <Input label="Date" value={form.date} onChange={e => up('date', e.target.value)} type="date" error={errors.date} required />
          <Input label="Start" value={form.startTime} onChange={e => up('startTime', e.target.value)} type="time" />
          <Input label="End" value={form.endTime} onChange={e => up('endTime', e.target.value)} type="time" />
        </div>
        <div>
          <div className="flex items-center justify-between mb-1.5">
            <label className="text-[12px] font-semibold text-slate-600">Cover / Pubmat Image</label>
            {form.image && <button type="button" onClick={() => up('image', '')} className="text-[11px] font-semibold text-rose-500 hover:text-rose-600 flex items-center gap-1"><X size={11} />Remove</button>}
          </div>
          {form.image ? (
            <div className="rounded-lg overflow-hidden border border-slate-200 aspect-[2/1] bg-slate-50">
              <img src={form.image} alt="preview" className="w-full h-full object-cover" onError={e => e.target.style.display = 'none'} />
            </div>
          ) : (
            <label className={cn('flex flex-col items-center justify-center gap-2 py-8 rounded-xl border-2 border-dashed cursor-pointer transition-all', imgUploading ? 'border-slate-200 bg-slate-50 opacity-60' : 'border-slate-200 hover:border-slate-300 bg-slate-50')}>
              {imgUploading ? <span className="w-5 h-5 border-2 border-slate-300 border-t-[#1a1a2e] rounded-full animate-spin" /> : <Upload size={22} className="text-slate-400" />}
              <span className="text-[12px] font-semibold text-slate-500">{imgUploading ? 'Uploading…' : 'Tap to upload a cover image'}</span>
              <span className="text-[10px] text-slate-400">JPG or PNG, max 5MB · 2:1 landscape recommended</span>
              <input type="file" accept="image/*" className="hidden" disabled={imgUploading} onChange={e => onImageFile(e.target.files?.[0])} />
            </label>
          )}
          <Input value={form.image} onChange={e => up('image', e.target.value)} icon={ImageIcon} placeholder="…or paste an image URL instead" />
        </div>
        <Input label="Capacity" value={form.capacity} onChange={e => up('capacity', e.target.value)} type="number" icon={Users} error={errors.capacity} required />
      </div>

      <div className="bg-white rounded-2xl border border-slate-200/70 p-6 space-y-4">
        <p className="font-bold text-[13px]">Pricing & Settings</p>
        <div className="grid grid-cols-3 gap-2">
          {[['free', 'Free', Ticket], ['paid', 'Paid', DollarSign], ['walk-in', 'Walk-in', UserCheck]].map(([k, l, Icon]) => (
            <button key={k} type="button" onClick={() => up('pricing', k)} className={cn('flex flex-col items-center gap-1.5 p-3 rounded-xl border transition-all', form.pricing === k ? 'border-[#1a1a2e] bg-slate-50' : 'border-slate-200 hover:border-slate-300')}>
              <Icon size={18} className={form.pricing === k ? 'text-[#1a1a2e]' : 'text-slate-400'} /><span className={cn('text-[12px] font-semibold', form.pricing === k ? 'text-slate-800' : 'text-slate-500')}>{l}</span>
            </button>
          ))}
        </div>
        {form.pricing === 'paid' && <Input label="Price (₱)" value={form.price} onChange={e => up('price', e.target.value)} type="number" icon={DollarSign} />}
        <Toggle checked={form.allowWalkIns} onChange={v => up('allowWalkIns', v)} icon={UserCheck} label="Allow walk-ins" color="#0f9d8f" />
        <Toggle checked={form.isPrivate} onChange={v => up('isPrivate', v)} icon={Lock} label="Private event" color="#e94560" />
        <Toggle checked={form.requiresCertificate} onChange={v => up('requiresCertificate', v)} icon={Award} label="Certificate of attendance" color="#6d28d9" />
        <Toggle checked={form.feedbackEnabled} onChange={v => up('feedbackEnabled', v)} icon={MessageSquare} label="Collect feedback" color="#1a1a2e" />
        <Input label="Tags" value={form.tags} onChange={e => up('tags', e.target.value)} icon={Tag} placeholder="AI, Networking" />
      </div>

      <div className="bg-white rounded-2xl border border-slate-200/70 p-6 space-y-3">
        <div className="flex items-center justify-between">
          <p className="font-bold text-[13px]">Custom registration fields</p>
          <Btn variant="ghost" size="sm" icon={Plus} onClick={addCustomField}>Add field</Btn>
        </div>
        {form.customFields.map((f, i) => (
          <div key={f.id} className="flex gap-2 items-end">
            <div className="flex-1"><Input label={i === 0 ? 'Label' : undefined} value={f.label} onChange={e => updateCustomField(i, { label: e.target.value })} /></div>
            <div className="w-32"><Select label={i === 0 ? 'Type' : undefined} value={f.type} onChange={e => updateCustomField(i, { type: e.target.value })} options={FIELD_TYPE_OPTIONS} /></div>
            <button type="button" onClick={() => updateCustomField(i, { required: !f.required })} className={cn('px-3 py-2.5 rounded-xl text-[11px] font-semibold border', f.required ? 'bg-[#1a1a2e] text-white border-[#1a1a2e]' : 'bg-white text-slate-500 border-slate-200')}>Req</button>
            <button type="button" onClick={() => removeCustomField(i)} className="p-2.5 rounded-xl text-slate-400 hover:text-rose-500 hover:bg-rose-50"><Trash2 size={15} /></button>
          </div>
        ))}
        {form.customFields.length === 0 && <p className="text-[11px] text-slate-400">No custom fields.</p>}
      </div>

      {form.feedbackEnabled && (
        <div className="bg-white rounded-2xl border border-slate-200/70 p-6 space-y-3">
          <div className="flex items-center justify-between">
            <p className="font-bold text-[13px]">Feedback form</p>
            <Btn variant="ghost" size="sm" icon={Plus} onClick={addQuestion}>Add question</Btn>
          </div>
          {form.feedbackQuestions.map((q, i) => {
            const isCore = i < 5
            return (
              <div key={q.id} className="flex gap-2 items-center">
                <div className="flex-1"><Input value={q.label} onChange={e => updQuestion(q.id, 'label', e.target.value)} /></div>
                {isCore ? (
                  <span className="text-[11px] font-semibold px-3 py-2.5 rounded-xl bg-slate-100 text-slate-500">Rating</span>
                ) : (
                  <div className="w-28"><Select value={q.type} onChange={e => updQuestion(q.id, 'type', e.target.value)} options={[{ value: 'text', label: 'Text' }, { value: 'rating', label: 'Rating' }]} /></div>
                )}
                <button type="button" onClick={() => updQuestion(q.id, 'required', !q.required)} className={cn('px-3 py-2.5 rounded-xl text-[11px] font-semibold border', q.required ? 'bg-[#1a1a2e] text-white border-[#1a1a2e]' : 'bg-white text-slate-500 border-slate-200')}>Req</button>
                {!isCore && <button type="button" onClick={() => delQuestion(q.id)} className="p-2.5 rounded-xl text-slate-400 hover:text-rose-500 hover:bg-rose-50"><Trash2 size={15} /></button>}
              </div>
            )
          })}
        </div>
      )}

      <div className="flex items-center justify-between">
        <Btn variant="ghost" onClick={() => navigate(`/organizer/events/${event.id}`)}>Cancel</Btn>
        <div className="flex items-center gap-2">
          {event.status === 'draft' && <Btn variant="primary" icon={Send} loading={submitting} onClick={submitForApproval}>Submit for Approval</Btn>}
          <Btn variant="accent" size="lg" loading={loading} onClick={save}>Save Changes</Btn>
        </div>
      </div>
    </div>
  )
}
