import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { http } from '../lib/api.js'
import { Card } from '../components/common/Card.jsx'
import ScreenshotEvidence from '../components/build/ScreenshotEvidence.jsx'
import { fmtDate } from '../lib/format.js'

export default function BotReportDetail() {
  const { id } = useParams()
  const [r, setR] = useState(null)
  const [err, setErr] = useState('')

  useEffect(() => {
    http.get(`/bot-reports/${id}`).then(setR).catch((e) => setErr(e?.message || 'Failed to load'))
  }, [id])

  if (err && !r) return <div className="rounded bg-red-50 p-3 text-sm text-red-700">{err}</div>
  if (!r) return <div className="text-sm text-neutral-500">Loading…</div>

  const shots = Array.isArray(r.ui_screenshots) ? r.ui_screenshots : []

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <Link className="text-xs text-aicountly-700 hover:underline" to="/bot-reports">← Back</Link>
        <span className="font-mono text-sm">#{r.id}</span>
        <span className="build-tag">{r.ai_provider || 'unknown'}</span>
        <span className="ml-auto text-xs text-neutral-500">{fmtDate(r.created_at)}</span>
      </div>

      <Card title="Understanding">{r.understanding || <span className="text-neutral-400">—</span>}</Card>
      <Card title="Plan"><pre className="build-code">{JSON.stringify(r.plan ?? {}, null, 2)}</pre></Card>
      <Card title="Code changes"><pre className="build-code">{JSON.stringify(r.code_changes ?? {}, null, 2)}</pre></Card>
      <Card title="Tests"><pre className="build-code">{JSON.stringify(r.tests_run ?? {}, null, 2)}</pre></Card>
      <Card title="Errors"><pre className="build-code">{JSON.stringify(r.errors ?? {}, null, 2)}</pre></Card>
      <Card title="Screenshots">
        <ScreenshotEvidence before={shots.find((s) => s.phase === 'before')} after={shots.find((s) => s.phase === 'after')} />
      </Card>
      <Card title="Commit / PR / deployment details">
        <pre className="build-code">{JSON.stringify({ commit: r.commit_details, pr: r.pr_details, deployment: r.deployment_details }, null, 2)}</pre>
      </Card>
      <Card title="Next recommended action">
        <p className="text-sm text-neutral-800">{r.next_recommended_action || '—'}</p>
      </Card>
    </div>
  )
}
