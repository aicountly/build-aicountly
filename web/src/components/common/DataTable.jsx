import { classNames } from '../../lib/format.js'
import EmptyState from './EmptyState.jsx'

/**
 * Minimal table. `columns` is an array of { key, header, render? }.
 * Row click callback is optional.
 */
export default function DataTable({ columns, rows, empty, onRowClick }) {
  if (!rows || rows.length === 0) {
    return empty ?? <EmptyState title="No rows" message="Nothing matches the current filters." />
  }
  return (
    <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-sm">
      <table className="build-table">
        <thead>
          <tr>{columns.map((c) => <th key={c.key}>{c.header}</th>)}</tr>
        </thead>
        <tbody>
          {rows.map((row, i) => (
            <tr
              key={row.id ?? i}
              className={classNames(onRowClick && 'cursor-pointer hover:bg-aicountly-50/40')}
              onClick={onRowClick ? () => onRowClick(row) : undefined}
            >
              {columns.map((c) => (
                <td key={c.key}>{c.render ? c.render(row) : row[c.key]}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
