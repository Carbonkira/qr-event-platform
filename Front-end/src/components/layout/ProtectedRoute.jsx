import { Navigate } from 'react-router-dom'
import { useApp } from '../../context/AppContext'

// Guards the /organizer/* subtree — redirects to /login if no organizer
// session is present. Waits for the initial /auth/me check (authReady)
// before deciding, so a page refresh with a valid token doesn't flash
// a redirect to /login before the session is restored.
export default function ProtectedRoute({ children }) {
  const { user, authReady } = useApp()

  if (!authReady) {
    return <div className="min-h-screen flex items-center justify-center text-slate-400 text-sm">Loading…</div>
  }
  if (!user) {
    return <Navigate to="/login" replace />
  }
  return children
}
