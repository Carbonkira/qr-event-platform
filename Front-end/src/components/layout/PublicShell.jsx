import { Link, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { QrCode, Plus, ArrowRight } from 'lucide-react'
import { Btn } from '../ui'
import { useApp } from '../../context/AppContext'
import { cn, locale } from '../../lib/utils'

export default function PublicShell() {
  const location = useLocation()
  const navigate = useNavigate()
  const { user } = useApp()
  // Any account can both organize and attend events - a logged-in visitor's
  // "my tickets" replaces the email-lookup path meant for guests.
  const passPath = user ? '/my-tickets' : '/find-pass'

  return (
    <div>
      <header className="sticky top-0 z-40 bg-[#fafafa]/80 backdrop-blur-md border-b border-slate-200/60">
        <div className="max-w-5xl mx-auto px-5 h-16 flex items-center justify-between">
          <Link to="/" className="flex items-center gap-2">
            <div className="w-8 h-8 rounded-lg bg-[#1a1a2e] flex items-center justify-center"><QrCode size={17} className="text-white" /></div>
            <span className="font-extrabold text-[17px] tracking-tight">QRMeets</span>
          </Link>
          <div className="flex items-center gap-2">
            <Link to={passPath} className={cn('px-3.5 py-2 rounded-xl text-[13px] font-semibold transition-all hidden sm:block', location.pathname === passPath ? 'bg-white border border-slate-200 text-slate-800' : 'text-slate-600 hover:bg-slate-100')}>{user ? 'My Tickets' : 'My Pass'}</Link>
            <Link to="/" className={cn('px-3.5 py-2 rounded-xl text-[13px] font-semibold transition-all', location.pathname === '/' ? 'bg-white border border-slate-200 text-slate-800' : 'text-slate-600 hover:bg-slate-100')}>Browse Events</Link>
            <Btn variant="accent" size="md" icon={Plus} onClick={() => navigate('/organizer')}>{user ? 'Organizer Dashboard' : 'Organize an Event'}</Btn>
          </div>
        </div>
      </header>
      <div className="animate-fade"><Outlet /></div>
      <footer className="max-w-5xl mx-auto px-5 py-10 mt-10 border-t border-slate-200/60 flex flex-col sm:flex-row items-center justify-between gap-3">
        <p className="text-[12px] text-slate-400">QRMeets · Event attendance & feedback, automated · {locale.flag} {locale.city}</p>
        <button onClick={() => navigate('/organizer')} className="text-[12px] font-semibold text-slate-600 hover:text-[#e94560] flex items-center gap-1.5">Organize an Event <ArrowRight size={13} /></button>
      </footer>
    </div>
  )
}
