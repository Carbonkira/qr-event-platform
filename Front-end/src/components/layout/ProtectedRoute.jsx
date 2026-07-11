import { Navigate, useLocation } from 'react-router-dom'
import { useApp } from '../../context/AppContext'

// Guards auth-only routes (/my-events, /organizer/*) — redirects to /login
// if no session is present, preserving where they were headed so Login can
// send them back after signing in. Waits for the initial /auth/me check
// (authReady) before deciding, so a page refresh with a valid token doesn't
// flash a redirect to /login before the session is restored.
export default function ProtectedRoute({ children }) {
  const { user, authReady } = useApp()
  const location = useLocation()

  if (!authReady) {
    return <div className="min-h-screen flex items-center justify-center text-slate-400 text-sm">Loading…</div>
  }
  if (!user) {
    return <Navigate to={`/login?next=${encodeURIComponent(location.pathname)}`} replace />
  }
  return children
}
