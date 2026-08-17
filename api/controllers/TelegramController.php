<?php
declare(strict_types=1);

final class TelegramController
{
    public static function get(Request $request): void
    {
        Auth::requireUser();
        Response::json(TelegramService::getSettings());
    }

    public static function update(Request $request): void
    {
        $user = Auth::requireUser();
        $data = Validator::validate($request->json(), [
            'botToken' => 'optional|string',
            'chatId' => 'optional|string',
            'enabled' => 'optional|bool',
            'notifyOnDown' => 'optional|bool',
            'notifyOnUp' => 'optional|bool',
        ], true);

        Response::json(TelegramService::updateSettings(
            $data,
            $user['id'],
            ['ip' => $request->ip(), 'userAgent' => $request->userAgent()],
        ));
    }

    public static function test(Request $request): void
    {
        $user = Auth::requireUser();
        Response::json(TelegramService::sendTest(
            $user['id'],
            ['ip' => $request->ip(), 'userAgent' => $request->userAgent()],
        ));
    }
}
