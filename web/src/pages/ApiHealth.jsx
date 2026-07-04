import { useEffect, useState } from 'react'
import { http } from '../lib/api.js'
import { Card } from '../components/common/Card.jsx'
import IntegrationStatusCard from '../components/common/IntegrationStatusCard.jsx'

export default function ApiHealth() {
  const [health, setHealth]         = useState(null)
  const [integrations, setIntegrations] = useState(null)
  const [err, setErr]               = useState('')

  useEffect(() => {
    Promise.all([http.get('/health'), http.get('/health/integrations')])
      .then(([h, i]) => { setHealth(h); setIntegrations(i) })
      .catch((e) => setErr(e?.message || 'Failed to load health'))
  }, [])

  if (err) return <div className="rounded bg-red-50 p-4 text-sm text-red-700">{err}</div>
  if (!health || !integrations) return <div className="text-sm text-neutral-500">Loading…</div>

  return (
    <div className="space-y-4">
      <Card title="Backend" subtitle="Timestamp is UTC. Boolean checks come from Config/Database + JWT secrets.">
        <dl className="grid grid-cols-2 gap-y-1 text-sm sm:grid-cols-3">
          <dt className="text-neutral-500">service</dt><dd className="col-span-2 font-mono text-neutral-800">{health.service}</dd>
          <dt className="text-neutral-500">timestamp</dt><dd className="col-span-2 font-mono text-neutral-800">{health.timestamp}</dd>
          <dt className="text-neutral-500">jwt_secret</dt><dd className="col-span-2">{health.checks?.jwt_secret ? 'ok' : 'missing'}</dd>
          <dt className="text-neutral-500">vault_key</dt><dd className="col-span-2">{health.checks?.vault_key  ? 'ok' : 'missing'}</dd>
          <dt className="text-neutral-500">database</dt><dd className="col-span-2">{health.checks?.database ? 'reachable' : 'unreachable'}</dd>
        </dl>
      </Card>

      <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
        <IntegrationStatusCard label="GitHub"           configured={integrations.github.configured}   details={integrations.github}  />
        <IntegrationStatusCard label="AI provider"      configured={integrations.ai.configured}       details={integrations.ai} />
        <IntegrationStatusCard label="Playwright"       configured={integrations.worker.configured}   details={integrations.worker} />
        <IntegrationStatusCard label="Console outbound" configured={integrations.console.configured}  details={integrations.console} />
        <IntegrationStatusCard label="Flow inbound"     configured={integrations.flow.inbound_configured} details={integrations.flow} />
        <IntegrationStatusCard label="Deploy provider"  configured={integrations.deploy.configured}   details={integrations.deploy} />
      </div>
    </div>
  )
}
