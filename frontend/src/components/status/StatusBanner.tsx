import { cn } from '@/lib/cn'
import type { MonitorStatus } from '@/types/api'
import { statusLabel } from '@/components/shared/StatusBadge'

interface StatusBannerProps {
  overall: MonitorStatus
  generatedAt?: string
  className?: string
}

const BANNER: Record<MonitorStatus, { title: string; subtitle: string; className: string }> = {
  UP: {
    title: 'All Systems Operational',
    subtitle: 'All public monitors are responding normally.',
    className: 'border-success/30 bg-success/10 text-success',
  },
  DEGRADED: {
    title: 'Partial Outage',
    subtitle: 'Some services are experiencing degraded performance.',
    className: 'border-warning/30 bg-warning/10 text-warning',
  },
  DOWN: {
    title: 'Major Outage',
    subtitle: 'One or more services are currently unavailable.',
    className: 'border-destructive/30 bg-destructive/10 text-destructive',
  },
  UNKNOWN: {
    title: 'Status Unknown',
    subtitle: 'Waiting for the first monitoring checks to complete.',
    className: 'border-border bg-muted text-muted-foreground',
  },
}

export function StatusBanner({ overall, generatedAt, className }: StatusBannerProps) {
  const meta = BANNER[overall] ?? BANNER.UNKNOWN
  return (
    <div className={cn('rounded-2xl border px-5 py-6 shadow-sm', meta.className, className)}>
      <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.14em] opacity-80">System status</p>
          <h1 className="mt-1 text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
            {meta.title}
          </h1>
          <p className="mt-1 text-sm text-foreground/70">{meta.subtitle}</p>
        </div>
        <div className="text-xs text-foreground/60">
          Overall: {statusLabel(overall)}
          {generatedAt ? ` · Updated ${new Date(generatedAt).toLocaleString()}` : null}
        </div>
      </div>
    </div>
  )
}
