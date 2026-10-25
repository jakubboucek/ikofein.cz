<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Database\Explorer;
use Nette\Security\Passwords;
use SensitiveParameter;

final readonly class PasswordReset
{
    public function __construct(
        private Explorer $database,
        private Passwords $passwords,
    ) {
    }

    /** @return array{email: string, token: string}|null */
    public function request(string $email, string $ip): ?array
    {
        if (!$this->allow('request-ip:' . $ip, 20, 900)) {
            return null;
        }

        $user = $this->database->table('user')->where('email', $email)->fetch();
        $account = $user ? (string) $user->id : strtolower(trim($email));
        if (!$this->allow('request-account:' . $account, 5, 3600) || !$user) {
            return null;
        }

        return $this->database->transaction(function () use ($user): ?array {
            $row = $this->database->query('SELECT * FROM `user` WHERE id = ? FOR UPDATE', $user->id)->fetch();
            if (!$row) {
                return null;
            }
            $now = gmdate('Y-m-d H:i:s');
            if ($row->reset_requested_at !== null && (string) $row->reset_requested_at > gmdate('Y-m-d H:i:s', time() - 60)) {
                return null;
            }
            $selector = self::encode(random_bytes(16));
            $verifier = random_bytes(32);
            $this->database->table('user')->wherePrimary($row->id)->update([
                'reset_selector' => $selector,
                'reset_hash' => self::encode(hash('sha256', $verifier, true)),
                'reset_expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
                'reset_requested_at' => $now,
            ]);
            return ['email' => $row->email, 'token' => $selector . '.' . self::encode($verifier)];
        });
    }

    public function verify(#[SensitiveParameter] string $token, string $ip): void
    {
        $this->checkAttempt($ip);
        $this->find($token);
    }

    public function complete(#[SensitiveParameter] string $token, #[SensitiveParameter] string $password, string $ip): string
    {
        $this->checkAttempt($ip);
        $this->find($token);
        // Hash before locking the row; token validation is repeated under the lock.
        $hash = $this->passwords->hash($password);
        return $this->database->transaction(function () use ($token, $hash): string {
            $row = $this->find($token, true);
            $this->database->table('user')->wherePrimary($row['id'])->update([
                'password' => $hash,
                'reset_selector' => null,
                'reset_hash' => null,
                'reset_expires_at' => null,
                'auth_version' => $row['auth_version'] + 1,
            ]);
            return $row['email'];
        });
    }

    public function cancel(#[SensitiveParameter] string $token, string $ip): void
    {
        $this->checkAttempt($ip);
        $this->database->transaction(function () use ($token): void {
            $row = $this->find($token, true);
            $this->database->table('user')->wherePrimary($row['id'])->update([
                'reset_selector' => null,
                'reset_hash' => null,
                'reset_expires_at' => null,
            ]);
        });
    }

    private function find(#[SensitiveParameter] string $token, bool $lock = false): array
    {
        if (!preg_match('/\A([A-Za-z0-9_-]{22})\.([A-Za-z0-9_-]{43})\z/D', $token, $parts)) {
            throw new InvalidPasswordResetException;
        }
        self::decode($parts[1]);
        $verifier = self::decode($parts[2]);
        $row = $this->database->query(
            'SELECT * FROM `user` WHERE reset_selector = ?' . ($lock ? ' FOR UPDATE' : ''),
            $parts[1],
        )->fetch();
        if (!$row || $row->reset_hash === null || $row->reset_expires_at === null
            || (string) $row->reset_expires_at <= gmdate('Y-m-d H:i:s')
            || !hash_equals($row->reset_hash, self::encode(hash('sha256', $verifier, true)))) {
            throw new InvalidPasswordResetException;
        }
        return (array) $row;
    }

    private function checkAttempt(string $ip): void
    {
        if (!$this->allow('verify-ip:' . $ip, 60, 900)) {
            throw new InvalidPasswordResetException;
        }
    }

    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function decode(#[SensitiveParameter] string $encoded): string
    {
        $bytes = base64_decode(strtr($encoded, '-_', '+/'), true);
        if ($bytes === false || self::encode($bytes) !== $encoded) {
            throw new InvalidPasswordResetException;
        }
        return $bytes;
    }

    private function allow(string $scope, int $limit, int $seconds): bool
    {
        $key = hash('sha256', $scope);
        $now = time();
        return $this->database->transaction(function () use ($key, $now, $limit, $seconds): bool {
            $this->database->query('DELETE FROM password_reset_limit WHERE window_start < ?', $now - 86400);
            $this->database->query('INSERT IGNORE INTO password_reset_limit', [
                'key' => $key, 'window_start' => $now, 'attempts' => 0,
            ]);
            $row = $this->database->query('SELECT * FROM password_reset_limit WHERE `key` = ? FOR UPDATE', $key)->fetch();
            if (!$row) {
                return false;
            }
            $expired = $row->window_start <= $now - $seconds;
            $attempts = $expired ? 1 : $row->attempts + 1;
            $this->database->table('password_reset_limit')->wherePrimary($key)->update([
                'window_start' => $expired ? $now : $row->window_start,
                'attempts' => min($attempts, $limit + 1),
            ]);
            return $attempts <= $limit;
        });
    }
}
