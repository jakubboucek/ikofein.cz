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

class UserManager implements Nette\Security\Authenticator
{
    use Nette\SmartObject;

    private const string TABLE_NAME = 'user';
    private const string COLUMN_ID = 'id';
    private const string COLUMN_EMAIL = 'email';
    private const string COLUMN_PASSWORD_HASH = 'password';
    private const string COLUMN_ROLE = 'role';


    public function __construct(
        private readonly Explorer $database,
        private readonly Passwords $passwords,
    ) {
    }


    /**
     * Performs an authentication.
     */
    public function authenticate(string $username, string $password): IIdentity
    {
        return $this->database->transaction(fn(): IIdentity => $this->authenticateLocked($username, $password));
    }

    private function authenticateLocked(string $username, string $password): IIdentity
    {
        $this->database->query('SELECT id FROM `user` WHERE email = ? FOR UPDATE', $username)->fetch();
        try {
            $row = $this->getUserByEmail($username);
        } catch (UserNotFoundException) {
            throw new AuthenticationException(
                'The email is incorrect',
                self::IdentityNotFound
            );
        }

        if (!$this->passwords->verify($password, $row[self::COLUMN_PASSWORD_HASH])) {
            throw new AuthenticationException(
                'The password is incorrect',
                self::IdentityNotFound
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
        unset($arr[self::COLUMN_PASSWORD_HASH], $arr['reset_hash'], $arr['reset_selector'], $arr['reset_expires_at'], $arr['reset_requested_at']);
        return new SimpleIdentity($row[self::COLUMN_ID], $row[self::COLUMN_ROLE], $arr);
    }


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


    public function isIdentityCurrent(IIdentity $identity): bool
    {
        $row = $this->database->table('user')->wherePrimary($identity->getId())->fetch();
        return $row !== null && ($identity->getData()['auth_version'] ?? null) === $row->auth_version;
    }
}
