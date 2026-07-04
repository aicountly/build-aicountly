import { useLocation } from 'react-router-dom'
import { useAuth } from '../../lib/auth.jsx'

const titles = [
  { path: '/',                     title: 'Build Dashboard' },
  { path: '/bot-mode',             title: 'Bot Mode' },
  { path: '/api-health',           title: 'API Health' },
  { path: '/dev-requests',         title: 'Development Requests' },
  { path: '/flow-handoffs',        title: 'Flow Handoff Queue' },
  { path: '/approvals',            title: 'Approval Queue' },
  { path: '/repos',                title: 'Repo Registry' },
  { path: '/code-tasks',           title: 'Code Task Planner' },
  { path: '/bot-workbench',        title: 'Bot Workbench' },
  { path: '/playwright-review',    title: 'Playwright UI Review' },
  { path: '/tests',                title: 'Test Runner' },
  { path: '/commits',              title: 'Commit Queue' },
  { path: '/pull-requests',        title: 'Pull Requests' },
  { path: '/deployments/staging',  title: 'Staging Deployments' },
  { path: '/deployments/production', title: 'Production Deployments' },
  { path: '/bot-reports',          title: 'Bot Reports' },
  { path: '/github-activity',      title: 'GitHub Activity' },
  { path: '/console-sync',         title: 'Console Audit Sync' },
  { path: '/settings',             title: 'Settings' },
  { path: '/audit-logs',           title: 'Audit Logs' },
]

function resolveTitle(pathname) {
  const exact = titles.find((t) => t.path === pathname)
  if (exact) return exact.title
  const prefix = [...titles].sort((a, b) => b.path.length - a.path.length).find((t) => t.path !== '/' && pathname.startsWith(t.path))
  return prefix ? prefix.title : 'AICOUNTLY Build'
}

export default function Header() {
  const { pathname } = useLocation()
  const { user } = useAuth()
  const title = resolveTitle(pathname)

  return (
    <header className="sticky top-0 z-10 flex h-14 items-center justify-between border-b border-neutral-200 bg-white/95 px-4 backdrop-blur sm:px-6">
      <div>
        <h1 className="text-sm font-semibold text-neutral-900">{title}</h1>
        <p className="text-xs text-neutral-500">
          Internal AI Development Bot — approval-gated code automation authority
        </p>
      </div>
      <div className="hidden text-right text-xs text-neutral-500 sm:block">
        {user?.email}
        <div className="text-aicountly-700 font-medium">{(user?.roles || []).join(' · ')}</div>
      </div>
    </header>
  )
}
