<?php
declare(strict_types=1);

/**
 * Route map: [METHOD, pattern, handler]
 * Patterns use {param} placeholders.
 *
 * @return list<array{0:string,1:string,2:callable}>
 */
return [
    ['GET',  '/api/health',                     [HealthController::class, 'check']],

    ['POST', '/api/auth/login',                 [AuthController::class, 'login']],
    ['POST', '/api/auth/logout',                [AuthController::class, 'logout']],
    ['GET',  '/api/auth/me',                    [AuthController::class, 'me']],
    ['GET',  '/api/auth/csrf',                  [AuthController::class, 'csrf']],

    ['GET',  '/api/public/status',              [PublicController::class, 'status']],
    ['GET',  '/api/public/status/{slug}',       [PublicController::class, 'siteStatus']],

    ['GET',  '/api/dashboard',                  [DashboardController::class, 'get']],

    ['GET',  '/api/websites',                   [WebsiteController::class, 'list']],
    ['POST', '/api/websites',                   [WebsiteController::class, 'create']],
    ['GET',  '/api/websites/export',            [WebsiteController::class, 'export']],
    ['GET',  '/api/websites/preview',           [WebsiteController::class, 'preview']],
    ['POST', '/api/websites/check-all',         [WebsiteController::class, 'checkAll']],
    ['PATCH','/api/websites/bulk',              [WebsiteController::class, 'bulk']],
    ['GET',  '/api/websites/{id}',              [WebsiteController::class, 'get']],
    ['PUT',  '/api/websites/{id}',              [WebsiteController::class, 'update']],
    ['DELETE','/api/websites/{id}',             [WebsiteController::class, 'delete']],
    ['POST', '/api/websites/{id}/check',        [WebsiteController::class, 'check']],
    ['GET',  '/api/websites/{id}/uptime',       [WebsiteController::class, 'uptime']],
    ['GET',  '/api/websites/{id}/stats',        [WebsiteController::class, 'stats']],

    ['GET',  '/api/logs',                       [LogController::class, 'list']],
    ['GET',  '/api/logs/export',                [LogController::class, 'export']],

    ['GET',  '/api/settings/telegram',          [TelegramController::class, 'get']],
    ['PUT',  '/api/settings/telegram',          [TelegramController::class, 'update']],
    ['POST', '/api/settings/telegram/test',     [TelegramController::class, 'test']],

    ['GET',  '/api/audit',                      [AuditController::class, 'list']],

    ['GET',  '/api/events',                     [EventsController::class, 'stream']],
];
