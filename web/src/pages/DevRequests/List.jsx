import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { http } from '../../lib/api.js'
import DataTable from '../../components/common/DataTable.jsx'
import StatusBadge from '../../components/common/StatusBadge.jsx'
import RiskBadge from '../../components/common/RiskBadge.jsx'
import FilterBar from '../../components/common/FilterBar.jsx'
import { fmtRelative, niceSourceLabel, truncate } from '../../lib/format.js'

const STATUSES = [
  'received','analyzing','plan_prepared','pending_approval','approved_for_code',
  'coding','tests_running','pending_commit_approval','committed','pending_pr_approval',
  'pr_created','pending_staging_deployment','staging_deployed','pending_production_approval',
  'production_deployed','failed','rejected','closed',
]

export default function DevRequestsList() {
  const [rows, setRows] = useState(null)
  const [err, setErr]   = useState('')
  const [filters, setFilters] = useState({})

  const query = useMemo(() => {
    const qs = new URLSearchParams()
    Object.entries(filters).forEach(([k, v]) => { if (v) qs.set(k, v) })
    const str = qs.toString()
    return str ? `?${str}` : ''
  }, [filters])

  useEffect(() => {
    setRows(null); setErr('')
    http.get(`/dev-requests${query}`).then(setRows).catch((e) => setErr(e?.message || 'Failed to load'))
  }, [query])

  return (
    <div className="space-y-4">
      <FilterBar
        values={filters}
        onChange={setFilters}
        fields={[
          { key: 'status',        label: 'Status',    options: STATUSES.map(s => ({ value: s, label: s })) },
          { key: 'source',        label: 'Source',    options: [{value:'flow',label:'Flow'},{value:'console',label:'Console'},{value:'manual',label:'Manual'}] },
          { key: 'risk',          label: 'Risk',      options: ['low','medium','high','critical'].map(v => ({value:v,label:v})) },
          { key: 'priority',      label: 'Priority',  options: ['low','normal','high','urgent'].map(v => ({value:v,label:v})) },
          { key: 'request_type',  label: 'Type',      options: ['bug','feature','task','refactor','ui_fix','security','other'].map(v => ({value:v,label:v})) },
          { key: 'repo_id',       label: 'Repo ID',   type: 'number' },
        ]}
      />

      {err && <div className="rounded bg-red-50 p-3 text-sm text-red-700">{err}</div>}

      <DataTable
        columns={[
          { key: 'id',      header: 'ID',      render: (r) => <Link className="font-mono text-xs text-aicountly-700 hover:underline" to={`/dev-requests/${r.id}`}>#{r.id}</Link> },
          { key: 'status',  header: 'Status',  render: (r) => <StatusBadge status={r.status} /> },
          { key: 'risk',    header: 'Risk',    render: (r) => <RiskBadge level={r.risk_level} /> },
          { key: 'requirement', header: 'Requirement', render: (r) => <span className="text-sm">{truncate(r.requirement_text, 120)}</span> },
          { key: 'type',    header: 'Type',    render: (r) => <span className="build-tag">{r.request_type}</span> },
          { key: 'priority', header: 'Priority', render: (r) => r.priority },
          { key: 'source',  header: 'Source',  render: (r) => niceSourceLabel(r.source_portal) },
          { key: 'age',     header: 'Age',     render: (r) => <span className="text-xs text-neutral-500">{fmtRelative(r.created_at)}</span> },
        ]}
        rows={rows || []}
      />
    </div>
  )
}
