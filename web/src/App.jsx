import { Navigate, Route, Routes, useLocation } from 'react-router-dom'
import BuildLayout from './components/layout/BuildLayout.jsx'
import ProtectedRoute from './auth/ProtectedRoute.jsx'
import { useAuth } from './lib/auth.jsx'

import Login from './pages/Login.jsx'
import Dashboard from './pages/Dashboard.jsx'
import BotMode from './pages/BotMode.jsx'
import ApiHealth from './pages/ApiHealth.jsx'
import DevRequestsList from './pages/DevRequests/List.jsx'
import DevRequestDetail from './pages/DevRequests/Detail.jsx'
import FlowHandoffs from './pages/FlowHandoffs.jsx'
import ApprovalQueue from './pages/ApprovalQueue.jsx'
import ReposList from './pages/Repos/List.jsx'
import RepoDetail from './pages/Repos/Detail.jsx'
import CodeTaskPlanner from './pages/CodeTaskPlanner.jsx'
import BotWorkbench from './pages/BotWorkbench.jsx'
import PlaywrightReview from './pages/PlaywrightReview.jsx'
import TestRunner from './pages/TestRunner.jsx'
import CommitQueue from './pages/CommitQueue.jsx'
import PullRequests from './pages/PullRequests.jsx'
import PullRequestDetail from './pages/PullRequestDetail.jsx'
import StagingDeployments from './pages/StagingDeployments.jsx'
import ProductionDeployments from './pages/ProductionDeployments.jsx'
import BotReports from './pages/BotReports.jsx'
import BotReportDetail from './pages/BotReportDetail.jsx'
import GithubActivity from './pages/GithubActivity.jsx'
import ConsoleSync from './pages/ConsoleSync.jsx'
import Settings from './pages/Settings.jsx'
import AuditLogs from './pages/AuditLogs.jsx'

function Authed({ children, roles }) {
  return (
    <ProtectedRoute roles={roles}>
      <BuildLayout>{children}</BuildLayout>
    </ProtectedRoute>
  )
}

export default function App() {
  const { user, loading } = useAuth()
  const loc = useLocation()

  if (loading) {
    return (
      <div className="grid h-screen place-items-center text-sm text-neutral-500">
        Loading Build portal…
      </div>
    )
  }

  return (
    <Routes>
      <Route path="/login" element={user ? <Navigate to={loc.state?.from || '/'} replace /> : <Login />} />

      <Route path="/"                        element={<Authed><Dashboard /></Authed>} />
      <Route path="/bot-mode"                element={<Authed><BotMode /></Authed>} />
      <Route path="/api-health"              element={<Authed><ApiHealth /></Authed>} />

      <Route path="/dev-requests"            element={<Authed><DevRequestsList /></Authed>} />
      <Route path="/dev-requests/:id"        element={<Authed><DevRequestDetail /></Authed>} />
      <Route path="/flow-handoffs"           element={<Authed><FlowHandoffs /></Authed>} />
      <Route path="/approvals"               element={<Authed><ApprovalQueue /></Authed>} />

      <Route path="/repos"                   element={<Authed><ReposList /></Authed>} />
      <Route path="/repos/:id"               element={<Authed><RepoDetail /></Authed>} />
      <Route path="/code-tasks"              element={<Authed><CodeTaskPlanner /></Authed>} />
      <Route path="/bot-workbench"           element={<Authed><BotWorkbench /></Authed>} />
      <Route path="/playwright-review"       element={<Authed><PlaywrightReview /></Authed>} />
      <Route path="/tests"                   element={<Authed><TestRunner /></Authed>} />

      <Route path="/commits"                 element={<Authed><CommitQueue /></Authed>} />
      <Route path="/pull-requests"           element={<Authed><PullRequests /></Authed>} />
      <Route path="/pull-requests/:id"       element={<Authed><PullRequestDetail /></Authed>} />
      <Route path="/deployments/staging"     element={<Authed><StagingDeployments /></Authed>} />
      <Route path="/deployments/production"  element={<Authed><ProductionDeployments /></Authed>} />

      <Route path="/bot-reports"             element={<Authed><BotReports /></Authed>} />
      <Route path="/bot-reports/:id"         element={<Authed><BotReportDetail /></Authed>} />
      <Route path="/github-activity"         element={<Authed><GithubActivity /></Authed>} />
      <Route path="/console-sync"            element={<Authed><ConsoleSync /></Authed>} />

      <Route path="/settings"                element={<Authed><Settings /></Authed>} />
      <Route path="/audit-logs"              element={<Authed><AuditLogs /></Authed>} />

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
