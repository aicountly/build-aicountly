import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { http } from '../lib/api.js'
import { Card } from '../components/common/Card.jsx'
import StatusBadge from '../components/common/StatusBadge.jsx'
import { fmtDate } from '../lib/format.js'

export default function PullRequestDetail() {
  const { id } = useParams()
  const [pr, setPr] = useState(null)
  const [err, setErr] = useState('')

  useEffect(() => {
    http.get(`/pull-requests/${id}`).then(setPr).catch((e) => setErr(e?.message || 'Failed to load'))
  }, [id])

  if (err && !pr) return <div className="rounded bg-red-50 p-3 text-sm text-red-700">{err}</div>
  if (!pr) return <div className="text-sm text-neutral-500">Loading…</div>

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <Link className="text-xs text-aicountly-700 hover:underline" to="/pull-requests">← Back</Link>
        <span className="font-mono text-sm">#{pr.id}</span>
        <StatusBadge status={pr.status} />
      </div>
      <Card title={pr.title || 'Pull request'} subtitle={`Branch ${pr.branch} → ${pr.target_branch} · created ${fmtDate(pr.created_at)}`}>
        {pr.url && <p><a target="_blank" rel="noreferrer" className="text-aicountly-700 hover:underline" href={pr.url}>{pr.url}</a></p>}
        {pr.body && <pre className="build-code mt-3">{pr.body}</pre>}
      </Card>
    </div>
  )
}
