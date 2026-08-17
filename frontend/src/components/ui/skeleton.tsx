import type { HTMLAttributes } from 'react'
import { cn } from '@/lib/cn'

function Skeleton({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={cn('relative overflow-hidden rounded-md bg-muted', className)}
      {...props}
    >
      <div className="absolute inset-0 -translate-x-full animate-shimmer bg-gradient-to-r from-transparent via-white/20 to-transparent dark:via-white/5" />
    </div>
  )
}

export { Skeleton }
