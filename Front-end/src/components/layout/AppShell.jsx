import { useEffect, useRef, useState, lazy, Suspense } from 'react'
import { Link, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { Plus, ChevronDown, LogOut, MailWarning, Hourglass, MessageSquare, FileText, ClipboardList, Building2, Users2, User as UserIcon, Instagram, Linkedin, Facebook, Twitter, Globe } from 'lucide-react'
import { Btn, Logo } from '../ui'
import { cn } from '../../lib/utils'
import { useApp } from '../../context/AppContext'
import { useAdminEvents, useAnalytics, useOrganization, useConnections } from '../../hooks/useApi'

// Lazy - AppShell renders on every route, and CreateEventModal pulls in
// LocationPicker's Google Maps SDK. Loading it eagerly here meant every
// anonymous visitor downloaded the Maps bundle just to browse events.
// It's only ever needed once someone actually opens the modal.
const CreateEventModal = lazy(() => import('../admin/CreateEventModal'))

// Same social fields as the public event-detail page's SOC map - only
// rendered when the organization has actually filled one in.
const SOCIAL_ICONS = [['instagram', Instagram], ['linkedin', Linkedin], ['facebook', Facebook], ['twitter', Twitter], ['website', Globe]]

// Org-wide tools that don't have a natural per-event home the way
// Checklist/Guests/Scanner do - grouped behind the "Manage" dropdown,
// which only renders once the account actually hosts something (or is
// admin), rather than being a permanent fixture for every attendee.
const MANAGE_ITEMS = [
  { icon: Hourglass, label: 'Approvals', path: '/organizer/approvals', badgeKey: 'pendingApprovals', adminOnly: true },
  { icon: MessageSquare, label: 'Feedback', path: '/organizer/feedback' },
  { icon: FileText, label: 'Reports', path: '/organizer/reports' },
  { icon: ClipboardList, label: 'Checklists', path: '/organizer/templates' },
  { icon: Building2, label: 'My Organizations', path: '/organizer/organizations' },
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
  const { user, logout, addToast, resendVerificationEmail, place } = useApp()
  const { data: ownEvents } = useAdminEvents(!!user)
  const { data: analytics } = useAnalytics(!!user)
  const { data: org } = useOrganization()
  const { data: connections } = useConnections(!!user)
  const incomingCount = connections?.incoming?.length || 0
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
            <Logo size={32} />
            <span className="font-extrabold text-[17px] tracking-tight hidden sm:block">QRMeets</span>
          </Link>

          <nav className="flex items-center gap-1 flex-1 justify-center min-w-0">
            <Link to="/" className={navLinkClass(location.pathname === '/')}>Explore</Link>
            <Link to="/my-events" className={navLinkClass(location.pathname.startsWith('/my-events'))}>My Events</Link>
            <Link to="/organizations" className={navLinkClass(location.pathname.startsWith('/organizations') || location.pathname.startsWith('/org/'))}>Organizations</Link>
            {user && (
              <Link to="/connections" className={cn(navLinkClass(location.pathname.startsWith('/connections')), 'flex items-center gap-1.5')}>
                <Users2 size={14} />Connections
                {incomingCount > 0 && <span className="min-w-4 h-4 px-1 rounded-full bg-[#e94560] text-white text-[10px] font-bold flex items-center justify-center">{incomingCount}</span>}
              </Link>
            )}
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
                <button onClick={() => setProfileOpen(o => !o)} className="w-9 h-9 rounded-full overflow-hidden bg-gradient-to-br from-[#e94560] to-[#6d28d9] flex items-center justify-center text-white font-bold text-[13px] flex-shrink-0">
                  {user.avatar ? <img src={user.avatar} alt="" className="w-full h-full object-cover" /> : (user.name?.[0]?.toUpperCase() || '·')}
                </button>
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

      <footer className="border-t border-slate-200/60 mt-10">
        <div className="max-w-5xl mx-auto px-5 py-10">
          <div className="grid sm:grid-cols-3 gap-8 mb-8">
            <div>
              <div className="flex items-center gap-2 mb-2"><Logo size={24} /><span className="font-extrabold text-[14px] text-slate-800">QRMeets</span></div>
              <p className="text-[12px] text-slate-400 leading-relaxed">Event attendance & feedback, automated{place?.city ? ` · ${place.city}${place.country ? `, ${place.country}` : ''}` : ''}</p>
            </div>
            <div>
              <p className="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-3">Discover</p>
              <div className="space-y-2">
                <Link to="/" className="block text-[13px] text-slate-600 hover:text-[#e94560]">Explore events</Link>
                <Link to="/my-events" className="block text-[13px] text-slate-600 hover:text-[#e94560]">My events</Link>
                <Link to="/find-pass" className="block text-[13px] text-slate-600 hover:text-[#e94560]">Find my pass</Link>
                {user && <Link to="/connections" className="block text-[13px] text-slate-600 hover:text-[#e94560]">My connections</Link>}
              </div>
            </div>
            <div>
              <p className="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-3">Account</p>
              <div className="space-y-2">
                {user ? (
                  <Link to="/organizer/profile" className="block text-[13px] text-slate-600 hover:text-[#e94560]">Profile</Link>
                ) : (
                  <Link to="/login" className="block text-[13px] text-slate-600 hover:text-[#e94560]">Log in</Link>
                )}
                <Link to="/organizer/register" className="block text-[13px] text-slate-600 hover:text-[#e94560]">Host an event</Link>
              </div>
            </div>
          </div>

          {SOCIAL_ICONS.some(([key]) => org?.[key]) && (
            <div className="flex items-center gap-2 mb-8">
              {SOCIAL_ICONS.map(([key, Icon]) => org?.[key] ? (
                <a key={key} href={key === 'website' ? (org[key].startsWith('http') ? org[key] : `https://${org[key]}`) : '#'} target="_blank" rel="noreferrer" className="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-600 transition-all">
                  <Icon size={14} />
                </a>
              ) : null)}
            </div>
          )}

          <div className="pt-6 border-t border-slate-200/60 flex flex-col sm:flex-row items-center justify-between gap-3">
            <p className="text-[12px] text-slate-400">© {new Date().getFullYear()} QRMeets</p>
            {org?.privacyPolicyUrl && <a href={org.privacyPolicyUrl} target="_blank" rel="noreferrer" className="text-[12px] text-slate-400 hover:text-slate-600">Privacy Policy</a>}
          </div>
        </div>
      </footer>

      {createOpen && (
        <Suspense fallback={
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm">
            <span className="w-6 h-6 border-2 border-white/30 border-t-white rounded-full animate-spin" />
          </div>
        }>
          <CreateEventModal open={createOpen} onClose={() => setCreateOpen(false)} toast={addToast} />
        </Suspense>
      )}
    </div>
  )
}
