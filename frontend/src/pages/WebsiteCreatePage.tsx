import { useNavigate } from 'react-router-dom'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { apiClient } from '@/lib/services'
import { ApiError } from '@/lib/api'
import { PageHeader } from '@/components/shared/PageHeader'
import { WebsiteForm } from '@/components/websites/WebsiteForm'
import { Card, CardContent } from '@/components/ui/card'
import type { WebsiteInput } from '@/types/api'

export function WebsiteCreatePage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const create = useMutation({
    mutationFn: (body: WebsiteInput) => apiClient.websites.create(body),
    onSuccess: (res) => {
      toast.success('Website created')
      void queryClient.invalidateQueries({ queryKey: ['websites'] })
      navigate(`/admin/websites/${res.website.id}`)
    },
    onError: (err) => toast.error(err instanceof ApiError ? err.message : 'Create failed'),
  })

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <PageHeader title="Add website" description="Configure a new uptime monitor" />
      <Card>
        <CardContent className="pt-6">
          <WebsiteForm
            submitting={create.isPending}
            onCancel={() => navigate('/admin/websites')}
            onSubmit={async (values) => {
              await create.mutateAsync(values)
            }}
          />
        </CardContent>
      </Card>
    </div>
  )
}
