import { useQuery, useQueryClient, useMutation } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { api, setCsrfToken } from '@/lib/api'
import type { SessionUser } from '@/types/api'

export const authKeys = {
  me: ['auth', 'me'] as const,
}

export function useAuth() {
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  const meQuery = useQuery({
    queryKey: authKeys.me,
    queryFn: async () => {
      try {
        const data = await api<{ user: SessionUser }>('/auth/me')
        return data.user
      } catch (err) {
        const status = (err as { status?: number }).status
        if (status === 401) return null
        throw err
      }
    },
    retry: false,
    staleTime: 60_000,
  })

  const loginMutation = useMutation({
    mutationFn: async (payload: { email: string; password: string }) => {
      const data = await api<{ user: SessionUser; csrfToken: string }>('/auth/login', {
        method: 'POST',
        body: payload,
        skipCsrf: true,
      })
      setCsrfToken(data.csrfToken)
      return data.user
    },
    onSuccess: (user) => {
      queryClient.setQueryData(authKeys.me, user)
      navigate('/admin', { replace: true })
    },
  })

  const logoutMutation = useMutation({
    mutationFn: async () => {
      await api<{ ok: boolean }>('/auth/logout', { method: 'POST' })
    },
    onSuccess: () => {
      setCsrfToken(null)
      queryClient.setQueryData(authKeys.me, null)
      queryClient.clear()
      navigate('/login', { replace: true })
    },
  })

  return {
    user: meQuery.data ?? null,
    isLoading: meQuery.isLoading,
    isAuthenticated: Boolean(meQuery.data),
    login: loginMutation.mutateAsync,
    loginError: loginMutation.error,
    isLoggingIn: loginMutation.isPending,
    logout: logoutMutation.mutateAsync,
    isLoggingOut: logoutMutation.isPending,
    refetch: meQuery.refetch,
  }
}
