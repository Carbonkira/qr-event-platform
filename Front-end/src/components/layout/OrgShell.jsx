import { useState } from 'react'
import { Link, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { BarChart3, Hourglass, MessageSquare, FileText, ClipboardList, Building2, QrCode, LogOut, Plus, MailWarning } from 'lucide-react'
import { Btn } from '../ui'
import { cn } from '../../lib/utils'
import { useApp } from '../../context/AppContext'
import { useAnalytics } from '../../hooks/useApi'
import CreateEventModal from '../admin/CreateEventModal'

// Scanning, editing, guests, checklist, and reporting all now live inside
// each event's own page (EventDetail) - the sidebar only lists things that
// span *every* event: approvals, org-wide feedback/reports, reusable
// checklist templates, and account settings.
const NAV_ITEMS = [
  { icon: BarChart3, label: 'Dashboard', path: '/organizer' },
  // Admin-only: the API rejects approve/reject from non-admins regardless -
  // hiding the link just keeps organizers from hitting a 403 they can't act on.
  { icon: Hourglass, label: 'Approvals', path: '/organizer/approvals', badgeKey: 'pendingApprovals', adminOnly: true },
  { icon: MessageSquare, label: 'Feedback', path: '/organizer/feedback' },
  { icon: FileText, label: 'Reports', path: '/organizer/reports' },
  { icon: ClipboardList, label: 'Checklists', path: '/organizer/templates' },
  { icon: Building2, label: 'Profile', path: '/organizer/profile' },
]

export default function OrgShell() {
  const location = useLocation()
  const navigate = useNavigate()
  const { user, logout, addToast, resendVerificationEmail } = useApp()
  const { data: analytics } = useAnalytics()
  const [createOpen, setCreateOpen] = useState(false)
  const [resending, setResending] = useState(false)

  const onExit = async () => {
    await logout()
    navigate('/')
  }

  const resend = async () => {
    setResending(true)
    try { await resendVerificationEmail(); addToast('Verification email sent!', 'success') }
    catch (err) { addToast(err.message || 'Could not resend right now', 'error') }
    finally { setResending(false) }
  }

  return (
    <div className="flex min-h-screen">
      <aside className="w-60 bg-[#1a1a2e] flex flex-col sticky top-0 h-screen flex-shrink-0">
        <div className="px-5 h-16 flex items-center gap-2 border-b border-white/10">
          <div className="w-8 h-8 rounded-lg bg-[#e94560] flex items-center justify-center"><QrCode size={17} className="text-white" /></div>
          <span className="font-extrabold text-white text-[15px]">QRMeets</span>
        </div>
        <nav className="flex-1 p-3 space-y-1 overflow-y-auto">
          {NAV_ITEMS.filter(item => !item.adminOnly || user?.role === 'admin').map(({ icon: Icon, label, path, badgeKey }) => {
            const active = location.pathname === path
            const badge = badgeKey ? analytics?.[badgeKey] : null
            return (
              <Link key={path} to={path} className={cn('w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-[13px] font-semibold transition-all', active ? 'bg-white/10 text-white' : 'text-slate-400 hover:text-white hover:bg-white/5')}>
                <Icon size={17} /><span className="flex-1 text-left">{label}</span>
                {badge > 0 && <span className="min-w-5 h-5 px-1.5 rounded-full bg-[#e94560] text-white text-[10px] font-bold flex items-center justify-center">{badge}</span>}
              </Link>
            )
          })}
        </nav>
        <div className="p-3 border-t border-white/10">
          <div className="flex items-center gap-2.5 px-2 py-2 mb-1">
            <div className="w-8 h-8 rounded-full bg-gradient-to-br from-[#e94560] to-[#6d28d9] flex items-center justify-center text-white font-bold text-[13px]">{user?.name?.[0]?.toUpperCase() || '·'}</div>
            <div className="min-w-0"><p className="text-[12px] font-semibold text-white truncate">{user?.name || 'Account'}</p><p className="text-[10px] text-slate-400 truncate">{user?.email}</p></div>
          </div>
          <button onClick={onExit} className="w-full flex items-center gap-2.5 px-3.5 py-2 rounded-xl text-[12px] text-slate-400 hover:text-white hover:bg-white/5"><LogOut size={15} />Exit to public site</button>
        </div>
      </aside>
      <div className="flex-1 min-w-0 flex flex-col">
        <header className="h-16 bg-white border-b border-slate-200 px-6 flex items-center justify-between sticky top-0 z-20">
          <span />
          <Btn variant="accent" size="md" icon={Plus} onClick={() => setCreateOpen(true)}>Create Event</Btn>
        </header>
        {user && !user.emailVerifiedAt && (
          <div className="px-6 py-2.5 bg-amber-50 border-b border-amber-200 flex items-center justify-center gap-3">
            <p className="text-[12px] text-amber-700 flex items-center gap-1.5"><MailWarning size={13} />Verify your email ({user.email}) to make sure you don't miss event notifications.</p>
            <button onClick={resend} disabled={resending} className="text-[12px] font-semibold text-amber-800 underline hover:text-amber-900 disabled:opacity-50">{resending ? 'Sending…' : 'Resend link'}</button>
          </div>
        )}
        <main className="flex-1 p-6 max-w-6xl w-full animate-fade">
          <Outlet context={{ openCreate: () => setCreateOpen(true), toast: addToast }} />
        </main>
      </div>
      <CreateEventModal open={createOpen} onClose={() => setCreateOpen(false)} toast={addToast} />
    </div>
  )
}
