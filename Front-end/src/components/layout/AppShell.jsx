import { useEffect, useRef, useState } from 'react'
import { Link, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { QrCode, Plus, ChevronDown, LogOut, MailWarning, Hourglass, MessageSquare, FileText, ClipboardList, User as UserIcon } from 'lucide-react'
import { Btn } from '../ui'
import { cn } from '../../lib/utils'
import { useApp } from '../../context/AppContext'
import { useAdminEvents, useAnalytics } from '../../hooks/useApi'
import CreateEventModal from '../admin/CreateEventModal'

// Org-wide tools that don't have a natural per-event home the way
// Checklist/Guests/Scanner do - grouped behind the "Manage" dropdown,
// which only renders once the account actually hosts something (or is
// admin), rather than being a permanent fixture for every attendee.
const MANAGE_ITEMS = [
  { icon: Hourglass, label: 'Approvals', path: '/organizer/approvals', badgeKey: 'pendingApprovals', adminOnly: true },
  { icon: MessageSquare, label: 'Feedback', path: '/organizer/feedback' },
  { icon: FileText, label: 'Reports', path: '/organizer/reports' },
  { icon: ClipboardList, label: 'Checklists', path: '/organizer/templates' },
]

// Single shell for the whole app - replaces the old PublicShell/OrgShell
// split, which required an explicit "exit to public site" mode switch and
// (per live tester feedback) made it look like registering for one event
// granted full organizer-dashboard access. Every route now shares the same
// nav; "Manage" and the Hosting side of My Events only show real data
// because adminIndex/analytics/feedback are scoped server-side to events
// the account actually owns (see EventController/AnalyticsController).
export default function AppShell() {
  const location = useLocation()
  const navigate = useNavigate()
  const { user, logout, addToast, resendVerificationEmail } = useApp()
  const { data: ownEvents } = useAdminEvents(!!user)
  const { data: analytics } = useAnalytics(!!user)
  const [createOpen, setCreateOpen] = useState(false)
  const [resending, setResending] = useState(false)
  const [manageOpen, setManageOpen] = useState(false)
  const [profileOpen, setProfileOpen] = useState(false)
  const manageRef = useRef(null)
  const profileRef = useRef(null)

  const canManage = user?.role === 'admin' || (ownEvents || []).length > 0

  useEffect(() => {
    const onClick = (e) => {
      if (manageRef.current && !manageRef.current.contains(e.target)) setManageOpen(false)
      if (profileRef.current && !profileRef.current.contains(e.target)) setProfileOpen(false)
    }
    document.addEventListener('mousedown', onClick)
    return () => document.removeEventListener('mousedown', onClick)
  }, [])

  const onCreateClick = () => {
    if (!user) { navigate('/login?next=/my-events'); return }
    setCreateOpen(true)
  }

  const onLogout = async () => {
    await logout()
    navigate('/')
  }

  const resend = async () => {
    setResending(true)
    try { await resendVerificationEmail(); addToast('Verification email sent!', 'success') }
    catch (err) { addToast(err.message || 'Could not resend right now', 'error') }
    finally { setResending(false) }
  }

  const navLinkClass = (active) => cn('px-3.5 py-2 rounded-xl text-[13px] font-semibold transition-all whitespace-nowrap', active ? 'bg-white border border-slate-200 text-slate-800' : 'text-slate-600 hover:bg-slate-100')

  return (
    <div>
      <header className="sticky top-0 z-40 bg-[#fafafa]/80 backdrop-blur-md border-b border-slate-200/60">
        <div className="max-w-5xl mx-auto px-5 h-16 flex items-center justify-between gap-2">
          <Link to="/" className="flex items-center gap-2 flex-shrink-0">
            <div className="w-8 h-8 rounded-lg bg-[#1a1a2e] flex items-center justify-center"><QrCode size={17} className="text-white" /></div>
            <span className="font-extrabold text-[17px] tracking-tight hidden sm:block">QRMeets</span>
          </Link>

          <nav className="flex items-center gap-1 flex-1 justify-center overflow-x-auto">
            <Link to="/" className={navLinkClass(location.pathname === '/')}>Explore</Link>
            <Link to="/my-events" className={navLinkClass(location.pathname.startsWith('/my-events'))}>My Events</Link>
            {canManage && (
              <div className="relative" ref={manageRef}>
                <button onClick={() => setManageOpen(o => !o)} className={cn(navLinkClass(location.pathname.startsWith('/organizer/') && !location.pathname.startsWith('/organizer/events')), 'flex items-center gap-1')}>
                  Manage <ChevronDown size={13} />
                </button>
                {manageOpen && (
                  <div className="absolute top-full mt-1.5 left-0 w-48 bg-white rounded-xl border border-slate-200 shadow-lg py-1.5 z-50">
                    {MANAGE_ITEMS.filter(i => !i.adminOnly || user?.role === 'admin').map(({ icon: Icon, label, path, badgeKey }) => (
                      <Link key={path} to={path} onClick={() => setManageOpen(false)} className="flex items-center gap-2.5 px-3.5 py-2 text-[13px] font-medium text-slate-700 hover:bg-slate-50">
                        <Icon size={15} className="text-slate-400" /><span className="flex-1">{label}</span>
                        {badgeKey && analytics?.[badgeKey] > 0 && <span className="min-w-5 h-5 px-1.5 rounded-full bg-[#e94560] text-white text-[10px] font-bold flex items-center justify-center">{analytics[badgeKey]}</span>}
                      </Link>
                    ))}
                  </div>
                )}
              </div>
            )}
          </nav>

          <div className="flex items-center gap-2 flex-shrink-0">
            <Btn variant="accent" size="md" icon={Plus} onClick={onCreateClick}>Create Event</Btn>
            {user ? (
              <div className="relative" ref={profileRef}>
                <button onClick={() => setProfileOpen(o => !o)} className="w-9 h-9 rounded-full bg-gradient-to-br from-[#e94560] to-[#6d28d9] flex items-center justify-center text-white font-bold text-[13px] flex-shrink-0">{user.name?.[0]?.toUpperCase() || '·'}</button>
                {profileOpen && (
                  <div className="absolute top-full mt-1.5 right-0 w-52 bg-white rounded-xl border border-slate-200 shadow-lg py-1.5 z-50">
                    <div className="px-3.5 py-2 border-b border-slate-100 mb-1"><p className="text-[13px] font-semibold text-slate-800 truncate">{user.name}</p><p className="text-[11px] text-slate-400 truncate">{user.email}</p></div>
                    <Link to="/organizer/profile" onClick={() => setProfileOpen(false)} className="flex items-center gap-2.5 px-3.5 py-2 text-[13px] font-medium text-slate-700 hover:bg-slate-50"><UserIcon size={15} className="text-slate-400" />Profile</Link>
                    <button onClick={onLogout} className="w-full flex items-center gap-2.5 px-3.5 py-2 text-[13px] font-medium text-slate-700 hover:bg-slate-50"><LogOut size={15} className="text-slate-400" />Log out</button>
                  </div>
                )}
              </div>
            ) : (
              <Link to="/login" className={navLinkClass(location.pathname === '/login')}>Log in</Link>
            )}
          </div>
        </div>
      </header>

      {user && !user.emailVerifiedAt && (
        <div className="px-5 py-2.5 bg-amber-50 border-b border-amber-200 flex items-center justify-center gap-3 flex-wrap">
          <p className="text-[12px] text-amber-700 flex items-center gap-1.5"><MailWarning size={13} />Verify your email ({user.email}) to make sure you don't miss event notifications.</p>
          <button onClick={resend} disabled={resending} className="text-[12px] font-semibold text-amber-800 underline hover:text-amber-900 disabled:opacity-50">{resending ? 'Sending…' : 'Resend link'}</button>
        </div>
      )}

      <div className="animate-fade"><Outlet context={{ openCreate: onCreateClick, toast: addToast }} /></div>

      <CreateEventModal open={createOpen} onClose={() => setCreateOpen(false)} toast={addToast} />
    </div>
  )
}
