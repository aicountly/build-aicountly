import Sidebar from './Sidebar.jsx'
import Header from './Header.jsx'

export default function BuildLayout({ children }) {
  return (
    <div className="flex h-screen w-screen overflow-hidden bg-neutral-50">
      <Sidebar />
      <div className="flex min-w-0 flex-1 flex-col">
        <Header />
        <main className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-6 sm:py-6">
          {children}
        </main>
      </div>
    </div>
  )
}
