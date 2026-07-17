import { api, setToken } from './client'

// ─── Auth ───
// There's only one kind of account - it can organize events and register
// for other people's events at the same time (see Backend's User model).
// `createAccount` backs both the "become an organizer" page and the
// account-creation step baked into event registration.
export async function login(email, password) {
  const data = await api.post('/auth/login', { email, password })
  setToken(data.token)
  return data.user
}
export async function createAccount(payload) {
  const data = await api.post('/auth/register', payload)
  setToken(data.token)
  return data.user
}
export function resendVerificationEmail() {
  return api.post('/auth/email/verification-notification')
}
export function forgotPassword(email) {
  return api.post('/auth/forgot-password', { email })
}
export function resetPassword(payload) {
  return api.post('/auth/reset-password', payload)
}
export async function validateResetToken(email, token) {
  const { valid } = await api.post('/auth/reset-password/validate', { email, token })
  return valid
}
export async function logout() {
  // Logging out locally (clearing the token/user state) has to succeed
  // even if the server-side call fails - e.g. the token was already
  // invalid (expired, or revoked by logging in elsewhere - see
  // AuthController::login's single-session policy), which 401s here and,
  // uncaught, used to leave the UI looking still logged in since neither
  // setUser(null) nor the post-logout redirect ever ran.
  try { await api.post('/auth/logout') } catch { /* best-effort */ } finally { setToken(null) }
}
export function me() {
  return api.get('/auth/me')
}
export function updateProfile(payload) {
  return api.put('/auth/me', payload)
}
export function uploadAvatar(file) {
  const form = new FormData()
  form.append('avatar', file)
  return api.post('/auth/me/avatar', form)
}

// ─── Events ───
export function getPublicEvents(params) {
  return api.get('/events', params)
}
export function getAdminEvents() {
  return api.get('/admin/events')
}
export function getEvent(slug) {
  return api.get(`/events/${slug}`)
}
export function createEvent(payload) {
  return api.post('/events', payload)
}
export async function generateEventDescription(title, type, industry) {
  const { description } = await api.post('/events/generate-description', { title, type, industry })
  return description
}
export async function uploadEventImage(file) {
  const form = new FormData()
  form.append('image', file)
  const { url } = await api.post('/events/upload-image', form)
  return url
}
export function updateEvent(id, payload) {
  return api.put(`/events/${id}`, payload)
}
export function deleteEvent(id) {
  return api.del(`/events/${id}`)
}
export function approveEvent(id) {
  return api.post(`/events/${id}/approve`)
}
export function rejectEvent(id) {
  return api.post(`/events/${id}/reject`)
}
export function submitEvent(id) {
  return api.post(`/events/${id}/submit`)
}
export function completeEvent(id) {
  return api.post(`/events/${id}/complete`)
}
export function cancelEvent(id) {
  return api.post(`/events/${id}/cancel`)
}
export function duplicateEvent(id) {
  return api.post(`/events/${id}/duplicate`)
}
export function addTask(eventId, label) {
  return api.post(`/events/${eventId}/tasks`, { label })
}
export function toggleTask(eventId, taskId) {
  return api.patch(`/events/${eventId}/tasks/${taskId}`)
}

