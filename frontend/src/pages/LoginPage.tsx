import { useState } from 'react'
import { Link, Navigate, useLocation } from 'react-router-dom'
import { Activity, BookOpen } from 'lucide-react'
import { toast } from 'sonner'
import { useAuth } from '@/hooks/useAuth'
import { ApiError } from '@/lib/api'
import { DEMO_ACCOUNTS } from '@/lib/demoAccounts'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { ThemeToggle } from '@/components/layout/ThemeToggle'

export function LoginPage() {
  const { login, isLoggingIn, isAuthenticated, isLoading } = useAuth()
  const location = useLocation()
  const from = (location.state as { from?: string } | null)?.from ?? '/admin'
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')

  if (!isLoading && isAuthenticated) {
    return <Navigate to={from} replace />
  }

  function fillAccount(role: 'admin' | 'user') {
    const account = DEMO_ACCOUNTS[role]
    setEmail(account.email)
    setPassword(account.password)
  }

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    try {
      await login({ email, password })
      toast.success('Welcome back')
    } catch (err) {
      const message = err instanceof ApiError ? err.message : 'Login failed'
      toast.error(message)
    }
  }

  return (
    <div className="relative flex min-h-screen items-center justify-center px-4 py-10">
      <div className="absolute right-4 top-4 flex items-center gap-2">
        <Button variant="ghost" size="sm" asChild>
          <Link to="/manual">
            <BookOpen className="h-4 w-4" />
            User Manual
          </Link>
        </Button>
        <ThemeToggle />
      </div>

      <Card className="w-full max-w-md border-border/70 shadow-lg">
        <CardHeader className="space-y-3 text-center">
          <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-md shadow-primary/25">
            <Activity className="h-6 w-6" />
          </div>
          <p className="text-sm text-muted-foreground">Please sign in to continue</p>
          <CardTitle className="text-2xl tracking-tight">WebMonitor</CardTitle>
          <CardDescription>Monitor uptime, latency, and incidents.</CardDescription>
        </CardHeader>

        <CardContent>
          <div className="mb-5 space-y-2">
            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              Quick fill
            </p>
            <div className="flex flex-wrap items-center gap-2">
              <Button type="button" variant="outline" size="sm" onClick={() => fillAccount('admin')}>
                Autofill Admin
              </Button>
              <Button type="button" variant="outline" size="sm" onClick={() => fillAccount('user')}>
                Autofill User
              </Button>
              <Link
                to="/manual"
                className="ml-auto text-sm font-medium text-primary hover:underline"
              >
                User Manual
              </Link>
            </div>
          </div>

          <form onSubmit={onSubmit} className="space-y-4" autoComplete="on">
            <div className="space-y-2">
              <Label htmlFor="email">Email</Label>
              <Input
                id="email"
                name="email"
                type="email"
                autoComplete="username"
                required
                autoFocus
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="you@example.com"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="password">Password</Label>
              <Input
                id="password"
                name="password"
                type="password"
                autoComplete="current-password"
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
              />
            </div>
            <Button type="submit" className="w-full" disabled={isLoggingIn}>
              {isLoggingIn ? 'Signing in…' : 'Sign in'}
            </Button>
          </form>

          <div className="mt-5 rounded-lg border border-border/70 bg-primary/5 px-3 py-3 text-sm text-muted-foreground">
            <p className="mb-1 font-semibold text-foreground">Demo accounts</p>
            <p>
              Admin: <code className="text-xs">{DEMO_ACCOUNTS.admin.email}</code> /{' '}
              <code className="text-xs">{DEMO_ACCOUNTS.admin.password}</code>
            </p>
            <p>
              User: <code className="text-xs">{DEMO_ACCOUNTS.user.email}</code> /{' '}
              <code className="text-xs">{DEMO_ACCOUNTS.user.password}</code>
            </p>
          </div>

          <p className="mt-5 text-center text-sm">
            <Link to="/status" className="font-medium text-primary hover:underline">
              View public status page →
            </Link>
          </p>
        </CardContent>
      </Card>
    </div>
  )
}
