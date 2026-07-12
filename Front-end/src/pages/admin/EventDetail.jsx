import { useEffect, useState, lazy, Suspense } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import {
  ArrowLeft, Pencil, ScanLine, Download, Lock, Hourglass, Copy, Check, X,
  Users, CheckCircle2, MessageSquare, Star, CheckSquare, Square, Plus,
  Search as SearchIcon, FileSpreadsheet, Award, Clock3, MapPinned, Eye,
  Trash2, UserPlus, Copy as CopyIcon, Send, Upload, ArrowUpCircle, ListPlus, Ban,
} from 'lucide-react'
import { Card, Badge, Btn, Input, Toggle, KPI, PriceTag, StarRating, Modal } from '../../components/ui'
import AiSummaryCard from '../../components/admin/AiSummaryCard'

// Lazy - html5-qrcode is a large camera/decoding library only needed once
// an organizer actually opens the Scanner tab, not on every event-detail visit.
const EventScannerPanel = lazy(() => import('../../components/admin/EventScannerPanel'))
import { useAdminEvents, useRegistrations, useFeedback, useMyOrgs } from '../../hooks/useApi'
import { addTask, toggleTask, verifyPayment, updateRegistration, deleteRegistration, addGuest, duplicateEvent, submitEvent, completeEvent, cancelEvent, deleteEvent, importGuestsCsv, promoteRegistration } from '../../api/resources'
import { useApp } from '../../context/AppContext'
import { cn, fmtDate, fmtDateLong, fmtTime, locale } from '../../lib/utils'

const STATUS_COLOR = { draft: 'slate', pending: 'amber', approved: 'green', rejected: 'rose', completed: 'slate', cancelled: 'rose' }
const DELETABLE_STATUSES = ['draft', 'pending', 'rejected', 'cancelled']
const CANCELLABLE_STATUSES = ['pending', 'approved']

