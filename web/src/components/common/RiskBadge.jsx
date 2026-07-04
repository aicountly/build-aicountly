import { classNames } from '../../lib/format.js'

const styles = {
  low:      'bg-neutral-100 text-neutral-700',
  medium:   'bg-amber-100 text-amber-800',
  high:     'bg-orange-100 text-orange-800',
  critical: 'bg-red-100 text-red-800',
  info:     'bg-blue-50 text-blue-700 border border-blue-200',
}

export default function RiskBadge({ level }) {
  const k = (level || '').toLowerCase()
  return (
    <span className={classNames('build-badge', styles[k] || styles.medium)}>
      {level || 'medium'}
    </span>
  )
}
