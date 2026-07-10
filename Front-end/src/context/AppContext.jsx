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
      user, authReady, login, createAccount, logout, resendVerificationEmail,
      toasts, addToast, removeToast,
    }}>
      {children}
    </AppContext.Provider>
  )
}

export const useApp = () => useContext(AppContext)
