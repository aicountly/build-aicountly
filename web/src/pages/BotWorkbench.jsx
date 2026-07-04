import { useState } from 'react'
import { http } from '../lib/api.js'
import { Card } from '../components/common/Card.jsx'
import CodeDiffViewer from '../components/build/CodeDiffViewer.jsx'

export default function BotWorkbench() {
  const [devRequestId, setDevRequestId] = useState('')
  const [analysis, setAnalysis]         = useState(null)
  const [plan, setPlan]                 = useState(null)
  const [code, setCode]                 = useState(null)
  const [busy, setBusy]                 = useState('')
  const [err, setErr]                   = useState('')

  async function analyze() {
    setBusy('analyze'); setErr('')
    try { setAnalysis(await http.post('/bot/analyze', { dev_request_id: Number(devRequestId) })) }
    catch (e) { setErr(e?.message || 'Analyze failed') } finally { setBusy('') }
  }
  async function planIt() {
    setBusy('plan'); setErr('')
    try { setPlan(await http.post('/bot/plan', { dev_request_id: Number(devRequestId) })) }
    catch (e) { setErr(e?.message || 'Plan failed') } finally { setBusy('') }
  }
  async function generate() {
    setBusy('generate'); setErr('')
    try { setCode(await http.post('/bot/generate-code', { dev_request_id: Number(devRequestId) })) }
    catch (e) { setErr(e?.message || 'Code generation failed') } finally { setBusy('') }
  }

  return (
    <div className="space-y-4">
      <Card title="Workbench" subtitle="Analyze → plan → generate. Actual writes still require an approved code approval.">
        <div className="flex flex-wrap items-end gap-3">
          <div>
            <label className="build-label">Dev request ID</label>
            <input className="build-input" value={devRequestId} onChange={(e) => setDevRequestId(e.target.value)} type="number" min="1" placeholder="e.g. 12" />
          </div>
          <button className="build-btn-secondary" disabled={!devRequestId || busy === 'analyze'} onClick={analyze}>{busy === 'analyze' ? 'Analyzing…' : 'Analyze'}</button>
          <button className="build-btn-secondary" disabled={!devRequestId || busy === 'plan'} onClick={planIt}>{busy === 'plan' ? 'Planning…' : 'Prepare plan'}</button>
          <button className="build-btn-primary"   disabled={!devRequestId || busy === 'generate'} onClick={generate}>{busy === 'generate' ? 'Generating…' : 'Generate code (requires code approval)'}</button>
        </div>
        {err && <div className="mt-3 rounded bg-red-50 p-2 text-xs text-red-700">{err}</div>}
      </Card>

      {analysis && (
        <Card title="Analysis" subtitle={`Suggested type: ${analysis.suggested_request_type} · priority ${analysis.suggested_priority} · risk ${analysis.suggested_risk_level}`}>
          <p className="text-sm text-neutral-800">{analysis.summary}</p>
          {analysis.notes && <p className="mt-2 text-xs text-neutral-500">{analysis.notes}</p>}
        </Card>
      )}

      {plan && (
        <Card title="Plan" subtitle={plan.summary}>
          <pre className="build-code">{JSON.stringify(plan.steps ?? [], null, 2)}</pre>
          <h3 className="mt-3 text-xs font-semibold uppercase tracking-wide text-neutral-500">Test plan</h3>
          <pre className="build-code">{JSON.stringify(plan.test_plan ?? [], null, 2)}</pre>
        </Card>
      )}

      {code && (
        <Card title="Code proposal" subtitle={code.summary}>
          <CodeDiffViewer files={code.files || []} />
        </Card>
      )}
    </div>
  )
}
