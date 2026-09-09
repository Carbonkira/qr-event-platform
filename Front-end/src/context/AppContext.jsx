import { createContext, useContext, useState, useCallback, useEffect } from 'react'
import * as api from '../api/resources'
import { getToken, setToken, getAccountType } from '../api/client'

const AppContext = createContext()

export function AppProvider({ children }) {
  const [user, setUser] = useState(null) // { id, name, email, institution, role? }
  // 'organizer' | 'participant' | null - genuinely separate account types
  // (see Backend's Organizer/Participant models), not a role flag on one
  // shared account. Set at login/register time, restored from client.js's
  // stored value on mount.
  const [accountType, setAccountTypeState] = useState(() => getAccountType())
  const [authReady, setAuthReady] = useState(false)
  const [toasts, setToasts] = useState([])
  const [coords, setCoords] = useState(null)
  const [place, setPlace] = useState(null) // { city, country } - reverse-geocoded, best-effort
  // 'idle' | 'locating' | 'granted' | 'denied' | 'unsupported'
  const [locationStatus, setLocationStatus] = useState('idle')

  // On mount, if a token is stored, restore the session by asking the API who it is.
  useEffect(() => {
    // Both are always set together (see login/createAccount below) - a
    // token with no known account type shouldn't happen, but would 404
    // against /auth/null/me forever rather than cleanly failing, so treat
    // it the same as no session at all.
    if (!getToken() || !getAccountType()) { setAuthReady(true); return }
    let cancelled = false

    const restore = async () => {
      try {
        const u = await api.me()
        if (!cancelled) setUser(u)
      } catch (err) {
        // Only a genuine 401 means the token itself is actually invalid
        // (expired, revoked, logged in elsewhere) - that used to be the
        // only case handled, but ANY failure (a network blip, or the
        // backend still waking up after sitting idle, which is exactly the
        // kind of gap "closed every tab for a while" produces) was treated
        // the same way and silently deleted a perfectly good token, forcing
        // a real re-login for no reason. One retry after a couple seconds
        // covers the common cold-start case instead of giving up instantly.
        if (err.status === 401) { setToken(null); return }
        await new Promise(r => setTimeout(r, 2000))
        try {
          const u = await api.me()
          if (!cancelled) setUser(u)
        } catch (err2) {
          if (err2.status === 401) setToken(null)
        }
      }
    }

    restore().finally(() => { if (!cancelled) setAuthReady(true) })
    return () => { cancelled = true }
  }, [])

  // Requested once per app load here (not per-page, which is what used to
  // trigger a fresh browser permission prompt every time Explore mounted).
  // Reverse geocoding uses OSM Nominatim - free, no API key/setup required,
  // matching this app's "no key = still works" convention elsewhere.
  useEffect(() => {
    if (!navigator.geolocation) { setLocationStatus('unsupported'); return }
    setLocationStatus('locating')
    navigator.geolocation.getCurrentPosition(
      async (pos) => {
        const { latitude, longitude } = pos.coords
        setCoords({ lat: latitude, lng: longitude })
        setLocationStatus('granted')
        try {
          const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latitude}&lon=${longitude}`, { headers: { 'Accept-Language': 'en' } })
          const data = await res.json()
          const city = data.address?.city || data.address?.town || data.address?.municipality || data.address?.county
          if (city || data.address?.country) setPlace({ city, country: data.address?.country })
        } catch {
          // best-effort - just skip showing a place name if this fails
        }
      },
      () => setLocationStatus('denied'),
      { timeout: 8000, maximumAge: 10 * 60 * 1000 }
    )
  }, [])

  // client.js fires this the moment any request comes back 401 with a token
  // attached - i.e. this session's been revoked, most often because the
  // same account logged in somewhere else (see AuthController::login's
  // single-session policy). Clearing `user` here is what actually kicks
  // this tab back to the login screen instead of leaving it looking live
  // while every request silently fails.
  useEffect(() => {
    const onSessionRevoked = () => { setUser(null); setAccountTypeState(null) }
    window.addEventListener('auth:session-revoked', onSessionRevoked)
    return () => window.removeEventListener('auth:session-revoked', onSessionRevoked)
  }, [])

  // The browser's back-forward cache can restore a page as a frozen
  // snapshot of exactly how it looked before you navigated away, without
  // re-running any app logic - useAsync (see hooks/useApi.js) only ever
  // fetches on mount, and a bfcache restore never re-runs that. In
  // practice: register for an event, then hit Back to the event page or
  // the events list, and it can still show "Register" / an old "going"
  // count instead of your new registration - every page that fetches
  // data this way is affected, not just one. event.persisted is how the
  // browser tells us this was a bfcache restore rather than a real mount;
  // a full reload is the simplest way to guarantee everything on the
  // restored page is genuinely fresh, matching what manually refreshing
  // already fixes for people running into this.
  useEffect(() => {
    const onPageShow = (event) => { if (event.persisted) window.location.reload() }
    window.addEventListener('pageshow', onPageShow)
    return () => window.removeEventListener('pageshow', onPageShow)
  }, [])

  // `type` is 'organizer' or 'participant' - genuinely separate accounts
  // (see Backend's Organizer/Participant models), so every caller has to
  // say which one it means (RegisterOrganizer.jsx / Login.jsx's toggle
  // always pass 'organizer'; Register.jsx's embedded account step always
  // passes 'participant', since registering for an event is participant-only).
  const login = useCallback(async (type, email, password) => {
    const u = await api.login(type, email, password)
    setUser(u)
    setAccountTypeState(type)
    return u
  }, [])

  const createAccount = useCallback(async (type, payload) => {
    const u = await api.createAccount(type, payload)
    setUser(u)
    setAccountTypeState(type)
    return u
  }, [])

  const logout = useCallback(async () => {
    await api.logout()
    setUser(null)
    setAccountTypeState(null)
  }, [])

  const updateProfile = useCallback(async (payload) => {
    const u = await api.updateProfile(payload)
    setUser(u)
    return u
  }, [])

  const uploadAvatar = useCallback(async (file) => {
    const u = await api.uploadAvatar(file)
    setUser(u)
    return u
  }, [])

  // Pulls fresh /auth/me state - needed after the user clicks the
  // verification link in another tab, since nothing else updates
  // `user.emailVerifiedAt` in this tab on its own.
  const refreshUser = useCallback(async () => {
    const u = await api.me()
    setUser(u)
    return u
  }, [])

  const resendVerificationEmail = useCallback(() => api.resendVerificationEmail(), [])

  const addToast = useCallback((message, type = 'success', duration = 3500) => {
    const id = Date.now() + Math.random()
    setToasts(prev => [...prev, { id, message, type }])
    setTimeout(() => setToasts(prev => prev.filter(t => t.id !== id)), duration)
  }, [])

  const removeToast = useCallback((id) => {
    setToasts(prev => prev.filter(t => t.id !== id))
  }, [])

  return (
    <AppContext.Provider value={{
      user, accountType, authReady, login, createAccount, logout, updateProfile, uploadAvatar, refreshUser, resendVerificationEmail,
      toasts, addToast, removeToast,
      coords, place, locationStatus,
    }}>
      {children}
    </AppContext.Provider>
  )
}

export const useApp = () => useContext(AppContext)
