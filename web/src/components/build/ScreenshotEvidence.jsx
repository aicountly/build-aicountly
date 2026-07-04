export default function ScreenshotEvidence({ before, after }) {
  return (
    <div className="grid gap-3 sm:grid-cols-2">
      <Slot label="Before" url={before?.url} path={before?.path} />
      <Slot label="After"  url={after?.url}  path={after?.path} />
    </div>
  )
}

function Slot({ label, url, path }) {
  return (
    <div className="rounded-lg border border-neutral-200 bg-white p-2">
      <div className="mb-1 flex items-center justify-between text-[11px] font-semibold uppercase tracking-wide text-neutral-500">
        <span>{label}</span>
        {url && <a className="text-aicountly-600 hover:underline" href={url} target="_blank" rel="noreferrer">open</a>}
      </div>
      {path
        ? <img src={path} alt={label} className="w-full rounded border border-neutral-100" />
        : <div className="grid h-40 place-items-center rounded border border-dashed border-neutral-300 text-xs text-neutral-400">no screenshot</div>}
    </div>
  )
}
