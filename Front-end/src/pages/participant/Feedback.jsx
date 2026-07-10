import { useState } from 'react'
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom'
import { MessageSquare, Star, Trophy, Send } from 'lucide-react'
import { Btn, Card, StarRating, Textarea, Toggle } from '../../components/ui'
import { submitFeedback } from '../../api/resources'
import { useFeedback } from '../../hooks/useApi'
import { useApp } from '../../context/AppContext'

const DEFAULT_QUESTIONS = [
  { id: 'q1', label: 'Check-in experience', type: 'rating', required: true },
  { id: 'q2', label: 'Event organization', type: 'rating', required: true },
  { id: 'q3', label: 'Content quality', type: 'rating', required: true },
  { id: 'q4', label: 'Venue & facilities', type: 'rating', required: true },
  { id: 'q5', label: 'Overall satisfaction', type: 'rating', required: true },
]

export default function Feedback() {
  const { regId } = useParams()
  const location = useLocation()
  const navigate = useNavigate()
  const { addToast } = useApp()
  const registration = location.state

  const { data: existingFb } = useFeedback(registration?.eventId)
  const count = (existingFb || []).length
  const previewBadge = count >= 2 ? '🏆 Super Reviewer' : count >= 1 ? '⭐ Top Reviewer' : null

  const event = registration?.event || registration
  // The first 5 questions are always the fixed rating columns (q1-q5,
  // possibly relabeled by the organizer); anything after that is an
  // organizer-added extra question, rating or free text.
  const questions = event?.feedbackQuestions?.length ? event.feedbackQuestions : DEFAULT_QUESTIONS
  const coreQuestions = questions.slice(0, 5)
  const extraQuestions = questions.slice(5)

  const [r, setR] = useState({ q1: 0, q2: 0, q3: 0, q4: 0, q5: 0 })
  const [customAnswers, setCustomAnswers] = useState({})
  const [comment, setComment] = useState('')
  const [important, setImportant] = useState(false)
  const [submitting, setSubmitting] = useState(false)

  if (!registration) {
    return (
      <div className="max-w-md mx-auto px-5 py-16 text-center">
        <Card className="p-8">
          <h2 className="text-xl font-extrabold mb-1">Feedback link expired</h2>
          <p className="text-[13px] text-slate-500 mb-6">We need your pass details to attach feedback to the right event. Find your pass again to continue.</p>
          <Link to="/find-pass"><Btn variant="secondary" full>Find my pass</Btn></Link>
        </Card>
      </div>
    )
  }

  const avg = Object.values(r).some(v => v > 0) ? (Object.values(r).reduce((a, b) => a + b, 0) / 5).toFixed(1) : 0
  const setCustom = (id) => (v) => setCustomAnswers(a => ({ ...a, [id]: v }))

  const submit = async (e) => {
    e.preventDefault()
    if (Object.values(r).some(v => v === 0)) { addToast('Please rate all 5 questions', 'error'); return }
    const missingExtra = extraQuestions.find(q => q.required && !String(customAnswers[q.id] || '').trim())
    if (missingExtra) { addToast(`Please answer "${missingExtra.label}"`, 'error'); return }
    setSubmitting(true)
    try {
      const fb = await submitFeedback(registration.eventId, { registrationId: regId, ...r, comment, isImportant: important, customAnswers })
      navigate(`/feedback/${regId}/done`, { state: { badge: fb.badge, rating: avg } })
    } catch (err) {
      addToast(err.message, 'error')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="max-w-md mx-auto px-5 py-8">
      <Card className="p-6">
        <div className="text-center mb-5">
          <div className="w-12 h-12 rounded-2xl bg-rose-50 flex items-center justify-center mx-auto mb-2"><MessageSquare size={22} className="text-[#e94560]" /></div>
          <h2 className="font-extrabold text-lg">How was it?</h2>
          <p className="text-[12px] text-slate-500">{event.title}</p>
          {previewBadge && <div className="inline-flex items-center gap-1.5 mt-2 px-3 py-1 rounded-full bg-amber-50 border border-amber-200 text-[11px] font-semibold text-amber-700"><Trophy size={11} />Complete to earn {previewBadge}</div>}
        </div>

        <form onSubmit={submit} className="space-y-3">
          {coreQuestions.map(q => (
            <div key={q.id} className="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
              <span className="text-[13px] font-medium text-slate-700">{q.label}</span>
              <StarRating size={18} value={r[q.id]} onChange={v => setR(s => ({ ...s, [q.id]: v }))} />
            </div>
          ))}

          {extraQuestions.map(q => (
            <div key={q.id} className="p-3 rounded-xl bg-slate-50 border border-slate-200">
              <span className="text-[13px] font-medium text-slate-700 block mb-2">{q.label}{q.required && <span className="text-rose-500 ml-0.5">*</span>}</span>
              {q.type === 'rating' ? (
                <StarRating size={18} value={Number(customAnswers[q.id]) || 0} onChange={v => setCustom(q.id)(v)} />
              ) : (
                <input value={customAnswers[q.id] || ''} onChange={e => setCustom(q.id)(e.target.value)} className="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 outline-none focus:border-[#1a1a2e]" />
              )}
            </div>
          ))}

          <Textarea label="Comments & suggestions" value={comment} onChange={e => setComment(e.target.value)} rows={3} placeholder="What stood out? What could be better?" />
          <Toggle checked={important} onChange={setImportant} icon={Star} label="Flag as important for organizer" desc="Highlights this in their report" color="#f59e0b" />
          {avg > 0 && <div className="flex items-center justify-between p-3 rounded-xl bg-emerald-50 border border-emerald-100"><span className="text-[13px] font-semibold text-emerald-700">Your average</span><span className="font-extrabold text-emerald-700">{avg} / 5</span></div>}

          <Btn type="submit" variant="accent" size="lg" full icon={Send} loading={submitting}>Submit Feedback</Btn>
        </form>
      </Card>
    </div>
  )
}
