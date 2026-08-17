import { useQuery } from '@tanstack/react-query'
import { formatDistanceToNow } from 'date-fns'
import { apiClient } from '@/lib/services'
import { StatusBanner } from '@/components/status/StatusBanner'
import { MonitorCard } from '@/components/status/MonitorCard'
import { EmptyState } from '@/components/shared/EmptyState'
import { ErrorState } from '@/components/shared/EmptyState'
import { Skeleton } from '@/components/ui/skeleton'

export function PublicStatusPage() {
  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['public-status'],
    queryFn: () => apiClient.publicStatus(),
    refetchInterval: 30_000,
  })

  if (isLoading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-36 w-full rounded-2xl" />
        <div className="grid gap-4 md:grid-cols-2">
          <Skeleton className="h-56 rounded-xl" />
          <Skeleton className="h-56 rounded-xl" />
        </div>
      </div>
    )
  }

  if (isError || !data) {
    return (
      <ErrorState
        message={(error as Error)?.message ?? 'Failed to load status'}
        onRetry={() => void refetch()}
      />
    )
  }

  return (
    <div className="space-y-8">
      <StatusBanner overall={data.overall} generatedAt={data.generatedAt} />
      {data.websites.length === 0 ? (
        <EmptyState
          title="No public monitors"
          description="Public websites will appear here once they are configured."
        />
      ) : (
        <div className="grid gap-4 md:grid-cols-2">
          {data.websites.map((site) => (
            <MonitorCard key={site.id} website={site} />
          ))}
        </div>
      )}
      <p className="text-center text-xs text-muted-foreground">
        Auto-refreshes every 30 seconds
        {data.generatedAt
          ? ` · Last update ${formatDistanceToNow(new Date(data.generatedAt), { addSuffix: true })}`
          : null}
      </p>
    </div>
  )
}
