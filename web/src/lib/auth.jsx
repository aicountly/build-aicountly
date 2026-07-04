import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import { api, getToken, http, setToken, v1 } from './api'

const AuthCtx = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  const refresh = useCallback(async () => {
    if (!getToken()) {
      setUser(null)
      setLoading(false)
      return
    }
    try {
      const me = await http.get('/me')
      setUser(me)
    } catch {
      setUser(null)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { refresh() }, [refresh])

  const login = useCallback(async (email, password) => {
    const { data: body } = await api.post(v1('/auth/login'), { email, password })
    if (!body?.success) {
      const err = new Error(body?.message || 'Login failed')
      err.errors = body?.errors || {}
      throw err
    }
    const token = body?.data?.token
    if (!token) throw new Error('Login succeeded but no token was returned')
    setToken(token)
    setUser(body.data.user)
    return body.data.user
  }, [])

  const logout = useCallback(async () => {
    try { await http.post('/auth/logout') } catch { /* ignore */ }
    setToken('')
    setUser(null)
  }, [])

  const hasRole = useCallback(
    (roles) => {
      if (!user) return false
      const allowed = Array.isArray(roles) ? roles : [roles]
      return allowed.some((r) => (user.roles || []).includes(r))
    },
    [user],
  )

  return (
    <AuthCtx.Provider value={{ user, loading, login, logout, refresh, hasRole }}>
      {children}
    </AuthCtx.Provider>
  )
}

export function useAuth() {
  const ctx = useContext(AuthCtx)
  if (!ctx) throw new Error('useAuth must be inside <AuthProvider>')
  return ctx
}
