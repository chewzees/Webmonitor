import { useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Download, Plus } from 'lucide-react'
import { toast } from 'sonner'
import { apiClient } from '@/lib/services'
import { ApiError } from '@/lib/api'
import { downloadBlob } from '@/lib/utils'
import { useDebounce } from '@/hooks/useDebounce'
import { PageHeader } from '@/components/shared/PageHeader'
import { EmptyState, ErrorState } from '@/components/shared/EmptyState'
import { TableSkeleton } from '@/components/shared/LoadingPage'
import { ConfirmDialog } from '@/components/shared/ConfirmDialog'
import { WebsiteTable } from '@/components/websites/WebsiteTable'
import { BulkActions } from '@/components/websites/BulkActions'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import type { BulkAction, MonitorStatus } from '@/types/api'

export function WebsitesPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState<MonitorStatus | 'all'>('all')
  const [isActive, setIsActive] = useState<'all' | 'true' | 'false'>('all')
  const [sortBy, setSortBy] = useState('name')
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('asc')
  const [selected, setSelected] = useState<Set<string>>(new Set())
  const [pendingBulk, setPendingBulk] = useState<BulkAction | null>(null)
  const debouncedSearch = useDebounce(search, 300)

  const params = useMemo(
    () => ({
      page,
      limit: 20,
      search: debouncedSearch || undefined,
      status: status === 'all' ? undefined : status,
      isActive: isActive === 'all' ? undefined : isActive,
      sortBy,
      sortOrder,
    }),
    [page, debouncedSearch, status, isActive, sortBy, sortOrder],
  )

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['websites', params],
    queryFn: () => apiClient.websites.list(params),
  })

  const bulkMutation = useMutation({
    mutationFn: ({ ids, action }: { ids: string[]; action: BulkAction }) =>
      apiClient.websites.bulk(ids, action),
    onSuccess: (res) => {
      toast.success(`${res.action}: ${res.affected} affected`)
      setSelected(new Set())
      setPendingBulk(null)
      void queryClient.invalidateQueries({ queryKey: ['websites'] })
      void queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    },
    onError: (err) => toast.error(err instanceof ApiError ? err.message : 'Bulk action failed'),
  })

  function onSort(column: string) {
    if (sortBy === column) {
      setSortOrder((o) => (o === 'asc' ? 'desc' : 'asc'))
    } else {
      setSortBy(column)
      setSortOrder('asc')
    }
  }

  function onBulk(action: BulkAction) {
    if (action === 'delete') {
      setPendingBulk('delete')
      return
    }
    bulkMutation.mutate({ ids: Array.from(selected), action })
  }

  async function exportCsv() {
    try {
      const blob = await apiClient.websites.export()
      downloadBlob(blob, 'websites.csv')
      toast.success('Export downloaded')
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : 'Export failed')
    }
  }

  const items = data?.items ?? []

  return (
    <div className="space-y-6">
      <PageHeader
        title="Websites"
        description="Manage monitors, intervals, and public visibility"
        actions={
          <>
            <Button variant="outline" onClick={() => void exportCsv()}>
              <Download className="h-4 w-4" />
              Export CSV
            </Button>
            <Button asChild>
              <Link to="/admin/websites/new">
                <Plus className="h-4 w-4" />
                Add website
              </Link>
            </Button>
          </>
        }
      />

      <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
        <Input
          placeholder="Search name, URL, or slug…"
          value={search}
          onChange={(e) => {
            setSearch(e.target.value)
            setPage(1)
          }}
          className="lg:max-w-sm"
        />
        <Select
          value={status}
          onValueChange={(v) => {
            setStatus(v as MonitorStatus | 'all')
            setPage(1)
          }}
        >
          <SelectTrigger className="w-full lg:w-40">
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
          value={isActive}
          onValueChange={(v) => {
            setIsActive(v as 'all' | 'true' | 'false')
            setPage(1)
          }}
        >
          <SelectTrigger className="w-full lg:w-40">
            <SelectValue placeholder="Active" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All</SelectItem>
            <SelectItem value="true">Active</SelectItem>
            <SelectItem value="false">Paused</SelectItem>
          </SelectContent>
        </Select>
        <BulkActions
          selectedCount={selected.size}
          onAction={onBulk}
          loading={bulkMutation.isPending}
        />
      </div>

      {isLoading ? (
        <TableSkeleton />
      ) : isError ? (
        <ErrorState message={(error as Error)?.message} onRetry={() => void refetch()} />
      ) : items.length === 0 ? (
        <EmptyState
          title="No websites yet"
          description="Create your first monitor to start tracking uptime."
          action={{ label: 'Add website', onClick: () => navigate('/admin/websites/new') }}
        />
      ) : (
        <>
          <WebsiteTable
            items={items}
            selected={selected}
            onToggle={(id) =>
              setSelected((prev) => {
                const next = new Set(prev)
                if (next.has(id)) next.delete(id)
                else next.add(id)
                return next
              })
            }
            onToggleAll={(checked) => {
              setSelected(checked ? new Set(items.map((i) => i.id)) : new Set())
            }}
            sortBy={sortBy}
            sortOrder={sortOrder}
            onSort={onSort}
          />
          <div className="flex items-center justify-between text-sm text-muted-foreground">
            <span>
              Page {data?.page ?? 1} of {data?.totalPages ?? 1} · {data?.total ?? 0} total
            </span>
            <div className="flex gap-2">
              <Button
                variant="outline"
                size="sm"
                disabled={page <= 1}
                onClick={() => setPage((p) => p - 1)}
              >
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

      <ConfirmDialog
        open={pendingBulk === 'delete'}
        onOpenChange={(open) => !open && setPendingBulk(null)}
        title="Delete selected websites?"
        description={`This will permanently delete ${selected.size} website${selected.size === 1 ? '' : 's'} and their logs.`}
        confirmLabel="Delete"
        loading={bulkMutation.isPending}
        onConfirm={() =>
          bulkMutation.mutate({ ids: Array.from(selected), action: 'delete' })
        }
      />
    </div>
  )
}
