import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { http } from '../lib/api.js'
import { Card } from '../components/common/Card.jsx'
import DataTable from '../components/common/DataTable.jsx'
import StatusBadge from '../components/common/StatusBadge.jsx'
import { fmtRelative } from '../lib/format.js'

const KINDS = ['before_screenshot','ui_inspection','smoke_navigation','after_screenshot','visual_evidence_report']

export default function PlaywrightReview() {
  const [rows, setRows] = useState(null)
  const [err, setErr]   = useState('')
  const [msg, setMsg]   = useState('')
  const [form, setForm] = useState({ kind: 'ui_inspection', target_url: '', dev_request_id: '', repo_id: '' })

  const load = () => http.get('/playwright/jobs').then(setRows).catch((e) => setErr(e?.message || 'Failed to load'))
  useEffect(load, [])

  async function enqueue() {
    setErr(''); setMsg('')
    try {
      await http.post('/playwright/jobs', {
        kind: form.kind,
        target_url: form.target_url,
        dev_request_id: form.dev_request_id ? Number(form.dev_request_id) : null,
        repo_id: form.repo_id ? Number(form.repo_id) : null,
      })
      setMsg('Job queued.'); load()
    } catch (e) { setErr(e?.message || 'Enqueue failed') }
  }

  return (
    <div className="space-y-4">
      <Card title="Enqueue Playwright job" subtitle="Worker at worker.apis.aicountly.com. Screenshots and inspections only — never any code or GitHub logic.">
        <div className="grid gap-3 sm:grid-cols-5">
          <div>
            <label className="build-label">Kind</label>
            <select className="build-input" value={form.kind} onChange={(e) => setForm({ ...form, kind: e.target.value })}>
              {KINDS.map((k) => <option key={k} value={k}>{k}</option>)}
            </select>
          </div>
          <div className="sm:col-span-2">
            <label className="build-label">Target URL</label>
            <input className="build-input" value={form.target_url} onChange={(e) => setForm({ ...form, target_url: e.target.value })} placeholder="https://example.aicountly.org/page" />
          </div>
          <div>
            <label className="build-label">Dev request ID (optional)</label>
            <input className="build-input" type="number" value={form.dev_request_id} onChange={(e) => setForm({ ...form, dev_request_id: e.target.value })} />
          </div>
          <div>
            <label className="build-label">Repo ID (optional)</label>
            <input className="build-input" type="number" value={form.repo_id} onChange={(e) => setForm({ ...form, repo_id: e.target.value })} />
          </div>
        </div>
        <div className="mt-3">
          <button className="build-btn-primary" onClick={enqueue} disabled={!form.target_url}>Enqueue</button>
        </div>
        {msg && <div className="mt-3 rounded bg-aicountly-50 p-2 text-xs text-aicountly-700">{msg}</div>}
        {err && <div className="mt-3 rounded bg-red-50 p-2 text-xs text-red-700">{err}</div>}
      </Card>

      <DataTable
        rows={rows || []}
        columns={[
          { key: 'id',       header: 'ID',    render: (r) => <Link className="font-mono text-xs text-aicountly-700 hover:underline" to={`/playwright-review/${r.id}`}>#{r.id}</Link> },
          { key: 'kind',     header: 'Kind',  render: (r) => <span className="build-tag">{r.kind}</span> },
          { key: 'target',   header: 'Target', render: (r) => <span className="font-mono text-xs">{r.target_url}</span> },
          { key: 'status',   header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
          { key: 'dev_req',  header: 'Dev Request', render: (r) => r.dev_request_id ? <Link className="font-mono text-xs text-aicountly-700 hover:underline" to={`/dev-requests/${r.dev_request_id}`}>#{r.dev_request_id}</Link> : <span className="text-neutral-400">—</span> },
          { key: 'age',      header: 'Requested', render: (r) => <span className="text-xs text-neutral-500">{fmtRelative(r.requested_at)}</span> },
        ]}
      />
    </div>
  )
}
