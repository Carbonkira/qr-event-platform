import { BrowserRouter, Routes, Route } from 'react-router-dom'
import { CheckCircle2, AlertCircle } from 'lucide-react'
import { AppProvider, useApp } from './context/AppContext'
import ProtectedRoute from './components/layout/ProtectedRoute'
import PublicShell from './components/layout/PublicShell'
import OrgShell from './components/layout/OrgShell'

import Home from './pages/public/Home'
import EventDetail from './pages/public/EventDetail'
import Login from './pages/public/Login'
import RegisterOrganizer from './pages/public/RegisterOrganizer'
import VerifyEmail from './pages/public/VerifyEmail'
import EmailVerified from './pages/public/EmailVerified'
import ForgotPassword from './pages/public/ForgotPassword'
import ResetPassword from './pages/public/ResetPassword'

import Register from './pages/participant/Register'
import Confirm from './pages/participant/Confirm'
import Pass from './pages/participant/Pass'
import FindPass from './pages/participant/FindPass'
import MyTickets from './pages/participant/MyTickets'
import ParticipantFeedback from './pages/participant/Feedback'
import FeedbackDone from './pages/participant/FeedbackDone'

import Dashboard from './pages/admin/Dashboard'
import Events from './pages/admin/Events'
import AdminEventDetail from './pages/admin/EventDetail'
import EditEvent from './pages/admin/EditEvent'
import AdminFeedback from './pages/admin/Feedback'
import Reports from './pages/admin/Reports'
import Templates from './pages/admin/Templates'
import Profile from './pages/admin/Profile'
import Approvals from './pages/admin/Approvals'

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

function AppShell() {
  return (
    <div style={{ fontFamily: "'Inter',system-ui,sans-serif" }} className="min-h-screen bg-[#fafafa] text-slate-800">
      <GlobalStyles />
      <Toasts />
      <Routes>
        <Route element={<PublicShell />}>
          <Route index element={<Home />} />
          <Route path="events/:slug" element={<EventDetail />} />
          <Route path="events/:slug/register" element={<Register />} />
          <Route path="events/:slug/confirm/:regId" element={<Confirm />} />
          <Route path="pass/:regId" element={<Pass />} />
          <Route path="feedback/:regId" element={<ParticipantFeedback />} />
          <Route path="feedback/:regId/done" element={<FeedbackDone />} />
          <Route path="find-pass" element={<FindPass />} />
          <Route path="my-tickets" element={<MyTickets />} />
          <Route path="login" element={<Login />} />
          <Route path="forgot-password" element={<ForgotPassword />} />
          <Route path="reset-password" element={<ResetPassword />} />
          <Route path="organizer/register" element={<RegisterOrganizer />} />
          <Route path="organizer/verify-email" element={<VerifyEmail />} />
          <Route path="email-verified" element={<EmailVerified />} />
        </Route>

        <Route path="organizer" element={<ProtectedRoute><OrgShell /></ProtectedRoute>}>
          <Route index element={<Dashboard />} />
          <Route path="events" element={<Events />} />
          <Route path="events/:id" element={<AdminEventDetail />} />
          <Route path="events/:id/edit" element={<EditEvent />} />
          <Route path="feedback" element={<AdminFeedback />} />
          <Route path="reports" element={<Reports />} />
          <Route path="templates" element={<Templates />} />
          <Route path="profile" element={<Profile />} />
          <Route path="approvals" element={<Approvals />} />
        </Route>

        <Route path="*" element={<NotFound />} />
      </Routes>
    </div>
  )
}

export default function App() {
  return (
    <BrowserRouter>
      <AppProvider>
        <AppShell />
      </AppProvider>
    </BrowserRouter>
  )
}
