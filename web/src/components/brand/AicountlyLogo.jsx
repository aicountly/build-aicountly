export default function AicountlyLogo({ className = 'h-8 w-8', accent = '#16a34a' }) {
  return (
    <svg
      viewBox="0 0 32 32"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      aria-label="AICOUNTLY Build"
    >
      <rect width="32" height="32" rx="7" fill={accent} />
      <path
        d="M9 10h8a4 4 0 0 1 0 8H9V10zm0 8h9a4 4 0 0 1 0 8H9v-8z"
        fill="none"
        stroke="white"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}
