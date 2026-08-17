import { Link } from 'react-router-dom'
import { Bell, ChevronRight, Shield } from 'lucide-react'
import { PageHeader } from '@/components/shared/PageHeader'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

const items = [
  {
    to: '/admin/settings/telegram',
    title: 'Telegram notifications',
    description: 'Configure bot token, chat ID, and alert preferences',
    icon: Bell,
  },
  {
    to: '/admin/audit',
    title: 'Audit log',
    description: 'Review administrative actions and security events',
    icon: Shield,
  },
]

export function SettingsPage() {
  return (
    <div className="space-y-6">
      <PageHeader title="Settings" description="Configure notifications and account preferences" />
      <div className="grid gap-4 md:grid-cols-2">
        {items.map((item) => (
          <Link key={item.to} to={item.to}>
            <Card className="h-full transition-shadow hover:shadow-md">
              <CardHeader className="flex flex-row items-start justify-between space-y-0">
                <div className="flex items-start gap-3">
                  <div className="rounded-lg bg-primary/10 p-2 text-primary">
                    <item.icon className="h-5 w-5" />
                  </div>
                  <div>
                    <CardTitle className="text-base">{item.title}</CardTitle>
                    <CardDescription className="mt-1">{item.description}</CardDescription>
                  </div>
                </div>
                <ChevronRight className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent />
            </Card>
          </Link>
        ))}
      </div>
    </div>
  )
}
