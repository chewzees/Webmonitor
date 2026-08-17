<?php
declare(strict_types=1);

final class DashboardController
{
    public static function get(Request $request): void
    {
        Auth::requireUser();
        Response::json(DashboardService::get());
    }
}
