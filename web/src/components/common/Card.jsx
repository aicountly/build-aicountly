import { classNames } from '../../lib/format.js'

export function Card({ title, subtitle, action, className, children }) {
  return (
    <section className={classNames('build-card', className)}>
      {(title || action) && (
        <header className="mb-3 flex items-start justify-between gap-2">
          <div>
            {title && <h2 className="text-sm font-semibold text-neutral-900">{title}</h2>}
            {subtitle && <p className="mt-0.5 text-xs text-neutral-500">{subtitle}</p>}
          </div>
          {action && <div className="shrink-0">{action}</div>}
        </header>
      )}
      {children}
    </section>
  )
}

export function CardGrid({ children, className }) {
  return <div className={classNames('grid gap-4 sm:grid-cols-2 lg:grid-cols-3', className)}>{children}</div>
}
