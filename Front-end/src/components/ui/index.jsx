import { useEffect, useState } from 'react'
import { AlertCircle, X, Star, Eye, EyeOff } from 'lucide-react'
import { cn } from '../../lib/utils'

// Shared UI primitives — extracted from the original App.jsx monolith
// (Btn/Input/Select/Textarea/Toggle/Badge/Card/StarRating/Modal/KPI/PriceTag)
// so every ported page imports one set instead of redefining them.

// The one brand mark, used everywhere it appears (header, favicon, emails)
// so it's never a different icon/color in different places. Keep this in
// sync with public/favicon.svg and Backend/public/logo-email.png.
export const Logo = ({ size = 32 }) => (
  <svg width={size} height={size} viewBox="0 0 32 32" fill="none">
    <rect width="32" height="32" rx="8" fill="#1a1a2e" />
    <rect x="7" y="7" width="8" height="8" rx="1.5" fill="white" />
    <rect x="17" y="7" width="8" height="8" rx="1.5" fill="white" />
    <rect x="7" y="17" width="8" height="8" rx="1.5" fill="white" />
    <rect x="19" y="19" width="4" height="4" rx="1" fill="white" />
  </svg>
)

export const Btn = ({ children, onClick, variant = 'primary', size = 'md', icon: Icon, loading, disabled, className = '', type = 'button', full }) => {
  const base = 'inline-flex items-center justify-center gap-2 font-semibold rounded-xl transition-all duration-150 disabled:opacity-50 disabled:cursor-not-allowed active:scale-[0.98]'
  const sizes = { sm: 'px-3 py-1.5 text-xs', md: 'px-4 py-2.5 text-sm', lg: 'px-6 py-3.5 text-[15px]' }
  const variants = {
    primary: 'bg-[#1a1a2e] text-white hover:bg-[#2d2d44] shadow-sm',
    accent: 'bg-[#e94560] text-white hover:bg-[#d63651] shadow-sm shadow-rose-200',
    secondary: 'bg-white text-slate-700 border border-slate-200 hover:border-slate-300 hover:bg-slate-50',
    ghost: 'text-slate-600 hover:bg-slate-100',
    dark: 'bg-white/10 text-white border border-white/15 hover:bg-white/20 backdrop-blur',
  }
  return <button type={type} onClick={onClick} disabled={disabled || loading} className={cn(base, sizes[size], variants[variant], full && 'w-full', className)}>
    {loading ? <span className="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin" /> : Icon ? <Icon size={size === 'sm' ? 13 : 16} /> : null}{children}
  </button>
}

export const Input = ({ label, value, onChange, placeholder, type = 'text', icon: Icon, error, required, disabled, hint }) => {
  const [revealed, setRevealed] = useState(false)
  const isPassword = type === 'password'
  return (
    <div>
      {label && <label className="block text-[12px] font-semibold text-slate-600 mb-1.5">{label}{required && <span className="text-rose-500 ml-0.5">*</span>}</label>}
      <div className="relative">
        {Icon && <Icon size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />}
        <input type={isPassword && revealed ? 'text' : type} value={value} onChange={onChange} placeholder={placeholder} disabled={disabled}
          className={cn('w-full rounded-xl border text-sm px-3.5 py-2.5 outline-none transition-all', Icon && 'pl-9', isPassword && 'pr-9', error ? 'border-rose-300 bg-rose-50' : 'border-slate-200 bg-white focus:border-[#1a1a2e] focus:ring-2 focus:ring-slate-100', disabled && 'opacity-60 bg-slate-50 cursor-not-allowed')} />
        {isPassword && (
          <button type="button" onClick={() => setRevealed(r => !r)} tabIndex={-1} className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
            {revealed ? <EyeOff size={15} /> : <Eye size={15} />}
          </button>
        )}
      </div>
      {hint && !error && <p className="text-[11px] text-slate-400 mt-1">{hint}</p>}
      {error && <p className="text-[11px] text-rose-600 mt-1 flex items-center gap-1"><AlertCircle size={11} />{error}</p>}
    </div>
  )
}

