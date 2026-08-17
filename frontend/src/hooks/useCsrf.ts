import { useCallback } from 'react'
import { ensureCsrf, getCsrfToken, setCsrfToken } from '@/lib/api'

export function useCsrf() {
  const refresh = useCallback(async () => {
    const token = await ensureCsrf()
    return token
  }, [])

  return {
    token: getCsrfToken(),
    refresh,
    setToken: setCsrfToken,
  }
}
