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

    private const string TableName = 'user';
    private const string ColumnId = 'id';
    private const string ColumnEmail = 'email';
    private const string ColumnPasswordHash = 'password';
    private const string ColumnRole = 'role';


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

        if (!$this->passwords->verify($password, $row[self::ColumnPasswordHash])) {
            throw new AuthenticationException(
                'The password is incorrect',
                self::IdentityNotFound
            );
        }

        if ($this->passwords->needsRehash($row[self::ColumnPasswordHash])) {
            $row->update(
                [
                    self::ColumnPasswordHash => $this->passwords->hash($password),
                ]
            );
        }

        $arr = $row->toArray();
        unset($arr[self::ColumnPasswordHash], $arr['reset_hash'], $arr['reset_selector'], $arr['reset_expires_at'], $arr['reset_requested_at']);
        return new SimpleIdentity($row[self::ColumnId], $row[self::ColumnRole], $arr);
    }


    public function getUserByEmail(string $email): ActiveRow
    {
        $row = $this->database
            ->table(self::TableName)
            ->where(self::ColumnEmail, $email)
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