export const Select = ({ label, value, onChange, options, required }) => (
  <div>
    {label && <label className="block text-[12px] font-semibold text-slate-600 mb-1.5">{label}{required && <span className="text-rose-500 ml-0.5">*</span>}</label>}
    <select value={value} onChange={onChange} className="w-full rounded-xl border border-slate-200 bg-white text-sm px-3.5 py-2.5 outline-none focus:border-[#1a1a2e] focus:ring-2 focus:ring-slate-100 transition-all">
      {options.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
    </select>
  </div>
)

export const Textarea = ({ label, value, onChange, placeholder, rows = 3, required }) => (
  <div>
    {label && <label className="block text-[12px] font-semibold text-slate-600 mb-1.5">{label}{required && <span className="text-rose-500 ml-0.5">*</span>}</label>}
    <textarea value={value} onChange={onChange} placeholder={placeholder} rows={rows} className="w-full rounded-xl border border-slate-200 bg-white text-sm px-3.5 py-2.5 outline-none focus:border-[#1a1a2e] focus:ring-2 focus:ring-slate-100 transition-all resize-none" />
  </div>
)

export const Toggle = ({ checked, onChange, label, desc, icon: Icon, color = '#1a1a2e' }) => (
  <button type="button" onClick={() => onChange(!checked)} className={cn('w-full flex items-center gap-3 p-3 rounded-xl border transition-all text-left', checked ? 'border-slate-300 bg-slate-50' : 'border-slate-200 bg-white hover:border-slate-300')}>
    {Icon && <div className={cn('w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0', checked ? 'text-white' : 'bg-slate-100 text-slate-400')} style={checked ? { background: color } : {}}><Icon size={16} /></div>}
    <div className="flex-1 min-w-0">
      <p className="text-[13px] font-semibold text-slate-800">{label}</p>
      {desc && <p className="text-[11px] text-slate-500 mt-0.5">{desc}</p>}
    </div>
    <div className={cn('w-10 h-6 rounded-full p-0.5 transition-all flex-shrink-0', checked ? 'bg-[#1a1a2e]' : 'bg-slate-200')}>
      <div className={cn('w-5 h-5 rounded-full bg-white shadow transition-all', checked && 'translate-x-4')} />
    </div>
  </button>
)

export const Badge = ({ children, color = 'slate', size = 'sm' }) => {
  const cls = { slate: 'bg-slate-100 text-slate-600', green: 'bg-emerald-50 text-emerald-700', amber: 'bg-amber-50 text-amber-700', rose: 'bg-rose-50 text-rose-700', violet: 'bg-violet-50 text-violet-700', blue: 'bg-blue-50 text-blue-700', dark: 'bg-[#1a1a2e] text-white' }
  return <span className={cn('inline-flex items-center gap-1 rounded-full font-medium', size === 'xs' ? 'px-2 py-0.5 text-[10px]' : 'px-2.5 py-1 text-[11px]', cls[color])}>{children}</span>
}

export const Card = ({ children, className = '', onClick, hover }) => (
  <div onClick={onClick} className={cn('bg-white rounded-2xl border border-slate-200/70', hover && 'hover:border-slate-300 hover:shadow-md transition-all cursor-pointer', className)}>{children}</div>
)

export const StarRating = ({ value, onChange, readonly, size = 22 }) => (
  <div className="flex gap-1">
    {[1, 2, 3, 4, 5].map(n => <button key={n} type="button" onClick={() => !readonly && onChange?.(n)} className={cn('transition-transform', !readonly && 'hover:scale-110')}>
      <Star size={size} className={n <= value ? 'text-amber-400 fill-amber-400' : 'text-slate-200 fill-slate-100'} />
    </button>)}
  </div>
)

export const Modal = ({ open, onClose, children, size = 'md', title }) => {
  useEffect(() => {
    if (open) { document.body.style.overflow = 'hidden'; return () => { document.body.style.overflow = '' } }
  }, [open])
  if (!open) return null
  const maxW = { sm: 'max-w-md', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-3xl' }
  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center p-4 overflow-y-auto" onClick={onClose}>
      <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" />
      <div className={cn('relative bg-white rounded-2xl shadow-2xl w-full my-8 animate-modal', maxW[size])} onClick={e => e.stopPropagation()}>
        {title && <div className="flex items-center justify-between px-6 py-4 border-b border-slate-100 sticky top-0 bg-white rounded-t-2xl z-10">
          <h3 className="font-bold text-slate-800">{title}</h3>
          <button onClick={onClose} className="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400"><X size={18} /></button>
        </div>}
        {children}
      </div>
    </div>
  )
}

export const KPI = ({ icon: Icon, label, value, sub, color = '#1a1a2e' }) => (
  <Card className="p-5">
    <div className="flex items-center gap-2.5 mb-3">
      <div className="w-9 h-9 rounded-xl flex items-center justify-center text-white" style={{ background: color }}><Icon size={16} /></div>
      <p className="text-[12px] font-semibold text-slate-500">{label}</p>
    </div>
    <p className="text-2xl font-extrabold text-slate-800">{value}</p>
    {sub && <p className="text-[11px] text-slate-400 mt-0.5">{sub}</p>}
  </Card>
)

export const PriceTag = ({ event }) => {
  if (event.pricing === 'free') return <Badge color="green">Free</Badge>
  if (event.pricing === 'walk-in') return <Badge color="blue">Walk-in</Badge>
  return <Badge color="dark">₱{event.price}</Badge>
}
