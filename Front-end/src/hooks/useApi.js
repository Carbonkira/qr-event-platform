import { useCallback, useEffect, useState } from 'react'
import * as api from '../api/resources'

// Generic async-fetch hook: calls `fn`, tracks loading/error, and exposes
// `refetch` so mutation handlers can force a refresh after a write.
// `deps` re-runs the fetch when any dependency changes (e.g. an id param).
export function useAsync(fn, deps = []) {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const refetch = useCallback(() => {
    let cancelled = false
    setLoading(true)
    setError(null)
    fn()
      .then(result => { if (!cancelled) setData(result) })
      .catch(err => { if (!cancelled) setError(err) })
      .finally(() => { if (!cancelled) setLoading(false) })
    return () => { cancelled = true }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps)

  useEffect(() => refetch(), [refetch])

  return { data, loading, error, refetch }
}

export function usePublicEvents(params = {}) {
  return useAsync(() => api.getPublicEvents(params), [JSON.stringify(params)])
}
export function useAdminEvents(enabled = true) {
  return useAsync(() => (enabled ? api.getAdminEvents() : Promise.resolve([])), [enabled])
}
export function useEvent(slug) {
  return useAsync(() => (slug ? api.getEvent(slug) : Promise.resolve(null)), [slug])
}
export function useRegistrations(eventId) {
  return useAsync(() => (eventId ? api.getRegistrations(eventId) : Promise.resolve([])), [eventId])
}
export function useMyRegistrations(enabled = true) {
  return useAsync(() => (enabled ? api.getMyRegistrations() : Promise.resolve([])), [enabled])
}
export function useFeedback(eventId) {
  return useAsync(() => api.getFeedback(eventId), [eventId])
}
export function useAnalytics(enabled = true) {
  return useAsync(() => (enabled ? api.getAnalytics() : Promise.resolve(null)), [enabled])
}
export function useOrganization() {
  return useAsync(() => api.getOrganization(), [])
}
export function useMyOrgs(enabled = true) {
  return useAsync(() => (enabled ? api.getMyOrgs() : Promise.resolve([])), [enabled])
}
export function useOrgMembers(id, enabled = true) {
  return useAsync(() => (enabled ? api.getOrgMembers(id) : Promise.resolve([])), [id, enabled])
}
export function useOrgInvites(id, enabled = true) {
  return useAsync(() => (enabled ? api.getOrgInvites(id) : Promise.resolve([])), [id, enabled])
}
export function useTaskTemplates() {
  return useAsync(() => api.getTaskTemplates(), [])
}
