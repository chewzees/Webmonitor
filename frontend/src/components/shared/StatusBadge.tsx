import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/cn'
import type { MonitorStatus } from '@/types/api'

const STATUS_META: Record<
  MonitorStatus,
  { label: string; className: string; dot: string }
> = {
  UP: {
    label: 'Operational',
    className: 'border-transparent bg-success/15 text-success',
    dot: 'bg-success',
  },
  DOWN: {
    label: 'Down',
    className: 'border-transparent bg-destructive/15 text-destructive',
    dot: 'bg-destructive',
  },
  DEGRADED: {
    label: 'Degraded',
    className: 'border-transparent bg-warning/15 text-warning',
    dot: 'bg-warning',
  },
  UNKNOWN: {
    label: 'Unknown',
    className: 'border-transparent bg-muted text-muted-foreground',
    dot: 'bg-muted-foreground',
  },
}

interface StatusBadgeProps {
  status: MonitorStatus
  pulse?: boolean
  className?: string
  showLabel?: boolean
}

export function StatusBadge({ status, pulse = true, className, showLabel = true }: StatusBadgeProps) {
  const meta = STATUS_META[status] ?? STATUS_META.UNKNOWN
  return (
    <Badge variant="outline" className={cn('gap-1.5 font-medium', meta.className, className)}>
      <span className={cn('h-1.5 w-1.5 rounded-full', meta.dot, pulse && status !== 'UNKNOWN' && 'animate-pulse-dot')} />
      {showLabel ? meta.label : null}
    </Badge>
  )
}

export function statusLabel(status: MonitorStatus) {
  return STATUS_META[status]?.label ?? 'Unknown'
}
