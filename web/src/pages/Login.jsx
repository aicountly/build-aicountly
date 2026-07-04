import { useState } from 'react'
import { useNavigate, useLocation } from 'react-router-dom'
import AicountlyLogo from '../components/brand/AicountlyLogo.jsx'
import { useAuth } from '../lib/auth.jsx'

export default function Login() {
  const { login } = useAuth()
  const nav = useNavigate()
  const loc = useLocation()
  const from = loc.state?.from || '/'

  const [email, setEmail]         = useState('')
  const [pass, setPass]           = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [err, setErr]             = useState('')

  async function onSubmit(e) {
    e.preventDefault()
    setSubmitting(true); setErr('')
    try {
      await login(email.trim(), pass)
      nav(from, { replace: true })
    } catch (e) {
      setErr(e?.message || 'Login failed')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="grid h-screen w-screen place-items-center bg-gradient-to-br from-white to-aicountly-50 px-4">
      <div className="w-full max-w-sm">
        <div className="mb-6 flex items-center gap-3">
          <AicountlyLogo className="h-10 w-10" />
          <div>
            <div className="text-base font-semibold text-neutral-900">AICOUNTLY Build</div>
            <div className="text-xs text-neutral-500">build.aicountly.org · independent superadmin auth</div>
          </div>
        </div>

        <form onSubmit={onSubmit} className="build-card">
          <h1 className="text-lg font-semibold text-neutral-900">Sign in</h1>
          <p className="mt-1 text-xs text-neutral-500">
            This portal does <strong>not</strong> use my.aicountly.com login.
            Only superadmins can access it.
          </p>

          <div className="mt-4">
            <label className="build-label" htmlFor="email">Email</label>
            <input
              id="email" type="email" autoComplete="username" required
              className="build-input" value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
          </div>

          <div className="mt-3">
            <label className="build-label" htmlFor="pass">Password</label>
            <input
              id="pass" type="password" autoComplete="current-password" required
              className="build-input" value={pass}
              onChange={(e) => setPass(e.target.value)}
            />
          </div>

          {err ? (
            <div className="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">{err}</div>
          ) : null}

          <button type="submit" className="build-btn-primary mt-5 w-full justify-center" disabled={submitting}>
            {submitting ? 'Signing in…' : 'Sign in'}
          </button>
        </form>

        <p className="mt-4 text-center text-[11px] text-neutral-400">
          Code automation authority · every write action requires superadmin approval
        </p>
      </div>
    </div>
  )
}
