import { NavLink } from 'react-router-dom'
import AicountlyLogo from '../brand/AicountlyLogo.jsx'
import { useAuth } from '../../lib/auth.jsx'

const groups = [
  {
    label: 'Overview',
    items: [
      { to: '/',           label: 'Build Dashboard',    icon: 'M3 12l9-9 9 9M5 10v10a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V10' },
      { to: '/bot-mode',   label: 'Bot Mode',           icon: 'M12 2l3 6 6 1-4.5 4.5L18 20l-6-3-6 3 1.5-6.5L3 9l6-1z' },
      { to: '/api-health', label: 'API Health',         icon: 'M4 12h4l3 8 4-16 3 8h4' },
    ],
  },
  {
    label: 'Requests',
    items: [
      { to: '/dev-requests',  label: 'Development Requests', icon: 'M9 5h6l1 2h3v14H5V7h3z' },
      { to: '/flow-handoffs', label: 'Flow Handoff Queue',   icon: 'M4 12h16m0 0-4-4m4 4-4 4' },
      { to: '/approvals',     label: 'Approval Queue',       icon: 'M5 13l4 4L19 7' },
    ],
  },
  {
    label: 'Code',
    items: [
      { to: '/repos',              label: 'Repo Registry',      icon: 'M6 4h9l5 5v11H6z M15 4v5h5' },
      { to: '/code-tasks',         label: 'Code Task Planner',  icon: 'M4 6h16M4 12h16M4 18h10' },
      { to: '/bot-workbench',      label: 'Bot Workbench',      icon: 'M8 3v3M16 3v3M4 8h16v13H4z M4 8l2-3h12l2 3' },
      { to: '/playwright-review',  label: 'Playwright UI Review', icon: 'M3 5h18v13H3z M8 21h8' },
      { to: '/tests',              label: 'Test Runner',        icon: 'M9 3v6l-6 6a3 3 0 0 0 4 4l6-6h6v-4l-6-6z' },
    ],
  },
  {
    label: 'Delivery',
    items: [
      { to: '/commits',                 label: 'Commit Queue',              icon: 'M4 12a8 8 0 1 0 16 0M12 4v8' },
      { to: '/pull-requests',           label: 'Pull Requests',             icon: 'M7 6v11m0 0a3 3 0 1 0 0 6 3 3 0 0 0 0-6zM17 6a3 3 0 1 1 0 6 3 3 0 0 1 0-6zm0 6v5a2 2 0 0 1-2 2h-4' },
      { to: '/deployments/staging',     label: 'Staging Deployments',       icon: 'M4 13h16v6H4z M4 5h16v6H4z' },
      { to: '/deployments/production',  label: 'Production Deployments',    icon: 'M12 3l9 4v5c0 5-4 9-9 9s-9-4-9-9V7z' },
    ],
  },
  {
    label: 'Insights',
    items: [
      { to: '/bot-reports',    label: 'Bot Reports',       icon: 'M9 17v-6h13M9 11V5h13M3 7h4M3 13h4M3 19h4' },
      { to: '/github-activity', label: 'GitHub Activity',  icon: 'M12 2C6 2 2 6 2 12c0 4.5 3 8 7 9.4v-3.3c-3-.7-3.6-3.1-3.6-3.1-.3-.9-.9-1.1-.9-1.1-.7-.5.1-.5.1-.5.8.1 1.2.8 1.2.8.7 1.2 1.8.9 2.3.7.1-.5.3-.9.5-1.1-2.4-.3-4.9-1.2-4.9-5.4 0-1.2.4-2.1 1.1-2.9-.1-.3-.5-1.4.1-2.9 0 0 .9-.3 3 1.1a10 10 0 0 1 5.4 0c2.1-1.4 3-1.1 3-1.1.6 1.5.2 2.6.1 2.9.7.8 1.1 1.7 1.1 2.9 0 4.2-2.5 5.1-4.9 5.4.4.3.7.9.7 1.9v2.8c4-1.4 7-4.9 7-9.4 0-6-4-10-10-10z' },
      { to: '/console-sync',   label: 'Console Audit Sync', icon: 'M4 4h16v6H4zM4 14h10v6H4z' },
    ],
  },
  {
    label: 'Admin',
    items: [
      { to: '/settings',   label: 'Settings',   icon: 'M10.3 3.6a1 1 0 0 1 1.4 0l1 1a1 1 0 0 0 1 .2l1.4-.3a1 1 0 0 1 1.2.7l.4 1.3a1 1 0 0 0 .7.7l1.3.4a1 1 0 0 1 .7 1.2l-.3 1.4a1 1 0 0 0 .2 1l1 1a1 1 0 0 1 0 1.4l-1 1a1 1 0 0 0-.2 1l.3 1.4a1 1 0 0 1-.7 1.2l-1.3.4a1 1 0 0 0-.7.7l-.4 1.3a1 1 0 0 1-1.2.7l-1.4-.3a1 1 0 0 0-1 .2l-1 1a1 1 0 0 1-1.4 0l-1-1a1 1 0 0 0-1-.2l-1.4.3a1 1 0 0 1-1.2-.7l-.4-1.3a1 1 0 0 0-.7-.7l-1.3-.4a1 1 0 0 1-.7-1.2l.3-1.4a1 1 0 0 0-.2-1l-1-1a1 1 0 0 1 0-1.4l1-1a1 1 0 0 0 .2-1L3.4 9a1 1 0 0 1 .7-1.2l1.3-.4a1 1 0 0 0 .7-.7l.4-1.3A1 1 0 0 1 7.7 4.5l1.4.3a1 1 0 0 0 1-.2l1-1zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z' },
      { to: '/audit-logs', label: 'Audit Logs', icon: 'M5 4h14a1 1 0 0 1 1 1v15l-4-2-4 2-4-2-4 2V5a1 1 0 0 1 1-1z' },
    ],
  },
]

