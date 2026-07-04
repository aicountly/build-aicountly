import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { http } from '../lib/api.js'
import DataTable from '../components/common/DataTable.jsx'
import StatusBadge from '../components/common/StatusBadge.jsx'
import { fmtRelative } from '../lib/format.js'

export default function StagingDeployments() {
  const [rows, setRows] = useState(null)
  const [err, setErr] = useState('')
  const [msg, setMsg] = useState('')

  const load = () => http.get('/deployments?environment=staging').then(setRows).catch((e) => setErr(e?.message || 'Failed to load'))
  useEffect(load, [])

  async function approve(id) {
    setMsg('')
    try { await http.post(`/deployments/${id}/approve`); setMsg('Approved.'); load() }
    catch (e) { setErr(e?.message || 'Approve failed') }
  }
  async function markDeployed(id) {
    setMsg('')
    try { await http.post(`/deployments/${id}/deployed`); setMsg('Marked deployed.'); load() }
    catch (e) { setErr(e?.message || 'Mark failed') }
  }

  return (
    <div className="space-y-3">
      <p className="text-xs text-neutral-500">
        Staging deployments require a staging_deploy approval. Production deployments live in their own page.
      </p>
      {msg && <div className="rounded bg-aicountly-50 p-2 text-xs text-aicountly-700">{msg}</div>}
      {err && <div className="rounded bg-red-50 p-2 text-xs text-red-700">{err}</div>}
      <DeploymentTable rows={rows} onApprove={approve} onDeployed={markDeployed} />
    </div>
  )
}

function DeploymentTable({ rows, onApprove, onDeployed }) {
  return (
    <DataTable
      rows={rows || []}
      columns={[
        { key: 'id',      header: 'ID',     render: (r) => <span className="font-mono text-xs">#{r.id}</span> },
        { key: 'dev_req', header: 'Dev Request', render: (r) => <Link className="font-mono text-xs text-aicountly-700 hover:underline" to={`/dev-requests/${r.dev_request_id}`}>#{r.dev_request_id}</Link> },
        { key: 'env',     header: 'Env',    render: (r) => <span className="build-tag">{r.environment}</span> },
        { key: 'status',  header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
        { key: 'age',     header: 'Requested', render: (r) => <span className="text-xs text-neutral-500">{fmtRelative(r.created_at)}</span> },
        { key: 'actions', header: '', render: (r) => (
          <div className="flex gap-1">
            {r.status === 'requested' && <button className="build-btn-secondary text-xs" onClick={() => onApprove(r.id)}>Approve</button>}
            {(r.status === 'approved' || r.status === 'provider_unconfigured') && <button className="build-btn-primary text-xs" onClick={() => onDeployed(r.id)}>Mark deployed</button>}
          </div>
        ) },
      ]}
    />
  )
}
