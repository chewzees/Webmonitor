import { useEffect, useRef, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { Sparkles } from 'lucide-react'
import { toast } from 'sonner'
import { api, ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { slugify } from '@/lib/utils'
import type { Website, WebsiteInput } from '@/types/api'

const schema = z.object({
  name: z.string().min(1, 'Name is required').max(200),
  url: z.string().url('Enter a valid URL'),
  slug: z
    .string()
    .regex(/^[a-z0-9]+(?:-[a-z0-9]+)*$/, 'Use lowercase kebab-case')
    .optional()
    .or(z.literal('')),
  description: z.string().max(1000).optional().or(z.literal('')),
  method: z.enum(['GET', 'HEAD', 'POST']),
  intervalSeconds: z.number().min(30).max(86400),
  timeoutMs: z.number().min(1000).max(60000),
  expectedStatus: z.number().min(100).max(599),
  keyword: z.string().max(500).optional().or(z.literal('')),
  headersJson: z
    .string()
    .optional()
    .or(z.literal(''))
    .refine((val) => {
      if (!val) return true
      try {
        const parsed = JSON.parse(val)
        return parsed && typeof parsed === 'object' && !Array.isArray(parsed)
      } catch {
        return false
      }
    }, 'Headers must be a JSON object'),
  isActive: z.boolean(),
  isPublic: z.boolean(),
})

export type WebsiteFormValues = z.infer<typeof schema>

interface WebsiteFormProps {
  initial?: Website
  submitting?: boolean
  onSubmit: (values: WebsiteInput) => Promise<void> | void
  onCancel?: () => void
}

interface PreviewResponse {
  reachable: boolean
  message?: string
  suggested: {
    name: string
    slug: string
    description: string
    expectedStatus: number
    method: 'GET' | 'HEAD' | 'POST'
  }
  analysis: {
    purpose: string
    category: string
    summary: string
    confidence?: number
    signals?: string[]
    tech?: string[]
  }
}

export function WebsiteForm({ initial, submitting, onSubmit, onCancel }: WebsiteFormProps) {
  const isCreate = !initial
  const [analyzing, setAnalyzing] = useState(false)
  const [insight, setInsight] = useState<PreviewResponse['analysis'] | null>(null)
  const [statusText, setStatusText] = useState(
    isCreate
      ? 'Paste a link — we will detect what the site is for and fill the fields below.'
      : 'Smart autofill is available when creating a new website.',
  )
  const dirtyRef = useRef({ name: false, slug: false, description: false })
  const lastUrl = useRef('')

  const form = useForm<WebsiteFormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: initial?.name ?? '',
      url: initial?.url ?? '',
      slug: initial?.slug ?? '',
      description: initial?.description ?? '',
      method: initial?.method ?? 'GET',
      intervalSeconds: initial?.intervalSeconds ?? 60,
      timeoutMs: initial?.timeoutMs ?? 10000,
      expectedStatus: initial?.expectedStatus ?? 200,
      keyword: initial?.keyword ?? '',
      headersJson: (() => {
        if (!initial?.headersJson) return ''
        try {
          return JSON.stringify(JSON.parse(initial.headersJson), null, 2)
        } catch {
          return initial.headersJson
        }
      })(),
      isActive: initial?.isActive ?? true,
      isPublic: initial?.isPublic ?? true,
    },
  })

  const name = form.watch('name')
  const url = form.watch('url')

  useEffect(() => {
    if (!initial && name && !form.getFieldState('slug').isDirty && !dirtyRef.current.slug) {
      form.setValue('slug', slugify(name))
    }
  }, [name, initial, form])

  async function analyzeUrl(targetUrl: string, force = false) {
    if (!isCreate) return
    const trimmed = targetUrl.trim()
    if (!trimmed || (!force && trimmed === lastUrl.current)) return
    if (!/^https?:\/\/.+/i.test(trimmed)) {
      setStatusText('Enter a full URL starting with http:// or https://')
      return
    }

    lastUrl.current = trimmed
    setAnalyzing(true)
    setStatusText('Analyzing website…')
    try {
      const data = await api<PreviewResponse>('/websites/preview', {
        params: { url: trimmed },
      })
      const s = data.suggested
      if (!dirtyRef.current.name || !form.getValues('name')) form.setValue('name', s.name, { shouldDirty: false })
      if (!dirtyRef.current.slug || !form.getValues('slug')) form.setValue('slug', s.slug, { shouldDirty: false })
      if (!dirtyRef.current.description || !form.getValues('description')) {
        form.setValue('description', s.description, { shouldDirty: false })
      }
      if (form.getValues('expectedStatus') === 200) {
        form.setValue('expectedStatus', s.expectedStatus)
      }
      form.setValue('method', s.method)
      setInsight(data.analysis)
      setStatusText(data.message || 'Ready — fields updated from page analysis.')
    } catch (err) {
      setInsight(null)
      setStatusText(err instanceof ApiError ? err.message : 'Could not analyze that URL.')
      toast.error(err instanceof ApiError ? err.message : 'Analyze failed')
    } finally {
      setAnalyzing(false)
    }
  }

  useEffect(() => {
    if (!isCreate) return
    const handle = window.setTimeout(() => {
      if (/^https?:\/\/.+\..+/i.test(url.trim())) {
        void analyzeUrl(url)
      }
    }, 700)
    return () => window.clearTimeout(handle)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [url, isCreate])

  const handleSubmit = form.handleSubmit(async (values) => {
    const payload: WebsiteInput = {
      name: values.name,
      url: values.url,
      slug: values.slug || undefined,
      description: values.description || null,
      method: values.method,
      intervalSeconds: values.intervalSeconds,
      timeoutMs: values.timeoutMs,
      expectedStatus: values.expectedStatus,
      keyword: values.keyword || null,
      headersJson: values.headersJson || null,
      isActive: values.isActive,
      isPublic: values.isPublic,
    }
    await onSubmit(payload)
  })

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      <div className="grid gap-4 sm:grid-cols-2">
        <div className="space-y-2 sm:col-span-2">
          <Label htmlFor="url">URL</Label>
          <div className="flex flex-col gap-2 sm:flex-row">
            <Input
              id="url"
              placeholder="https://example.com"
              className="flex-1"
              {...form.register('url')}
              onPaste={() => {
                window.setTimeout(() => {
                  void analyzeUrl(form.getValues('url'), true)
                }, 50)
              }}
            />
            <Button
              type="button"
              variant="outline"
              disabled={!isCreate || analyzing}
              onClick={() => void analyzeUrl(form.getValues('url'), true)}
            >
              <Sparkles className="h-4 w-4" />
              {analyzing ? 'Analyzing…' : 'Analyze'}
            </Button>
          </div>
          <p className="text-xs text-muted-foreground">{statusText}</p>
          {form.formState.errors.url ? (
            <p className="text-xs text-destructive">{form.formState.errors.url.message}</p>
          ) : null}
        </div>

        {insight ? (
          <div className="sm:col-span-2 rounded-xl border border-border/70 bg-primary/5 px-4 py-3">
            <div className="flex flex-wrap items-center gap-2">
              <p className="text-xs font-semibold uppercase tracking-wide text-primary">
                {insight.category} · {insight.purpose}
              </p>
              {typeof insight.confidence === 'number' ? (
                <span className="rounded-full border border-border/70 bg-card px-2 py-0.5 text-[11px] font-bold text-primary">
                  {insight.confidence}% confidence
                </span>
              ) : null}
            </div>
            <p className="mt-1 text-sm text-muted-foreground">{insight.summary}</p>
            {(insight.signals?.length || insight.tech?.length) ? (
              <div className="mt-2 flex flex-wrap gap-1.5">
                {(insight.signals ?? []).slice(0, 6).map((s) => (
                  <span
                    key={s}
                    className="rounded-full border border-border/70 bg-card px-2 py-0.5 text-[11px] font-medium text-muted-foreground"
                  >
                    {s.replace(/^(path|copy|jsonld|format|ctype|sector|auth|cms|stack|fallback):/, '')}
                  </span>
                ))}
                {(insight.tech ?? []).slice(0, 4).map((t) => (
                  <span
                    key={t}
                    className="rounded-full border border-primary/30 bg-card px-2 py-0.5 text-[11px] font-semibold text-primary"
                  >
                    {t}
                  </span>
                ))}
              </div>
            ) : null}
          </div>
        ) : null}

        <div className="space-y-2 sm:col-span-2">
          <Label htmlFor="name">Name</Label>
          <Input
            id="name"
            {...form.register('name', {
              onChange: () => {
                dirtyRef.current.name = true
              },
            })}
          />
          {form.formState.errors.name ? (
            <p className="text-xs text-destructive">{form.formState.errors.name.message}</p>
          ) : null}
        </div>
        <div className="space-y-2">
          <Label htmlFor="slug">Slug</Label>
          <Input
            id="slug"
            {...form.register('slug', {
              onChange: () => {
                dirtyRef.current.slug = true
              },
            })}
          />
          {form.formState.errors.slug ? (
            <p className="text-xs text-destructive">{form.formState.errors.slug.message}</p>
          ) : null}
        </div>
        <div className="space-y-2">
          <Label>Method</Label>
          <Select
            value={form.watch('method')}
            onValueChange={(v) => form.setValue('method', v as 'GET' | 'HEAD' | 'POST')}
          >
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="GET">GET</SelectItem>
              <SelectItem value="HEAD">HEAD</SelectItem>
              <SelectItem value="POST">POST</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-2 sm:col-span-2">
          <Label htmlFor="description">Description</Label>
          <Textarea
            id="description"
            rows={3}
            {...form.register('description', {
              onChange: () => {
                dirtyRef.current.description = true
              },
            })}
          />
        </div>
        <div className="space-y-2">
          <Label htmlFor="intervalSeconds">Interval (seconds)</Label>
          <Input
            id="intervalSeconds"
            type="number"
            {...form.register('intervalSeconds', { valueAsNumber: true })}
          />
        </div>
        <div className="space-y-2">
          <Label htmlFor="timeoutMs">Timeout (ms)</Label>
          <Input id="timeoutMs" type="number" {...form.register('timeoutMs', { valueAsNumber: true })} />
        </div>
        <div className="space-y-2">
          <Label htmlFor="expectedStatus">Expected status</Label>
          <Input
            id="expectedStatus"
            type="number"
            {...form.register('expectedStatus', { valueAsNumber: true })}
          />
        </div>
        <div className="space-y-2">
          <Label htmlFor="keyword">Keyword (optional)</Label>
          <Input id="keyword" {...form.register('keyword')} />
        </div>
        <div className="space-y-2 sm:col-span-2">
          <Label htmlFor="headersJson">Headers JSON</Label>
          <Textarea
            id="headersJson"
            rows={4}
            placeholder='{"Authorization":"Bearer …"}'
            className="font-mono text-xs"
            {...form.register('headersJson')}
          />
          {form.formState.errors.headersJson ? (
            <p className="text-xs text-destructive">{form.formState.errors.headersJson.message}</p>
          ) : null}
        </div>
        <div className="flex items-center justify-between rounded-lg border px-3 py-3">
          <div>
            <Label>Active</Label>
            <p className="text-xs text-muted-foreground">Include in monitoring schedule</p>
          </div>
          <Switch
            checked={form.watch('isActive')}
            onCheckedChange={(v) => form.setValue('isActive', v)}
          />
        </div>
        <div className="flex items-center justify-between rounded-lg border px-3 py-3">
          <div>
            <Label>Public</Label>
            <p className="text-xs text-muted-foreground">Show on public status page</p>
          </div>
          <Switch
            checked={form.watch('isPublic')}
            onCheckedChange={(v) => form.setValue('isPublic', v)}
          />
        </div>
      </div>
      <div className="flex justify-end gap-2">
        {onCancel ? (
          <Button type="button" variant="outline" onClick={onCancel}>
            Cancel
          </Button>
        ) : null}
        <Button type="submit" disabled={submitting}>
          {submitting ? 'Saving…' : initial ? 'Save changes' : 'Create website'}
        </Button>
      </div>
    </form>
  )
}
