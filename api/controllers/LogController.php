<?php
declare(strict_types=1);

final class LogController
{
    public static function list(Request $request): void
    {
        Auth::requireUser();
        Response::json(LogService::list($request->queryAll()));
    }

    public static function export(Request $request): void
    {
        Auth::requireUser();
        $csv = LogService::exportCsv($request->queryAll());
        header('Content-Disposition: attachment; filename="monitor-logs.csv"');
        Response::text($csv, 200, 'text/csv; charset=utf-8');
    }
}
