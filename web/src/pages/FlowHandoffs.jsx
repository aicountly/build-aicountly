import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { http } from '../lib/api.js'
import DataTable from '../components/common/DataTable.jsx'
import StatusBadge from '../components/common/StatusBadge.jsx'
import { fmtDate } from '../lib/format.js'

export default function FlowHandoffs() {
  const [rows, setRows] = useState(null)
  const [err, setErr] = useState('')

  useEffect(() => {
    http.get('/flow-handoffs').then(setRows).catch((e) => setErr(e?.message || 'Failed to load'))
  }, [])

  if (err && !rows) return <div className="rounded bg-red-50 p-3 text-sm text-red-700">{err}</div>

  return (
    <DataTable
      columns={[
        { key: 'id',      header: 'ID',      render: (r) => <span className="font-mono text-xs">#{r.id}</span> },
        { key: 'flow_id', header: 'Flow ID', render: (r) => <span className="font-mono text-xs">{r.flow_handoff_id}</span> },
        { key: 'source',  header: 'Source',  render: (r) => <span className="text-xs">{r.source_type} #{r.source_id}</span> },
        { key: 'status',  header: 'Status',  render: (r) => <StatusBadge status={r.status} /> },
        { key: 'dev_request', header: 'Dev Request', render: (r) => r.dev_request_id
            ? <Link className="font-mono text-xs text-aicountly-700 hover:underline" to={`/dev-requests/${r.dev_request_id}`}>#{r.dev_request_id}</Link>
            : <span className="text-neutral-400">—</span> },
        { key: 'received', header: 'Received', render: (r) => <span className="text-xs text-neutral-500">{fmtDate(r.received_at)}</span> },
      ]}
      rows={rows || []}
    />
  )
}
