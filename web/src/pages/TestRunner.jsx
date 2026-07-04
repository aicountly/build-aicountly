import { useState } from 'react'
import { http } from '../lib/api.js'
import { Card } from '../components/common/Card.jsx'

export default function TestRunner() {
  const [devRequestId, setDevRequestId] = useState('')
  const [suites, setSuites] = useState('default')
  const [latest, setLatest] = useState(null)
  const [busy, setBusy] = useState(false)
  const [err, setErr]   = useState('')
  const [msg, setMsg]   = useState('')

  async function run() {
    setBusy(true); setErr(''); setMsg('')
    try {
      const res = await http.post('/tests/run', {
        dev_request_id: Number(devRequestId),
        suites: suites.split(',').map((s) => s.trim()).filter(Boolean),
      })
      setMsg(`Test run recorded (task #${res.code_task_id}).`)
      await lookupLatest()
    } catch (e) { setErr(e?.message || 'Failed to record test run') } finally { setBusy(false) }
  }
  async function lookupLatest() {
    if (!devRequestId) return
    setLatest(await http.get(`/tests/latest/${Number(devRequestId)}`))
  }

  return (
    <div className="max-w-2xl space-y-4">
      <Card title="Record a test run" subtitle="Build tracks intent; actual CI execution happens on the target repo.">
        <div className="grid gap-3 sm:grid-cols-2">
          <div>
            <label className="build-label">Dev request ID</label>
            <input type="number" className="build-input" value={devRequestId} onChange={(e) => setDevRequestId(e.target.value)} />
          </div>
          <div>
            <label className="build-label">Suites (comma-separated)</label>
            <input className="build-input" value={suites} onChange={(e) => setSuites(e.target.value)} placeholder="unit,smoke" />
          </div>
        </div>
        <div className="mt-3 flex gap-2">
          <button className="build-btn-primary" disabled={busy || !devRequestId} onClick={run}>{busy ? 'Recording…' : 'Run tests'}</button>
          <button className="build-btn-secondary" disabled={!devRequestId} onClick={lookupLatest}>Latest for this request</button>
        </div>
        {msg && <div className="mt-3 rounded bg-aicountly-50 p-2 text-xs text-aicountly-700">{msg}</div>}
        {err && <div className="mt-3 rounded bg-red-50 p-2 text-xs text-red-700">{err}</div>}
      </Card>

      {latest && (
        <Card title="Latest test run">
          <pre className="build-code">{JSON.stringify(latest, null, 2)}</pre>
        </Card>
      )}
    </div>
  )
}
