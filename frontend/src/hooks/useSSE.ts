import { useEffect } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { apiBase } from '@/lib/api'

const INVALIDATE_PREFIXES = [
  ['dashboard'],
  ['websites'],
  ['logs'],
  ['public-status'],
  ['audit'],
]

export function useSSE(enabled: boolean) {
  const queryClient = useQueryClient()

  useEffect(() => {
    if (!enabled) return

    let es: EventSource | null = null
    let closed = false
    let retryTimer: number | undefined

    const invalidateAll = () => {
      for (const key of INVALIDATE_PREFIXES) {
        void queryClient.invalidateQueries({ queryKey: key })
      }
    }

    const connect = () => {
      if (closed) return
      es = new EventSource(`${apiBase()}/events`, { withCredentials: true })

      const onEvent = () => invalidateAll()

      es.addEventListener('connected', onEvent)
      es.addEventListener('check.completed', onEvent)
      es.addEventListener('status.changed', onEvent)
      es.addEventListener('website.updated', onEvent)

      es.onerror = () => {
        es?.close()
        es = null
        if (!closed) {
          retryTimer = window.setTimeout(connect, 5000)
        }
      }
    }

    connect()

    return () => {
      closed = true
      if (retryTimer) window.clearTimeout(retryTimer)
      es?.close()
    }
  }, [enabled, queryClient])
}
