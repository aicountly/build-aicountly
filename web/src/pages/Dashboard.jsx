import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { http } from '../lib/api.js'
import { Card, CardGrid } from '../components/common/Card.jsx'
import StatusBadge from '../components/common/StatusBadge.jsx'
import RiskBadge from '../components/common/RiskBadge.jsx'
import IntegrationStatusCard from '../components/common/IntegrationStatusCard.jsx'
import BotModeBadge from '../components/build/BotModeBadge.jsx'
import { fmtRelative, niceSourceLabel } from '../lib/format.js'

export default function Dashboard() {
  const [summary, setSummary] = useState(null)
  const [integrations, setIntegrations] = useState(null)
  const [botMode, setBotMode] = useState(null)
  const [err, setErr] = useState('')

  useEffect(() => {
    Promise.all([
      http.get('/dashboard/summary'),
      http.get('/health/integrations'),
      http.get('/dashboard/bot-mode'),
    ]).then(([s, i, b]) => {
      setSummary(s); setIntegrations(i); setBotMode(b)
    }).catch((e) => setErr(e?.message || 'Failed to load dashboard'))
  }, [])

  if (err)     return <div className="rounded-lg bg-red-50 p-4 text-sm text-red-700">{err}</div>
  if (!summary || !integrations || !botMode) {
    return <div className="text-sm text-neutral-500">Loading…</div>
  }

  const totals = summary.dev_request_status_counts || {}
  const pendingApproval  = (totals.pending_approval || 0) + (totals.pending_commit_approval || 0) + (totals.pending_pr_approval || 0)
  const pendingDeploy    = (totals.pending_staging_deployment || 0) + (totals.pending_production_approval || 0)
  const activeCoding     = (totals.coding || 0) + (totals.tests_running || 0)

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center gap-3">
        <BotModeBadge mode={botMode.bot_mode} />
        <Link to="/bot-mode" className="text-xs text-aicountly-700 hover:underline">Change bot mode →</Link>
        <span className="text-xs text-neutral-500">Default risk: <RiskBadge level={botMode.default_risk} /></span>
      </div>

      <CardGrid className="lg:grid-cols-4">
        <Metric title="Total requests"          value={summary.total_dev_requests}     hint="all statuses" to="/dev-requests" />
        <Metric title="Pending approvals"       value={pendingApproval}                 hint="code / commit / pr" to="/approvals" />
        <Metric title="Pending deployments"     value={pendingDeploy}                   hint="staging + production" to="/deployments/staging" />
        <Metric title="Active in coding"        value={activeCoding}                    hint="coding + tests_running" to="/dev-requests" />
      </CardGrid>

      <Card title="Integrations" subtitle="Live status of every cross-portal glue. Never exposes token values.">
        <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
          <IntegrationStatusCard label="GitHub"    configured={integrations.github.configured}    details={integrations.github}   hint="Backend-only token; used for branch, commit, PR" />
          <IntegrationStatusCard label="AI provider" configured={integrations.ai.configured}       details={integrations.ai}       hint={`BUILD_AI_PROVIDER=${integrations.ai.provider}`} />
          <IntegrationStatusCard label="Playwright worker" configured={integrations.worker.configured} details={integrations.worker} hint="worker.apis.aicountly.com (screenshots only)" />
          <IntegrationStatusCard label="Console outbound" configured={integrations.console.configured}  details={integrations.console} hint="8 event kinds — audit, approvals, PR, deploy, mode, health" />
          <IntegrationStatusCard label="Flow inbound" configured={integrations.flow.inbound_configured} details={integrations.flow}   hint="Handoff endpoint dedups on flow_handoff_id" />
          <IntegrationStatusCard label="Deploy provider" configured={integrations.deploy.configured} details={integrations.deploy}   hint="No live deploy execution when unset — records intent only" />
        </div>
      </Card>

      <Card title="Recent development requests" subtitle="Latest 10 requests across all sources.">
        <ul className="divide-y divide-neutral-100">
          {(summary.recent_requests || []).map((r) => (
            <li key={r.id} className="flex flex-wrap items-center gap-3 py-2 text-sm">
              <Link to={`/dev-requests/${r.id}`} className="font-mono text-xs text-aicountly-700 hover:underline">#{r.id}</Link>
              <StatusBadge status={r.status} />
              <RiskBadge level={r.risk_level} />
              <span className="min-w-0 flex-1 truncate text-neutral-800">{r.requirement_text}</span>
              <span className="text-xs text-neutral-500">{niceSourceLabel(r.source_portal)}</span>
              <span className="text-xs text-neutral-500">{fmtRelative(r.created_at)}</span>
            </li>
          ))}
          {(!summary.recent_requests || summary.recent_requests.length === 0) && (
            <li className="py-3 text-xs text-neutral-500">No requests yet.</li>
          )}
        </ul>
      </Card>
    </div>
  )
}

function Metric({ title, value, hint, to }) {
  const inner = (
    <div className="build-card">
      <div className="text-xs uppercase tracking-wide text-neutral-500">{title}</div>
      <div className="mt-2 text-3xl font-semibold text-neutral-900">{value ?? 0}</div>
      {hint && <div className="mt-1 text-xs text-neutral-500">{hint}</div>}
    </div>
  )
  return to ? <Link to={to} className="block transition hover:-translate-y-0.5">{inner}</Link> : inner
}
