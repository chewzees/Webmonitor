export type Role = 'ADMIN' | 'USER'
export type MonitorStatus = 'UP' | 'DOWN' | 'UNKNOWN' | 'DEGRADED'
export type HttpMethod = 'GET' | 'HEAD' | 'POST'

export interface ApiErrorBody {
  error: {
    code: string
    message: string
    details?: unknown
  }
}

export interface Paginated<T> {
  items: T[]
  total: number
  page: number
  limit: number
  totalPages: number
}

export interface UptimeStats {
  total: number
  up: number
  down: number
  degraded: number
  unknown: number
  uptimePercent: number
  avgResponseMs: number | null
}

export interface MultiPeriodUptime {
  h24: UptimeStats
  d7: UptimeStats
  d30: UptimeStats
  d90: UptimeStats
}

export interface Incident {
  id: string
  websiteId: string
  status: MonitorStatus
  startedAt: string
  resolvedAt: string | null
  summary: string | null
}

export interface SessionUser {
  id: string
  email: string
  name: string
  role: Role
}

export interface Website {
  id: string
  name: string
  url: string
  slug: string
  description: string | null
  method: HttpMethod
  intervalSeconds: number
  timeoutMs: number
  expectedStatus: number
  keyword: string | null
  headersJson: string | null
  isActive: boolean
  isPublic: boolean
  currentStatus: MonitorStatus
  lastCheckedAt: string | null
  lastResponseMs: number | null
  lastStatusCode: number | null
  lastError: string | null
  createdAt: string
  updatedAt: string
  isStale: boolean
}

export interface WebsiteInput {
  name: string
  url: string
  slug?: string
  description?: string | null
  method?: HttpMethod
  intervalSeconds?: number
  timeoutMs?: number
  expectedStatus?: number
  keyword?: string | null
  headersJson?: string | null
  isActive?: boolean
  isPublic?: boolean
}

export interface MonitorLog {
  id: string
  websiteId: string
  status: MonitorStatus
  statusCode: number | null
  responseMs: number | null
  errorMessage: string | null
  checkedAt: string
}

export interface MonitorLogRow extends MonitorLog {
  website: { id: string; name: string; slug: string; url: string }
}

export interface AuditLogRow {
  id: string
  userId: string | null
  action: string
  entityType: string | null
  entityId: string | null
  metadata: string | null
  ip: string | null
  userAgent: string | null
  createdAt: string
  user: { id: string; email: string; name: string } | null
}

export interface TelegramSettingsPublic {
  id: string
  botTokenMasked: string
  hasBotToken: boolean
  chatId: string
  enabled: boolean
  notifyOnDown: boolean
  notifyOnUp: boolean
  updatedAt: string
  createdAt: string
}

export interface DashboardData {
  totalSites: number
  statusCounts: { UP: number; DOWN: number; UNKNOWN: number; DEGRADED: number }
  avgResponseMs: number | null
  overallUptime24h: number
  recentIncidents: Array<
    Incident & { website: { id: string; name: string; slug: string } }
  >
  checksLast24h: number
  staleCount: number
}

export interface PublicStatusWebsite {
  id: string
  name: string
  slug: string
  url: string
  description: string | null
  currentStatus: MonitorStatus
  lastCheckedAt: string | null
  lastResponseMs: number | null
  lastStatusCode: number | null
  isStale: boolean
  uptime: { h24: number; d7: number; d30: number; d90: number }
  avgResponse: {
    h24: number | null
    d7: number | null
    d30: number | null
    d90: number | null
  }
  history: Array<{ date: string; segment: UptimeSegment }>
  openIncidents: Incident[]
}

export interface PublicStatusResponse {
  overall: MonitorStatus
  websites: PublicStatusWebsite[]
  generatedAt: string
}

export interface PublicSiteStatusResponse {
  website: {
    id: string
    name: string
    slug: string
    url: string
    description: string | null
    currentStatus: MonitorStatus
    lastCheckedAt: string | null
    lastResponseMs: number | null
    lastStatusCode: number | null
    isStale: boolean
  }
  uptime: MultiPeriodUptime
  sparkline: Array<{ t: string; ms: number | null; s: MonitorStatus }>
  history: Array<{ date: string; segment: UptimeSegment }>
  incidents: Incident[]
  generatedAt: string
}

export interface WebsiteStatsResponse {
  website: Website
  uptime: MultiPeriodUptime
  responseTimeSeries: Array<{
    checkedAt: string
    responseMs: number | null
    status: MonitorStatus
  }>
}

export interface WebsiteListParams {
  page?: number
  limit?: number
  search?: string
  status?: MonitorStatus | ''
  isActive?: 'true' | 'false' | ''
  sortBy?: string
  sortOrder?: 'asc' | 'desc'
}

export interface LogsListParams {
  page?: number
  limit?: number
  websiteId?: string
  status?: MonitorStatus | ''
  from?: string
  to?: string
  search?: string
  sortBy?: string
  sortOrder?: 'asc' | 'desc'
}

export interface AuditListParams {
  page?: number
  limit?: number
  action?: string
  userId?: string
  from?: string
  to?: string
}

export type BulkAction = 'activate' | 'deactivate' | 'delete' | 'check'

export type UptimeSegment = 'up' | 'down' | 'degraded' | 'unknown' | 'empty'
