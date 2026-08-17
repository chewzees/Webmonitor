import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { PublicLayout } from '@/components/layout/PublicLayout'
import { AdminLayout } from '@/components/layout/AdminLayout'
import { ProtectedRoute } from '@/components/layout/ProtectedRoute'
import { PublicStatusPage } from '@/pages/PublicStatusPage'
import { PublicSiteStatusPage } from '@/pages/PublicSiteStatusPage'
import { LoginPage } from '@/pages/LoginPage'
import { DashboardPage } from '@/pages/DashboardPage'
import { WebsitesPage } from '@/pages/WebsitesPage'
import { WebsiteCreatePage } from '@/pages/WebsiteCreatePage'
import { WebsiteDetailPage } from '@/pages/WebsiteDetailPage'
import { WebsiteEditPage } from '@/pages/WebsiteEditPage'
import { LogsPage } from '@/pages/LogsPage'
import { SettingsPage } from '@/pages/SettingsPage'
import { TelegramSettingsPage } from '@/pages/TelegramSettingsPage'
import { AuditPage } from '@/pages/AuditPage'
import { ManualPage } from '@/pages/ManualPage'

const routerBasename = (() => {
  const base = import.meta.env.BASE_URL.replace(/\/$/, '')
  return base === '' ? undefined : base
})()

export default function App() {
  return (
    <BrowserRouter basename={routerBasename}>
      <Routes>
        {/* Root opens the admin dashboard (login redirect if needed) */}
        <Route path="/" element={<Navigate to="/admin" replace />} />

        <Route element={<PublicLayout />}>
          <Route path="/status" element={<PublicStatusPage />} />
          <Route path="/status/:slug" element={<PublicSiteStatusPage />} />
        </Route>

        <Route path="/login" element={<LoginPage />} />
        <Route path="/manual" element={<ManualPage />} />

        <Route element={<ProtectedRoute />}>
          <Route path="/admin" element={<AdminLayout />}>
            <Route index element={<DashboardPage />} />
            <Route path="websites" element={<WebsitesPage />} />
            <Route path="websites/new" element={<WebsiteCreatePage />} />
            <Route path="websites/:id" element={<WebsiteDetailPage />} />
            <Route path="websites/:id/edit" element={<WebsiteEditPage />} />
            <Route path="logs" element={<LogsPage />} />
            <Route path="settings" element={<SettingsPage />} />
            <Route path="settings/telegram" element={<TelegramSettingsPage />} />
            <Route path="audit" element={<AuditPage />} />
          </Route>
        </Route>

        <Route path="*" element={<Navigate to="/admin" replace />} />
      </Routes>
    </BrowserRouter>
  )
}