export default function EventDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { user, addToast } = useApp()
  const { data: eventsData, refetch } = useAdminEvents()
  const event = (eventsData || []).find(e => String(e.id) === id)
  const { data: myOrgs } = useMyOrgs()
  // Matches EventController::authorizeOrgMember exactly: any member (not
  // just whoever created it) of the event's organization can manage it.
  // Legacy events with no organization stay editable by anyone.
  const canManage = event && (event.organizationId == null || (myOrgs || []).some(o => o.id === event.organizationId))
  const { data: regsData, refetch: refetchRegs } = useRegistrations(id)
  const regs = regsData || []
  const { data: fbData } = useFeedback(id)
  const fb = fbData || []

  const [tab, setTab] = useState('overview')
  const [newTask, setNewTask] = useState('')
  // Optimistic overlay for the checklist - toggling a task used to wait on
  // both the write request and a full admin-events refetch before the
  // checkbox visually moved, which read as unresponsive/buggy and invited
  // impatient double-clicks that flipped a task right back off.
  const [localTasks, setLocalTasks] = useState(null)
  const [pendingTaskIds, setPendingTaskIds] = useState(() => new Set())
  useEffect(() => { setLocalTasks(event?.tasks || null) }, [event?.tasks])
  const [guestSearch, setGuestSearch] = useState('')
  const [guestFilter, setGuestFilter] = useState('all')
  const [proofModal, setProofModal] = useState(null)
  const [guestModal, setGuestModal] = useState(null) // { mode: 'edit'|'add', registration? }
  const [guestForm, setGuestForm] = useState(null)
  const [guestSaving, setGuestSaving] = useState(false)
  const [workflowLoading, setWorkflowLoading] = useState(false)
  const [importing, setImporting] = useState(false)

  if (!event) return <div className="text-center py-20 text-slate-400 text-[13px]">Loading event…</div>

  const att = regs.filter(r => r.attended)
  const tasks = localTasks ?? (event.tasks || [])
  const done = tasks.filter(t => t.done).length
  const avg = fb.length ? (fb.reduce((s, f) => s + (f.q1 + f.q2 + f.q3 + f.q4 + f.q5) / 5, 0) / fb.length).toFixed(1) : '—'
  const highlighted = fb.filter(f => f.isHighlighted)
  const pendingPayments = regs.filter(r => r.paymentStatus === 'pending')
  const tabs = ['overview', 'checklist', 'guests', 'scanner', ...(event.pricing === 'paid' ? ['payments'] : []), 'feedback', 'report']

  const onToggleTask = async (taskId) => {
    if (pendingTaskIds.has(taskId)) return
    setPendingTaskIds(ids => new Set(ids).add(taskId))
    setLocalTasks(ts => (ts || []).map(t => t.id === taskId ? { ...t, done: !t.done } : t))
    try {
      await toggleTask(event.id, taskId)
      refetch()
    } catch (err) {
      setLocalTasks(ts => (ts || []).map(t => t.id === taskId ? { ...t, done: !t.done } : t))
      addToast(err.message || 'Failed to update task', 'error')
    } finally {
      setPendingTaskIds(ids => { const next = new Set(ids); next.delete(taskId); return next })
    }
  }
  const onAddTask = async () => {
    if (!newTask.trim()) return
    try { await addTask(event.id, newTask.trim()); setNewTask(''); refetch() }
    catch (err) { addToast(err.message || 'Failed to add task', 'error') }
  }

  const csvEscape = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`
  const downloadFeedbackCsv = () => {
    const qLabels = ['Check-in experience', 'Event organization', 'Content quality', 'Venue & facilities', 'Overall satisfaction']
    const header = ['Name', 'Email', ...qLabels, 'Average', 'Comment', 'Submitted'].map(csvEscape).join(',')
    const rows = fb.map(f => {
      const average = ((f.q1 + f.q2 + f.q3 + f.q4 + f.q5) / 5).toFixed(1)
      const cells = [
        f.registration?.name || '', f.registration?.email || '',
        f.q1, f.q2, f.q3, f.q4, f.q5, average, f.comment || '',
        f.createdAt ? new Date(f.createdAt).toLocaleString() : '',
      ]
      return cells.map(csvEscape).join(',')
    })
    const blob = new Blob([[header, ...rows].join('\n')], { type: 'text/csv' })
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = `${event.slug || event.id}-feedback.csv`
    a.click()
    URL.revokeObjectURL(a.href)
  }
  const onVerifyPayment = async (regId, approved) => {
    try { await verifyPayment(regId, approved); refetchRegs(); addToast(approved ? 'Payment verified' : 'Payment rejected', approved ? 'success' : 'info') }
    catch (err) { addToast(err.message || 'Failed to update payment', 'error') }
  }

  const openEditGuest = (r) => { setGuestModal({ mode: 'edit', registration: r }); setGuestForm({ name: r.name, email: r.email, customData: { ...(r.customData || {}) }, needsCertificate: !!r.needsCertificate, attended: !!r.attended }) }
  const openAddGuest = () => { setGuestModal({ mode: 'add' }); setGuestForm({ name: '', email: '', customData: {}, needsCertificate: false, attended: false }) }
  const closeGuestModal = () => { setGuestModal(null); setGuestForm(null) }

  const saveGuest = async () => {
    if (!guestForm.name.trim() || !guestForm.email.trim()) { addToast('Name and email are required', 'error'); return }
    setGuestSaving(true)
    try {
      if (guestModal.mode === 'edit') await updateRegistration(guestModal.registration.id, guestForm)
      else await addGuest(event.id, guestForm)
      addToast(guestModal.mode === 'edit' ? 'Guest updated' : 'Guest added', 'success')
      refetchRegs()
      closeGuestModal()
    } catch (err) {
      addToast(err.message || 'Failed to save guest', 'error')
    } finally {
      setGuestSaving(false)
    }
  }

  const removeGuest = async (r) => {
    if (!window.confirm(`Remove ${r.name} from the guest list?`)) return
    try { await deleteRegistration(r.id); addToast('Guest removed', 'success'); refetchRegs() }
    catch (err) { addToast(err.message || 'Failed to remove guest', 'error') }
  }

  const onPromote = async (r) => {
    try { await promoteRegistration(r.id); addToast(`${r.name} moved off the waitlist`, 'success'); refetchRegs() }
    catch (err) { addToast(err.message || 'Failed to promote guest', 'error') }
  }

  const onImportCsv = async (file) => {
    if (!file) return
    setImporting(true)
    try {
      const result = await importGuestsCsv(event.id, file)
      addToast(`Imported ${result.imported} guest(s)${result.skipped ? `, skipped ${result.skipped}` : ''}`, result.imported ? 'success' : 'info')
      if (result.errors?.length) addToast(result.errors[0], 'error')
      refetchRegs()
    } catch (err) {
      addToast(err.message || 'Import failed', 'error')
    } finally {
      setImporting(false)
    }
  }

  const onDuplicate = async () => {
    setWorkflowLoading(true)
    try {
      const copy = await duplicateEvent(event.id)
      addToast('Event duplicated as a draft', 'success')
      navigate(`/organizer/events/${copy.id}/edit`)
    } catch (err) {
      addToast(err.message || 'Failed to duplicate event', 'error')
    } finally {
      setWorkflowLoading(false)
    }
  }

  const onSubmitForApproval = async () => {
    setWorkflowLoading(true)
    try {
      const updated = await submitEvent(event.id)
      addToast(updated?.status === 'approved' ? 'Event is live!' : 'Submitted for approval', 'success')
      refetch()
    } catch (err) {
      const firstError = err.errors ? Object.values(err.errors)[0]?.[0] : null
      addToast(firstError || err.message || 'Failed to submit event', 'error')
    } finally {
      setWorkflowLoading(false)
    }
  }

  const onCompleteEvent = async () => {
    setWorkflowLoading(true)
    try {
      await completeEvent(event.id)
      addToast('Event marked completed', 'success')
      refetch()
    } catch (err) {
      addToast(err.message || 'Failed to mark event completed', 'error')
    } finally {
      setWorkflowLoading(false)
    }
  }

  const onCancelEvent = async () => {
    if (!window.confirm(`Cancel "${event.title}"? Every registrant (${regs.length}) will be emailed that it's cancelled.`)) return
    setWorkflowLoading(true)
    try {
      await cancelEvent(event.id)
      addToast('Event cancelled - registrants have been notified', 'success')
      refetch()
    } catch (err) {
      addToast(err.message || 'Failed to cancel event', 'error')
    } finally {
      setWorkflowLoading(false)
    }
  }

  const onDeleteEvent = async () => {
    if (!window.confirm(`Delete "${event.title}"? This can't be undone.`)) return
    setWorkflowLoading(true)
    try {
      await deleteEvent(event.id)
      addToast('Event deleted', 'success')
      navigate('/my-events')
    } catch (err) {
      addToast(err.message || 'Failed to delete event', 'error')
      setWorkflowLoading(false)
    }
  }

  const exportGuests = () => {
    let csv = `Name,Email,Status,Check-in,Needs Cert,Feedback,Payment Ref,Payment Status\n`
    regs.forEach(r => { csv += `"${r.name}","${r.email}","${r.attended ? 'Checked in' : 'Registered'}","${r.checkInTime || ''}","${r.needsCertificate ? 'Yes' : 'No'}","${r.feedbackSubmitted ? 'Yes' : 'No'}","${r.paymentRef || ''}","${r.paymentStatus || 'n/a'}"\n` })
    downloadCsv(csv, `guests-${event.slug}.csv`)
    addToast('Guest list exported!', 'success')
  }

  const exportReport = () => {
    let csv = `POST-EVENT REPORT\n${event.title}\n\nDate,${fmtDateLong(event.date)}\nVenue,${event.venue}\nLocation,${event.location}\nOrganized by,${event.organizedBy}\nLocale,${locale.city} (${locale.region}, ${locale.country})\n\nMETRICS\nRegistered,${regs.length}\nAttended,${att.length}\nAttendance Rate,${regs.length ? ((att.length / regs.length) * 100).toFixed(1) : 0}%\nFeedback,${fb.length}\nAvg Satisfaction,${avg}\nCertificates needed,${regs.filter(r => r.needsCertificate && r.attended).length}\n\nATTENDANCE\nName,Email,Check-in,Needs Cert,Feedback\n`
    regs.forEach(r => { csv += `"${r.name}","${r.email}","${r.checkInTime || ''}","${r.needsCertificate ? 'Yes' : 'No'}","${r.feedbackSubmitted ? 'Yes' : 'No'}"\n` })
    downloadCsv(csv, `report-${event.slug}.csv`)
    addToast('Report downloaded!', 'success')
  }

  let guestList = regs
  if (guestSearch) guestList = guestList.filter(r => r.name.toLowerCase().includes(guestSearch.toLowerCase()) || r.email.toLowerCase().includes(guestSearch.toLowerCase()))
  if (guestFilter === 'checked-in') guestList = guestList.filter(r => r.attended)
  if (guestFilter === 'not-in') guestList = guestList.filter(r => !r.attended)
  if (guestFilter === 'cert') guestList = guestList.filter(r => r.needsCertificate)
  if (guestFilter === 'walk-in') guestList = guestList.filter(r => r.isWalkIn)
  if (guestFilter === 'waitlist') guestList = guestList.filter(r => r.waitlisted)
  const waitlistCount = regs.filter(r => r.waitlisted).length

  return (
    <div className="space-y-5">
      <div className="flex items-center gap-3">
        <button onClick={() => navigate('/my-events')} className="p-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-50"><ArrowLeft size={16} /></button>
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 mb-0.5">
            <Badge color={STATUS_COLOR[event.status] || 'slate'} size="xs">{event.status}</Badge>
            {event.isPrivate && <Badge color="rose"><Lock size={10} />Private</Badge>}
            <PriceTag event={event} />
          </div>
          <h1 className="text-xl font-extrabold truncate">{event.title}</h1>
        </div>
        {canManage && <Btn variant="secondary" size="sm" icon={Pencil} onClick={() => navigate(`/organizer/events/${event.id}/edit`)}>Edit</Btn>}
        <Btn variant="secondary" size="sm" icon={CopyIcon} loading={workflowLoading} onClick={onDuplicate}>Duplicate</Btn>
        {canManage && event.status === 'draft' && <Btn variant="primary" size="sm" icon={Send} loading={workflowLoading} onClick={onSubmitForApproval}>Submit for approval</Btn>}
        {canManage && event.status === 'approved' && <Btn variant="primary" size="sm" icon={CheckCircle2} loading={workflowLoading} onClick={onCompleteEvent}>Mark Completed</Btn>}
        {canManage && CANCELLABLE_STATUSES.includes(event.status) && <Btn variant="secondary" size="sm" icon={Ban} loading={workflowLoading} onClick={onCancelEvent}>Cancel</Btn>}
        {canManage && DELETABLE_STATUSES.includes(event.status) && <Btn variant="secondary" size="sm" icon={Trash2} loading={workflowLoading} onClick={onDeleteEvent} className="!text-rose-600 hover:!bg-rose-50">Delete</Btn>}
        <Btn variant="secondary" size="sm" icon={ScanLine} onClick={() => setTab('scanner')}>Scan</Btn>
        <Btn variant="primary" size="sm" icon={Download} onClick={exportReport}>Report</Btn>
      </div>

      <div className="flex gap-1 border-b border-slate-200 overflow-x-auto overflow-y-hidden">
        {tabs.map(t => <button key={t} onClick={() => setTab(t)} className={cn('px-4 py-2.5 text-[13px] font-semibold capitalize whitespace-nowrap border-b-2 -mb-px transition-all', tab === t ? 'border-[#1a1a2e] text-[#1a1a2e]' : 'border-transparent text-slate-400 hover:text-slate-600')}>{t}</button>)}
      </div>

      {tab === 'overview' && (
        <div className="space-y-4">
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <KPI icon={Users} label="Registered" value={regs.length} sub={`of ${event.capacity}`} color="#1a1a2e" />
            <KPI icon={CheckCircle2} label="Attended" value={att.length} sub={`${regs.length ? ((att.length / regs.length) * 100).toFixed(0) : 0}%`} color="#0f9d8f" />
            <KPI icon={MessageSquare} label="Feedback" value={fb.length} color="#6d28d9" />
            <KPI icon={Star} label="Avg Rating" value={avg} color="#f59e0b" />
          </div>
          {event.isPrivate && event.privateLink && (
            <Card className="p-4">
              <p className="text-[12px] font-bold mb-2 flex items-center gap-1.5"><Lock size={13} className="text-[#e94560]" />Private invite link</p>
              <div className="flex items-center gap-2">
                <code className="flex-1 text-[11px] bg-slate-50 px-3 py-2 rounded-lg border border-slate-200 text-slate-600 truncate">/events/{event.slug}?access={event.privateLink}</code>
                <button onClick={() => { navigator.clipboard?.writeText(`${window.location.origin}/events/${event.slug}?access=${event.privateLink}`); addToast('Copied!', 'success') }} className="p-2 rounded-lg bg-slate-100 hover:bg-slate-200"><Copy size={14} /></button>
              </div>
            </Card>
          )}
          <Card className="p-5">
            <p className="text-[12px] font-bold mb-3">Event details</p>
            <div className="grid sm:grid-cols-2 gap-2.5">
              {[['Date', fmtDateLong(event.date)], ['Time', `${fmtTime(event.startTime)} – ${fmtTime(event.endTime)}`], ['Venue', event.venue], ['Industry', event.industry], ['Organized by', event.organizedBy], ['Capacity', event.capacity]].map(([k, v]) => (
                <div key={k} className="p-2.5 rounded-lg bg-slate-50"><p className="text-[10px] font-bold text-slate-400 uppercase">{k}</p><p className="text-[13px] font-medium text-slate-700 mt-0.5">{v}</p></div>
              ))}
            </div>
          </Card>
        </div>
      )}

      {tab === 'checklist' && (
        <Card className="p-5">
          <div className="flex items-center justify-between mb-4">
            <div><p className="font-bold text-[14px]">Checklist / To-do</p><p className="text-[11px] text-slate-400">{done}/{tasks.length} done</p></div>
            <div className="relative w-12 h-12">
              <svg viewBox="0 0 48 48" className="w-12 h-12"><circle cx={24} cy={24} r={20} fill="none" stroke="#e2e8f0" strokeWidth={5} /><circle cx={24} cy={24} r={20} fill="none" stroke={done === tasks.length && tasks.length ? '#0f9d8f' : '#1a1a2e'} strokeWidth={5} strokeDasharray={`${(done / Math.max(tasks.length, 1)) * 125.6} 125.6`} strokeLinecap="round" transform="rotate(-90 24 24)" /><text x={24} y={28} textAnchor="middle" fontSize={11} fontWeight={700} fill="#1a1a2e">{tasks.length ? Math.round((done / tasks.length) * 100) : 0}%</text></svg>
            </div>
          </div>
          <div className="space-y-2 mb-4">
            {tasks.map(t => (
              <button key={t.id} disabled={pendingTaskIds.has(t.id)} onClick={() => onToggleTask(t.id)} className={cn('w-full flex items-center gap-3 p-3 rounded-xl border text-left transition-all disabled:opacity-60', t.done ? 'bg-emerald-50 border-emerald-200' : 'bg-white border-slate-200 hover:border-slate-300')}>
                {t.done ? <CheckSquare size={17} className="text-emerald-500" /> : <Square size={17} className="text-slate-300" />}
                <span className={cn('text-[13px] flex-1', t.done ? 'line-through text-emerald-700' : 'text-slate-700')}>{t.label}</span>
              </button>
            ))}
            {tasks.length === 0 && <p className="text-center text-slate-400 text-[12px] py-4">No tasks yet — add some below.</p>}
          </div>
          <div className="flex gap-2">
            <input value={newTask} onChange={e => setNewTask(e.target.value)} onKeyDown={e => { if (e.key === 'Enter') onAddTask() }} placeholder="Add a task…" className="flex-1 px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm outline-none focus:border-[#1a1a2e]" />
            <Btn variant="primary" icon={Plus} onClick={onAddTask}>Add</Btn>
          </div>
        </Card>
      )}

      {tab === 'guests' && (
        <Card className="p-5">
          <div className="flex items-center justify-between mb-4 gap-2">
            <p className="font-bold text-[14px] whitespace-nowrap">Guests ({guestList.length}){waitlistCount > 0 && <span className="font-normal text-slate-400"> · {waitlistCount} waitlisted</span>}</p>
            <div className="flex items-center gap-2">
              <Btn variant="secondary" size="sm" icon={UserPlus} onClick={openAddGuest}>Add guest</Btn>
              <label className={cn('inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-semibold bg-white text-slate-700 border border-slate-200 hover:border-slate-300 cursor-pointer', importing && 'opacity-50 pointer-events-none')}>
                <Upload size={13} />Import CSV
                <input type="file" accept=".csv,text/csv" className="hidden" onChange={e => { onImportCsv(e.target.files?.[0]); e.target.value = '' }} />
              </label>
              <Btn variant="secondary" size="sm" icon={FileSpreadsheet} onClick={exportGuests}>Export</Btn>
              <Btn variant="secondary" size="sm" icon={ScanLine} onClick={() => setTab('scanner')}>Scan</Btn>
            </div>
          </div>
          <div className="flex items-center gap-2 mb-4 flex-wrap">
            <div className="relative flex-1 min-w-[180px]">
              <SearchIcon size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
              <input value={guestSearch} onChange={e => setGuestSearch(e.target.value)} placeholder="Search name or email…" className="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 bg-white text-[13px] outline-none focus:border-[#1a1a2e]" />
            </div>
            <select value={guestFilter} onChange={e => setGuestFilter(e.target.value)} className="px-3 py-2 rounded-xl border border-slate-200 bg-white text-[13px] outline-none">
              <option value="all">All</option><option value="checked-in">Checked in</option><option value="not-in">Not checked in</option><option value="cert">Needs certificate</option><option value="walk-in">Walk-ins</option><option value="waitlist">Waitlisted</option>
            </select>
          </div>
          <div className="space-y-2">
            {guestList.map(r => (
              <div key={r.id} className="flex items-center gap-3 p-3 rounded-xl border border-slate-200">
                <div className={cn('w-9 h-9 rounded-full flex items-center justify-center font-bold text-[13px]', r.attended ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400')}>{r.name[0]}</div>
                <div className="flex-1 min-w-0"><p className="text-[13px] font-semibold truncate">{r.name} {r.isWalkIn && <Badge color="blue" size="xs">Walk-in</Badge>}</p><p className="text-[11px] text-slate-400 truncate">{r.email}</p></div>
                <div className="flex items-center gap-1.5">
                  {r.waitlisted && <Badge color="violet" size="xs"><ListPlus size={9} />Waitlist</Badge>}
                  {r.paymentStatus === 'pending' && <Badge color="amber" size="xs"><Clock3 size={9} />Pay?</Badge>}
                  {r.paymentStatus === 'verified' && <Badge color="green" size="xs"><Check size={9} />Paid</Badge>}
                  {r.needsCertificate && <Badge color="violet" size="xs"><Award size={9} />Cert</Badge>}
                  {r.attended ? <Badge color="green" size="xs"><CheckCircle2 size={9} />In</Badge> : <Badge color="slate" size="xs">Not in</Badge>}
                  {r.feedbackSubmitted && <Badge color="amber" size="xs"><Star size={9} />FB</Badge>}
                </div>
                <div className="flex items-center gap-1 ml-1">
                  {r.waitlisted && <button onClick={() => onPromote(r)} title="Promote off waitlist" className="p-1.5 rounded-lg text-slate-400 hover:text-emerald-600 hover:bg-emerald-50"><ArrowUpCircle size={14} /></button>}
                  <button onClick={() => openEditGuest(r)} className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100"><Pencil size={14} /></button>
                  <button onClick={() => removeGuest(r)} className="p-1.5 rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-50"><Trash2 size={14} /></button>
                </div>
              </div>
            ))}
            {guestList.length === 0 && <p className="text-center text-slate-400 text-[12px] py-6">No guests match.</p>}
          </div>

          <Modal open={!!guestModal} onClose={closeGuestModal} title={guestModal?.mode === 'edit' ? 'Edit Guest' : 'Add Guest'} size="sm">
            {guestForm && (
              <div className="p-5 space-y-3">
                <Input label="Full Name" value={guestForm.name} onChange={e => setGuestForm(f => ({ ...f, name: e.target.value }))} required />
                <Input label="Email" type="email" value={guestForm.email} onChange={e => setGuestForm(f => ({ ...f, email: e.target.value }))} required />
                {(event.customFields || []).map(cf => (
                  <Input key={cf.id} label={cf.label} value={guestForm.customData[cf.id] || ''} onChange={e => setGuestForm(f => ({ ...f, customData: { ...f.customData, [cf.id]: e.target.value } }))} />
                ))}
                {event.requiresCertificate && <Toggle checked={guestForm.needsCertificate} onChange={v => setGuestForm(f => ({ ...f, needsCertificate: v }))} icon={Award} label="Needs certificate" color="#6d28d9" />}
                <Toggle checked={guestForm.attended} onChange={v => setGuestForm(f => ({ ...f, attended: v }))} icon={CheckCircle2} label="Checked in" color="#0f9d8f" />
                <div className="flex gap-2 pt-2">
                  <Btn variant="ghost" full onClick={closeGuestModal}>Cancel</Btn>
                  <Btn variant="accent" full loading={guestSaving} onClick={saveGuest}>{guestModal?.mode === 'edit' ? 'Save Changes' : 'Add Guest'}</Btn>
                </div>
              </div>
            )}
          </Modal>
        </Card>
      )}

      {tab === 'scanner' && (
        <Suspense fallback={<div className="text-center py-16 text-slate-400 text-[13px]">Loading scanner…</div>}>
          <EventScannerPanel eventId={event.id} checkedInCount={att.length} totalCount={regs.length} onCheckedIn={refetchRegs} />
        </Suspense>
      )}

      {tab === 'payments' && (
        <Card className="p-5">
          <div className="flex items-center justify-between mb-4">
            <div><p className="font-bold text-[14px]">Payment Verification</p><p className="text-[11px] text-slate-400">{pendingPayments.length} awaiting review · ₱{event.price} each</p></div>
          </div>
          <div className="space-y-2">
            {regs.filter(r => r.paymentRef).map(r => (
              <div key={r.id} className="flex items-center gap-3 p-3 rounded-xl border border-slate-200">
                <div className="flex-1 min-w-0">
                  <p className="text-[13px] font-semibold truncate">{r.name}</p>
                  <p className="text-[11px] text-slate-400">Ref: <span className="font-mono">{r.paymentRef}</span></p>
                </div>
                {r.paymentScreenshotUrl && <button onClick={() => setProofModal(r)} className="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-[11px] font-semibold text-slate-600 flex items-center gap-1.5"><Eye size={12} />Proof</button>}
                {r.paymentStatus === 'pending' ? (
                  <div className="flex gap-1.5">
                    <button onClick={() => onVerifyPayment(r.id, true)} className="px-2.5 py-1.5 rounded-lg bg-emerald-500 text-white text-[11px] font-semibold flex items-center gap-1"><Check size={12} />Verify</button>
                    <button onClick={() => onVerifyPayment(r.id, false)} className="px-2.5 py-1.5 rounded-lg bg-slate-100 text-slate-500 text-[11px] font-semibold flex items-center gap-1"><X size={12} />Reject</button>
                  </div>
                ) : r.paymentStatus === 'verified' ? <Badge color="green"><Check size={10} />Verified</Badge> : <Badge color="rose"><X size={10} />Rejected</Badge>}
              </div>
            ))}
            {regs.filter(r => r.paymentRef).length === 0 && <p className="text-center text-slate-400 text-[12px] py-6">No payments submitted yet.</p>}
          </div>

          <Modal open={!!proofModal} onClose={() => setProofModal(null)} title={proofModal ? `${proofModal.name} — Payment Proof` : ''} size="md">
            {proofModal && (
              <div className="p-5">
                <div className="rounded-xl bg-slate-50 border border-slate-200 p-3 mb-3"><p className="text-[12px]"><span className="text-slate-400">Reference:</span> <span className="font-mono font-semibold">{proofModal.paymentRef}</span></p></div>
                {proofModal.paymentScreenshotUrl && <img src={proofModal.paymentScreenshotUrl} alt="payment proof" className="w-full rounded-xl border border-slate-200" />}
                {proofModal.paymentStatus === 'pending' && (
                  <div className="flex gap-2 mt-4">
                    <Btn variant="primary" full icon={Check} onClick={() => { onVerifyPayment(proofModal.id, true); setProofModal(null) }}>Verify Payment</Btn>
                    <Btn variant="secondary" icon={X} onClick={() => { onVerifyPayment(proofModal.id, false); setProofModal(null) }}>Reject</Btn>
                  </div>
                )}
              </div>
            )}
          </Modal>
        </Card>
      )}

      {tab === 'feedback' && (
        <div className="space-y-3">
          <div className="flex justify-end">
            <Btn variant="secondary" size="sm" icon={Download} onClick={downloadFeedbackCsv} disabled={fb.length === 0}>Download CSV</Btn>
          </div>
          <AiSummaryCard eventId={event.id} />
          {highlighted.length > 0 && (
            <Card className="p-5 bg-amber-50 border-amber-200">
              <p className="text-[13px] font-bold text-amber-800 mb-3 flex items-center gap-1.5"><Star size={14} />Important comments ({highlighted.length})</p>
              <div className="space-y-2">
                {highlighted.map(f => (
                  <div key={f.id} className="p-3 rounded-xl bg-white border border-amber-200">
                    <div className="flex justify-between mb-1"><span className="text-[12px] font-semibold">{f.badge || 'Feedback'}</span><span className="text-[11px] font-bold text-amber-600">★ {((f.q1 + f.q2 + f.q3 + f.q4 + f.q5) / 5).toFixed(1)}</span></div>
                    <p className="text-[12px] text-slate-600">{f.comment}</p>
                  </div>
                ))}
              </div>
            </Card>
          )}
          {fb.map(f => { const av = ((f.q1 + f.q2 + f.q3 + f.q4 + f.q5) / 5).toFixed(1); const extraQuestions = (event.feedbackQuestions || []).slice(5); return (
            <Card key={f.id} className="p-4">
              <div className="flex items-center justify-between mb-2">
                <div><p className="text-[12px] font-semibold">{f.badge || 'Feedback'}</p><p className="text-[10px] text-slate-400">{fmtDate(f.createdAt)}</p></div>
                <StarRating size={15} value={Math.round(av)} readonly />
              </div>
              {f.comment && <p className="text-[12px] text-slate-600">{f.comment}</p>}
              {extraQuestions.map(q => f.customAnswers?.[q.id] ? (
                <p key={q.id} className="text-[11px] text-slate-500 mt-1.5"><span className="font-semibold text-slate-600">{q.label}:</span> {f.customAnswers[q.id]}</p>
              ) : null)}
            </Card>
          )})}
          {fb.length === 0 && <Card className="p-10 text-center"><MessageSquare size={32} className="text-slate-300 mx-auto mb-2" /><p className="text-[13px] text-slate-400">No feedback yet</p></Card>}
        </div>
      )}

      {tab === 'report' && (
        <Card className="p-6">
          <div className="flex items-center justify-between mb-5"><div><h3 className="font-extrabold text-[16px]">Post-Event Report</h3><p className="text-[12px] text-slate-400">Summary for HQ reporting</p></div><Btn variant="primary" icon={Download} onClick={exportReport}>Download CSV</Btn></div>
          <div className="grid sm:grid-cols-2 gap-2.5 mb-5">
            {[['Event', event.title], ['Date', fmtDate(event.date)], ['Venue', event.venue], ['Locale', `${event.location} · ${locale.region}`], ['Registered', regs.length], ['Attended', `${att.length} (${regs.length ? ((att.length / regs.length) * 100).toFixed(0) : 0}%)`], ['Certificates', regs.filter(r => r.needsCertificate && r.attended).length], ['Feedback', fb.length], ['Avg Satisfaction', avg]].map(([k, v]) => (
              <div key={k} className="p-3 rounded-lg bg-slate-50 border border-slate-200"><p className="text-[10px] font-bold text-slate-400 uppercase">{k}</p><p className="text-[13px] font-semibold text-slate-700 mt-0.5">{String(v)}</p></div>
            ))}
          </div>
          <div className="p-4 rounded-xl bg-[#1a1a2e] text-white">
            <p className="text-[12px] font-bold mb-1 flex items-center gap-1.5"><MapPinned size={13} />Locale metrics for HQ</p>
            <p className="text-[12px] text-slate-300">{locale.city}, {locale.region}, {locale.country} · Attendance {regs.length ? ((att.length / regs.length) * 100).toFixed(1) : 0}% · Feedback {att.length ? ((fb.length / att.length) * 100).toFixed(1) : 0}%</p>
          </div>
        </Card>
      )}
    </div>
  )
}

function downloadCsv(csv, filename) {
  const blob = new Blob([csv], { type: 'text/csv' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = filename
  a.click()
}
