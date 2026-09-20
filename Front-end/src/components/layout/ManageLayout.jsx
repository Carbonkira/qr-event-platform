import { Link, Outlet } from 'react-router-dom'
import { Hourglass, ShieldAlert } from 'lucide-react'
import { Card } from '../ui'
import { applicationStatusOf } from '../shared/ApplicationStatus'
import { useApp } from '../../context/AppContext'

// The old OrgShell supplied this padding/max-width around every organizer
// page (Reports, Feedback, Templates, Profile, Approvals, EventDetail,
// EditEvent) - they're all bare `space-y-*` divs with no width constraint
// of their own. AppShell itself stays unpadded (most other pages, like
// Home/Register/Login, already self-wrap), so this narrower layout is
// scoped to just the /organizer/* subtree instead.
//
// Also the single gatekeeper for the whole subtree: a Participant account
// has no legitimate reason to ever be here (genuinely separate account
// types, see Backend's Organizer/Participant models) - ProtectedRoute only
// checks "is anyone logged in", so a participant typing/bookmarking a
// /organizer/* URL would otherwise land on a page whose API calls just 403.
export default function ManageLayout() {
  const { accountType, user } = useApp()
  const applicationStatus = applicationStatusOf(user, accountType)

  // An organizer the admin hasn't approved (yet, or at all) has the account
  // type but not the access - every page below would just answer 403. Say why
  // instead, and point at where their status lives.
  if (applicationStatus) {
    return (
      <div className="max-w-6xl mx-auto px-5 py-8 w-full">
        <Card className="p-14 text-center">
          <Hourglass size={32} className="mx-auto text-slate-300 mb-2" />
          <p className="text-[13px] font-semibold text-slate-600">{applicationStatus === 'rejected' ? 'Your application wasn\'t approved' : 'Waiting for admin approval'}</p>
          <p className="text-[13px] text-slate-400 mt-1">Event management tools unlock once an admin approves your organizer application.</p>
          <Link to="/my-events" className="inline-block mt-4 text-[13px] font-semibold text-[var(--accent)] hover:underline">See your application status</Link>
        </Card>
      </div>
    )
  }

  if (accountType === 'participant') {
    return (
      <div className="max-w-6xl mx-auto px-5 py-8 w-full">
        <Card className="p-14 text-center">
          <ShieldAlert size={32} className="mx-auto text-slate-300 mb-2" />
          <p className="text-[13px] font-semibold text-slate-600">Organizer accounts only</p>
          <p className="text-[13px] text-slate-400 mt-1">You're signed in as a participant - event management tools aren't part of that account.</p>
        </Card>
      </div>
    )
  }

  return (
    <div className="max-w-6xl mx-auto px-5 py-8 w-full">
      <Outlet />
    </div>
  )
}
