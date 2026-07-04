import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { http } from '../lib/api.js'
import DataTable from '../components/common/DataTable.jsx'
import { fmtRelative } from '../lib/format.js'

export default function GithubActivity() {
  const [rows, setRows] = useState(null)
  const [err, setErr] = useState('')

  useEffect(() => {
    http.get('/github-activity').then(setRows).catch((e) => setErr(e?.message || 'Failed to load'))
  }, [])

  if (err && !rows) return <div className="rounded bg-red-50 p-3 text-sm text-red-700">{err}</div>

  return (
    <DataTable
      rows={rows || []}
      columns={[
        { key: 'id',       header: 'ID',    render: (r) => <span className="font-mono text-xs">#{r.id}</span> },
        { key: 'kind',     header: 'Kind',  render: (r) => <span className="build-tag">{r.kind}</span> },
        { key: 'repo_id',  header: 'Repo',  render: (r) => r.repo_id ? <Link className="font-mono text-xs text-aicountly-700 hover:underline" to={`/repos/${r.repo_id}`}>#{r.repo_id}</Link> : '—' },
        { key: 'dev_req',  header: 'Dev Request', render: (r) => r.dev_request_id ? <Link className="font-mono text-xs text-aicountly-700 hover:underline" to={`/dev-requests/${r.dev_request_id}`}>#{r.dev_request_id}</Link> : '—' },
        { key: 'ref',      header: 'Ref',   render: (r) => <span className="font-mono text-xs">{r.ref || '—'}</span> },
        { key: 'actor',    header: 'Actor', render: (r) => r.actor || '—' },
        { key: 'age',      header: 'When',  render: (r) => <span className="text-xs text-neutral-500">{fmtRelative(r.created_at)}</span> },
      ]}
    />
  )
}
