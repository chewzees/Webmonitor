import { Link } from 'react-router-dom'
import { formatDistanceToNow } from 'date-fns'
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react'
import { Checkbox } from '@/components/ui/checkbox'
import { Badge } from '@/components/ui/badge'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { formatMs } from '@/lib/utils'
import type { Website } from '@/types/api'
import { cn } from '@/lib/cn'

interface WebsiteTableProps {
  items: Website[]
  selected: Set<string>
  onToggle: (id: string) => void
  onToggleAll: (checked: boolean) => void
  sortBy: string
  sortOrder: 'asc' | 'desc'
  onSort: (column: string) => void
}

function SortIcon({ active, order }: { active: boolean; order: 'asc' | 'desc' }) {
  if (!active) return <ArrowUpDown className="h-3.5 w-3.5 opacity-40" />
  return order === 'asc' ? <ArrowUp className="h-3.5 w-3.5" /> : <ArrowDown className="h-3.5 w-3.5" />
}

export function WebsiteTable({
  items,
  selected,
  onToggle,
  onToggleAll,
  sortBy,
  sortOrder,
  onSort,
}: WebsiteTableProps) {
  const allSelected = items.length > 0 && items.every((i) => selected.has(i.id))

  const columns: Array<{ key: string; label: string; className?: string }> = [
    { key: 'name', label: 'Name' },
    { key: 'currentStatus', label: 'Status' },
    { key: 'lastResponseMs', label: 'Latency' },
    { key: 'lastCheckedAt', label: 'Last checked' },
    { key: 'intervalSeconds', label: 'Interval' },
  ]

  return (
    <div className="rounded-xl border bg-card">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead className="w-10">
              <Checkbox
                checked={allSelected}
                onCheckedChange={(v) => onToggleAll(Boolean(v))}
                aria-label="Select all"
              />
            </TableHead>
            {columns.map((col) => (
              <TableHead key={col.key}>
                <button
                  type="button"
                  className="inline-flex items-center gap-1 hover:text-foreground"
                  onClick={() => onSort(col.key)}
                >
                  {col.label}
                  <SortIcon active={sortBy === col.key} order={sortOrder} />
                </button>
              </TableHead>
            ))}
            <TableHead>Flags</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {items.map((site) => (
            <TableRow key={site.id} data-state={selected.has(site.id) ? 'selected' : undefined}>
              <TableCell>
                <Checkbox
                  checked={selected.has(site.id)}
                  onCheckedChange={() => onToggle(site.id)}
                  aria-label={`Select ${site.name}`}
                />
              </TableCell>
              <TableCell>
                <div className="min-w-0">
                  <Link
                    to={`/admin/websites/${site.id}`}
                    className={cn('font-medium hover:text-primary hover:underline')}
                  >
                    {site.name}
                  </Link>
                  <p className="truncate text-xs text-muted-foreground">{site.url}</p>
                </div>
              </TableCell>
              <TableCell>
                <div className="flex flex-wrap items-center gap-1">
                  <StatusBadge status={site.currentStatus} />
                  {site.isStale ? <Badge variant="warning">Stale</Badge> : null}
                </div>
              </TableCell>
              <TableCell className="tabular-nums">{formatMs(site.lastResponseMs)}</TableCell>
              <TableCell className="text-muted-foreground">
                {site.lastCheckedAt
                  ? formatDistanceToNow(new Date(site.lastCheckedAt), { addSuffix: true })
                  : '—'}
              </TableCell>
              <TableCell className="tabular-nums">{site.intervalSeconds}s</TableCell>
              <TableCell>
                <div className="flex flex-wrap gap-1">
                  <Badge variant={site.isActive ? 'success' : 'muted'}>
                    {site.isActive ? 'Active' : 'Paused'}
                  </Badge>
                  {site.isPublic ? <Badge variant="secondary">Public</Badge> : null}
                </div>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  )
}
