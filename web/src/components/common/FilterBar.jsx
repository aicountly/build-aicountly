export default function FilterBar({ values, onChange, fields }) {
  return (
    <div className="build-card mb-4">
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        {fields.map((f) => (
          <div key={f.key}>
            <label className="build-label">{f.label}</label>
            {f.options ? (
              <select
                className="build-input"
                value={values[f.key] || ''}
                onChange={(e) => onChange({ ...values, [f.key]: e.target.value })}
              >
                <option value="">All</option>
                {f.options.map((o) => (
                  <option key={o.value} value={o.value}>{o.label}</option>
                ))}
              </select>
            ) : (
              <input
                type={f.type || 'text'}
                className="build-input"
                value={values[f.key] || ''}
                onChange={(e) => onChange({ ...values, [f.key]: e.target.value })}
                placeholder={f.placeholder}
              />
            )}
          </div>
        ))}
      </div>
    </div>
  )
}
