import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { http } from '../lib/api.js'
import DataTable from '../components/common/DataTable.jsx'
import FilterBar from '../components/common/FilterBar.jsx'
import { fmtRelative, truncate } from '../lib/format.js'

export default function BotReports() {
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
    http.get(`/bot-reports${query}`).then(setRows).catch((e) => setErr(e?.message || 'Failed to load'))
  }, [query])

  return (
    <div className="space-y-3">
      <FilterBar values={filters} onChange={setFilters} fields={[
        { key: 'dev_request_id', label: 'Dev Request ID', type: 'number' },
      ]} />
      {err && <div className="rounded bg-red-50 p-3 text-sm text-red-700">{err}</div>}
      <DataTable
        rows={rows || []}
        columns={[
          { key: 'id',      header: 'ID',    render: (r) => <Link className="font-mono text-xs text-aicountly-700 hover:underline" to={`/bot-reports/${r.id}`}>#{r.id}</Link> },
          { key: 'dev_req', header: 'Dev Request', render: (r) => <Link className="font-mono text-xs text-aicountly-700 hover:underline" to={`/dev-requests/${r.dev_request_id}`}>#{r.dev_request_id}</Link> },
          { key: 'bot',     header: 'Bot', render: (r) => r.bot_name },
          { key: 'ai',      header: 'AI', render: (r) => <span className="build-tag">{r.ai_provider || '—'}</span> },
          { key: 'next',    header: 'Next recommended action', render: (r) => <span className="text-sm">{truncate(r.next_recommended_action, 100)}</span> },
          { key: 'age',     header: 'Created', render: (r) => <span className="text-xs text-neutral-500">{fmtRelative(r.created_at)}</span> },
        ]}
      />
    </div>
  )
}
