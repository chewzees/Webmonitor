import { api, apiBlob } from '@/lib/api'
import type {
  AuditListParams,
  AuditLogRow,
  BulkAction,
  DashboardData,
  LogsListParams,
  MonitorLogRow,
  Paginated,
  PublicSiteStatusResponse,
  PublicStatusResponse,
  TelegramSettingsPublic,
  Website,
  WebsiteInput,
  WebsiteListParams,
  WebsiteStatsResponse,
  MultiPeriodUptime,
  UptimeStats,
} from '@/types/api'

export const apiClient = {
  health: () =>
    api<{ status: string; uptime: number; db: string; timestamp: string }>('/health'),

  publicStatus: () => api<PublicStatusResponse>('/public/status'),
  publicSite: (slug: string) => api<PublicSiteStatusResponse>(`/public/status/${slug}`),

  dashboard: () => api<DashboardData>('/dashboard'),

  websites: {
    list: (params?: WebsiteListParams) =>
      api<Paginated<Website>>('/websites', { params: params as Record<string, string | number | boolean | undefined | null> }),
    get: (id: string) => api<{ website: Website }>(`/websites/${id}`),
    create: (body: WebsiteInput) =>
      api<{ website: Website }>('/websites', { method: 'POST', body }),
    update: (id: string, body: Partial<WebsiteInput>) =>
      api<{ website: Website }>(`/websites/${id}`, { method: 'PUT', body }),
    remove: (id: string) => api<{ ok: boolean }>(`/websites/${id}`, { method: 'DELETE' }),
    check: (id: string) =>
      api<{ website: Website }>(`/websites/${id}/check`, { method: 'POST' }),
    checkAll: () => api<{ checked: number }>('/websites/check-all', { method: 'POST' }),
    bulk: (ids: string[], action: BulkAction) =>
      api<{ action: string; affected: number }>('/websites/bulk', {
        method: 'PATCH',
        body: { ids, action },
      }),
    uptime: (id: string, days = 90) =>
      api<{ websiteId: string; days: number; stats: UptimeStats; multi: MultiPeriodUptime | null }>(
        `/websites/${id}/uptime`,
        { params: { days } },
      ),
    stats: (id: string) => api<WebsiteStatsResponse>(`/websites/${id}/stats`),
    export: () => apiBlob('/websites/export'),
  },

  logs: {
    list: (params?: LogsListParams) =>
      api<Paginated<MonitorLogRow>>('/logs', {
        params: params as Record<string, string | number | boolean | undefined | null>,
      }),
    export: (params?: LogsListParams) =>
      apiBlob('/logs/export', params as Record<string, string | number | boolean | undefined | null>),
  },

  telegram: {
    get: () => api<{ settings: TelegramSettingsPublic }>('/settings/telegram'),
    update: (body: {
      botToken?: string
      chatId?: string
      enabled?: boolean
      notifyOnDown?: boolean
      notifyOnUp?: boolean
    }) => api<{ settings: TelegramSettingsPublic }>('/settings/telegram', { method: 'PUT', body }),
    test: () => api<{ ok: boolean }>('/settings/telegram/test', { method: 'POST' }),
  },

  audit: {
    list: (params?: AuditListParams) =>
      api<Paginated<AuditLogRow>>('/audit', {
        params: params as Record<string, string | number | boolean | undefined | null>,
      }),
  },
}
