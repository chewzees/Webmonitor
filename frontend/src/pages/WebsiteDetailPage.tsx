import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { formatDistanceToNow } from 'date-fns'
import { ArrowLeft, Pencil, Play, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { toast } from 'sonner'
import { apiClient } from '@/lib/services'
import { ApiError } from '@/lib/api'
import { formatMs, formatPercent } from '@/lib/utils'
import { PageHeader } from '@/components/shared/PageHeader'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { ErrorState } from '@/components/shared/EmptyState'
import { LoadingPage } from '@/components/shared/LoadingPage'
import { ConfirmDialog } from '@/components/shared/ConfirmDialog'
import { ResponseTimeChart, UptimeChart } from '@/components/charts/ResponseTimeChart'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

export function WebsiteDetailPage() {
  const { id = '' } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [confirmDelete, setConfirmDelete] = useState(false)

  const websiteQuery = useQuery({
    queryKey: ['websites', id],
    queryFn: () => apiClient.websites.get(id),
    enabled: Boolean(id),
  })

  const statsQuery = useQuery({
    queryKey: ['websites', id, 'stats'],
    queryFn: () => apiClient.websites.stats(id),
    enabled: Boolean(id),
    refetchInterval: 30_000,
  })

  const logsQuery = useQuery({
    queryKey: ['logs', { websiteId: id, limit: 20 }],
    queryFn: () => apiClient.logs.list({ websiteId: id, limit: 20, sortBy: 'checkedAt', sortOrder: 'desc' }),
    enabled: Boolean(id),
  })

  const checkMutation = useMutation({
    mutationFn: () => apiClient.websites.check(id),
    onSuccess: () => {
      toast.success('Check completed')
      void queryClient.invalidateQueries({ queryKey: ['websites', id] })
      void queryClient.invalidateQueries({ queryKey: ['logs'] })
      void queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    },
    onError: (err) => toast.error(err instanceof ApiError ? err.message : 'Check failed'),
  })

  const deleteMutation = useMutation({
    mutationFn: () => apiClient.websites.remove(id),
    onSuccess: () => {
      toast.success('Website deleted')
      void queryClient.invalidateQueries({ queryKey: ['websites'] })
      navigate('/admin/websites')
    },
    onError: (err) => toast.error(err instanceof ApiError ? err.message : 'Delete failed'),
  })

  if (websiteQuery.isLoading) return <LoadingPage />
  if (websiteQuery.isError || !websiteQuery.data) {
    return (
      <ErrorState
        message={(websiteQuery.error as Error)?.message}
        onRetry={() => void websiteQuery.refetch()}
      />
    )
  }

  const website = websiteQuery.data.website
  const stats = statsQuery.data
  const uptimeChart = stats
    ? [
        { label: '24h', uptime: stats.uptime.h24.uptimePercent },
        { label: '7d', uptime: stats.uptime.d7.uptimePercent },
        { label: '30d', uptime: stats.uptime.d30.uptimePercent },
        { label: '90d', uptime: stats.uptime.d90.uptimePercent },
      ]
    : []

  return (
    <div className="space-y-6">
      <Button variant="ghost" size="sm" asChild className="-ml-2">
        <Link to="/admin/websites">
          <ArrowLeft className="h-4 w-4" />
          Back to websites
        </Link>
      </Button>

      <PageHeader
        title={website.name}
        description={website.url}
        actions={
          <>
            <Button variant="outline" onClick={() => checkMutation.mutate()} disabled={checkMutation.isPending}>
              <Play className="h-4 w-4" />
              {checkMutation.isPending ? 'Checking…' : 'Check now'}
            </Button>
            <Button variant="outline" asChild>
              <Link to={`/admin/websites/${id}/edit`}>
                <Pencil className="h-4 w-4" />
                Edit
              </Link>
            </Button>
            <Button variant="destructive" onClick={() => setConfirmDelete(true)}>
              <Trash2 className="h-4 w-4" />
              Delete
            </Button>
          </>
        }
      />

      <div className="flex flex-wrap gap-2">
        <StatusBadge status={website.currentStatus} />
        {website.isStale ? <Badge variant="warning">Stale</Badge> : null}
        <Badge variant={website.isActive ? 'success' : 'muted'}>
          {website.isActive ? 'Active' : 'Paused'}
        </Badge>
        {website.isPublic ? <Badge variant="secondary">Public</Badge> : null}
        <Badge variant="outline" className="font-mono text-xs">
          {website.method} · every {website.intervalSeconds}s
        </Badge>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {[
          { label: 'Last response', value: formatMs(website.lastResponseMs) },
          { label: 'Status code', value: website.lastStatusCode ?? '—' },
          {
            label: 'Last checked',
            value: website.lastCheckedAt
              ? formatDistanceToNow(new Date(website.lastCheckedAt), { addSuffix: true })
              : '—',
          },
          {
            label: '90d uptime',
            value: stats ? formatPercent(stats.uptime.d90.uptimePercent) : '—',
          },
        ].map((stat) => (
          <Card key={stat.label}>
            <CardHeader className="pb-2">
              <CardTitle className="text-xs uppercase tracking-wide text-muted-foreground">
                {stat.label}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-xl font-semibold tabular-nums">{stat.value}</p>
            </CardContent>
          </Card>
        ))}
      </div>

      {website.lastError ? (
        <div className="rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
          {website.lastError}
        </div>
      ) : null}

      <div className="grid gap-4 lg:grid-cols-2">
        <ResponseTimeChart data={stats?.responseTimeSeries ?? []} />
        <UptimeChart data={uptimeChart} title="Uptime by period" />
      </div>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle className="text-base">Recent checks</CardTitle>
          <Button variant="ghost" size="sm" asChild>
            <Link to={`/admin/logs?websiteId=${id}`}>View all logs</Link>
          </Button>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Status</TableHead>
                <TableHead>Code</TableHead>
                <TableHead>Latency</TableHead>
                <TableHead>Checked</TableHead>
                <TableHead>Error</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(logsQuery.data?.items ?? []).map((log) => (
                <TableRow key={log.id}>
                  <TableCell>
                    <StatusBadge status={log.status} pulse={false} />
                  </TableCell>
                  <TableCell className="tabular-nums">{log.statusCode ?? '—'}</TableCell>
                  <TableCell className="tabular-nums">{formatMs(log.responseMs)}</TableCell>
                  <TableCell className="text-muted-foreground">
                    {formatDistanceToNow(new Date(log.checkedAt), { addSuffix: true })}
                  </TableCell>
                  <TableCell className="max-w-[220px] truncate text-xs text-muted-foreground">
                    {log.errorMessage ?? '—'}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          {(logsQuery.data?.items.length ?? 0) === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">No checks yet</p>
          ) : null}
        </CardContent>
      </Card>

      <ConfirmDialog
        open={confirmDelete}
        onOpenChange={setConfirmDelete}
        title="Delete website?"
        description={`This will permanently delete “${website.name}” and associated monitoring history.`}
        confirmLabel="Delete"
        loading={deleteMutation.isPending}
        onConfirm={() => deleteMutation.mutate()}
      />
    </div>
  )
}
