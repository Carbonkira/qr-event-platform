import { createContext, useContext, useState, useCallback, useEffect } from 'react'
import * as api from '../api/resources'
import { getToken, setToken } from '../api/client'

const AppContext = createContext()

export function AppProvider({ children }) {
  const [user, setUser] = useState(null) // { id, name, email, institution, role }
  const [authReady, setAuthReady] = useState(false)
  const [toasts, setToasts] = useState([])
  const [coords, setCoords] = useState(null)
  const [place, setPlace] = useState(null) // { city, country } - reverse-geocoded, best-effort
  // 'idle' | 'locating' | 'granted' | 'denied' | 'unsupported'
  const [locationStatus, setLocationStatus] = useState('idle')

  // On mount, if a token is stored, restore the session by asking the API who it is.
  useEffect(() => {
    if (!getToken()) { setAuthReady(true); return }
    api.me()
      .then(setUser)
      .catch(() => setToken(null))
      .finally(() => setAuthReady(true))
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
    const onSessionRevoked = () => setUser(null)
    window.addEventListener('auth:session-revoked', onSessionRevoked)
    return () => window.removeEventListener('auth:session-revoked', onSessionRevoked)
  }, [])

  const login = useCallback(async (email, password) => {
    const u = await api.login(email, password)
    setUser(u)
    return u
  }, [])

  // Backs both "become an organizer" and the account-creation step of event
  // registration — there's only one account type (see Backend's User model).
  const createAccount = useCallback(async (payload) => {
    const u = await api.createAccount(payload)
    setUser(u)
    return u
  }, [])

  const logout = useCallback(async () => {
    await api.logout()
    setUser(null)
  }, [])

  const updateProfile = useCallback(async (payload) => {
    const u = await api.updateProfile(payload)
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
      user, authReady, login, createAccount, logout, updateProfile, refreshUser, resendVerificationEmail,
      toasts, addToast, removeToast,
      coords, place, locationStatus,
    }}>
      {children}
    </AppContext.Provider>
  )
}

export const useApp = () => useContext(AppContext)
