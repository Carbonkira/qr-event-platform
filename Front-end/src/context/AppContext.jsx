import { createContext, useContext, useState, useCallback, useEffect } from 'react'
import * as api from '../api/resources'
import { getToken, setToken } from '../api/client'

const AppContext = createContext()

export function AppProvider({ children }) {
  const [user, setUser] = useState(null) // { id, name, email, institution, role }
  const [authReady, setAuthReady] = useState(false)
  const [toasts, setToasts] = useState([])

  // On mount, if a token is stored, restore the session by asking the API who it is.
  useEffect(() => {
    if (!getToken()) { setAuthReady(true); return }
    api.me()
      .then(setUser)
      .catch(() => setToken(null))
      .finally(() => setAuthReady(true))
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
      user, authReady, login, createAccount, logout, updateProfile, resendVerificationEmail,
      toasts, addToast, removeToast,
    }}>
      {children}
    </AppContext.Provider>
  )
}

export const useApp = () => useContext(AppContext)
