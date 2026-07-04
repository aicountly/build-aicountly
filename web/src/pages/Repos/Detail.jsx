import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { http } from '../../lib/api.js'
import { Card } from '../../components/common/Card.jsx'
import { fmtDate } from '../../lib/format.js'

export default function RepoDetail() {
  const { id } = useParams()
  const [repo, setRepo] = useState(null)
  const [err, setErr]   = useState('')
  const [msg, setMsg]   = useState('')
  const [saving, setSaving] = useState(false)

  const load = () => http.get(`/repos/${id}`).then(setRepo).catch((e) => setErr(e?.message || 'Failed to load'))
  useEffect(load, [id])

  async function save(patch) {
    setSaving(true); setMsg(''); setErr('')
    try { await http.put(`/repos/${id}`, patch); setMsg('Saved.'); load() }
    catch (e) { setErr(e?.message || 'Save failed') } finally { setSaving(false) }
  }
  async function sync() {
    setMsg('')
    try { await http.post(`/repos/${id}/sync`); setMsg('Synced from GitHub.'); load() }
    catch (e) { setErr(e?.message || 'Sync failed') }
  }

  if (err && !repo) return <div className="rounded bg-red-50 p-3 text-sm text-red-700">{err}</div>
  if (!repo) return <div className="text-sm text-neutral-500">Loading…</div>

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <Link className="text-xs text-aicountly-700 hover:underline" to="/repos">← Back</Link>
        <span className="text-lg font-semibold">{repo.repo_code}</span>
        <span className="build-tag">{repo.product || '—'}</span>
        <span className="ml-auto text-xs text-neutral-500">Last sync {repo.last_sync_at ? fmtDate(repo.last_sync_at) : 'never'}</span>
      </div>

      {msg && <div className="rounded bg-aicountly-50 p-2 text-xs text-aicountly-700">{msg}</div>}
      {err && <div className="rounded bg-red-50 p-2 text-xs text-red-700">{err}</div>}

      <Card title="Repository">
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="repo_code"       value={repo.repo_code} />
          <Field label="repo_name"       value={repo.repo_name}       onSave={(v) => save({ repo_name: v })} />
          <Field label="product"         value={repo.product || ''}   onSave={(v) => save({ product: v })} />
          <Field label="github_org"      value={repo.github_org}      onSave={(v) => save({ github_org: v })} />
          <Field label="github_repo"     value={repo.github_repo}     onSave={(v) => save({ github_repo: v })} />
          <Field label="default_branch"  value={repo.default_branch}  onSave={(v) => save({ default_branch: v })} />
          <Field label="protected_branch" value={repo.protected_branch || ''} onSave={(v) => save({ protected_branch: v })} />
          <Field label="allowed_working_branch_prefix" value={repo.allowed_working_branch_prefix} onSave={(v) => save({ allowed_working_branch_prefix: v })} />
          <Field label="staging_url"     value={repo.staging_url || ''} onSave={(v) => save({ staging_url: v })} />
          <Field label="production_url"  value={repo.production_url || ''} onSave={(v) => save({ production_url: v })} />
          <Field label="deployment_type" value={repo.deployment_type} onSave={(v) => save({ deployment_type: v })} />
          <Field label="enabled" value={repo.enabled ? 'true' : 'false'} onSave={(v) => save({ enabled: v === 'true' })} />
        </div>
        <div className="mt-4 flex gap-2">
          <button className="build-btn-secondary" disabled={saving} onClick={sync}>Sync from GitHub</button>
        </div>
      </Card>

      <Card title="Notes">
        <Field label="notes" multiline value={repo.notes || ''} onSave={(v) => save({ notes: v })} />
      </Card>
    </div>
  )
}

function Field({ label, value, onSave, multiline }) {
  const [v, setV] = useState(value)
  const [dirty, setDirty] = useState(false)
  useEffect(() => { setV(value); setDirty(false) }, [value])

  return (
    <div>
      <label className="build-label">{label}</label>
      {multiline
        ? <textarea className="build-textarea" value={v ?? ''} onChange={(e) => { setV(e.target.value); setDirty(true) }} disabled={!onSave} />
        : <input     className="build-input"    value={v ?? ''} onChange={(e) => { setV(e.target.value); setDirty(true) }} disabled={!onSave} />}
      {dirty && onSave && (
        <div className="mt-1 flex gap-2">
          <button className="build-btn-primary text-xs" onClick={() => onSave(v)}>Save</button>
          <button className="build-btn-ghost text-xs"   onClick={() => { setV(value); setDirty(false) }}>Cancel</button>
        </div>
      )}
    </div>
  )
}
