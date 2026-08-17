<?php
declare(strict_types=1);

final class WebsiteController
{
    private static function meta(Request $request): array
    {
        return ['ip' => $request->ip(), 'userAgent' => $request->userAgent()];
    }

    public static function list(Request $request): void
    {
        Auth::requireUser();
        $q = $request->queryAll();
        Response::json(WebsiteService::list($q));
    }

    public static function get(Request $request): void
    {
        Auth::requireUser();
        Response::json(WebsiteService::get((string) $request->param('id')));
    }

    public static function create(Request $request): void
    {
        $user = Auth::requireUser();
        $data = Validator::validate($request->json(), [
            'name' => 'required|string|min:1|max:200',
            'url' => 'required|url',
            'slug' => 'optional|slug|max:100',
            'description' => 'nullable|string|max:1000',
            'method' => 'optional|in:GET,HEAD,POST',
            'intervalSeconds' => 'optional|int|min:30|max:86400',
            'timeoutMs' => 'optional|int|min:1000|max:60000',
            'expectedStatus' => 'optional|int|min:100|max:599',
            'keyword' => 'nullable|string|max:500',
            'headersJson' => 'nullable|json_string',
            'isActive' => 'optional|bool',
            'isPublic' => 'optional|bool',
        ]);
        Response::json(WebsiteService::create($data, $user['id'], self::meta($request)), 201);
    }

    public static function update(Request $request): void
    {
        $user = Auth::requireUser();
        $data = Validator::validate($request->json(), [
            'name' => 'optional|string|min:1|max:200',
            'url' => 'optional|url',
            'slug' => 'optional|slug|max:100',
            'description' => 'nullable|string|max:1000',
            'method' => 'optional|in:GET,HEAD,POST',
            'intervalSeconds' => 'optional|int|min:30|max:86400',
            'timeoutMs' => 'optional|int|min:1000|max:60000',
            'expectedStatus' => 'optional|int|min:100|max:599',
            'keyword' => 'nullable|string|max:500',
            'headersJson' => 'nullable|json_string',
            'isActive' => 'optional|bool',
            'isPublic' => 'optional|bool',
        ], true);
        Response::json(WebsiteService::update((string) $request->param('id'), $data, $user['id'], self::meta($request)));
    }

    public static function delete(Request $request): void
    {
        $user = Auth::requireUser();
        WebsiteService::delete((string) $request->param('id'), $user['id'], self::meta($request));
        Response::json(['ok' => true]);
    }

    public static function check(Request $request): void
    {
        Auth::requireUser();
        Response::json(WebsiteService::checkNow((string) $request->param('id')));
    }

    public static function checkAll(Request $request): void
    {
        Auth::requireUser();
        Response::json(WebsiteService::checkAll());
    }

    public static function bulk(Request $request): void
    {
        $user = Auth::requireUser();
        $body = $request->json();
        $data = Validator::validate($body, [
            'ids' => 'required|array',
            'action' => 'required|in:activate,deactivate,delete,check',
        ]);
        if (!is_array($data['ids']) || $data['ids'] === []) {
            throw new AppException('ids must be a non-empty array', 400, 'VALIDATION_ERROR');
        }
        Response::json(WebsiteService::bulkAction($data, $user['id'], self::meta($request)));
    }

    public static function uptime(Request $request): void
    {
        Auth::requireUser();
        $days = max(1, min(90, (int) ($request->query('days') ?? 90)));
        Response::json(WebsiteService::getUptime((string) $request->param('id'), $days));
    }

    public static function stats(Request $request): void
    {
        Auth::requireUser();
        Response::json(WebsiteService::getStats((string) $request->param('id')));
    }

    public static function export(Request $request): void
    {
        Auth::requireUser();
        $csv = WebsiteService::exportCsv();
        header('Content-Disposition: attachment; filename="websites.csv"');
        Response::text($csv, 200, 'text/csv; charset=utf-8');
    }

    public static function preview(Request $request): void
    {
        Auth::requireUser();
        $url = trim((string) ($request->query('url') ?? ''));
        if ($url === '' && $request->method() !== 'GET') {
            $url = trim((string) ($request->json()['url'] ?? ''));
        }
        Response::json(WebsitePreviewService::analyze($url));
    }
}
