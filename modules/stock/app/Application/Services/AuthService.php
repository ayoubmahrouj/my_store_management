<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Domain\Entities\User;
use App\Infrastructure\Persistence\PdoUserRepository;
use PDO;

final class AuthService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /** @return array{user: ?User, error: ?string, code: ?string} */
    public function attempt(string $login, string $password, string $ip, string $userAgent): array
    {
        $login = trim($login);
        if ($login === '' || $password === '') {
            return ['user' => null, 'error' => t('Veuillez renseigner vos identifiants.'), 'code' => 'missing_credentials'];
        }

        $max = (int) Config::get('security.max_login_attempts', 5);
        $lockMinutes = (int) Config::get('security.lock_minutes', 15);

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE success = 0 AND created_at >= :cutoff AND (login = :login OR ip = :ip)'
        );
        $stmt->execute([
            'cutoff' => date('Y-m-d H:i:s', time() - ($lockMinutes * 60)),
            'login'  => $login,
            'ip'     => $ip,
        ]);

        if ((int) $stmt->fetchColumn() >= $max) {
            return [
                'user'  => null,
                'error' => t('Trop de tentatives. Réessayez dans :minutes minutes.', ['minutes' => $lockMinutes]),
                'code'  => 'throttled',
            ];
        }

        $user = (new PdoUserRepository())->findByLogin($login);
        $valid = $user !== null && $user->isActive() && password_verify($password, $user->passwordHash());

        $this->logAttempt($login, $ip, $valid);

        if (!$valid || $user === null) {
            return ['user' => null, 'error' => t('Identifiants incorrects.'), 'code' => 'invalid_credentials'];
        }

        if (password_needs_rehash($user->passwordHash(), PASSWORD_DEFAULT)) {
            $this->rehash($user->id(), $password);
        }

        Auth::login($user);
        $this->audit($user->id(), 'login', 'user', $user->id(), $ip, $userAgent);

        return ['user' => $user, 'error' => null, 'code' => null];
    }

    private function logAttempt(string $login, string $ip, bool $success): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO login_attempts (login, ip, success, created_at) VALUES (:login, :ip, :success, NOW())'
        );
        $stmt->execute([
            'login'   => mb_substr($login, 0, 100),
            'ip'      => $ip,
            'success' => $success ? 1 : 0,
        ]);
    }

    private function rehash(int $userId, string $password): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $userId]);
    }

    private function audit(int $userId, string $action, string $entity, int $entityId, string $ip, string $userAgent): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip, user_agent, created_at)
             VALUES (:user_id, :action, :entity, :entity_id, :ip, :ua, NOW())'
        );
        $stmt->execute([
            'user_id'   => $userId,
            'action'    => $action,
            'entity'    => $entity,
            'entity_id' => $entityId,
            'ip'        => $ip,
            'ua'        => $userAgent,
        ]);
    }
}
