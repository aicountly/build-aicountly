import { http, v1Absolute } from '../lib/api.js'

export async function getLauncherApps() {
  return http.get('/auth/controller-apps/launcher')
}

export async function getSsoLaunchUrl(appCode) {
  try {
    return await http.get('/auth/sso/launch-url', {
      params: { app_code: appCode },
    })
  } catch {
    return {
      redirect_url: v1Absolute(`/auth/sso/launch?app_code=${encodeURIComponent(appCode)}`),
    }
  }
}
