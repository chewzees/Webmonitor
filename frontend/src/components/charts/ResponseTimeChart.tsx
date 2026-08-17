import {
  Area,
  AreaChart,
  CartesianGrid,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { format } from 'date-fns'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

interface Point {
  checkedAt: string
  responseMs: number | null
}

export function ResponseTimeChart({
  data,
  title = 'Response time',
}: {
  data: Point[]
  title?: string
}) {
  const chartData = data.map((d) => ({
    time: d.checkedAt,
    ms: d.responseMs ?? 0,
    label: format(new Date(d.checkedAt), 'MMM d HH:mm'),
  }))

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">{title}</CardTitle>
      </CardHeader>
      <CardContent className="h-72">
        {chartData.length === 0 ? (
          <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
            No response time data yet
          </div>
        ) : (
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={chartData}>
              <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
              <XAxis dataKey="label" tick={{ fontSize: 11 }} minTickGap={32} />
              <YAxis tick={{ fontSize: 11 }} unit="ms" width={48} />
              <Tooltip
                contentStyle={{
                  borderRadius: 8,
                  border: '1px solid hsl(var(--border))',
                  background: 'hsl(var(--popover))',
                  color: 'hsl(var(--popover-foreground))',
                }}
                formatter={(value) => [`${value as number} ms`, 'Response']}
              />
              <Line
                type="monotone"
                dataKey="ms"
                stroke="hsl(var(--primary))"
                strokeWidth={2}
                dot={false}
                activeDot={{ r: 4 }}
              />
            </LineChart>
          </ResponsiveContainer>
        )}
      </CardContent>
    </Card>
  )
}

interface UptimePoint {
  label: string
  uptime: number
}

export function UptimeChart({
  data,
  title = 'Uptime',
}: {
  data: UptimePoint[]
  title?: string
}) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">{title}</CardTitle>
      </CardHeader>
      <CardContent className="h-72">
        {data.length === 0 ? (
          <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
            No uptime data yet
          </div>
        ) : (
          <ResponsiveContainer width="100%" height="100%">
            <AreaChart data={data}>
              <defs>
                <linearGradient id="uptimeFill" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="hsl(var(--primary))" stopOpacity={0.35} />
                  <stop offset="95%" stopColor="hsl(var(--primary))" stopOpacity={0.02} />
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
              <XAxis dataKey="label" tick={{ fontSize: 11 }} />
              <YAxis domain={[0, 100]} tick={{ fontSize: 11 }} unit="%" width={40} />
              <Tooltip
                contentStyle={{
                  borderRadius: 8,
                  border: '1px solid hsl(var(--border))',
                  background: 'hsl(var(--popover))',
                  color: 'hsl(var(--popover-foreground))',
                }}
                formatter={(value) => [`${Number(value).toFixed(2)}%`, 'Uptime']}
              />
              <Area
                type="monotone"
                dataKey="uptime"
                stroke="hsl(var(--primary))"
                fill="url(#uptimeFill)"
                strokeWidth={2}
              />
            </AreaChart>
          </ResponsiveContainer>
        )}
      </CardContent>
    </Card>
  )
}
