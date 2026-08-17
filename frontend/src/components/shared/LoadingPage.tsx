import { Loader2 } from 'lucide-react'
import { Skeleton } from '@/components/ui/skeleton'
import { cn } from '@/lib/cn'

export function LoadingPage({ label = 'Loading…', className }: { label?: string; className?: string }) {
  return (
    <div className={cn('flex min-h-[40vh] flex-col items-center justify-center gap-3 text-muted-foreground', className)}>
      <Loader2 className="h-6 w-6 animate-spin text-primary" />
      <p className="text-sm">{label}</p>
    </div>
  )
}

export function DashboardSkeleton() {
  return (
    <div className="space-y-6 page-enter">
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {Array.from({ length: 4 }).map((_, i) => (
          <Skeleton key={i} className="h-28 rounded-xl" />
        ))}
      </div>
      <div className="grid gap-4 lg:grid-cols-2">
        <Skeleton className="h-72 rounded-xl" />
        <Skeleton className="h-72 rounded-xl" />
      </div>
    </div>
  )
}

export function TableSkeleton({ rows = 6 }: { rows?: number }) {
  return (
    <div className="space-y-2">
      <Skeleton className="h-10 w-full" />
      {Array.from({ length: rows }).map((_, i) => (
        <Skeleton key={i} className="h-12 w-full" />
      ))}
    </div>
  )
}
