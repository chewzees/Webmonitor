import { Link } from 'react-router-dom'
import { formatDistanceToNow } from 'date-fns'
import { ExternalLink } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { UptimeBar, segmentsFromUptimePercent } from '@/components/status/UptimeBar'
import { formatMs, formatPercent } from '@/lib/utils'
import type { PublicStatusWebsite } from '@/types/api'

interface MonitorCardProps {
  website: PublicStatusWebsite
}

export function MonitorCard({ website }: MonitorCardProps) {
  const segments =
    website.history?.length > 0
      ? website.history.map((h) => h.segment)
      : segmentsFromUptimePercent(website.uptime.d90, 90)

  return (
    <Card className="overflow-hidden transition-shadow hover:shadow-md">
      <CardHeader className="flex flex-row items-start justify-between gap-3 space-y-0 pb-3">
        <div className="min-w-0">
          <CardTitle className="truncate text-base">
            <Link to={`/status/${website.slug}`} className="hover:text-primary hover:underline">
              {website.name}
            </Link>
          </CardTitle>
          <a
            href={website.url}
            target="_blank"
            rel="noreferrer"
            className="mt-1 inline-flex max-w-full items-center gap-1 truncate text-xs text-muted-foreground hover:text-foreground"
          >
            <span className="truncate">{website.url}</span>
            <ExternalLink className="h-3 w-3 shrink-0" />
          </a>
        </div>
        <div className="flex flex-col items-end gap-1">
          <StatusBadge status={website.currentStatus} />
          {website.isStale ? <Badge variant="warning">Stale</Badge> : null}
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="grid grid-cols-3 gap-3 text-sm">
          <div>
            <p className="text-xs text-muted-foreground">Uptime (90d)</p>
            <p className="font-semibold tabular-nums">{formatPercent(website.uptime.d90)}</p>
          </div>
          <div>
            <p className="text-xs text-muted-foreground">Response</p>
            <p className="font-semibold tabular-nums">{formatMs(website.lastResponseMs)}</p>
          </div>
          <div>
            <p className="text-xs text-muted-foreground">Last checked</p>
            <p className="font-medium">
              {website.lastCheckedAt
                ? formatDistanceToNow(new Date(website.lastCheckedAt), { addSuffix: true })
                : '—'}
            </p>
          </div>
        </div>
        <UptimeBar
          segments={segments}
          dates={website.history?.map((h) => h.date)}
        />
      </CardContent>
    </Card>
  )
}
