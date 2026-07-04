import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { http } from '../lib/api.js'
import DataTable from '../components/common/DataTable.jsx'
import StatusBadge from '../components/common/StatusBadge.jsx'
import FilterBar from '../components/common/FilterBar.jsx'
import ApprovalActions from '../components/build/ApprovalActions.jsx'
import { fmtRelative } from '../lib/format.js'

const ACTIONS = ['code','commit','pr','staging_deploy','prod_deploy','high_risk_override']

export default function ApprovalQueue() {
  const [rows, setRows]     = useState(null)
  const [err, setErr]       = useState('')
  const [msg, setMsg]       = useState('')
  const [filters, setFilters] = useState({ status: 'pending' })

  const query = useMemo(() => {
    const qs = new URLSearchParams()
    Object.entries(filters).forEach(([k, v]) => { if (v) qs.set(k, v) })
    const str = qs.toString()
    return str ? `?${str}` : ''
  }, [filters])

  const load = () => {
    setRows(null); setErr('')
    http.get(`/approvals${query}`).then(setRows).catch((e) => setErr(e?.message || 'Failed to load'))
  }
  useEffect(load, [query])

  async function approve(id) {
    setMsg('')
    try { await http.post(`/approvals/${id}/approve`); setMsg('Approved.'); load() }
    catch (e) { setErr(e?.message || 'Approve failed') }
  }
  async function reject(id, reason) {
    setMsg('')
    try { await http.post(`/approvals/${id}/reject`, { reason }); setMsg('Rejected.'); load() }
    catch (e) { setErr(e?.message || 'Reject failed') }
  }

  return (
    <div className="space-y-4">
      <FilterBar values={filters} onChange={setFilters} fields={[
        { key: 'status', label: 'Status', options: ['pending','approved','rejected','cancelled'].map(v => ({value:v,label:v})) },
        { key: 'action', label: 'Action', options: ACTIONS.map(v => ({value:v,label:v})) },
      ]} />

      {msg && <div className="rounded bg-aicountly-50 p-2 text-xs text-aicountly-700">{msg}</div>}
      {err && <div className="rounded bg-red-50 p-2 text-xs text-red-700">{err}</div>}

      <DataTable
        rows={rows || []}
        columns={[
          { key: 'id',       header: 'ID',    render: (r) => <span className="font-mono text-xs">#{r.id}</span> },
          { key: 'entity',   header: 'Dev Request', render: (r) => <Link className="font-mono text-xs text-aicountly-700 hover:underline" to={`/dev-requests/${r.entity_id}`}>#{r.entity_id}</Link> },
          { key: 'action',   header: 'Action', render: (r) => <span className="build-tag">{r.action}</span> },
          { key: 'status',   header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
          { key: 'requester', header: 'Requester', render: (r) => r.requester_id || <span className="text-neutral-400">—</span> },
          { key: 'age',      header: 'Requested', render: (r) => <span className="text-xs text-neutral-500">{fmtRelative(r.created_at)}</span> },
          { key: 'actions',  header: '', render: (r) => r.status === 'pending'
              ? <ApprovalActions onApprove={() => approve(r.id)} onReject={(reason) => reject(r.id, reason)} />
              : <span className="text-xs text-neutral-500">—</span> },
        ]}
      />
    </div>
  )
}
