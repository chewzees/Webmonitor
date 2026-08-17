import { cn } from '@/lib/cn'
import type { UptimeSegment } from '@/types/api'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'

interface UptimeBarProps {
  segments: UptimeSegment[]
  dates?: string[]
  className?: string
  label?: string
}

const COLORS: Record<UptimeSegment, string> = {
  up: 'bg-success',
  down: 'bg-destructive',
  degraded: 'bg-warning',
  unknown: 'bg-muted-foreground/40',
  empty: 'bg-muted',
}

/** Build a Better Stack-style 90-day bar from an uptime percentage. */
export function segmentsFromUptimePercent(uptimePercent: number, days = 90): UptimeSegment[] {
  const clamped = Math.max(0, Math.min(100, uptimePercent))
  const downDays = Math.round(((100 - clamped) / 100) * days)
  const segments: UptimeSegment[] = Array.from({ length: days }, () => 'up')
  if (downDays > 0) {
    const step = days / downDays
    for (let i = 0; i < downDays; i++) {
      const idx = Math.min(days - 1, Math.floor(i * step + step / 2))
      segments[idx] = 'down'
    }
  }
  return segments
}

export function UptimeBar({ segments, dates, className, label = '90-day history' }: UptimeBarProps) {
  return (
    <div className={cn('space-y-1.5', className)}>
      <TooltipProvider delayDuration={100}>
        <div className="flex h-8 gap-px overflow-hidden rounded-md">
          {segments.map((seg, i) => (
            <Tooltip key={dates?.[i] ?? i}>
              <TooltipTrigger asChild>
                <div
                  className={cn('h-full min-w-0 flex-1 transition-opacity hover:opacity-80', COLORS[seg])}
                />
              </TooltipTrigger>
              <TooltipContent>
                {dates?.[i] ?? `Day ${i + 1}`}: {seg}
              </TooltipContent>
            </Tooltip>
          ))}
        </div>
      </TooltipProvider>
      <div className="flex justify-between text-[11px] text-muted-foreground">
        <span>{segments.length} days ago</span>
        <span>{label}</span>
        <span>Today</span>
      </div>
    </div>
  )
}
