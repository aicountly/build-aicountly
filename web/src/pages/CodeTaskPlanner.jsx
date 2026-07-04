import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { http } from '../lib/api.js'
import DataTable from '../components/common/DataTable.jsx'
import StatusBadge from '../components/common/StatusBadge.jsx'
import FilterBar from '../components/common/FilterBar.jsx'
import { fmtRelative } from '../lib/format.js'

const STATUSES = ['pending','approved','running','completed','failed','skipped','blocked_by_safe_guard']

export default function CodeTaskPlanner() {
  const [rows, setRows]     = useState(null)
  const [err, setErr]       = useState('')
  const [filters, setFilters] = useState({})

  const query = useMemo(() => {
    const qs = new URLSearchParams()
    Object.entries(filters).forEach(([k, v]) => { if (v) qs.set(k, v) })
    const str = qs.toString()
    return str ? `?${str}` : ''
  }, [filters])

  useEffect(() => {
    setRows(null); setErr('')
    http.get(`/code-tasks${query}`).then(setRows).catch((e) => setErr(e?.message || 'Failed to load'))
  }, [query])

  return (
    <div className="space-y-4">
      <FilterBar values={filters} onChange={setFilters} fields={[
        { key: 'status',         label: 'Status', options: STATUSES.map(v => ({value:v,label:v})) },
        { key: 'dev_request_id', label: 'Dev Request', type: 'number' },
      ]} />
      {err && <div className="rounded bg-red-50 p-3 text-sm text-red-700">{err}</div>}
      <DataTable
        rows={rows || []}
        columns={[
          { key: 'id',       header: 'ID',   render: (r) => <span className="font-mono text-xs">#{r.id}</span> },
          { key: 'dev_req',  header: 'Dev Request', render: (r) => <Link className="font-mono text-xs text-aicountly-700 hover:underline" to={`/dev-requests/${r.dev_request_id}`}>#{r.dev_request_id}</Link> },
          { key: 'kind',     header: 'Kind', render: (r) => <span className="build-tag">{r.kind}</span> },
          { key: 'status',   header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
          { key: 'age',      header: 'Created', render: (r) => <span className="text-xs text-neutral-500">{fmtRelative(r.created_at)}</span> },
        ]}
      />
    </div>
  )
}
