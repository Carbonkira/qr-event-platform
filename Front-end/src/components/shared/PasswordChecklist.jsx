import { Check, X } from 'lucide-react'
import { cn } from '../../lib/utils'

// Mirrors the backend's actual rule set (Backend/app/Http/Controllers/Api/
// AuthController::passwordRules() - min(8)->mixedCase()->numbers()->symbols())
// so this checklist tells the truth about what will pass validation. The
// one thing it can't preview client-side is uncompromised() (checked
// against HaveIBeenPwned on submit), since that needs the real network call.
const RULES = [
  { label: 'At least 8 characters', test: (pw) => pw.length >= 8 },
  { label: 'Upper & lowercase letters', test: (pw) => /[a-z]/.test(pw) && /[A-Z]/.test(pw) },
  { label: 'At least one number', test: (pw) => /\d/.test(pw) },
  { label: 'At least one symbol', test: (pw) => /[^A-Za-z0-9]/.test(pw) },
]

export default function PasswordChecklist({ password }) {
  const pw = password || ''
  return (
    <div className="grid grid-cols-2 gap-x-3 gap-y-1 -mt-2 mb-1">
      {RULES.map(({ label, test }) => {
        const ok = test(pw)
        return (
          <div key={label} className={cn('flex items-center gap-1.5 text-[11px]', ok ? 'text-emerald-600' : 'text-slate-400')}>
            {ok ? <Check size={11} /> : <X size={11} />}
            {label}
          </div>
        )
      })}
    </div>
  )
}
