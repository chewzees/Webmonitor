import { useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { format, formatDistanceToNow } from 'date-fns'
import { Download } from 'lucide-react'
import { toast } from 'sonner'
import { apiClient } from '@/lib/services'
import { ApiError } from '@/lib/api'
import { downloadBlob, formatMs } from '@/lib/utils'
import { useDebounce } from '@/hooks/useDebounce'
import { PageHeader } from '@/components/shared/PageHeader'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { EmptyState, ErrorState } from '@/components/shared/EmptyState'
import { TableSkeleton } from '@/components/shared/LoadingPage'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { MonitorStatus } from '@/types/api'

export function LogsPage() {
  const [searchParams] = useSearchParams()
  const initialWebsiteId = searchParams.get('websiteId') ?? ''
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState<MonitorStatus | 'all'>('all')
  const [websiteId, setWebsiteId] = useState(initialWebsiteId)
  const [sortBy, setSortBy] = useState('checkedAt')
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('desc')
  const debouncedSearch = useDebounce(search, 300)

  const params = useMemo(
    () => ({
      page,
      limit: 50,
      search: debouncedSearch || undefined,
      status: status === 'all' ? undefined : status,
      websiteId: websiteId || undefined,
      sortBy,
      sortOrder,
    }),
    [page, debouncedSearch, status, websiteId, sortBy, sortOrder],
  )

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['logs', params],
    queryFn: () => apiClient.logs.list(params),
  })

  const websitesQuery = useQuery({
    queryKey: ['websites', { limit: 100, sortBy: 'name' }],
    queryFn: () => apiClient.websites.list({ limit: 100, sortBy: 'name', sortOrder: 'asc' }),
  })

  async function exportCsv() {
    try {
      const blob = await apiClient.logs.export(params)
      downloadBlob(blob, `logs-${format(new Date(), 'yyyy-MM-dd')}.csv`)
      toast.success('Logs exported')
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : 'Export failed')
    }
  }

  const items = data?.items ?? []

  return (
    <div className="space-y-6">
      <PageHeader
        title="Monitoring logs"
        description="Global check history across all websites"
        actions={
          <Button variant="outline" onClick={() => void exportCsv()}>
            <Download className="h-4 w-4" />
            Export CSV
          </Button>
        }
      />

      <div className="flex flex-col gap-3 lg:flex-row">
        <Input
          placeholder="Search error or website…"
          value={search}
          onChange={(e) => {
            setSearch(e.target.value)
            setPage(1)
          }}
          className="lg:max-w-xs"
        />
        <Select
          value={status}
          onValueChange={(v) => {
            setStatus(v as MonitorStatus | 'all')
            setPage(1)
          }}
        >
          <SelectTrigger className="lg:w-40">
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            <SelectItem value="UP">Up</SelectItem>
            <SelectItem value="DOWN">Down</SelectItem>
            <SelectItem value="DEGRADED">Degraded</SelectItem>
            <SelectItem value="UNKNOWN">Unknown</SelectItem>
          </SelectContent>
        </Select>
        <Select
          value={websiteId || 'all'}
          onValueChange={(v) => {
            setWebsiteId(v === 'all' ? '' : v)
            setPage(1)
          }}
        >
          <SelectTrigger className="lg:w-56">
            <SelectValue placeholder="Website" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All websites</SelectItem>
            {(websitesQuery.data?.items ?? []).map((w) => (
              <SelectItem key={w.id} value={w.id}>
                {w.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Select
          value={`${sortBy}:${sortOrder}`}
          onValueChange={(v) => {
            const [by, order] = v.split(':')
            setSortBy(by)
            setSortOrder(order as 'asc' | 'desc')
          }}
        >
          <SelectTrigger className="lg:w-48">
            <SelectValue placeholder="Sort" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="checkedAt:desc">Newest first</SelectItem>
            <SelectItem value="checkedAt:asc">Oldest first</SelectItem>
            <SelectItem value="responseMs:desc">Slowest first</SelectItem>
            <SelectItem value="responseMs:asc">Fastest first</SelectItem>
            <SelectItem value="status:asc">Status A–Z</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {isLoading ? (
        <TableSkeleton rows={10} />
      ) : isError ? (
        <ErrorState message={(error as Error)?.message} onRetry={() => void refetch()} />
      ) : items.length === 0 ? (
        <EmptyState title="No logs" description="Checks will appear here as monitors run." />
      ) : (
        <>
          <div className="rounded-xl border bg-card">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Website</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Code</TableHead>
                  <TableHead>Latency</TableHead>
                  <TableHead>Checked</TableHead>
                  <TableHead>Error</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.map((log) => (
                  <TableRow key={log.id}>
                    <TableCell>
                      <div>
                        <p className="font-medium">{log.website.name}</p>
                        <p className="text-xs text-muted-foreground">{log.website.slug}</p>
                      </div>
                    </TableCell>
                    <TableCell>
                      <StatusBadge status={log.status} pulse={false} />
                    </TableCell>
                    <TableCell className="tabular-nums">{log.statusCode ?? '—'}</TableCell>
                    <TableCell className="tabular-nums">{formatMs(log.responseMs)}</TableCell>
                    <TableCell className="text-muted-foreground">
                      <span title={new Date(log.checkedAt).toLocaleString()}>
                        {formatDistanceToNow(new Date(log.checkedAt), { addSuffix: true })}
                      </span>
                    </TableCell>
                    <TableCell className="max-w-[240px] truncate text-xs text-muted-foreground">
                      {log.errorMessage ?? '—'}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
          <div className="flex items-center justify-between text-sm text-muted-foreground">
            <span>
              Page {data?.page ?? 1} of {data?.totalPages ?? 1} · {data?.total ?? 0} total
            </span>
            <div className="flex gap-2">
              <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                Previous
              </Button>
              <Button
                variant="outline"
                size="sm"
                disabled={!data || page >= data.totalPages}
                onClick={() => setPage((p) => p + 1)}
              >
                Next
              </Button>
            </div>
          </div>
        </>
      )}
    </div>
  )
}
