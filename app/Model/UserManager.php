<?php

declare(strict_types=1);

namespace App\Model;

use Nette;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Security\AuthenticationException;
use Nette\Security\IIdentity;
use Nette\Security\Passwords;
use Nette\Security\SimpleIdentity;
use Nette\Utils\Random;

class UserManager implements Nette\Security\IAuthenticator
{
    use Nette\SmartObject;

    private const TABLE_NAME = 'user';
    private const COLUMN_ID = 'id';
    private const COLUMN_NAME = 'name';
    private const COLUMN_EMAIL = 'email';
    private const COLUMN_PASSWORD_HASH = 'password';
    private const COLUMN_ROLE = 'role';
    private const COLUMN_RESET_TOKEN = 'reset_hash';

    private Explorer $database;
    private Passwords $passwords;


    public function __construct(Explorer $database, Passwords $passwords)
    {
        $this->database = $database;
        $this->passwords = $passwords;
    }


    /**
     * Performs an authentication.
     * @throws AuthenticationException
     */
    public function authenticate(array $credentials): IIdentity
    {
        [$email, $password] = $credentials;

        try {
            $row = $this->getUserByEmail($email);
        } catch (UserNotFoundException $e) {
            throw new AuthenticationException(
                'The email is incorrect',
                self::IDENTITY_NOT_FOUND
            );
        }

        if (!$this->passwords->verify($password, $row[self::COLUMN_PASSWORD_HASH])) {
            throw new AuthenticationException(
                'The password is incorrect',
                self::INVALID_CREDENTIAL
            );
        }

        if ($this->passwords->needsRehash($row[self::COLUMN_PASSWORD_HASH])) {
            $row->update(
                [
                    self::COLUMN_PASSWORD_HASH => $this->passwords->hash($password),
                ]
            );
        }

        $arr = $row->toArray();
        unset($arr[self::COLUMN_PASSWORD_HASH]);
        return new SimpleIdentity($row[self::COLUMN_ID], $row[self::COLUMN_ROLE], $arr);
    }


    /**
     * @throws UserNotFoundException
     */
    public function getUserByEmail(string $email): ActiveRow
    {
        $row = $this->database
            ->table(self::TABLE_NAME)
            ->where(self::COLUMN_EMAIL, $email)
            ->fetch();

        if ($row === null) {
            throw new UserNotFoundException("User \"$email\" not found");
        }

        return $row;
    }


    /**
     * @throws UserNotFoundException
     */
    public function startResetPassword(string $email): string
    {
        $row = $this->getUserByEmail($email);

        $token = Random::generate(16);

        $hash = base64_encode(hash('sha256', $token, true));

        $row->update([self::COLUMN_RESET_TOKEN => $hash]);

        return $token;
    }


    public function stopResetPassword(string $email): void
    {
        $row = $this->getUserByEmail($email);

        $row->update([self::COLUMN_RESET_TOKEN => null]);
    }

    public function verifyResetPasswordToken(string $email, string $token): bool
    {
        $row = $this->getUserByEmail($email);

        if ($row['reset_hash'] === null) {
            return false;
        }

        $hash = base64_encode(hash('sha256', $token, true));

        return hash_equals($row['reset_hash'], $hash);
    }


    public function setPassword(string $email, string $password): void
    {
        $row = $this->getUserByEmail($email);
        $row->update(
            [
                self::COLUMN_PASSWORD_HASH => $this->passwords->hash($password),
                self::COLUMN_RESET_TOKEN => null,
            ]
        );
    }
}
