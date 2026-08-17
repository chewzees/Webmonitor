<?php
declare(strict_types=1);

final class AuthController
{
    public static function login(Request $request): void
    {
        RateLimit::assertLogin($request);

        $body = $request->json();
        $data = Validator::validate($body, [
            'email' => 'required|email',
            'password' => 'required|string|min:1',
        ]);

        $user = AuthService::authenticate($data['email'], $data['password']);
        Auth::regenerate();
        Auth::setUser($user);
        $csrfToken = Csrf::issue();

        AuditService::write([
            'userId' => $user['id'],
            'action' => 'auth.login',
            'entityType' => 'User',
            'entityId' => $user['id'],
            'ip' => $request->ip(),
            'userAgent' => $request->userAgent(),
        ]);

        Response::json(['user' => $user, 'csrfToken' => $csrfToken]);
    }

    public static function logout(Request $request): void
    {
        $user = Auth::user();
        if ($user) {
            AuditService::write([
                'userId' => $user['id'],
                'action' => 'auth.logout',
                'entityType' => 'User',
                'entityId' => $user['id'],
                'ip' => $request->ip(),
                'userAgent' => $request->userAgent(),
            ]);
        }

        Csrf::clearCookie();
        Auth::clear();
        Response::json(['ok' => true]);
    }

    public static function me(Request $request): void
    {
        $user = Auth::user();
        if (!$user) {
            Response::error('UNAUTHORIZED', 'Not authenticated', 401);
        }
        Response::json(['user' => $user]);
    }

    public static function csrf(Request $request): void
    {
        $token = Csrf::issue();
        Response::json(['csrfToken' => $token]);
    }
}
