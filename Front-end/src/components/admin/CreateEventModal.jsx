import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  X, Check, ChevronLeft, ChevronRight, Send, Wand2, MapPin, MapPinned,
  Image as ImageIcon, AlertCircle, Ticket, DollarSign, UserCheck, Lock, Award,
  MessageSquare, Instagram, Linkedin, Facebook, Globe, Shield, User, Mail,
  Plus, Tag, Trash2, Hourglass, Upload,
} from 'lucide-react'
import { Modal, Btn, Input, Select, Textarea, Toggle, Badge } from '../ui'
import LocationPicker from '../shared/LocationPicker'
import { createEvent, generateEventDescription, uploadEventImage } from '../../api/resources'
import { useTaskTemplates } from '../../hooks/useApi'
import { cn } from '../../lib/utils'

const DEFAULT_FEEDBACK_QUESTIONS = [
  { id: 'q1', label: 'Check-in experience', type: 'rating', required: true },
  { id: 'q2', label: 'Event organization', type: 'rating', required: true },
  { id: 'q3', label: 'Content quality', type: 'rating', required: true },
  { id: 'q4', label: 'Venue & facilities', type: 'rating', required: true },
  { id: 'q5', label: 'Overall satisfaction', type: 'rating', required: true },
]

const blankForm = () => ({
  title: '', type: 'Meetup', description: '', venue: '', location: '', lat: null, lng: null,
  date: '', startTime: '', endTime: '',
  organizedBy: '', industry: 'Technology', capacity: '50',
  image: '', pricing: 'free', price: '', allowWalkIns: true, isPrivate: false, requiresCertificate: false,
  feedbackEnabled: true, privacyPolicyUrl: '', taskTemplateId: '',
  socials: { instagram: '', linkedin: '', facebook: '', website: '' },
  customFields: [], tags: '',
  feedbackQuestions: DEFAULT_FEEDBACK_QUESTIONS.map(q => ({ ...q })),
})

const STEPS = ['Details', 'Settings', 'Registration']
const MAX_IMAGE_BYTES = 5 * 1024 * 1024 // 5MB — matches the backend's own limit

