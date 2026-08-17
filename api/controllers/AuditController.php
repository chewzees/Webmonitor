<?php
declare(strict_types=1);

final class AuditController
{
    public static function list(Request $request): void
    {
        Auth::requireUser();
        Response::json(AuditService::list($request->queryAll()));
    }
}
