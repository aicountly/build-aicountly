export default function BotModeBadge({ mode }) {
  const auto = mode === 'auto'
  return (
    <span className={
      'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ' +
      (auto
        ? 'bg-orange-100 text-orange-800'
        : 'bg-aicountly-100 text-aicountly-800')
    }>
      <span className={'h-2 w-2 rounded-full ' + (auto ? 'bg-orange-500' : 'bg-aicountly-500')} />
      {auto ? 'Auto mode' : 'Confirm mode'}
    </span>
  )
}