// 3-step event creation wizard, rendered once globally by OrgShell. On
// success it navigates to the new event's detail page — that page fetches
// fresh data on mount, so no page-specific list-refresh wiring is needed.
export default function CreateEventModal({ open, onClose, toast, onCreated }) {
  const navigate = useNavigate()
  const { data: templatesData } = useTaskTemplates()
  const templates = templatesData || []

  const [form, setForm] = useState(blankForm)
  const [step, setStep] = useState(1)
  const [loading, setLoading] = useState(false)
  const [genLoading, setGenLoading] = useState(false)
  const [imgError, setImgError] = useState('')
  const [imgUploading, setImgUploading] = useState(false)
  const [errors, setErrors] = useState({})

  useEffect(() => { if (open) { setForm(blankForm()); setStep(1); setImgError(''); setErrors({}) } }, [open])

  const up = (k, v) => { setForm(f => ({ ...f, [k]: v })); setErrors(e => ({ ...e, [k]: undefined })) }
  const upSocial = (k, v) => setForm(f => ({ ...f, socials: { ...f.socials, [k]: v } }))

  const genDesc = async () => {
    if (!form.title.trim()) { toast?.('Give the event a title first', 'error'); return }
    setGenLoading(true)
    try {
      const d = await generateEventDescription(form.title, form.type, form.industry)
      up('description', d)
      toast?.('Description generated ✨', 'info')
    } catch (err) {
      toast?.(err.message || 'Failed to generate a description', 'error')
    } finally {
      setGenLoading(false)
    }
  }

  const checkImage = (url) => {
    setImgError('')
    if (!url) return
    const img = new Image()
    img.onload = () => {
      const ratio = img.width / img.height
      if (img.width < 800) setImgError(`Image is ${img.width}px wide — use at least 800px for crisp pubmats`)
      else if (ratio < 1.3 || ratio > 2.6) setImgError(`Aspect ratio ${ratio.toFixed(2)}:1 — recommended 2:1 landscape (e.g. 1200×600)`)
      else setImgError('')
    }
    img.onerror = () => setImgError("Couldn't load that image URL")
    img.src = url
  }

  const onImageFile = async (file) => {
    if (!file) return
    if (!file.type.startsWith('image/')) { toast?.('Please upload an image', 'error'); return }
    if (file.size > MAX_IMAGE_BYTES) { toast?.('Image must be under 5MB', 'error'); return }
    setImgUploading(true)
    setImgError('')
    try {
      const url = await uploadEventImage(file)
      up('image', url)
      checkImage(url)
    } catch (err) {
      toast?.(err.message || 'Failed to upload image', 'error')
    } finally {
      setImgUploading(false)
    }
  }

  // No hyphen/underscore in the id: it doubles as a customData dictionary
  // key, and the API's camelCase<->snake_case boundary middleware re-cases
  // object *keys* using Str::camel/Str::snake, which strip "-"/"_" as word
  // delimiters — a hyphenated id would come back mangled and stop matching.
  const addField = () => up('customFields', [...form.customFields, { id: `cf${Date.now()}`, label: '', type: 'text', required: false }])
  const updField = (id, k, v) => up('customFields', form.customFields.map(f => f.id === id ? { ...f, [k]: v } : f))
  const delField = (id) => up('customFields', form.customFields.filter(f => f.id !== id))

  // First 5 (q1-q5) are the fixed rating columns - relabel only, can't
  // remove or retype. Anything past that is an organizer-added extra
  // question, answered into feedback.customAnswers.
  const updQuestion = (id, k, v) => up('feedbackQuestions', form.feedbackQuestions.map(q => q.id === id ? { ...q, [k]: v } : q))
  const addQuestion = () => up('feedbackQuestions', [...form.feedbackQuestions, { id: `fq${Date.now()}`, label: '', type: 'text', required: false }])
  const delQuestion = (id) => up('feedbackQuestions', form.feedbackQuestions.filter(q => q.id !== id))

  const handleClose = () => { onClose() }

  const submit = async (saveAsDraft = false) => {
    if (!saveAsDraft && (!form.title.trim() || !form.venue.trim() || !form.date)) {
      toast?.('Fill in title, venue and date', 'error')
      setStep(1)
      return
    }
    if (saveAsDraft && !form.title.trim()) {
      toast?.('Give the event a title before saving', 'error')
      setStep(1)
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
        taskTemplateId: form.taskTemplateId || null,
        saveAsDraft,
      }
      const created = await createEvent(payload)
      toast?.(saveAsDraft ? 'Draft saved!' : 'Event submitted for approval!', 'success')
      handleClose()
      onCreated?.(created)
      if (created?.id) navigate(saveAsDraft ? `/organizer/events/${created.id}/edit` : `/organizer/events/${created.id}`)
    } catch (err) {
      if (err.errors) {
        const fieldErrs = {}
        Object.entries(err.errors).forEach(([k, v]) => { fieldErrs[k] = Array.isArray(v) ? v[0] : v })
        setErrors(fieldErrs)
      }
      toast?.(err.message || 'Failed to create event', 'error')
    } finally {
      setLoading(false)
    }
  }

  return (
    <Modal open={open} onClose={handleClose} size="lg">
      <div className="px-6 py-4 border-b border-slate-100 sticky top-0 bg-white rounded-t-2xl z-10">
        <div className="flex items-center justify-between mb-3">
          <h3 className="font-bold text-slate-800">Create Event</h3>
          <button onClick={handleClose} className="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400"><X size={18} /></button>
        </div>
        <div className="flex items-center gap-1.5">
          {STEPS.map((s, i) => (
            <div key={s} className="flex items-center gap-1.5 flex-1">
              <div className={cn('flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-semibold transition-all', step === i + 1 ? 'bg-[#1a1a2e] text-white' : step > i + 1 ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-400')}>
                {step > i + 1 ? <Check size={11} /> : <span>{i + 1}</span>}{s}
              </div>
              {i < STEPS.length - 1 && <div className={cn('h-px flex-1', step > i + 1 ? 'bg-emerald-300' : 'bg-slate-200')} />}
            </div>
          ))}
        </div>
      </div>

      <div className="p-6 max-h-[60vh] overflow-y-auto">
        {step === 1 && (
          <div className="space-y-4">
            <Input label="Event Title" value={form.title} onChange={e => up('title', e.target.value)} placeholder="Founder Networking Night" error={errors.title} required />
            <div className="grid grid-cols-2 gap-3">
              <Select label="Type" value={form.type} onChange={e => up('type', e.target.value)} options={['Meetup', 'Conference', 'Workshop', 'Seminar', 'Networking', 'Training'].map(v => ({ value: v, label: v }))} />
              <Select label="Industry" value={form.industry} onChange={e => up('industry', e.target.value)} options={['Technology', 'Education', 'Design', 'Finance', 'Healthcare', 'Marketing', 'Real Estate', 'Other'].map(v => ({ value: v, label: v }))} />
            </div>
            <div>
              <div className="flex items-center justify-between mb-1.5">
                <label className="text-[12px] font-semibold text-slate-600">Description</label>
                <button type="button" onClick={genDesc} disabled={genLoading} className="inline-flex items-center gap-1.5 text-[11px] font-semibold text-[#6d28d9] hover:text-[#5b21b6] disabled:opacity-50">
                  {genLoading ? <span className="w-3 h-3 border-2 border-current border-t-transparent rounded-full animate-spin" /> : <Wand2 size={12} />}
                  {genLoading ? 'Generating…' : 'Generate description'}
                </button>
              </div>
              <Textarea value={form.description} onChange={e => up('description', e.target.value)} rows={4} placeholder="Describe your event, or generate a draft…" />
            </div>
            <div className="space-y-3">
              <p className="text-[12px] font-semibold text-slate-600">Venue & Location <span className="text-rose-500">*</span></p>
              <LocationPicker venue={form.venue} location={form.location} lat={form.lat} lng={form.lng} onChange={patch => setForm(f => ({ ...f, ...patch }))} />
              {errors.location && <p className="text-[11px] text-rose-600 flex items-center gap-1"><AlertCircle size={11} />{errors.location}</p>}
            </div>
            <div className="grid grid-cols-3 gap-3">
              <Input label="Date" value={form.date} onChange={e => up('date', e.target.value)} type="date" error={errors.date} required />
              <Input label="Start" value={form.startTime} onChange={e => up('startTime', e.target.value)} type="time" />
              <Input label="End" value={form.endTime} onChange={e => up('endTime', e.target.value)} type="time" />
            </div>
            <div>
              <div className="flex items-center justify-between mb-1.5">
                <label className="text-[12px] font-semibold text-slate-600">Cover / Pubmat Image</label>
                {form.image && <button type="button" onClick={() => { up('image', ''); setImgError('') }} className="text-[11px] font-semibold text-rose-500 hover:text-rose-600 flex items-center gap-1"><X size={11} />Remove</button>}
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
              {imgError && <p className="text-[11px] text-amber-600 mt-1.5 flex items-center gap-1"><AlertCircle size={11} />{imgError}</p>}
              <Input value={form.image} onChange={e => { up('image', e.target.value); checkImage(e.target.value) }} icon={ImageIcon} placeholder="…or paste an image URL instead" hint="Recommended: 2:1 landscape, min 800px wide, for crisp pubmats" />
            </div>
            <Input label="Capacity" value={form.capacity} onChange={e => up('capacity', e.target.value)} type="number" error={errors.capacity} required />
          </div>
        )}

        {step === 2 && (
          <div className="space-y-5">
            <div>
              <p className="text-[12px] font-bold text-slate-700 mb-2">Pricing</p>
              <div className="grid grid-cols-3 gap-2">
                {[['free', 'Free', Ticket], ['paid', 'Paid', DollarSign], ['walk-in', 'Walk-in', UserCheck]].map(([k, l, Icon]) => (
                  <button key={k} type="button" onClick={() => up('pricing', k)} className={cn('flex flex-col items-center gap-1.5 p-3 rounded-xl border transition-all', form.pricing === k ? 'border-[#1a1a2e] bg-slate-50' : 'border-slate-200 hover:border-slate-300')}>
                    <Icon size={18} className={form.pricing === k ? 'text-[#1a1a2e]' : 'text-slate-400'} />
                    <span className={cn('text-[12px] font-semibold', form.pricing === k ? 'text-slate-800' : 'text-slate-500')}>{l}</span>
                  </button>
                ))}
              </div>
              {form.pricing === 'paid' && <div className="mt-3"><Input label="Price (₱)" value={form.price} onChange={e => up('price', e.target.value)} type="number" icon={DollarSign} placeholder="500" /></div>}
            </div>

            <div className="space-y-2.5">
              <Toggle checked={form.allowWalkIns} onChange={v => up('allowWalkIns', v)} icon={UserCheck} label="Allow walk-ins" desc="Let guests register on-site without filling the form" color="#0f9d8f" />
              <Toggle checked={form.isPrivate} onChange={v => up('isPrivate', v)} icon={Lock} label="Private event" desc="Only people with the invite link can view & register" color="#e94560" />
              <Toggle checked={form.requiresCertificate} onChange={v => up('requiresCertificate', v)} icon={Award} label="Certificate of attendance" desc="Attendees can request a certificate" color="#6d28d9" />
              <Toggle checked={form.feedbackEnabled} onChange={v => up('feedbackEnabled', v)} icon={MessageSquare} label="Collect feedback" desc="Enable post-event ratings & comments" color="#1a1a2e" />
            </div>

            <div>
              <p className="text-[12px] font-bold text-slate-700 mb-2">Organizer socials</p>
              <div className="grid grid-cols-2 gap-2">
                <Input value={form.socials.instagram} onChange={e => upSocial('instagram', e.target.value)} icon={Instagram} placeholder="instagram" />
                <Input value={form.socials.linkedin} onChange={e => upSocial('linkedin', e.target.value)} icon={Linkedin} placeholder="linkedin" />
                <Input value={form.socials.facebook} onChange={e => upSocial('facebook', e.target.value)} icon={Facebook} placeholder="facebook" />
                <Input value={form.socials.website} onChange={e => upSocial('website', e.target.value)} icon={Globe} placeholder="website.com" />
              </div>
            </div>

            <Input label="Privacy Policy URL" value={form.privacyPolicyUrl} onChange={e => up('privacyPolicyUrl', e.target.value)} icon={Shield} placeholder="https://yoursite.com/privacy" hint="Linked on your event & registration pages" />

            <div>
              <p className="text-[12px] font-bold text-slate-700 mb-2">Checklist / To-do template</p>
              <Select value={form.taskTemplateId} onChange={e => up('taskTemplateId', e.target.value)} options={[{ value: '', label: "None — I'll add tasks later" }, ...templates.map(t => ({ value: String(t.id), label: `${t.name} (${(t.tasks || []).length} tasks)` }))]} />
            </div>
          </div>
        )}

        {step === 3 && (
          <div className="space-y-4">
            <div className="rounded-xl bg-slate-50 border border-slate-200 p-4">
              <p className="text-[12px] font-bold text-slate-700 mb-1">Default registration fields</p>
              <p className="text-[11px] text-slate-500 mb-3">Every attendee provides these.</p>
              <div className="flex gap-2">
                <Badge color="dark"><User size={10} />Name</Badge>
                <Badge color="dark"><Mail size={10} />Email</Badge>
              </div>
            </div>

            <div>
              <div className="flex items-center justify-between mb-2">
                <p className="text-[12px] font-bold text-slate-700">Custom fields</p>
                <button type="button" onClick={addField} className="text-[11px] font-semibold text-[#e94560] flex items-center gap-1"><Plus size={12} />Add field</button>
              </div>
              {form.customFields.length === 0 ? <p className="text-[12px] text-slate-400 py-3 text-center bg-slate-50 rounded-xl">No custom fields. Add one to collect extra info (e.g. company name).</p> : (
                <div className="space-y-2">
                  {form.customFields.map(f => (
                    <div key={f.id} className="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200">
                      <input value={f.label} onChange={e => updField(f.id, 'label', e.target.value)} placeholder="Field label" className="flex-1 text-sm px-2.5 py-1.5 rounded-lg border border-slate-200 outline-none focus:border-[#1a1a2e]" />
                      <button type="button" onClick={() => updField(f.id, 'required', !f.required)} className={cn('text-[11px] font-semibold px-2.5 py-1.5 rounded-lg', f.required ? 'bg-[#1a1a2e] text-white' : 'bg-slate-100 text-slate-500')}>{f.required ? 'Required' : 'Optional'}</button>
                      <button type="button" onClick={() => delField(f.id)} className="p-1.5 text-slate-400 hover:text-rose-500"><Trash2 size={14} /></button>
                    </div>
                  ))}
                </div>
              )}
            </div>
            <Input label="Tags" value={form.tags} onChange={e => up('tags', e.target.value)} icon={Tag} placeholder="AI, Networking (comma separated)" />

            {form.feedbackEnabled && (
              <div>
                <div className="flex items-center justify-between mb-2">
                  <p className="text-[12px] font-bold text-slate-700">Feedback form</p>
                  <button type="button" onClick={addQuestion} className="text-[11px] font-semibold text-[#e94560] flex items-center gap-1"><Plus size={12} />Add question</button>
                </div>
                <div className="space-y-2">
                  {form.feedbackQuestions.map((q, i) => {
                    const isCore = i < 5
                    return (
                      <div key={q.id} className="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200">
                        <input value={q.label} onChange={e => updQuestion(q.id, 'label', e.target.value)} placeholder="Question" className="flex-1 text-sm px-2.5 py-1.5 rounded-lg border border-slate-200 outline-none focus:border-[#1a1a2e]" />
                        {isCore ? (
                          <span className="text-[10px] font-semibold px-2.5 py-1.5 rounded-lg bg-slate-100 text-slate-500">Rating</span>
                        ) : (
                          <select value={q.type} onChange={e => updQuestion(q.id, 'type', e.target.value)} className="text-[11px] font-semibold px-2 py-1.5 rounded-lg border border-slate-200 bg-white">
                            <option value="text">Text</option>
                            <option value="rating">Rating</option>
                          </select>
                        )}
                        <button type="button" onClick={() => updQuestion(q.id, 'required', !q.required)} className={cn('text-[11px] font-semibold px-2.5 py-1.5 rounded-lg', q.required ? 'bg-[#1a1a2e] text-white' : 'bg-slate-100 text-slate-500')}>{q.required ? 'Required' : 'Optional'}</button>
                        {!isCore && <button type="button" onClick={() => delQuestion(q.id)} className="p-1.5 text-slate-400 hover:text-rose-500"><Trash2 size={14} /></button>}
                      </div>
                    )
                  })}
                </div>
              </div>
            )}

            <div className="rounded-xl bg-amber-50 border border-amber-200 p-3 flex items-start gap-2">
              <Hourglass size={15} className="text-amber-600 flex-shrink-0 mt-0.5" />
              <p className="text-[12px] text-amber-700">Your event will be submitted for <b>approval</b> before going live publicly.</p>
            </div>
          </div>
        )}
      </div>

      <div className="px-6 py-4 border-t border-slate-100 flex items-center justify-between sticky bottom-0 bg-white rounded-b-2xl">
        {step > 1 ? <Btn variant="ghost" icon={ChevronLeft} onClick={() => setStep(step - 1)}>Back</Btn> : <span />}
        <div className="flex items-center gap-2">
          <Btn variant="secondary" loading={loading} onClick={() => submit(true)}>Save Draft</Btn>
          {step < 3 ? <Btn variant="primary" onClick={() => setStep(step + 1)}>Continue <ChevronRight size={15} /></Btn>
            : <Btn variant="accent" icon={Send} loading={loading} onClick={() => submit(false)}>Submit for Approval</Btn>}
        </div>
      </div>
    </Modal>
  )
}
