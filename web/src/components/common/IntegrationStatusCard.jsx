import { classNames } from '../../lib/format.js'

export default function IntegrationStatusCard({ label, configured, hint, details }) {
  return (
    <div className="build-card">
      <div className="flex items-start justify-between">
        <div>
          <h3 className="text-sm font-semibold text-neutral-900">{label}</h3>
          {hint && <p className="mt-0.5 text-xs text-neutral-500">{hint}</p>}
        </div>
        <span className={classNames(
          'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium',
          configured
            ? 'bg-aicountly-100 text-aicountly-800'
            : 'bg-neutral-100 text-neutral-500 border border-dashed border-neutral-300',
        )}>
          <span className={classNames('h-2 w-2 rounded-full', configured ? 'bg-aicountly-500' : 'bg-neutral-400')} />
          {configured ? 'configured' : 'not configured'}
        </span>
      </div>
      {details && (
        <dl className="mt-3 grid grid-cols-1 gap-y-1 text-xs sm:grid-cols-2">
          {Object.entries(details).map(([k, v]) => (
            <div key={k}>
              <dt className="text-[10px] uppercase tracking-wide text-neutral-500">{k}</dt>
              <dd className="font-mono text-neutral-800 break-all">{String(v ?? '—')}</dd>
            </div>
          ))}
        </dl>
      )}
    </div>
  )
}
