import { Link, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { formatDistanceToNow } from 'date-fns'
import { ArrowLeft, ExternalLink } from 'lucide-react'
import { apiClient } from '@/lib/services'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { ErrorState } from '@/components/shared/EmptyState'
import { LoadingPage } from '@/components/shared/LoadingPage'
import { UptimeBar, segmentsFromUptimePercent } from '@/components/status/UptimeBar'
import { ResponseTimeChart } from '@/components/charts/ResponseTimeChart'
import { formatMs, formatPercent } from '@/lib/utils'

export function PublicSiteStatusPage() {
  const { slug = '' } = useParams()
  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['public-status', slug],
    queryFn: () => apiClient.publicSite(slug),
    enabled: Boolean(slug),
    refetchInterval: 30_000,
  })

  if (isLoading) return <LoadingPage />
  if (isError || !data) {
    return (
      <ErrorState
        title="Monitor not found"
        message={(error as Error)?.message ?? 'This status page is unavailable.'}
        onRetry={() => void refetch()}
      />
    )
  }

  const { website, uptime, sparkline, incidents, history } = data
  const segments =
    history?.length > 0
      ? history.map((h) => h.segment)
      : segmentsFromUptimePercent(uptime.d90.uptimePercent, 90)
  const chartData = sparkline.map((p) => ({
    checkedAt: p.t,
    responseMs: p.ms,
  }))

  return (
    <div className="space-y-6">
      <Button variant="ghost" size="sm" asChild className="-ml-2">
        <Link to="/status">
          <ArrowLeft className="h-4 w-4" />
          All systems
        </Link>
      </Button>

      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h1 className="text-2xl font-semibold tracking-tight">{website.name}</h1>
            <StatusBadge status={website.currentStatus} />
            {website.isStale ? <Badge variant="warning">Stale</Badge> : null}
          </div>
          <a
            href={website.url}
            target="_blank"
            rel="noreferrer"
            className="mt-1 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
          >
            {website.url}
            <ExternalLink className="h-3.5 w-3.5" />
          </a>
          {website.description ? (
            <p className="mt-2 max-w-2xl text-sm text-muted-foreground">{website.description}</p>
          ) : null}
        </div>
        <div className="text-sm text-muted-foreground">
          Last checked{' '}
          {website.lastCheckedAt
            ? formatDistanceToNow(new Date(website.lastCheckedAt), { addSuffix: true })
            : '—'}
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {[
          { label: '24h uptime', value: formatPercent(uptime.h24.uptimePercent) },
          { label: '7d uptime', value: formatPercent(uptime.d7.uptimePercent) },
          { label: '30d uptime', value: formatPercent(uptime.d30.uptimePercent) },
          { label: 'Avg response', value: formatMs(website.lastResponseMs) },
        ].map((stat) => (
          <Card key={stat.label}>
            <CardHeader className="pb-2">
              <CardTitle className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {stat.label}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-2xl font-semibold tabular-nums">{stat.value}</p>
            </CardContent>
          </Card>
        ))}
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">90-day availability</CardTitle>
        </CardHeader>
        <CardContent>
          <UptimeBar
            segments={segments}
            dates={history?.map((h) => h.date)}
          />
        </CardContent>
      </Card>

      <ResponseTimeChart data={chartData} title="Response time (24h)" />

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Recent incidents</CardTitle>
        </CardHeader>
        <CardContent>
          {incidents.length === 0 ? (
            <p className="text-sm text-muted-foreground">No incidents recorded.</p>
          ) : (
            <ul className="space-y-3">
              {incidents.map((inc) => (
                <li key={inc.id} className="flex items-start justify-between gap-3 border-b pb-3 last:border-0 last:pb-0">
                  <div>
                    <div className="flex items-center gap-2">
                      <StatusBadge status={inc.status} pulse={false} />
                      <span className="text-sm">{inc.summary ?? 'Status change'}</span>
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                      Started {formatDistanceToNow(new Date(inc.startedAt), { addSuffix: true })}
                      {inc.resolvedAt
                        ? ` · Resolved ${formatDistanceToNow(new Date(inc.resolvedAt), { addSuffix: true })}`
                        : ' · Ongoing'}
                    </p>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
