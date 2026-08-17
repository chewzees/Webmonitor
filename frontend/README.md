# WebMonitor Frontend

Production-ready React SPA for the WebMonitor SaaS.

## Stack

- React 18+ / TypeScript / Vite
- Tailwind CSS + shadcn-style UI
- React Router, TanStack Query, Recharts, sonner, next-themes

## Develop

```bash
cd frontend
npm install
npm run dev
```

App: http://localhost:5173  
API proxy: `/api` → `http://localhost:4000`

## Scripts

| Script | Description |
|--------|-------------|
| `npm run dev` | Start Vite dev server |
| `npm run build` | Typecheck + production build |
| `npm run preview` | Preview production build |
| `npm run lint` | Run oxlint |

## Docker

```bash
docker build -t webmonitor-frontend .
docker run -p 8080:80 webmonitor-frontend
```

Nginx proxies `/api` to the `backend` service (see `nginx.conf`).
