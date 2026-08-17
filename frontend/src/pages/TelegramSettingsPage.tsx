import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Send } from 'lucide-react'
import { toast } from 'sonner'
import { apiClient } from '@/lib/services'
import { ApiError } from '@/lib/api'
import { PageHeader } from '@/components/shared/PageHeader'
import { ErrorState } from '@/components/shared/EmptyState'
import { LoadingPage } from '@/components/shared/LoadingPage'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

export function TelegramSettingsPage() {
  const queryClient = useQueryClient()
  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['telegram'],
    queryFn: () => apiClient.telegram.get(),
  })

  const [botToken, setBotToken] = useState('')
  const [chatId, setChatId] = useState('')
  const [enabled, setEnabled] = useState(false)
  const [notifyOnDown, setNotifyOnDown] = useState(true)
  const [notifyOnUp, setNotifyOnUp] = useState(true)

  useEffect(() => {
    if (!data?.settings) return
    setChatId(data.settings.chatId ?? '')
    setEnabled(data.settings.enabled)
    setNotifyOnDown(data.settings.notifyOnDown)
    setNotifyOnUp(data.settings.notifyOnUp)
  }, [data])

  const save = useMutation({
    mutationFn: () =>
      apiClient.telegram.update({
        botToken: botToken.trim() || undefined,
        chatId,
        enabled,
        notifyOnDown,
        notifyOnUp,
      }),
    onSuccess: () => {
      toast.success('Telegram settings saved')
      setBotToken('')
      void queryClient.invalidateQueries({ queryKey: ['telegram'] })
    },
    onError: (err) => toast.error(err instanceof ApiError ? err.message : 'Save failed'),
  })

  const test = useMutation({
    mutationFn: () => apiClient.telegram.test(),
    onSuccess: () => toast.success('Test message sent'),
    onError: (err) => toast.error(err instanceof ApiError ? err.message : 'Test failed'),
  })

  if (isLoading) return <LoadingPage />
  if (isError || !data) {
    return <ErrorState message={(error as Error)?.message} onRetry={() => void refetch()} />
  }

  const settings = data.settings

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <Button variant="ghost" size="sm" asChild className="-ml-2">
        <Link to="/admin/settings">
          <ArrowLeft className="h-4 w-4" />
          Settings
        </Link>
      </Button>
      <PageHeader
        title="Telegram"
        description="Receive downtime and recovery alerts in Telegram"
      />

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Bot configuration</CardTitle>
          <CardDescription>
            Current token: {settings.hasBotToken ? settings.botTokenMasked : 'Not set'}
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-5">
          <div className="space-y-2">
            <Label htmlFor="botToken">Bot token</Label>
            <Input
              id="botToken"
              type="password"
              placeholder={settings.hasBotToken ? 'Leave blank to keep current token' : '123456:ABC…'}
              value={botToken}
              onChange={(e) => setBotToken(e.target.value)}
              autoComplete="off"
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="chatId">Chat ID</Label>
            <Input
              id="chatId"
              value={chatId}
              onChange={(e) => setChatId(e.target.value)}
              placeholder="-100…"
            />
          </div>

          <div className="space-y-3 rounded-lg border p-4">
            <div className="flex items-center justify-between">
              <div>
                <Label>Enabled</Label>
                <p className="text-xs text-muted-foreground">Send Telegram notifications</p>
              </div>
              <Switch checked={enabled} onCheckedChange={setEnabled} />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <Label>Notify on down</Label>
                <p className="text-xs text-muted-foreground">Alert when a monitor goes down</p>
              </div>
              <Switch checked={notifyOnDown} onCheckedChange={setNotifyOnDown} />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <Label>Notify on recovery</Label>
                <p className="text-xs text-muted-foreground">Alert when a monitor recovers</p>
              </div>
              <Switch checked={notifyOnUp} onCheckedChange={setNotifyOnUp} />
            </div>
          </div>

          <div className="flex flex-wrap gap-2">
            <Button onClick={() => save.mutate()} disabled={save.isPending}>
              {save.isPending ? 'Saving…' : 'Save settings'}
            </Button>
            <Button
              variant="outline"
              onClick={() => test.mutate()}
              disabled={test.isPending || !settings.hasBotToken}
            >
              <Send className="h-4 w-4" />
              {test.isPending ? 'Sending…' : 'Send test'}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
