import { useEffect, useMemo, useState } from 'react'
import { http } from '../lib/api.js'
import DataTable from '../components/common/DataTable.jsx'
import StatusBadge from '../components/common/StatusBadge.jsx'
import FilterBar from '../components/common/FilterBar.jsx'
import { fmtRelative } from '../lib/format.js'

export default function ConsoleSync() {
  const [rows, setRows] = useState(null)
  const [err, setErr] = useState('')
  const [filters, setFilters] = useState({})

  const query = useMemo(() => {
    const qs = new URLSearchParams()
    Object.entries(filters).forEach(([k, v]) => { if (v) qs.set(k, v) })
    const str = qs.toString()
    return str ? `?${str}` : ''
  }, [filters])

  useEffect(() => {
    setRows(null); setErr('')
    http.get(`/console-syncs${query}`).then(setRows).catch((e) => setErr(e?.message || 'Failed to load'))
  }, [query])

  return (
    <div className="space-y-3">
      <FilterBar values={filters} onChange={setFilters} fields={[
        { key: 'status', label: 'Status', options: ['pending','sent','delivered','failed','skipped_not_configured'].map(v => ({value:v,label:v})) },
        { key: 'direction', label: 'Direction', options: [{value:'outbound',label:'outbound'},{value:'inbound',label:'inbound'}] },
      ]} />
      {err && <div className="rounded bg-red-50 p-3 text-sm text-red-700">{err}</div>}
      <DataTable
        rows={rows || []}
        columns={[
          { key: 'id',       header: 'ID',    render: (r) => <span className="font-mono text-xs">#{r.id}</span> },
          { key: 'dir',      header: 'Direction', render: (r) => <span className="build-tag">{r.direction}</span> },
          { key: 'kind',     header: 'Kind',  render: (r) => <span className="build-tag">{r.kind}</span> },
          { key: 'status',   header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
          { key: 'retries',  header: 'Retries', render: (r) => r.retry_count },
          { key: 'age',      header: 'When', render: (r) => <span className="text-xs text-neutral-500">{fmtRelative(r.created_at)}</span> },
        ]}
      />
    </div>
  )
}