// ─── Registrations ───
// Pre-event registration requires an account (routes/api.php puts this
// behind auth:sanctum) — the frontend logs the user in/up first, then calls
// this. Self-serve walk-ins go through walkInForEvent() instead, which
// stays unauthenticated.
export function registerForEvent(eventId, payload) {
  // Use multipart only when there's a file to send — a paymentScreenshot
  // File object (payment proof upload). Plain JSON otherwise.
  if (payload.paymentScreenshot instanceof File) {
    const form = new FormData()
    Object.entries(payload).forEach(([key, value]) => {
      if (value === undefined || value === null) return
      if (key === 'customData') {
        // Bracket notation, not JSON.stringify — Laravel parses
        // customData[fieldId]=value into a proper nested array; a JSON
        // string would fail the `array` validation rule.
        Object.entries(value).forEach(([fieldId, fieldValue]) => form.append(`customData[${fieldId}]`, fieldValue))
        return
      }
      form.append(key, value)
    })
    return api.post(`/events/${eventId}/register`, form)
  }
  return api.post(`/events/${eventId}/register`, payload)
}
export function walkInForEvent(eventId, payload) {
  return api.post(`/events/${eventId}/walk-in`, payload)
}
export function addGuest(eventId, payload) {
  return api.post(`/events/${eventId}/registrations`, payload)
}
export function updateRegistration(registrationId, payload) {
  return api.put(`/registrations/${registrationId}`, payload)
}
export function deleteRegistration(registrationId) {
  return api.del(`/registrations/${registrationId}`)
}
export function getRegistrations(eventId) {
  return api.get(`/events/${eventId}/registrations`)
}
export function getMyRegistrations() {
  return api.get('/my/registrations')
}
export function findPassesByEmail(email) {
  return api.get('/pass/lookup', { email })
}
export function getRegistration(id) {
  return api.get(`/registrations/${id}`)
}
export function verifyPayment(registrationId, approved) {
  return api.post(`/registrations/${registrationId}/verify-payment`, { approved })
}
export function promoteRegistration(registrationId) {
  return api.post(`/registrations/${registrationId}/promote`)
}
export function importGuestsCsv(eventId, file) {
  const form = new FormData()
  form.append('file', file)
  return api.post(`/events/${eventId}/registrations/import`, form)
}

// ─── Attendance ───
export function scanAttendance(qrCode) {
  return api.post('/attendance/scan', { qrCode })
}

// ─── Feedback ───
export function submitFeedback(eventId, payload) {
  return api.post(`/events/${eventId}/feedback`, payload)
}
export function getFeedback(eventId) {
  return api.get('/feedback', { event_id: eventId })
}
export function getFeedbackSummary(eventId, refresh = false) {
  return api.post(`/events/${eventId}/feedback-summary${refresh ? '?refresh=true' : ''}`)
}

// ─── Analytics ───
export function getAnalytics() {
  return api.get('/analytics')
}

// ─── Orgs (real multi-organization) ───
export function getMyOrgs() {
  return api.get('/orgs/mine')
}
export function createOrg(payload) {
  return api.post('/orgs', payload)
}
export function updateOrg(id, payload) {
  return api.put(`/orgs/${id}`, payload)
}
export async function uploadOrgLogo(id, file) {
  const form = new FormData()
  form.append('logo', file)
  return api.post(`/orgs/${id}/logo`, form)
}
export function getOrgMembers(id) {
  return api.get(`/orgs/${id}/members`)
}
export function removeOrgMember(id, userId) {
  return api.del(`/orgs/${id}/members/${userId}`)
}
export function getOrgInvites(id) {
  return api.get(`/orgs/${id}/invites`)
}
export function inviteToOrg(id, email) {
  return api.post(`/orgs/${id}/invites`, { email })
}
export function revokeOrgInvite(id, inviteId) {
  return api.del(`/orgs/${id}/invites/${inviteId}`)
}
export function getInvite(token) {
  return api.get(`/invites/${token}`)
}
export function acceptInvite(token) {
  return api.post(`/invites/${token}/accept`)
}
export function getPublicOrg(slug) {
  return api.get(`/org/${slug}`)
}
export function getOrgDirectory() {
  return api.get('/orgs')
}

// ─── Discussion boards (per-organization) ───
export function getDiscussionThreads(orgId) {
  return api.get(`/orgs/${orgId}/discussion`)
}
export function createDiscussionThread(orgId, payload) {
  return api.post(`/orgs/${orgId}/discussion`, payload)
}
export function getDiscussionThread(threadId) {
  return api.get(`/discussion/threads/${threadId}`)
}
export function replyToDiscussionThread(threadId, body) {
  return api.post(`/discussion/threads/${threadId}/replies`, { body })
}

// ─── Connections ───
export function getFellowAttendees(eventId) {
  return api.get(`/events/${eventId}/attendees`)
}
export function getConnections() {
  return api.get('/connections')
}
export function sendConnectionRequest(recipientId) {
  return api.post('/connections', { recipientId })
}
export function acceptConnection(id) {
  return api.post(`/connections/${id}/accept`)
}
export function declineConnection(id) {
  return api.post(`/connections/${id}/decline`)
}
export function removeConnection(id) {
  return api.del(`/connections/${id}`)
}

// ─── Task Templates ───
export function getTaskTemplates() {
  return api.get('/task-templates')
}
export function createTaskTemplate(payload) {
  return api.post('/task-templates', payload)
}
