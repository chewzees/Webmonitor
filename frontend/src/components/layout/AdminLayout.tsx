import { NavLink, Outlet, Link } from 'react-router-dom'
import {
  Activity,
  FileText,
  LayoutDashboard,
  LogOut,
  Menu,
  ScrollText,
  Settings,
  Globe,
} from 'lucide-react'
import { useState } from 'react'
import { useAuth } from '@/hooks/useAuth'
import { useSSE } from '@/hooks/useSSE'
import { ThemeToggle } from '@/components/layout/ThemeToggle'
import { GlobalSearch } from '@/components/layout/GlobalSearch'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet'
import { cn } from '@/lib/cn'

const nav = [
  { to: '/admin', label: 'Dashboard', icon: LayoutDashboard, end: true },
  { to: '/admin/websites', label: 'Websites', icon: Globe },
  { to: '/admin/logs', label: 'Logs', icon: ScrollText },
  { to: '/admin/settings', label: 'Settings', icon: Settings },
  { to: '/admin/audit', label: 'Audit', icon: FileText },
]

function NavItems({ onNavigate }: { onNavigate?: () => void }) {
  return (
    <nav className="flex flex-col gap-1">
      {nav.map((item) => (
        <NavLink
          key={item.to}
          to={item.to}
          end={item.end}
          onClick={onNavigate}
          className={({ isActive }) =>
            cn(
              'flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
              isActive
                ? 'bg-primary/10 text-primary'
                : 'text-muted-foreground hover:bg-muted hover:text-foreground',
            )
          }
        >
          <item.icon className="h-4 w-4" />
          {item.label}
        </NavLink>
      ))}
    </nav>
  )
}

export function AdminLayout() {
  const { user, logout, isLoggingOut, isAuthenticated } = useAuth()
  const [open, setOpen] = useState(false)
  useSSE(isAuthenticated)

  return (
    <div className="min-h-screen">
      <header className="glass sticky top-0 z-40">
        <div className="mx-auto flex h-14 max-w-7xl items-center gap-3 px-4">
          <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
              <Button variant="ghost" size="icon" className="lg:hidden">
                <Menu className="h-5 w-5" />
              </Button>
            </SheetTrigger>
            <SheetContent side="left" className="w-72">
              <SheetHeader>
                <SheetTitle className="flex items-center gap-2">
                  <Activity className="h-5 w-5 text-primary" />
                  WebMonitor
                </SheetTitle>
              </SheetHeader>
              <div className="mt-6">
                <NavItems onNavigate={() => setOpen(false)} />
              </div>
            </SheetContent>
          </Sheet>

          <Link to="/admin" className="flex items-center gap-2 font-semibold tracking-tight">
            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
              <Activity className="h-4 w-4" />
            </span>
            <span className="hidden sm:inline">WebMonitor</span>
          </Link>

          <div className="ml-auto flex flex-1 items-center justify-end gap-2 sm:ml-6 sm:justify-between">
            <GlobalSearch className="hidden md:block" />
            <div className="flex items-center gap-1">
              <Button variant="ghost" size="sm" asChild className="hidden sm:inline-flex">
                <Link to="/status">Public status</Link>
              </Button>
              <ThemeToggle />
              <div className="hidden items-center gap-2 pl-2 text-sm text-muted-foreground sm:flex">
                <span className="max-w-[140px] truncate">{user?.name ?? user?.email}</span>
              </div>
              <Button variant="ghost" size="icon" onClick={() => void logout()} disabled={isLoggingOut} aria-label="Log out">
                <LogOut className="h-4 w-4" />
              </Button>
            </div>
          </div>
        </div>
      </header>

      <div className="mx-auto grid max-w-7xl gap-6 px-4 py-6 lg:grid-cols-[220px_1fr]">
        <aside className="hidden lg:block">
          <div className="sticky top-20 rounded-xl border bg-card/60 p-3 shadow-sm">
            <NavItems />
          </div>
        </aside>
        <main className="page-enter min-w-0">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
