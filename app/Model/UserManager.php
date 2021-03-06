<?php

declare(strict_types=1);

namespace App\Model;

use Nette;
use Nette\Database;
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

    /** @var Database\Context */
    private $database;
    
    /** @var Passwords */
    private $passwords;


    /**
     * UserManager constructor.
     * @param Database\Context $database
     * @param Passwords $passwords
     */
    public function __construct(Database\Context $database, Passwords $passwords)
    {
        $this->database = $database;
        $this->passwords = $passwords;
    }


    /**
     * Performs an authentication.
     * @param array $credentials
     * @return Nette\Security\Identity
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

        /** @var Nette\Database\Table\ActiveRow $row */

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
        return new Nette\Security\Identity($row[self::COLUMN_ID], $row[self::COLUMN_ROLE], $arr);
    }


    /**
     * @param string $email
     * @return Database\Table\ActiveRow
     * @throws UserNotFoundException
     */
    public function getUserByEmail(string $email): Database\Table\ActiveRow
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
     * @param string $email
     * @return string
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


    /**
     * @param string $email
     */
    public function stopReset($email): void
    {
        $row = $this->getUserByEmail($email);

        $row->update([
            self::COLUMN_RESET_TOKEN => null,
        ]);
    }


    /**
     * @param string $email
     * @param string $password
     */
    public function setPassword(string $email, string $password): void
    {
        $row = $this->getUserByEmail($email);
        $row->update([
            self::COLUMN_PASSWORD_HASH => $this->passwords->hash($password),
            self::COLUMN_RESET_TOKEN => null,
        ]);
    }
}
