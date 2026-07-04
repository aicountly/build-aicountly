import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { http } from '../../lib/api.js'
import { Card, CardGrid } from '../../components/common/Card.jsx'
import StatusBadge from '../../components/common/StatusBadge.jsx'
import RiskBadge from '../../components/common/RiskBadge.jsx'
import TimelineItem from '../../components/build/TimelineItem.jsx'
import { fmtDate, niceSourceLabel } from '../../lib/format.js'

export default function DevRequestDetail() {
  const { id } = useParams()
  const [dev, setDev]           = useState(null)
  const [events, setEvents]     = useState([])
  const [reports, setReports]   = useState([])
  const [msg, setMsg]           = useState('')
  const [err, setErr]           = useState('')

  const load = () => {
    setErr('')
    Promise.all([
      http.get(`/dev-requests/${id}`),
      http.get(`/dev-requests/${id}/timeline`),
      http.get(`/bot-reports?dev_request_id=${id}`),
    ]).then(([d, t, r]) => { setDev(d); setEvents(t); setReports(r) })
      .catch((e) => setErr(e?.message || 'Failed to load'))
  }
  useEffect(load, [id])

  async function analyze() {
    setMsg('')
    try { await http.post('/bot/analyze', { dev_request_id: Number(id) }); setMsg('Bot analysis recorded.'); load() }
    catch (e) { setErr(e?.message || 'Analyze failed') }
  }
  async function plan() {
    setMsg('')
    try { await http.post('/bot/plan', { dev_request_id: Number(id) }); setMsg('Bot plan recorded.'); load() }
    catch (e) { setErr(e?.message || 'Plan failed') }
  }
  async function requestApproval(action) {
    setMsg('')
    try {
      await http.post('/approvals', { entity_id: Number(id), action })
      setMsg(`Approval requested (${action}).`)
    } catch (e) { setErr(e?.message || 'Approval request failed') }
  }

  if (err && !dev) return <div className="rounded bg-red-50 p-3 text-sm text-red-700">{err}</div>
  if (!dev) return <div className="text-sm text-neutral-500">Loading…</div>

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-3">
        <Link className="text-xs text-aicountly-700 hover:underline" to="/dev-requests">← Back to requests</Link>
        <span className="font-mono text-sm">#{dev.id}</span>
        <StatusBadge status={dev.status} />
        <RiskBadge level={dev.risk_level} />
        <span className="build-tag">{dev.request_type}</span>
        <span className="text-xs text-neutral-500">Source: {niceSourceLabel(dev.source_portal)}</span>
        <span className="ml-auto text-xs text-neutral-500">Created {fmtDate(dev.created_at)}</span>
      </div>

      {msg && <div className="rounded bg-aicountly-50 p-2 text-xs text-aicountly-700">{msg}</div>}
      {err && <div className="rounded bg-red-50 p-2 text-xs text-red-700">{err}</div>}

      <CardGrid className="lg:grid-cols-2">
        <Card title="Requirement" subtitle={dev.product ? `Product: ${dev.product}` : undefined}>
          <p className="whitespace-pre-wrap text-sm text-neutral-800">{dev.requirement_text}</p>
        </Card>

        <Card title="Actions">
          <div className="grid gap-2 sm:grid-cols-2">
            <button className="build-btn-secondary" onClick={analyze}>1. Bot analyze</button>
            <button className="build-btn-secondary" onClick={plan}>2. Bot plan</button>
            <button className="build-btn-secondary" onClick={() => requestApproval('code')}>3. Request code approval</button>
            <button className="build-btn-secondary" onClick={() => requestApproval('commit')}>4. Request commit approval</button>
            <button className="build-btn-secondary" onClick={() => requestApproval('pr')}>5. Request PR approval</button>
            <button className="build-btn-secondary" onClick={() => requestApproval('staging_deploy')}>6. Request staging deploy</button>
            <button className="build-btn-danger sm:col-span-2" onClick={() => requestApproval('prod_deploy')}>Request PRODUCTION deploy</button>
          </div>
        </Card>
      </CardGrid>

      <Card title="Files likely affected">
        <pre className="build-code">{JSON.stringify(dev.files_likely_affected ?? {}, null, 2)}</pre>
      </Card>

      <Card title="Timeline" subtitle="Append-only history of status changes and bot actions.">
        {events.length === 0
          ? <div className="text-xs text-neutral-500">No events yet.</div>
          : <ol className="ml-2 space-y-3 border-l border-neutral-200 pl-3">{events.map((e) => <TimelineItem key={e.id} event={e} />)}</ol>}
      </Card>

      <Card title="Bot reports" subtitle="Latest first.">
        {reports.length === 0
          ? <div className="text-xs text-neutral-500">No reports yet.</div>
          : (
            <ul className="divide-y divide-neutral-100">
              {reports.map((r) => (
                <li key={r.id} className="flex items-center gap-2 py-2 text-sm">
                  <Link className="font-mono text-xs text-aicountly-700 hover:underline" to={`/bot-reports/${r.id}`}>#{r.id}</Link>
                  <span className="text-neutral-500">{r.ai_provider}</span>
                  <span className="min-w-0 flex-1 truncate text-neutral-700">{r.next_recommended_action || '—'}</span>
                  <span className="text-xs text-neutral-500">{fmtDate(r.created_at)}</span>
                </li>
              ))}
            </ul>
          )}
      </Card>
    </div>
  )
}
