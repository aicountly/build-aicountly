import { useState } from 'react'

export default function ApprovalActions({ onApprove, onReject, requireReasonOnReject = true, disabled }) {
  const [showReject, setShowReject] = useState(false)
  const [reason, setReason]         = useState('')
  const [busy, setBusy]             = useState(false)

  async function handleApprove() {
    setBusy(true)
    try { await onApprove?.() } finally { setBusy(false) }
  }
  async function handleReject() {
    setBusy(true)
    try { await onReject?.(reason) } finally { setBusy(false); setShowReject(false); setReason('') }
  }

  if (showReject) {
    return (
      <div className="rounded-lg border border-neutral-200 bg-white p-3">
        <label className="build-label">Reason for rejection</label>
        <textarea
          className="build-textarea min-h-[3rem]"
          value={reason}
          onChange={(e) => setReason(e.target.value)}
          placeholder="Explain briefly why this cannot proceed."
        />
        <div className="mt-2 flex gap-2">
          <button
            type="button"
            onClick={handleReject}
            disabled={busy || (requireReasonOnReject && reason.trim() === '')}
            className="build-btn-danger text-xs"
          >
            {busy ? 'Rejecting…' : 'Confirm reject'}
          </button>
          <button type="button" onClick={() => setShowReject(false)} className="build-btn-secondary text-xs" disabled={busy}>
            Cancel
          </button>
        </div>
      </div>
    )
  }

  return (
    <div className="flex gap-2">
      <button type="button" onClick={handleApprove} disabled={busy || disabled} className="build-btn-primary text-xs">
        {busy ? 'Approving…' : 'Approve'}
      </button>
      <button type="button" onClick={() => setShowReject(true)} disabled={busy || disabled} className="build-btn-secondary text-xs">
        Reject
      </button>
    </div>
  )
}
