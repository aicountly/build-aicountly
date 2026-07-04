import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { http } from '../../lib/api.js'
import DataTable from '../../components/common/DataTable.jsx'
import { fmtRelative } from '../../lib/format.js'

export default function ReposList() {
  const [rows, setRows] = useState(null)
  const [err, setErr] = useState('')

  useEffect(() => {
    http.get('/repos').then(setRows).catch((e) => setErr(e?.message || 'Failed to load'))
  }, [])

  if (err && !rows) return <div className="rounded bg-red-50 p-3 text-sm text-red-700">{err}</div>

  return (
    <DataTable
      rows={rows || []}
      columns={[
        { key: 'id',        header: 'ID',    render: (r) => <span className="font-mono text-xs">#{r.id}</span> },
        { key: 'code',      header: 'Repo',  render: (r) => <Link className="font-medium text-aicountly-700 hover:underline" to={`/repos/${r.id}`}>{r.repo_code}</Link> },
        { key: 'name',      header: 'Name',  render: (r) => r.repo_name },
        { key: 'product',   header: 'Product', render: (r) => <span className="build-tag">{r.product || '—'}</span> },
        { key: 'branch',    header: 'Default branch', render: (r) => <span className="font-mono text-xs">{r.default_branch}</span> },
        { key: 'prefix',    header: 'Working prefix', render: (r) => <span className="font-mono text-xs">{r.allowed_working_branch_prefix}</span> },
        { key: 'enabled',   header: 'Enabled', render: (r) => r.enabled ? 'yes' : 'no' },
        { key: 'synced',    header: 'Last sync', render: (r) => <span className="text-xs text-neutral-500">{r.last_sync_at ? fmtRelative(r.last_sync_at) : '—'}</span> },
      ]}
    />
  )
}
