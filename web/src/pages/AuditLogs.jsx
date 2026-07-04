import { useEffect, useMemo, useState } from 'react'
import { http } from '../lib/api.js'
import DataTable from '../components/common/DataTable.jsx'
import FilterBar from '../components/common/FilterBar.jsx'
import RiskBadge from '../components/common/RiskBadge.jsx'
import { fmtDate } from '../lib/format.js'

export default function AuditLogs() {
  const [rows, setRows] = useState(null)
  const [err, setErr] = useState('')
  const [filters, setFilters] = useState({ limit: '200' })

  const query = useMemo(() => {
    const qs = new URLSearchParams()
    Object.entries(filters).forEach(([k, v]) => { if (v) qs.set(k, v) })
    const str = qs.toString()
    return str ? `?${str}` : ''
  }, [filters])

  useEffect(() => {
    setRows(null); setErr('')
    http.get(`/audit-logs${query}`).then(setRows).catch((e) => setErr(e?.message || 'Failed to load'))
  }, [query])

  return (
    <div className="space-y-3">
      <FilterBar values={filters} onChange={setFilters} fields={[
        { key: 'risk_level',  label: 'Risk',    options: ['info','low','medium','high','critical'].map(v => ({value:v,label:v})) },
        { key: 'entity_type', label: 'Entity',  options: ['dev_request','approval','commit','pull_request','deployment_request','repo','setting'].map(v => ({value:v,label:v})) },
        { key: 'action',      label: 'Action contains', placeholder: 'e.g. approval' },
        { key: 'limit',       label: 'Limit', type: 'number' },
      ]} />
      {err && <div className="rounded bg-red-50 p-3 text-sm text-red-700">{err}</div>}
      <DataTable
        rows={rows || []}
        columns={[
          { key: 'id',       header: 'ID',     render: (r) => <span className="font-mono text-xs">#{r.id}</span> },
          { key: 'action',   header: 'Action', render: (r) => <span className="font-mono text-xs">{r.action}</span> },
          { key: 'actor',    header: 'Actor',  render: (r) => r.actor_email || <span className="text-neutral-400">system</span> },
          { key: 'entity',   header: 'Entity', render: (r) => r.entity_type ? `${r.entity_type} #${r.entity_id}` : '—' },
          { key: 'risk',     header: 'Risk',   render: (r) => <RiskBadge level={r.risk_level} /> },
          { key: 'when',     header: 'When',   render: (r) => <span className="text-xs text-neutral-500">{fmtDate(r.created_at)}</span> },
        ]}
      />
    </div>
  )
}
