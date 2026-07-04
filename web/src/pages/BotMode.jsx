import { useEffect, useState } from 'react'
import { http } from '../lib/api.js'
import { Card } from '../components/common/Card.jsx'
import BotModeBadge from '../components/build/BotModeBadge.jsx'

export default function BotMode() {
  const [mode, setMode]     = useState(null)
  const [risk, setRisk]     = useState(null)
  const [busy, setBusy]     = useState(false)
  const [msg, setMsg]       = useState('')
  const [err, setErr]       = useState('')

  useEffect(() => {
    http.get('/dashboard/bot-mode').then((d) => { setMode(d.bot_mode); setRisk(d.default_risk) })
      .catch((e) => setErr(e?.message || 'Failed to load bot mode'))
  }, [])

  async function change(next) {
    setBusy(true); setErr(''); setMsg('')
    try {
      await http.post('/dashboard/bot-mode', { bot_mode: next })
      setMode(next); setMsg('Bot mode updated.')
    } catch (e) {
      setErr(e?.message || 'Failed to update')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="max-w-2xl space-y-4">
      <Card title="Bot mode" subtitle="Confirm mode requires superadmin approval before every code write. Auto mode is reserved for known-safe recurring tasks.">
        <div className="flex items-center gap-3">
          <BotModeBadge mode={mode} />
          <span className="text-xs text-neutral-500">Default risk: <span className="font-semibold text-neutral-700">{risk || 'medium'}</span></span>
        </div>
        <div className="mt-4 flex gap-2">
          <button className="build-btn-primary" disabled={busy || mode === 'confirm'} onClick={() => change('confirm')}>Set to Confirm</button>
          <button className="build-btn-secondary" disabled={busy || mode === 'auto'}  onClick={() => change('auto')}>Set to Auto</button>
        </div>
        {msg && <div className="mt-3 rounded bg-aicountly-50 px-3 py-1 text-xs text-aicountly-700">{msg}</div>}
        {err && <div className="mt-3 rounded bg-red-50 px-3 py-1 text-xs text-red-700">{err}</div>}
      </Card>

      <Card title="Safety rules" subtitle="Even in Auto mode the following are always superadmin-gated.">
        <ul className="ml-4 list-disc space-y-1 text-sm text-neutral-700">
          <li>Writing code to any repo</li>
          <li>Creating a branch outside the working-branch prefix</li>
          <li>Creating a commit</li>
          <li>Opening a pull request</li>
          <li>Deploying to production (separate approval, always)</li>
          <li>Any high-risk override (destructive kind: file_delete, branch_delete, force_push)</li>
        </ul>
      </Card>
    </div>
  )
}
