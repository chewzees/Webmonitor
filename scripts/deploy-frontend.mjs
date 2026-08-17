import { cpSync, existsSync, mkdirSync, rmSync, readdirSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const dist = join(root, 'frontend', 'dist')
const publicDir = join(root, 'public')

if (!existsSync(dist)) {
  console.error('frontend/dist not found. Run: npm run build --prefix frontend')
  process.exit(1)
}

mkdirSync(publicDir, { recursive: true })

// Clear previous build artifacts but keep folder
for (const name of readdirSync(publicDir)) {
  rmSync(join(publicDir, name), { recursive: true, force: true })
}

cpSync(dist, publicDir, { recursive: true })
console.log('Deployed frontend build to public/')
console.log('Open: http://localhost/Webmonitor/')
