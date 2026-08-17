import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { formatDistanceToNow } from 'date-fns'
import { apiClient } from '@/lib/services'
import { useDebounce } from '@/hooks/useDebounce'
import { PageHeader } from '@/components/shared/PageHeader'
import { EmptyState, ErrorState } from '@/components/shared/EmptyState'
import { TableSkeleton } from '@/components/shared/LoadingPage'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'

export function AuditPage() {
  const [page, setPage] = useState(1)
  const [action, setAction] = useState('')
  const debouncedAction = useDebounce(action, 300)

  const params = useMemo(
    () => ({
      page,
      limit: 50,
      action: debouncedAction || undefined,
    }),
    [page, debouncedAction],
  )

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['audit', params],
    queryFn: () => apiClient.audit.list(params),
  })

  const items = data?.items ?? []

  return (
    <div className="space-y-6">
      <PageHeader
        title="Audit log"
        description="Track authentication events and administrative changes"
      />

      <Input
        placeholder="Filter by action…"
        value={action}
        onChange={(e) => {
          setAction(e.target.value)
          setPage(1)
        }}
        className="max-w-sm"
      />

      {isLoading ? (
        <TableSkeleton />
      ) : isError ? (
        <ErrorState message={(error as Error)?.message} onRetry={() => void refetch()} />
      ) : items.length === 0 ? (
        <EmptyState title="No audit events" description="Actions will appear here as they happen." />
      ) : (
        <>
          <div className="rounded-xl border bg-card">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Action</TableHead>
                  <TableHead>User</TableHead>
                  <TableHead>Entity</TableHead>
                  <TableHead>IP</TableHead>
                  <TableHead>When</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.map((row) => (
                  <TableRow key={row.id}>
                    <TableCell>
                      <Badge variant="secondary" className="font-mono text-xs">
                        {row.action}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      {row.user ? (
                        <div>
                          <p className="font-medium">{row.user.name}</p>
                          <p className="text-xs text-muted-foreground">{row.user.email}</p>
                        </div>
                      ) : (
                        <span className="text-muted-foreground">System</span>
                      )}
                    </TableCell>
                    <TableCell className="text-sm text-muted-foreground">
                      {row.entityType ? `${row.entityType}${row.entityId ? ` · ${row.entityId.slice(0, 8)}…` : ''}` : '—'}
                    </TableCell>
                    <TableCell className="font-mono text-xs text-muted-foreground">
                      {row.ip ?? '—'}
                    </TableCell>
                    <TableCell className="text-muted-foreground">
                      {formatDistanceToNow(new Date(row.createdAt), { addSuffix: true })}
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
