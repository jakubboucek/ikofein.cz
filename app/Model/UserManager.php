<?php

declare(strict_types=1);

namespace App\Model;

use Nette;
use Nette\Database;
use Nette\Database\Context;
use Nette\Database\Table\ActiveRow;
use Nette\Security\Identity;
use Nette\Security\IIdentity;
use Nette\Security\Passwords;
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

    private Context $database;
    private Passwords $passwords;


    public function __construct(Context $database, Passwords $passwords)
    {
        $this->database = $database;
        $this->passwords = $passwords;
    }


    /**
     * Performs an authentication.
     * @throws Nette\Security\AuthenticationException
     */
    public function authenticate(array $credentials): IIdentity
    {
        [$email, $password] = $credentials;

        try {
            $row = $this->getUserByEmail($email);
        } catch (UserNotFoundException $e) {
            throw new Nette\Security\AuthenticationException(
                'The email is incorrect',
                self::IDENTITY_NOT_FOUND
            );
        }

        if (!$this->passwords->verify($password, $row[self::COLUMN_PASSWORD_HASH])) {
            throw new Nette\Security\AuthenticationException(
                'The password is incorrect',
                self::INVALID_CREDENTIAL
            );
        }

        if ($this->passwords->needsRehash($row[self::COLUMN_PASSWORD_HASH])) {
            $row->update([
                self::COLUMN_PASSWORD_HASH => $this->passwords->hash($password),
            ]);
        }

        $arr = $row->toArray();
        unset($arr[self::COLUMN_PASSWORD_HASH]);
        return new Identity($row[self::COLUMN_ID], $row[self::COLUMN_ROLE], $arr);
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
    public function startReset(string $email): string
    {
        $row = $this->getUserByEmail($email);

        $hash = Random::generate(16);

        $row->update([
            self::COLUMN_RESET_TOKEN => $hash,
        ]);

        return $hash;
    }


    public function stopReset(string $email): void
    {
        $row = $this->getUserByEmail($email);

        $row->update([
            self::COLUMN_RESET_TOKEN => null,
        ]);
    }


    public function setPassword(string $email, string $password): void
    {
        $row = $this->getUserByEmail($email);
        $row->update([
            self::COLUMN_PASSWORD_HASH => $this->passwords->hash($password),
            self::COLUMN_RESET_TOKEN => null,
        ]);
    }
}
