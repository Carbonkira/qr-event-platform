import { Link, useLocation } from 'react-router-dom'
import { CheckCircle2, Calendar, Trophy } from 'lucide-react'
import { Btn, Card } from '../../components/ui'

export default function FeedbackDone() {
  const { badge, rating } = useLocation().state || {}

  return (
    <div className="max-w-sm mx-auto px-5 py-16 text-center">
      <Card className="p-8">
        {badge ? (
          <div className="w-20 h-20 rounded-full bg-gradient-to-br from-amber-400 to-amber-500 flex items-center justify-center mx-auto mb-4">
            <Trophy size={32} className="text-white" />
          </div>
        ) : (
          <div className="w-16 h-16 rounded-2xl bg-emerald-50 flex items-center justify-center mx-auto mb-4"><CheckCircle2 size={32} className="text-emerald-500" /></div>
        )}
        <h2 className="text-xl font-extrabold mb-2">Thank you!</h2>
        {badge && <div className="inline-flex items-center gap-1.5 mb-3 px-4 py-1.5 rounded-full bg-gradient-to-r from-amber-400 to-amber-500 text-white font-bold text-[13px]"><Trophy size={14} />{badge}</div>}
        <p className="text-[13px] text-slate-500 mb-1">Your feedback helps improve future events.</p>
        {rating > 0 && <p className="text-[13px] text-slate-400 mb-6">You rated this {rating}/5</p>}
        <Link to="/"><Btn variant="primary" size="lg" full icon={Calendar}>Browse More Events</Btn></Link>
      </Card>
    </div>
  )
}
