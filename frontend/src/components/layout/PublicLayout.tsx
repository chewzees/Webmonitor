import { Link, Outlet } from 'react-router-dom'
import { Activity } from 'lucide-react'
import { ThemeToggle } from '@/components/layout/ThemeToggle'
import { Button } from '@/components/ui/button'

export function PublicLayout() {
  return (
    <div className="min-h-screen">
      <header className="glass sticky top-0 z-40">
        <div className="mx-auto flex h-14 max-w-5xl items-center justify-between px-4">
          <Link to="/status" className="flex items-center gap-2 font-semibold tracking-tight">
            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
              <Activity className="h-4 w-4" />
            </span>
            WebMonitor
          </Link>
          <div className="flex items-center gap-2">
            <ThemeToggle />
            <Button variant="outline" size="sm" asChild>
              <Link to="/admin">Dashboard</Link>
            </Button>
          </div>
        </div>
      </header>
      <main className="page-enter mx-auto max-w-5xl px-4 py-8">
        <Outlet />
      </main>
      <footer className="border-t py-8 text-center text-xs text-muted-foreground">
        Powered by WebMonitor · Real-time uptime monitoring
      </footer>
    </div>
  )
}
