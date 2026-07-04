import { useEffect, useState } from 'react'
import { http } from '../lib/api.js'
import { Card } from '../components/common/Card.jsx'

export default function Settings() {
  const [data, setData] = useState(null)
  const [err, setErr] = useState('')
  const [msg, setMsg] = useState('')

  const load = () => http.get('/settings').then(setData).catch((e) => setErr(e?.message || 'Failed to load'))
  useEffect(load, [])

  async function saveKey(key, valueJson) {
    setErr(''); setMsg('')
    let value
    try { value = valueJson === '' ? null : JSON.parse(valueJson) }
    catch { setErr('Value must be valid JSON.'); return }
    try { await http.put('/settings', { key, value }); setMsg(`Saved ${key}.`); load() }
    catch (e) { setErr(e?.message || 'Save failed') }
  }

  if (err && !data) return <div className="rounded bg-red-50 p-3 text-sm text-red-700">{err}</div>
  if (!data) return <div className="text-sm text-neutral-500">Loading…</div>

  return (
    <div className="space-y-4">
      {msg && <div className="rounded bg-aicountly-50 p-2 text-xs text-aicountly-700">{msg}</div>}
      {err && <div className="rounded bg-red-50 p-2 text-xs text-red-700">{err}</div>}

      <Card title="Environment defaults (read-only)" subtitle="Values come from server-php/.env — edit there and redeploy to change.">
        <dl className="grid grid-cols-1 gap-y-1 text-xs sm:grid-cols-2">
          {Object.entries(data.env_defaults || {}).map(([k, v]) => (
            <div key={k}>
              <dt className="text-[10px] uppercase tracking-wide text-neutral-500">{k}</dt>
              <dd className="font-mono text-neutral-800">{String(v)}</dd>
            </div>
          ))}
        </dl>
      </Card>

      <Card title="Runtime settings" subtitle="Persisted in build_settings. Values are stored as JSON — enter valid JSON (booleans, numbers, or `null`).">
        <ul className="divide-y divide-neutral-100">
          {(data.settings || []).map((row) => (
            <SettingRow key={row.id} row={row} onSave={saveKey} />
          ))}
        </ul>
      </Card>
    </div>
  )
}

function SettingRow({ row, onSave }) {
  const [v, setV] = useState(typeof row.value_json === 'string' ? row.value_json : JSON.stringify(row.value_json))

  return (
    <li className="py-3">
      <div className="flex items-baseline justify-between text-xs">
        <span className="font-mono font-semibold text-neutral-800">{row.key}</span>
        {row.description && <span className="text-neutral-500">{row.description}</span>}
      </div>
      <textarea className="build-textarea mt-1" value={v} onChange={(e) => setV(e.target.value)} />
      <div className="mt-1">
        <button className="build-btn-primary text-xs" onClick={() => onSave(row.key, v)}>Save</button>
      </div>
    </li>
  )
}
