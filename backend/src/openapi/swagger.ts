import swaggerJsdoc from 'swagger-jsdoc';
import { env } from '../config/env';

const options: swaggerJsdoc.Options = {
  definition: {
    openapi: '3.0.3',
    info: {
      title: 'WebMonitor API',
      version: '1.0.0',
      description: 'Website monitoring system API',
    },
    servers: [
      {
        url: `http://localhost:${env.PORT}`,
        description: 'Local',
      },
    ],
    components: {
      securitySchemes: {
        cookieAuth: {
          type: 'apiKey',
          in: 'cookie',
          name: 'webmonitor.sid',
        },
      },
      schemas: {
        Error: {
          type: 'object',
          properties: {
            error: {
              type: 'object',
              properties: {
                code: { type: 'string' },
                message: { type: 'string' },
                details: {},
              },
            },
          },
        },
        Website: {
          type: 'object',
          properties: {
            id: { type: 'string' },
            name: { type: 'string' },
            url: { type: 'string' },
            slug: { type: 'string' },
            currentStatus: {
              type: 'string',
              enum: ['UP', 'DOWN', 'UNKNOWN', 'DEGRADED'],
            },
            isActive: { type: 'boolean' },
            isPublic: { type: 'boolean' },
          },
        },
      },
    },
    tags: [
      { name: 'Auth' },
      { name: 'Health' },
      { name: 'Public' },
      { name: 'Dashboard' },
      { name: 'Websites' },
      { name: 'Logs' },
      { name: 'Telegram' },
      { name: 'Audit' },
      { name: 'Events' },
    ],
    paths: {
      '/api/health': {
        get: {
          tags: ['Health'],
          summary: 'Health check',
          responses: {
            200: { description: 'Service healthy' },
            503: { description: 'Service degraded' },
          },
        },
      },
      '/api/public/status': {
        get: {
          tags: ['Public'],
          summary: 'Public status overview',
          responses: { 200: { description: 'Overall public status' } },
        },
      },
      '/api/public/status/{slug}': {
        get: {
          tags: ['Public'],
          summary: 'Public site detail',
          parameters: [
            {
              name: 'slug',
              in: 'path',
              required: true,
              schema: { type: 'string' },
            },
          ],
          responses: {
            200: { description: 'Site status detail' },
            404: { description: 'Not found' },
          },
        },
      },
      '/api/dashboard': {
        get: {
          tags: ['Dashboard'],
          summary: 'Dashboard widgets',
          security: [{ cookieAuth: [] }],
          responses: { 200: { description: 'Dashboard data' } },
        },
      },
      '/api/websites': {
        get: {
          tags: ['Websites'],
          summary: 'List websites',
          security: [{ cookieAuth: [] }],
          responses: { 200: { description: 'Paginated websites' } },
        },
        post: {
          tags: ['Websites'],
          summary: 'Create website',
          security: [{ cookieAuth: [] }],
          responses: { 201: { description: 'Created' } },
        },
      },
      '/api/websites/{id}': {
        get: {
          tags: ['Websites'],
          summary: 'Get website',
          security: [{ cookieAuth: [] }],
          parameters: [
            {
              name: 'id',
              in: 'path',
              required: true,
              schema: { type: 'string' },
            },
          ],
          responses: { 200: { description: 'Website' } },
        },
        put: {
          tags: ['Websites'],
          summary: 'Update website',
          security: [{ cookieAuth: [] }],
          parameters: [
            {
              name: 'id',
              in: 'path',
              required: true,
              schema: { type: 'string' },
            },
          ],
          responses: { 200: { description: 'Updated' } },
        },
        delete: {
          tags: ['Websites'],
          summary: 'Delete website',
          security: [{ cookieAuth: [] }],
          parameters: [
            {
              name: 'id',
              in: 'path',
              required: true,
              schema: { type: 'string' },
            },
          ],
          responses: { 200: { description: 'Deleted' } },
        },
      },
      '/api/logs': {
        get: {
          tags: ['Logs'],
          summary: 'List monitor logs',
          security: [{ cookieAuth: [] }],
          responses: { 200: { description: 'Paginated logs' } },
        },
      },
      '/api/settings/telegram': {
        get: {
          tags: ['Telegram'],
          summary: 'Get Telegram settings',
          security: [{ cookieAuth: [] }],
          responses: { 200: { description: 'Settings (token masked)' } },
        },
        put: {
          tags: ['Telegram'],
          summary: 'Update Telegram settings',
          security: [{ cookieAuth: [] }],
          responses: { 200: { description: 'Updated settings' } },
        },
      },
      '/api/audit': {
        get: {
          tags: ['Audit'],
          summary: 'List audit logs',
          security: [{ cookieAuth: [] }],
          responses: { 200: { description: 'Paginated audit logs' } },
        },
      },
      '/api/events': {
        get: {
          tags: ['Events'],
          summary: 'SSE event stream',
          security: [{ cookieAuth: [] }],
          responses: { 200: { description: 'text/event-stream' } },
        },
      },
    },
  },
  apis: ['./src/modules/**/*.ts', './dist/src/modules/**/*.js'],
};

export const swaggerSpec = swaggerJsdoc(options);
