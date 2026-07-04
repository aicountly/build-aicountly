export default function EmptyState({ title, message, action }) {
  return (
    <div className="rounded-xl border border-dashed border-neutral-300 bg-white p-8 text-center">
      <h3 className="text-sm font-semibold text-neutral-800">{title}</h3>
      {message && <p className="mt-1 text-xs text-neutral-500">{message}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  )
}
