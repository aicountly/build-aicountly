/**
 * Canonical route table for the 17 Build modules. Every list page is
 * `/module`, every detail page is `/module/:id`.
 */
export const routes = {
  dashboard:            '/',
  botMode:              '/bot-mode',
  apiHealth:            '/api-health',

  devRequests:          '/dev-requests',
  devRequestDetail:     '/dev-requests/:id',
  flowHandoffs:         '/flow-handoffs',
  approvals:            '/approvals',

  repos:                '/repos',
  repoDetail:           '/repos/:id',
  codeTasks:            '/code-tasks',
  botWorkbench:         '/bot-workbench',
  playwrightReview:     '/playwright-review',
  playwrightJobDetail:  '/playwright-review/:id',
  testRunner:           '/tests',

  commits:              '/commits',
  pullRequests:         '/pull-requests',
  pullRequestDetail:    '/pull-requests/:id',
  stagingDeployments:   '/deployments/staging',
  prodDeployments:      '/deployments/production',

  botReports:           '/bot-reports',
  botReportDetail:      '/bot-reports/:id',
  githubActivity:       '/github-activity',
  consoleSync:          '/console-sync',

  settings:             '/settings',
  auditLogs:            '/audit-logs',
}

export const routePath = (name, params = {}) => {
  const path = routes[name]
  if (!path) throw new Error(`Unknown route: ${name}`)
  return Object.entries(params).reduce(
    (p, [k, v]) => p.replace(`:${k}`, encodeURIComponent(String(v))),
    path,
  )
}
