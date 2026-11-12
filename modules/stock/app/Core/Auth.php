<?php
declare(strict_types=1);

namespace App\Core;

use App\Domain\Entities\User;
use App\Infrastructure\Persistence\PdoUserRepository;

final class Auth
{
    private static ?User $cachedUser = null;
    private static bool $loaded = false;

    public static function check(): bool
    {
        if (empty($_SESSION['user_id'])) {
            return false;
        }

        $lifetime = (int) Config::get('session.lifetime', 1800);
        $last = (int) ($_SESSION['last_activity'] ?? 0);
        if ($last > 0 && (time() - $last) > $lifetime) {
            self::logout();
            return false;
        }

        $_SESSION['last_activity'] = time();
        return self::user() !== null;
    }

    public static function user(): ?User
    {
        if (self::$loaded) {
            return self::$cachedUser;
        }
        self::$loaded = true;

        $id = (int) ($_SESSION['user_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        self::$cachedUser = (new PdoUserRepository())->findById($id);
        if (self::$cachedUser === null) {
            unset($_SESSION['user_id']);
        }
        return self::$cachedUser;
    }

    public static function login(User $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user->id();
        $_SESSION['last_activity'] = time();
        $_SESSION['login_at'] = time();
        self::$cachedUser = $user;
        self::$loaded = true;
        Csrf::rotate();
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        self::$cachedUser = null;
        self::$loaded = false;
    }

    public static function isRole(string ...$roles): bool
    {
        $user = self::user();
        return $user !== null && in_array($user->roleCode(), $roles, true);
    }

    public static function id(): int
    {
        return (int) (self::user()?->id() ?? 0);
    }
}
