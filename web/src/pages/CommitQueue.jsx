import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { http } from '../lib/api.js'
import DataTable from '../components/common/DataTable.jsx'
import StatusBadge from '../components/common/StatusBadge.jsx'
import { fmtRelative } from '../lib/format.js'

export default function CommitQueue() {
  const [rows, setRows] = useState(null)
  const [err, setErr] = useState('')
  const [msg, setMsg] = useState('')

  const load = () => http.get('/commits').then(setRows).catch((e) => setErr(e?.message || 'Failed to load'))
  useEffect(load, [])

  async function approve(id) {
    setMsg('')
    try { await http.post(`/commits/${id}/approve`); setMsg(`Commit #${id} approved.`); load() }
    catch (e) { setErr(e?.message || 'Approve failed') }
  }
  async function execute(id) {
    setMsg('')
    try { await http.post(`/commits/${id}/execute`); setMsg(`Commit #${id} pushed to GitHub.`); load() }
    catch (e) { setErr(e?.message || 'Execute failed') }
  }

  return (
    <div className="space-y-3">
      {msg && <div className="rounded bg-aicountly-50 p-2 text-xs text-aicountly-700">{msg}</div>}
      {err && <div className="rounded bg-red-50 p-2 text-xs text-red-700">{err}</div>}
      <DataTable
        rows={rows || []}
        columns={[
          { key: 'id',      header: 'ID',    render: (r) => <span className="font-mono text-xs">#{r.id}</span> },
          { key: 'dev_req', header: 'Dev Request', render: (r) => <Link className="font-mono text-xs text-aicountly-700 hover:underline" to={`/dev-requests/${r.dev_request_id}`}>#{r.dev_request_id}</Link> },
          { key: 'branch',  header: 'Branch', render: (r) => <span className="font-mono text-xs">{r.branch}</span> },
          { key: 'sha',     header: 'SHA',    render: (r) => <span className="font-mono text-xs">{r.sha ? r.sha.slice(0,7) : '—'}</span> },
          { key: 'status',  header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
          { key: 'age',     header: 'Created', render: (r) => <span className="text-xs text-neutral-500">{fmtRelative(r.created_at)}</span> },
          { key: 'actions', header: '', render: (r) => (
            <div className="flex gap-1">
              {r.status === 'pending'  && <button className="build-btn-secondary text-xs" onClick={() => approve(r.id)}>Approve</button>}
              {r.status === 'approved' && <button className="build-btn-primary text-xs" onClick={() => execute(r.id)}>Push to GitHub</button>}
            </div>
          ) },
        ]}
      />
    </div>
  )
}
