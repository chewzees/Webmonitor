<?php
declare(strict_types=1);

final class PublicController
{
    public static function status(Request $request): void
    {
        Response::json(PublicService::getPublicStatus());
    }

    public static function siteStatus(Request $request): void
    {
        Response::json(PublicService::getPublicSiteStatus((string) $request->param('slug')));
    }
}
