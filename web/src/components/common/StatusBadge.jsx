import { classNames } from '../../lib/format.js'

// Every one of the 18 dev-request statuses gets an explicit style.
const styles = {
  received:                     'bg-neutral-100 text-neutral-700',
  analyzing:                    'bg-blue-50 text-blue-700 border border-blue-200',
  plan_prepared:                'bg-indigo-50 text-indigo-700 border border-indigo-200',
  pending_approval:             'bg-amber-100 text-amber-800',
  approved_for_code:            'bg-aicountly-50 text-aicountly-700 border border-aicountly-200',
  coding:                       'bg-purple-100 text-purple-800',
  tests_running:                'bg-cyan-100 text-cyan-800',
  pending_commit_approval:      'bg-amber-100 text-amber-800',
  committed:                    'bg-emerald-100 text-emerald-800',
  pending_pr_approval:          'bg-amber-100 text-amber-800',
  pr_created:                   'bg-teal-100 text-teal-800',
  pending_staging_deployment:   'bg-amber-100 text-amber-800',
  staging_deployed:             'bg-lime-100 text-lime-800',
  pending_production_approval:  'bg-orange-100 text-orange-800',
  production_deployed:          'bg-aicountly-100 text-aicountly-800 border border-aicountly-200',
  failed:                       'bg-red-100 text-red-800',
  rejected:                     'bg-rose-100 text-rose-800',
  closed:                       'bg-neutral-200 text-neutral-700',

  // Reusable non-dev-request statuses
  approved:                     'bg-aicountly-100 text-aicountly-800',
  pending:                      'bg-amber-100 text-amber-800',
  running:                      'bg-blue-100 text-blue-800',
  completed:                    'bg-aicountly-100 text-aicountly-800',
  cancelled:                    'bg-neutral-200 text-neutral-700',
  requested:                    'bg-neutral-100 text-neutral-700',
  deployed:                     'bg-aicountly-100 text-aicountly-800',
  delivered:                    'bg-aicountly-100 text-aicountly-800',
  sent:                         'bg-blue-100 text-blue-800',
  skipped_not_configured:       'bg-neutral-100 text-neutral-500 border border-dashed border-neutral-300',
  disabled:                     'bg-neutral-100 text-neutral-500 border border-dashed border-neutral-300',
  provider_unconfigured:        'bg-neutral-100 text-neutral-500 border border-dashed border-neutral-300',
  open:                         'bg-teal-100 text-teal-800',
  merged:                       'bg-purple-100 text-purple-800',
}

export default function StatusBadge({ status }) {
  const k = (status || '').toLowerCase()
  return (
    <span className={classNames('build-badge', styles[k] || 'bg-neutral-100 text-neutral-700')}>
      {status || '—'}
    </span>
  )
}
