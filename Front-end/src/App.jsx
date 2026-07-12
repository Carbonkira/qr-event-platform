import { lazy, Suspense } from 'react'
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { CheckCircle2, AlertCircle } from 'lucide-react'
import { AppProvider, useApp } from './context/AppContext'
import ProtectedRoute from './components/layout/ProtectedRoute'
import AppShell from './components/layout/AppShell'
import ManageLayout from './components/layout/ManageLayout'

// Home is the one page most visitors land on directly, so it's the only
// route kept in the main bundle - everything else is fetched on demand.
// This is what keeps Google Maps (LocationPicker/EventMap), Recharts
// (Reports), and html5-qrcode (the scanner) out of a first-time visitor's
// initial download; see CreateEventModal/EventScannerPanel below for the
// same treatment applied to components rather than whole routes.
import Home from './pages/public/Home'

const EventDetail = lazy(() => import('./pages/public/EventDetail'))
const OrgDirectory = lazy(() => import('./pages/public/OrgDirectory'))
const OrgPublic = lazy(() => import('./pages/public/OrgPublic'))
const DiscussionThread = lazy(() => import('./pages/public/DiscussionThread'))
const Login = lazy(() => import('./pages/public/Login'))
const RegisterOrganizer = lazy(() => import('./pages/public/RegisterOrganizer'))
const VerifyEmail = lazy(() => import('./pages/public/VerifyEmail'))
const EmailVerified = lazy(() => import('./pages/public/EmailVerified'))
const ForgotPassword = lazy(() => import('./pages/public/ForgotPassword'))
const ResetPassword = lazy(() => import('./pages/public/ResetPassword'))
const InviteAccept = lazy(() => import('./pages/public/InviteAccept'))

const Register = lazy(() => import('./pages/participant/Register'))
const Confirm = lazy(() => import('./pages/participant/Confirm'))
const Pass = lazy(() => import('./pages/participant/Pass'))
const FindPass = lazy(() => import('./pages/participant/FindPass'))
const ParticipantFeedback = lazy(() => import('./pages/participant/Feedback'))
const FeedbackDone = lazy(() => import('./pages/participant/FeedbackDone'))

const MyEvents = lazy(() => import('./pages/MyEvents'))
const MyConnections = lazy(() => import('./pages/MyConnections'))
const AdminEventDetail = lazy(() => import('./pages/admin/EventDetail'))
const EditEvent = lazy(() => import('./pages/admin/EditEvent'))
const AdminFeedback = lazy(() => import('./pages/admin/Feedback'))
const Reports = lazy(() => import('./pages/admin/Reports'))
const Templates = lazy(() => import('./pages/admin/Templates'))
const Organizations = lazy(() => import('./pages/admin/Organizations'))
const Profile = lazy(() => import('./pages/admin/Profile'))
const Approvals = lazy(() => import('./pages/admin/Approvals'))

function GlobalStyles() {
  return (
    <style>{`
      @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
      *{box-sizing:border-box;margin:0;padding:0}
      body{font-family:'Inter',system-ui,sans-serif}
      .animate-fade{animation:fade .3s ease}.animate-up{animation:up .35s cubic-bezier(.16,1,.3,1)}
      .animate-modal{animation:modal .25s cubic-bezier(.16,1,.3,1)}
      @keyframes fade{from{opacity:0}to{opacity:1}}
      @keyframes up{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
      @keyframes modal{from{opacity:0;transform:translateY(16px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}
      .line-clamp-2{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
      .line-clamp-3{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
      input,select,textarea,button{font-family:inherit}
      ::-webkit-scrollbar{width:8px;height:8px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:4px}
      /* Horizontal-scroll strips (tabs, action-button rows) rely on the
         swipe/drag gesture itself as the affordance - a visible scrollbar
         underneath them just reads as a stray gray bar. */
      .scrollbar-hide{scrollbar-width:none;-ms-overflow-style:none}
      .scrollbar-hide::-webkit-scrollbar{display:none}
    `}</style>
  )
}

function Toasts() {
  const { toasts } = useApp()
  return (
    <div className="fixed top-4 right-4 z-[100] flex flex-col gap-2">
      {toasts.map(t => (
        <div key={t.id} className="animate-up px-4 py-2.5 rounded-xl text-[13px] font-semibold text-white shadow-lg flex items-center gap-2 min-w-[200px]" style={{ background: t.type === 'error' ? '#e94560' : t.type === 'info' ? '#1a1a2e' : '#0f9d8f' }}>
          {t.type === 'error' ? <AlertCircle size={15} /> : <CheckCircle2 size={15} />}{t.message}
        </div>
      ))}
    </div>
  )
}

function NotFound() {
  return <div className="min-h-screen flex items-center justify-center text-slate-400 text-sm">Page not found.</div>
}

function AppRoutes() {
  return (
    <div style={{ fontFamily: "'Inter',system-ui,sans-serif" }} className="min-h-screen bg-[#fafafa] text-slate-800">
      <GlobalStyles />
      <Toasts />
      <Suspense fallback={<div className="text-center py-24 text-slate-400 text-[13px]">Loading…</div>}>
      <Routes>
        <Route element={<AppShell />}>
          <Route index element={<Home />} />
          <Route path="events/:slug" element={<EventDetail />} />
          <Route path="organizations" element={<OrgDirectory />} />
          <Route path="org/:slug" element={<OrgPublic />} />
          <Route path="org/:slug/discussion/:threadId" element={<DiscussionThread />} />
          <Route path="events/:slug/register" element={<Register />} />
          <Route path="events/:slug/confirm/:regId" element={<Confirm />} />
          <Route path="pass/:regId" element={<Pass />} />
          <Route path="feedback/:regId" element={<ParticipantFeedback />} />
          <Route path="feedback/:regId/done" element={<FeedbackDone />} />
          <Route path="find-pass" element={<FindPass />} />
          <Route path="login" element={<Login />} />
          <Route path="forgot-password" element={<ForgotPassword />} />
          <Route path="reset-password" element={<ResetPassword />} />
          <Route path="organizer/register" element={<RegisterOrganizer />} />
          <Route path="organizer/verify-email" element={<VerifyEmail />} />
          <Route path="email-verified" element={<EmailVerified />} />
          <Route path="invites/:token" element={<InviteAccept />} />

          {/* Old links/bookmarks - kept working, redirected to their new home. */}
          <Route path="my-tickets" element={<Navigate to="/my-events" replace />} />
          <Route path="profile" element={<Navigate to="/organizer/profile" replace />} />

          <Route path="my-events" element={<ProtectedRoute><MyEvents /></ProtectedRoute>} />
          <Route path="connections" element={<ProtectedRoute><MyConnections /></ProtectedRoute>} />

          <Route path="organizer" element={<ProtectedRoute><ManageLayout /></ProtectedRoute>}>
            <Route index element={<Navigate to="/my-events" replace />} />
            <Route path="events/:id" element={<AdminEventDetail />} />
            <Route path="events/:id/edit" element={<EditEvent />} />
            <Route path="feedback" element={<AdminFeedback />} />
            <Route path="reports" element={<Reports />} />
            <Route path="templates" element={<Templates />} />
            <Route path="organizations" element={<Organizations />} />
            <Route path="profile" element={<Profile />} />
            <Route path="approvals" element={<Approvals />} />
          </Route>
        </Route>

        <Route path="*" element={<NotFound />} />
      </Routes>
      </Suspense>
    </div>
  )
}

export default function App() {
  return (
    <BrowserRouter>
      <AppProvider>
        <AppRoutes />
      </AppProvider>
    </BrowserRouter>
  )
}
