import AicountlyLogo from '../components/brand/AicountlyLogo.jsx'
import {
  GATE_CONSOLE_REQUIRED,
  GATE_ERROR,
  GATE_NO_ACCESS,
  useAuth,
} from '../lib/auth.jsx'
import { consoleLoginUrl } from '../lib/consoleAuth.js'

const APP_NAME = import.meta.env.VITE_APP_NAME || 'AICOUNTLY Build'

export default function ControllerGate() {
  const { gateReason, gateMessage, retryAuth, ssoPending } = useAuth()

  const reason = gateReason || GATE_CONSOLE_REQUIRED
  const isPending = ssoPending

  return (
    <div className="grid h-screen w-screen place-items-center bg-gradient-to-br from-white to-aicountly-50 px-4">
      <div className="w-full max-w-md">
        <div className="mb-6 flex items-center gap-3">
          <AicountlyLogo className="h-10 w-10" />
          <div>
            <div className="text-base font-semibold text-neutral-900">{APP_NAME}</div>
            <div className="text-xs text-neutral-500">build.aicountly.org · Console identity only</div>
          </div>
        </div>

        <div className="build-card">
          {isPending ? (
            <>
              <h1 className="text-lg font-semibold text-neutral-900">Signing you in…</h1>
              <p className="mt-2 text-sm text-neutral-600">Checking your Console session and controller access.</p>
            </>
          ) : reason === GATE_NO_ACCESS ? (
            <>
              <h1 className="text-lg font-semibold text-amber-800">Access not assigned</h1>
              <p className="mt-2 text-sm text-neutral-600">
                You are signed in to Console, but this account does not have access to the Build controller app.
              </p>
              {gateMessage ? (
                <div className="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">{gateMessage}</div>
              ) : null}
              <p className="mt-3 text-xs text-neutral-500">
                Ask a Console administrator to grant Build access under Controller App Access, then click Retry.
              </p>
            </>
          ) : reason === GATE_ERROR ? (
            <>
              <h1 className="text-lg font-semibold text-red-700">Sign-in failed</h1>
              <p className="mt-2 text-sm text-neutral-600">
                {gateMessage || 'Could not complete Console sign-in for Build Portal.'}
              </p>
            </>
          ) : (
            <>
              <h1 className="text-lg font-semibold text-neutral-900">Sign in via Console</h1>
              <p className="mt-2 text-sm text-neutral-600">
                This portal does not use a local email/password login. Sign in at{' '}
                <strong>console.aicountly.org</strong>, then open Build from Top Controller Apps or return here.
              </p>
              {gateMessage && gateMessage !== 'Sign in to Console first.' ? (
                <div className="mt-3 rounded-lg bg-neutral-50 px-3 py-2 text-xs text-neutral-700">{gateMessage}</div>
              ) : null}
            </>
          )}

          <div className="mt-5 flex flex-col gap-2 sm:flex-row">
            {reason === GATE_CONSOLE_REQUIRED ? (
              <a href={consoleLoginUrl()} className="build-btn-primary justify-center text-center">
                Open Console sign-in
              </a>
            ) : null}
            <button
              type="button"
              className="build-btn-secondary justify-center"
              onClick={() => retryAuth()}
              disabled={isPending}
            >
              {isPending ? 'Checking…' : 'Retry'}
            </button>
          </div>
        </div>

        <p className="mt-4 text-center text-[11px] text-neutral-400">
          Code automation authority · every write action requires superadmin approval
        </p>
      </div>
    </div>
  )
}
