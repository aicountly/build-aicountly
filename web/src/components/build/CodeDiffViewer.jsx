export default function CodeDiffViewer({ files = [] }) {
  if (!files.length) {
    return <div className="rounded-lg border border-dashed border-neutral-300 p-4 text-center text-xs text-neutral-500">No files in this proposal.</div>
  }
  return (
    <div className="space-y-3">
      {files.map((f, i) => (
        <div key={`${f.path}-${i}`} className="overflow-hidden rounded-lg border border-neutral-200">
          <div className="flex items-center justify-between bg-neutral-50 px-3 py-2 text-xs">
            <span className="font-mono text-neutral-800">{f.path}</span>
            <span className="build-tag">{f.action || 'update'}</span>
          </div>
          {f.before && (
            <details className="border-t border-neutral-100">
              <summary className="cursor-pointer bg-neutral-50 px-3 py-1 text-[11px] uppercase tracking-wide text-neutral-500">before</summary>
              <pre className="build-code">{f.before}</pre>
            </details>
          )}
          <details open className="border-t border-neutral-100">
            <summary className="cursor-pointer bg-neutral-50 px-3 py-1 text-[11px] uppercase tracking-wide text-neutral-500">after</summary>
            <pre className="build-code">{f.after || ''}</pre>
          </details>
        </div>
      ))}
    </div>
  )
}
