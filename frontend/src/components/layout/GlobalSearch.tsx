import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Search } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { useDebounce } from '@/hooks/useDebounce'
import { apiClient } from '@/lib/services'
import { cn } from '@/lib/cn'

export function GlobalSearch({ className }: { className?: string }) {
  const [query, setQuery] = useState('')
  const [open, setOpen] = useState(false)
  const debounced = useDebounce(query, 250)
  const navigate = useNavigate()

  const { data } = useQuery({
    queryKey: ['websites', 'search', debounced],
    queryFn: () => apiClient.websites.list({ search: debounced, limit: 8 }),
    enabled: debounced.trim().length >= 1,
  })

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault()
        document.getElementById('global-search')?.focus()
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [])

  const items = data?.items ?? []

  return (
    <div className={cn('relative w-full max-w-md', className)}>
      <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
      <Input
        id="global-search"
        value={query}
        onChange={(e) => {
          setQuery(e.target.value)
          setOpen(true)
        }}
        onFocus={() => setOpen(true)}
        onBlur={() => window.setTimeout(() => setOpen(false), 150)}
        placeholder="Search websites… ⌘K"
        className="pl-9"
      />
      {open && debounced && (
        <div className="absolute z-50 mt-1 w-full overflow-hidden rounded-lg border bg-popover shadow-lg">
          {items.length === 0 ? (
            <p className="px-3 py-3 text-sm text-muted-foreground">No websites found</p>
          ) : (
            <ul className="max-h-72 overflow-auto py-1">
              {items.map((site) => (
                <li key={site.id}>
                  <button
                    type="button"
                    className="flex w-full flex-col items-start px-3 py-2 text-left text-sm hover:bg-accent"
                    onMouseDown={(e) => e.preventDefault()}
                    onClick={() => {
                      setQuery('')
                      setOpen(false)
                      navigate(`/admin/websites/${site.id}`)
                    }}
                  >
                    <span className="font-medium">{site.name}</span>
                    <span className="truncate text-xs text-muted-foreground">{site.url}</span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  )
}
