import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { formatDistanceToNow } from 'date-fns'
import { AlertTriangle, Play, Plus, Activity, Gauge, Server, Timer } from 'lucide-react'
import { toast } from 'sonner'
import { apiClient } from '@/lib/services'
import { ApiError } from '@/lib/api'
import { PageHeader } from '@/components/shared/PageHeader'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { ErrorState } from '@/components/shared/EmptyState'
import { DashboardSkeleton } from '@/components/shared/LoadingPage'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { formatMs, formatPercent } from '@/lib/utils'

export function DashboardPage() {
  const queryClient = useQueryClient()
  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['dashboard'],
    queryFn: () => apiClient.dashboard(),
    refetchInterval: 30_000,
  })

  const checkAll = useMutation({
    mutationFn: () => apiClient.websites.checkAll(),
    onSuccess: (res) => {
      toast.success(`Queued checks for ${res.checked} websites`)
      void queryClient.invalidateQueries({ queryKey: ['dashboard'] })
      void queryClient.invalidateQueries({ queryKey: ['websites'] })
    },
    onError: (err) => toast.error(err instanceof ApiError ? err.message : 'Check failed'),
  })

  if (isLoading) return <DashboardSkeleton />
  if (isError || !data) {
    return <ErrorState message={(error as Error)?.message} onRetry={() => void refetch()} />
  }

  const stats = [
    { label: 'Total websites', value: data.totalSites, icon: Server },
    { label: '24h uptime', value: formatPercent(data.overallUptime24h), icon: Activity },
    { label: 'Avg latency', value: formatMs(data.avgResponseMs), icon: Gauge },
    { label: 'Checks (24h)', value: data.checksLast24h, icon: Timer },
  ]

  return (
    <div className="space-y-6">
      <PageHeader
        title="Dashboard"
        description="Overview of monitoring health across all websites"
        actions={
          <>
            <Button variant="outline" asChild>
              <Link to="/admin/websites/new">
                <Plus className="h-4 w-4" />
                Add website
              </Link>
            </Button>
            <Button onClick={() => checkAll.mutate()} disabled={checkAll.isPending}>
              <Play className="h-4 w-4" />
              {checkAll.isPending ? 'Running…' : 'Run all checks'}
            </Button>
          </>
        }
      />

      {data.staleCount > 0 ? (
        <div className="flex items-start gap-3 rounded-xl border border-warning/40 bg-warning/10 px-4 py-3 text-sm">
          <AlertTriangle className="mt-0.5 h-4 w-4 text-warning" />
          <div>
            <p className="font-medium text-foreground">
              {data.staleCount} monitor{data.staleCount === 1 ? '' : 's'} appear stale
            </p>
            <p className="text-muted-foreground">
              Checks have not completed within the expected interval. Consider running checks manually.
            </p>
          </div>
        </div>
      ) : null}

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {stats.map((stat) => (
          <Card key={stat.label}>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {stat.label}
              </CardTitle>
              <stat.icon className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <p className="text-2xl font-semibold tabular-nums">{stat.value}</p>
            </CardContent>
          </Card>
        ))}
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Status breakdown</CardTitle>
          </CardHeader>
          <CardContent className="grid grid-cols-2 gap-3">
            {(['UP', 'DOWN', 'DEGRADED', 'UNKNOWN'] as const).map((status) => (
              <div key={status} className="rounded-lg border p-3">
                <StatusBadge status={status} pulse={false} />
                <p className="mt-2 text-2xl font-semibold tabular-nums">
                  {data.statusCounts[status]}
                </p>
              </div>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle className="text-base">Recent incidents</CardTitle>
            <Button variant="ghost" size="sm" asChild>
              <Link to="/admin/logs">View logs</Link>
            </Button>
          </CardHeader>
          <CardContent>
            {data.recentIncidents.length === 0 ? (
              <p className="text-sm text-muted-foreground">No recent incidents. Looking good.</p>
            ) : (
              <ul className="space-y-3">
                {data.recentIncidents.map((inc) => (
                  <li key={inc.id} className="flex items-start justify-between gap-3 border-b pb-3 last:border-0">
                    <div>
                      <Link
                        to={`/admin/websites/${inc.websiteId}`}
                        className="font-medium hover:text-primary hover:underline"
                      >
                        {inc.website.name}
                      </Link>
                      <div className="mt-1 flex items-center gap-2">
                        <StatusBadge status={inc.status} pulse={false} />
                        <span className="text-xs text-muted-foreground">
                          {inc.summary ?? 'Incident'}
                        </span>
                      </div>
                    </div>
                    <span className="shrink-0 text-xs text-muted-foreground">
                      {formatDistanceToNow(new Date(inc.startedAt), { addSuffix: true })}
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