export default function Sidebar() {
  const { user, logout } = useAuth()

  return (
    <aside className="hidden lg:flex w-64 shrink-0 flex-col border-r border-neutral-200 bg-white">
      <div className="flex h-14 items-center gap-3 border-b border-neutral-200 px-4">
        <AicountlyLogo className="h-8 w-8" />
        <div className="text-sm">
          <div className="font-semibold text-neutral-900 leading-tight">AICOUNTLY Build</div>
          <div className="text-[11px] text-neutral-500 leading-tight">build.aicountly.org</div>
        </div>
      </div>

      <nav className="flex-1 overflow-y-auto px-2 py-3 space-y-3">
        {groups.map((g) => (
          <div key={g.label}>
            <div className="px-3 pb-1 text-[10px] font-semibold uppercase tracking-wide text-neutral-400">
              {g.label}
            </div>
            <div className="space-y-0.5">
              {g.items.map((n) => (
                <NavLink
                  key={n.to}
                  to={n.to}
                  end={n.to === '/'}
                  className={({ isActive }) =>
                    `flex items-center gap-3 rounded-lg px-3 py-1.5 text-sm font-medium transition
                     ${isActive
                       ? 'bg-aicountly-50 text-aicountly-700'
                       : 'text-neutral-700 hover:bg-neutral-50 hover:text-neutral-900'}`
                  }
                >
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4 shrink-0">
                    <path d={n.icon} />
                  </svg>
                  {n.label}
                </NavLink>
              ))}
            </div>
          </div>
        ))}
      </nav>

      <div className="border-t border-neutral-200 p-3">
        <div className="text-xs text-neutral-500 mb-2">
          {user?.email}
          <div className="text-aicountly-700 font-medium mt-0.5">
            {(user?.roles || []).join(' · ')}
          </div>
        </div>
        <button
          type="button"
          onClick={logout}
          className="build-btn-secondary w-full justify-center text-xs"
        >
          Sign out
        </button>
      </div>
    </aside>
  )
}
