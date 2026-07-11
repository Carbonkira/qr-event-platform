import { Outlet } from 'react-router-dom'

// The old OrgShell supplied this padding/max-width around every organizer
// page (Reports, Feedback, Templates, Profile, Approvals, EventDetail,
// EditEvent) - they're all bare `space-y-*` divs with no width constraint
// of their own. AppShell itself stays unpadded (most other pages, like
// Home/Register/Login, already self-wrap), so this narrower layout is
// scoped to just the /organizer/* subtree instead.
export default function ManageLayout() {
  return (
    <div className="max-w-6xl mx-auto px-5 py-8 w-full">
      <Outlet />
    </div>
  )
}
