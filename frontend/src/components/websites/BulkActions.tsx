import { Button } from '@/components/ui/button'
import type { BulkAction } from '@/types/api'
import { Play, Power, PowerOff, Trash2 } from 'lucide-react'

interface BulkActionsProps {
  selectedCount: number
  onAction: (action: BulkAction) => void
  loading?: boolean
}

export function BulkActions({ selectedCount, onAction, loading }: BulkActionsProps) {
  if (selectedCount === 0) return null

  return (
    <div className="flex flex-wrap items-center gap-2 rounded-lg border bg-card px-3 py-2 shadow-sm">
      <span className="text-sm text-muted-foreground">{selectedCount} selected</span>
      <Button size="sm" variant="outline" disabled={loading} onClick={() => onAction('check')}>
        <Play className="h-3.5 w-3.5" />
        Check
      </Button>
      <Button size="sm" variant="outline" disabled={loading} onClick={() => onAction('activate')}>
        <Power className="h-3.5 w-3.5" />
        Activate
      </Button>
      <Button size="sm" variant="outline" disabled={loading} onClick={() => onAction('deactivate')}>
        <PowerOff className="h-3.5 w-3.5" />
        Deactivate
      </Button>
      <Button size="sm" variant="destructive" disabled={loading} onClick={() => onAction('delete')}>
        <Trash2 className="h-3.5 w-3.5" />
        Delete
      </Button>
    </div>
  )
}
