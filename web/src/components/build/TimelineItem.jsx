import { fmtDate } from '../../lib/format.js'
import StatusBadge from '../common/StatusBadge.jsx'

export default function TimelineItem({ event }) {
  const from = event.from_status
  const to   = event.to_status
  return (
    <li className="relative pl-6">
      <span className="absolute left-0 top-1.5 h-3 w-3 rounded-full bg-aicountly-500" />
      <div className="flex flex-wrap items-baseline gap-2 text-xs">
        <span className="font-medium text-neutral-800">{event.event}</span>
        {from && to && (
          <span className="text-neutral-500">
            <StatusBadge status={from} /> → <StatusBadge status={to} />
          </span>
        )}
        <span className="ml-auto text-neutral-500">{fmtDate(event.created_at)}</span>
      </div>
      {event.actor_email && (
        <div className="mt-0.5 text-[11px] text-neutral-500">
          by {event.actor_email} · {event.actor_kind}
        </div>
      )}
      {event.note && <p className="mt-1 text-sm text-neutral-700">{event.note}</p>}
    </li>
  )
}
