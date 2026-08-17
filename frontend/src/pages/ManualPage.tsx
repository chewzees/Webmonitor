import { Link } from 'react-router-dom'
import {
  Activity,
  ArrowLeft,
  Bell,
  BookOpen,
  Gauge,
  Globe,
  LogIn,
  Play,
  ScrollText,
  Shield,
  UserRound,
} from 'lucide-react'
import { DEMO_ACCOUNTS } from '@/lib/demoAccounts'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { ThemeToggle } from '@/components/layout/ThemeToggle'

const sections = [
  {
    icon: LogIn,
    title: 'Signing in',
    body: 'Open the login page and use Admin or User quick-fill, then click Sign in. You can also type credentials manually.',
  },
  {
    icon: Gauge,
    title: 'Dashboard',
    body: 'See total websites, up/down counts, average latency, 24h uptime, stale monitors, and recent incidents. Use Run all checks to ping every active site immediately.',
  },
  {
    icon: Globe,
    title: 'Websites',
    body: 'Add, edit, or delete monitors. Set URL, check interval, timeout, expected HTTP status, optional keyword, and whether the site appears on the public status page. Use bulk actions and CSV export from the list.',
  },
  {
    icon: Play,
    title: 'Manual checks',
    body: 'On a website detail page, click Check now to run one check. Results update response time, status, and logs.',
  },
  {
    icon: ScrollText,
    title: 'Logs & audit',
    body: 'Monitoring Logs shows every check result with filters and CSV export. Audit lists admin actions such as login, website changes, and Telegram updates.',
  },
  {
    icon: Bell,
    title: 'Telegram alerts',
    body: 'Go to Settings → Telegram. Paste your bot token and chat ID, enable alerts, then send a test message. Alerts fire when a site goes down or recovers.',
  },
  {
    icon: Activity,
    title: 'Public status page',
    body: 'Share /status with anyone. It shows overall health, uptime percentages, and a 90-day history bar for each public monitor — no login required.',
  },
]

export function ManualPage() {
  return (
    <div className="relative min-h-screen px-4 py-10">
      <div className="absolute right-4 top-4">
        <ThemeToggle />
      </div>

      <div className="mx-auto max-w-3xl space-y-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <Button variant="ghost" size="sm" asChild className="-ml-2">
            <Link to="/login">
              <ArrowLeft className="h-4 w-4" />
              Back to login
            </Link>
          </Button>
          <Button size="sm" asChild>
            <Link to="/login">Go to login</Link>
          </Button>
        </div>

        <Card className="border-border/70 shadow-lg">
          <CardHeader>
            <div className="mb-2 flex h-11 w-11 items-center justify-center rounded-xl bg-primary text-primary-foreground">
              <BookOpen className="h-5 w-5" />
            </div>
            <CardTitle className="text-2xl">WebMonitor User Manual</CardTitle>
            <CardDescription>
              Quick guide to logging in, monitoring websites, viewing status, and configuring alerts.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            <div className="rounded-xl border bg-muted/40 p-4">
              <p className="mb-3 text-sm font-semibold">Demo accounts (quick-fill on login)</p>
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="rounded-lg border bg-card p-3">
                  <div className="mb-2 flex items-center gap-2 text-sm font-semibold">
                    <Shield className="h-4 w-4 text-primary" />
                    Admin
                  </div>
                  <p className="text-xs text-muted-foreground">Email</p>
                  <p className="font-mono text-sm">{DEMO_ACCOUNTS.admin.email}</p>
                  <p className="mt-2 text-xs text-muted-foreground">Password</p>
                  <p className="font-mono text-sm">{DEMO_ACCOUNTS.admin.password}</p>
                </div>
                <div className="rounded-lg border bg-card p-3">
                  <div className="mb-2 flex items-center gap-2 text-sm font-semibold">
                    <UserRound className="h-4 w-4 text-primary" />
                    User
                  </div>
                  <p className="text-xs text-muted-foreground">Email</p>
                  <p className="font-mono text-sm">{DEMO_ACCOUNTS.user.email}</p>
                  <p className="mt-2 text-xs text-muted-foreground">Password</p>
                  <p className="font-mono text-sm">{DEMO_ACCOUNTS.user.password}</p>
                </div>
              </div>
            </div>

            <div className="space-y-4">
              {sections.map((section) => (
                <div key={section.title} className="flex gap-3 rounded-xl border p-4">
                  <div className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <section.icon className="h-4 w-4" />
                  </div>
                  <div>
                    <h3 className="font-semibold">{section.title}</h3>
                    <p className="mt-1 text-sm text-muted-foreground">{section.body}</p>
                  </div>
                </div>
              ))}
            </div>

            <div className="rounded-xl border border-primary/20 bg-primary/5 p-4 text-sm">
              <p className="font-semibold text-foreground">Scheduled monitoring tip</p>
              <p className="mt-1 text-muted-foreground">
                For automatic checks on XAMPP, run{' '}
                <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">
                  cli/monitor.php
                </code>{' '}
                every minute with Windows Task Scheduler (or use{' '}
                <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">
                  scripts/run-monitor.bat
                </code>
                ).
              </p>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
