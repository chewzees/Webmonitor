import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { apiClient } from '@/lib/services'
import { ApiError } from '@/lib/api'
import { PageHeader } from '@/components/shared/PageHeader'
import { LoadingPage } from '@/components/shared/LoadingPage'
import { ErrorState } from '@/components/shared/EmptyState'
import { WebsiteForm } from '@/components/websites/WebsiteForm'
import { Card, CardContent } from '@/components/ui/card'
import type { WebsiteInput } from '@/types/api'

export function WebsiteEditPage() {
  const { id = '' } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['websites', id],
    queryFn: () => apiClient.websites.get(id),
    enabled: Boolean(id),
  })

  const update = useMutation({
    mutationFn: (body: Partial<WebsiteInput>) => apiClient.websites.update(id, body),
    onSuccess: () => {
      toast.success('Website updated')
      void queryClient.invalidateQueries({ queryKey: ['websites'] })
      navigate(`/admin/websites/${id}`)
    },
    onError: (err) => toast.error(err instanceof ApiError ? err.message : 'Update failed'),
  })

  if (isLoading) return <LoadingPage />
  if (isError || !data) {
    return <ErrorState message={(error as Error)?.message} onRetry={() => void refetch()} />
  }

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <PageHeader title={`Edit ${data.website.name}`} description="Update monitor configuration" />
      <Card>
        <CardContent className="pt-6">
          <WebsiteForm
            initial={data.website}
            submitting={update.isPending}
            onCancel={() => navigate(`/admin/websites/${id}`)}
            onSubmit={async (values) => {
              await update.mutateAsync(values)
            }}
          />
        </CardContent>
      </Card>
    </div>
  )
}
