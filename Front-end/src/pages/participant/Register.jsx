import { useEffect, useRef, useState } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { ArrowLeft, User, Mail, Lock, Receipt, Ticket, Award, Send, Clock3, Upload, ImageDown, X, UserPlus, LogIn, MailCheck, RefreshCw } from 'lucide-react'
import { Btn, Input, Toggle, Card } from '../../components/ui'
import PasswordChecklist from '../../components/shared/PasswordChecklist'
import { useEvent } from '../../hooks/useApi'
import { registerForEvent, walkInForEvent } from '../../api/resources'
import { useApp } from '../../context/AppContext'
import { cn, fmtDate, fmtTime } from '../../lib/utils'

const MAX_SCREENSHOT_BYTES = 5 * 1024 * 1024 // 5MB — matches the backend's own limit

export default function Register() {
  const { slug } = useParams()
  const [searchParams] = useSearchParams()
  const isWalkIn = searchParams.get('walkIn') === '1'
  const navigate = useNavigate()
  const { user, authReady, login, createAccount, refreshUser, resendVerificationEmail, addToast } = useApp()
  const { data: event, loading } = useEvent(slug)

  // Registering for an event doubles as creating an account (see
  // AuthController::register) - "account" comes first unless the visitor
  // is already logged in, in which case it's skipped entirely. An unverified
  // account gets routed through "verify" first - the register endpoint
  // requires a verified email, and a brand-new account has no way to be
  // verified yet seconds after signing up.
  const [step, setStep] = useState('account') // account | verify | form | payment
  const [accountMode, setAccountMode] = useState('create') // create | login
  const [accountForm, setAccountForm] = useState({ name: '', email: '', password: '' })
  const [accountErrors, setAccountErrors] = useState({})
  const [accountLoading, setAccountLoading] = useState(false)
  const [resending, setResending] = useState(false)
  const [checkingVerified, setCheckingVerified] = useState(false)

  const [form, setForm] = useState({ name: '', email: '', customData: {}, needsCertificate: false })
  const [paymentRef, setPaymentRef] = useState('')
  const [screenshot, setScreenshot] = useState(null) // { file, previewUrl, name }
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)
  const ranWalkIn = useRef(false)

  // Already logged in (or just finished the account step) - skip straight
  // to event details, prefilled from the account.
  useEffect(() => {
    if (user && step === 'account') {
      setForm(f => ({ ...f, name: f.name || user.name, email: f.email || user.email }))
      setStep(user.emailVerifiedAt ? 'form' : 'verify')
    }
  }, [user, step])

  const resend = async () => {
    setResending(true)
    try { await resendVerificationEmail(); addToast('Verification email sent!', 'success') }
    catch (err) { addToast(err.message || 'Could not resend right now', 'error') }
    finally { setResending(false) }
  }

  const checkVerified = async () => {
    setCheckingVerified(true)
    try {
      const fresh = await refreshUser()
      if (fresh.emailVerifiedAt) setStep('form')
      else addToast("Still not verified — click the link in your email first", 'error')
    } catch (err) {
      addToast(err.message || 'Could not check verification status', 'error')
    } finally {
      setCheckingVerified(false)
    }
  }

  useEffect(() => {
    if (isWalkIn && event && !ranWalkIn.current) {
      ranWalkIn.current = true
      walkInForEvent(event.id, { name: 'Walk-in Guest', email: `walkin-${Date.now()}@tmp`, customData: {} })
        .then(reg => navigate(`/events/${event.slug}/confirm/${reg.id}`, { state: { ...reg, event } }))
        .catch(err => addToast(err.message || 'Walk-in check-in failed', 'error'))
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [event])

  useEffect(() => () => { if (screenshot?.previewUrl) URL.revokeObjectURL(screenshot.previewUrl) }, [screenshot])

  if (loading || !authReady) return <div className="text-center py-20 text-slate-400 text-[13px]">Loading…</div>
  if (!event) return <div className="text-center py-20 text-slate-500">Event not found.</div>
  if (isWalkIn) return (
    <div className="text-center py-24">
      <div className="w-10 h-10 border-[3px] border-[#1a1a2e] border-t-transparent rounded-full animate-spin mx-auto mb-3" />
      <p className="text-slate-500 text-sm">Checking you in…</p>
    </div>
  )

  const setAccountField = (k) => (e) => { setAccountForm(f => ({ ...f, [k]: e.target.value })); setAccountErrors(er => ({ ...er, [k]: undefined })) }

  const submitAccount = async (e) => {
    e.preventDefault()
    if (accountMode === 'create' && accountForm.password !== accountForm.passwordConfirmation) {
      setAccountErrors({ password: ['Passwords do not match'] })
      return
    }
    setAccountLoading(true)
    setAccountErrors({})
    try {
      if (accountMode === 'login') {
        await login(accountForm.email, accountForm.password)
      } else {
        await createAccount(accountForm)
      }
      // `user` effect above advances to the form step once context updates.
    } catch (err) {
      setAccountErrors(err.errors || {})
      addToast(err.message || 'Something went wrong', 'error')
    } finally {
      setAccountLoading(false)
    }
  }

  const setCustom = (id) => (e) => setForm(f => ({ ...f, customData: { ...f.customData, [id]: e.target.value } }))

  const validate = () => {
    const e = {}
    if (!form.name.trim()) e.name = 'Required'
    if (!form.email.trim() || !/\S+@\S+\.\S+/.test(form.email)) e.email = 'Valid email required'
    ;(event.customFields || []).forEach(cf => { if (cf.required && !form.customData[cf.id]?.trim()) e[cf.id] = 'Required' })
    setErrors(e)
    return Object.keys(e).length === 0
  }

  const proceedFromForm = (e) => {
    e.preventDefault()
    if (!validate()) return
    if (event.pricing === 'paid') setStep('payment')
    else doRegister(null, null)
  }

  const doRegister = async (paymentRefValue, screenshotFile) => {
    setSubmitting(true)
    try {
      const registration = await registerForEvent(event.id, {
        name: form.name, email: form.email, customData: form.customData,
        needsCertificate: form.needsCertificate,
        paymentRef: paymentRefValue, paymentScreenshot: screenshotFile || undefined,
      })
      addToast(event.pricing === 'paid' ? 'Payment submitted for verification!' : "You're registered!", 'success')
      navigate(`/events/${event.slug}/confirm/${registration.id}`, { state: { ...registration, event } })
    } catch (err) {
      setErrors(err.errors || {})
      addToast(err.message || 'Registration failed', 'error')
    } finally {
      setSubmitting(false)
    }
  }

  const submitPayment = (e) => {
    e.preventDefault()
    const errs = {}
    if (!paymentRef.trim()) errs.paymentRef = 'Reference number required'
    if (!screenshot) errs.paymentScreenshot = 'Upload your payment screenshot'
    if (Object.keys(errs).length) { setErrors(errs); return }
    doRegister(paymentRef, screenshot.file)
  }

  const onFile = (file) => {
    if (!file) return
    if (!file.type.startsWith('image/')) { addToast('Please upload an image', 'error'); return }
    if (file.size > MAX_SCREENSHOT_BYTES) { addToast('Image must be under 5MB', 'error'); return }
    if (screenshot?.previewUrl) URL.revokeObjectURL(screenshot.previewUrl)
    setScreenshot({ file, previewUrl: URL.createObjectURL(file), name: file.name })
    setErrors(e => ({ ...e, paymentScreenshot: undefined }))
  }

  return (
    <div className="max-w-md mx-auto px-5 py-8">
      <button onClick={() => step === 'payment' ? setStep('form') : navigate(`/events/${slug}`)} className="flex items-center gap-1.5 text-[13px] text-slate-500 hover:text-slate-800 mb-4 font-medium"><ArrowLeft size={15} />Back</button>
      <Card className="p-6">
        <div className="flex items-center gap-3 mb-5 pb-5 border-b border-slate-100">
          <div className="w-12 h-12 rounded-xl overflow-hidden bg-slate-100 flex-shrink-0">{event.image && <img src={event.image} alt="" className="w-full h-full object-cover" />}</div>
          <div><h2 className="font-bold text-[15px] leading-snug line-clamp-2">{event.title}</h2><p className="text-[12px] text-slate-500 mt-0.5">{fmtDate(event.date)} · {fmtTime(event.startTime)}</p></div>
        </div>

        {step === 'account' && (
          <form onSubmit={submitAccount} className="space-y-4">
            <div className="flex gap-1.5 p-1 rounded-xl bg-slate-100">
              <button type="button" onClick={() => setAccountMode('create')} className={cn('flex-1 py-2 rounded-lg text-[12px] font-semibold transition-all', accountMode === 'create' ? 'bg-white shadow-sm text-slate-800' : 'text-slate-500')}>Create account</button>
              <button type="button" onClick={() => setAccountMode('login')} className={cn('flex-1 py-2 rounded-lg text-[12px] font-semibold transition-all', accountMode === 'login' ? 'bg-white shadow-sm text-slate-800' : 'text-slate-500')}>Log in</button>
            </div>
            <p className="text-[12px] text-slate-500 -mt-1">{accountMode === 'create' ? "You'll use this account for your QR pass, and to register for other events." : 'Log in to register with your existing account.'}</p>
            {accountMode === 'create' && <Input label="Full Name" value={accountForm.name} onChange={setAccountField('name')} icon={User} placeholder="Juan Dela Cruz" error={accountErrors.name?.[0]} required />}
            <Input label="Email" type="email" value={accountForm.email} onChange={setAccountField('email')} icon={Mail} placeholder="juan@email.com" error={accountErrors.email?.[0]} required />
            <Input label="Password" type="password" value={accountForm.password} onChange={setAccountField('password')} icon={Lock} placeholder="••••••••" error={accountErrors.password?.[0]} required />
            {accountMode === 'create' && accountForm.password && <PasswordChecklist password={accountForm.password} />}
            {accountMode === 'create' && (
              <Input label="Confirm Password" type="password" value={accountForm.passwordConfirmation || ''} onChange={setAccountField('passwordConfirmation')} icon={Lock} placeholder="••••••••" required />
            )}
            <Btn type="submit" variant="accent" size="lg" full icon={accountMode === 'create' ? UserPlus : LogIn} loading={accountLoading}>{accountMode === 'create' ? 'Create Account & Continue' : 'Log In & Continue'}</Btn>
          </form>
        )}

        {step === 'verify' && (
          <div className="text-center py-2">
            <div className="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-4"><MailCheck size={26} className="text-[#1a1a2e]" /></div>
            <h3 className="font-bold text-[16px] mb-1">Check your inbox</h3>
            <p className="text-[13px] text-slate-500 mb-6">We sent a verification link to <b className="text-slate-700">{user?.email}</b>. Click it, then continue below.</p>
            <div className="space-y-2">
              <Btn variant="accent" size="lg" full loading={checkingVerified} onClick={checkVerified}>I've verified — Continue</Btn>
              <Btn variant="secondary" size="lg" full icon={RefreshCw} loading={resending} onClick={resend}>Resend email</Btn>
            </div>
          </div>
        )}

        {step === 'form' && (
          <form onSubmit={proceedFromForm} className="space-y-4">
            {event.pricing === 'paid' && <div className="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200"><span className="text-[13px] font-semibold text-slate-600">Ticket price</span><span className="text-lg font-extrabold">₱{event.price}</span></div>}
            <Input label="Full Name" value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} icon={User} placeholder="Juan Dela Cruz" error={errors.name?.[0] || errors.name} required />
            <Input label="Email" value={form.email} onChange={e => setForm(f => ({ ...f, email: e.target.value }))} icon={Mail} type="email" placeholder="juan@email.com" error={errors.email?.[0] || errors.email} required />
            {(event.customFields || []).map(cf => (
              <Input key={cf.id} label={cf.label} value={form.customData[cf.id] || ''} onChange={setCustom(cf.id)} error={errors[cf.id]} required={cf.required} />
            ))}
            {event.requiresCertificate && <Toggle checked={form.needsCertificate} onChange={v => setForm(f => ({ ...f, needsCertificate: v }))} icon={Award} label="I need a Certificate of Attendance" desc="Prepared for qualifying attendees" color="#6d28d9" />}
            <Btn type="submit" variant="accent" size="lg" full icon={event.pricing === 'paid' ? Send : Ticket} loading={submitting}>{event.pricing === 'paid' ? 'Continue to Payment' : 'Complete Registration'}</Btn>
            {event.privacyPolicyUrl && <p className="text-[11px] text-slate-400 text-center">By registering you agree to the <a href={event.privacyPolicyUrl} target="_blank" rel="noreferrer" className="underline hover:text-slate-600">privacy policy</a>.</p>}
          </form>
        )}

        {step === 'payment' && (
          <form onSubmit={submitPayment} className="space-y-4">
            <div className="rounded-xl bg-[#1a1a2e] text-white p-4">
              <p className="text-[11px] text-slate-300 uppercase tracking-wide font-bold mb-1">Amount due</p>
              <p className="text-3xl font-extrabold">₱{event.price}</p>
            </div>
            <div className="rounded-xl bg-slate-50 border border-slate-200 p-4">
              <p className="text-[12px] font-bold text-slate-700 mb-2">How to pay</p>
              <ol className="text-[12px] text-slate-600 space-y-1.5 list-decimal list-inside">
                <li>Send ₱{event.price} to the organizer's account</li>
                <li>Take a screenshot of your payment confirmation</li>
                <li>Enter the reference number & upload the screenshot below</li>
              </ol>
              {event.organization && <p className="text-[11px] text-slate-400 mt-2">Pay to: <b className="text-slate-600">{event.organization.name}</b>{event.organization.email ? ` · ${event.organization.email}` : ''}</p>}
            </div>

            <Input label="Payment Reference Number" value={paymentRef} onChange={e => setPaymentRef(e.target.value)} icon={Receipt} placeholder="e.g. 0029384756" error={errors.paymentRef?.[0] || errors.paymentRef} required />

            <div>
              <label className="block text-[12px] font-semibold text-slate-600 mb-1.5">Payment Screenshot<span className="text-rose-500 ml-0.5">*</span></label>
              {screenshot ? (
                <div className="relative rounded-xl overflow-hidden border border-slate-200">
                  <img src={screenshot.previewUrl} alt="proof" className="w-full max-h-48 object-contain bg-slate-50" />
                  <button type="button" onClick={() => setScreenshot(null)} className="absolute top-2 right-2 w-7 h-7 rounded-lg bg-black/60 text-white flex items-center justify-center"><X size={14} /></button>
                  <div className="px-3 py-2 bg-white border-t border-slate-100 text-[11px] text-slate-500 truncate flex items-center gap-1.5"><ImageDown size={12} />{screenshot.name}</div>
                </div>
              ) : (
                <label className={cn('flex flex-col items-center justify-center gap-2 py-8 rounded-xl border-2 border-dashed cursor-pointer transition-all', errors.paymentScreenshot ? 'border-rose-300 bg-rose-50' : 'border-slate-200 hover:border-slate-300 bg-slate-50')}>
                  <Upload size={22} className="text-slate-400" />
                  <span className="text-[12px] font-semibold text-slate-500">Tap to upload screenshot</span>
                  <span className="text-[10px] text-slate-400">PNG or JPG, max 5MB</span>
                  <input type="file" accept="image/*" className="hidden" onChange={e => onFile(e.target.files?.[0])} />
                </label>
              )}
              {errors.paymentScreenshot && <p className="text-[11px] text-rose-600 mt-1">{Array.isArray(errors.paymentScreenshot) ? errors.paymentScreenshot[0] : errors.paymentScreenshot}</p>}
            </div>

            <div className="rounded-xl bg-amber-50 border border-amber-200 p-3 flex items-start gap-2">
              <Clock3 size={15} className="text-amber-600 flex-shrink-0 mt-0.5" />
              <p className="text-[12px] text-amber-700">Your spot is reserved once submitted. The organizer will verify your payment — you'll still get your QR pass right away.</p>
            </div>

            <Btn type="submit" variant="accent" size="lg" full icon={Send} loading={submitting}>Submit Payment Proof</Btn>
          </form>
        )}
      </Card>
    </div>
  )
}
