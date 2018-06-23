<?php

namespace App\Model;

use Exception;
use Nette;
use Nette\Security\IIdentity;
use Nette\Security\Passwords;
use Nette\Utils\Random;

class UserManager implements Nette\Security\IAuthenticator
{
    use Nette\SmartObject;

    public const
        TABLE_NAME = 'user',
        COLUMN_ID = 'id',
        COLUMN_NAME = 'name',
        COLUMN_EMAIL = 'email',
        COLUMN_PASSWORD_HASH = 'password',
        COLUMN_ROLE = 'role',
        COLUMN_RESET_TOKEN = 'reset_hash';


    /** @var Nette\Database\Context */
    private $database;


    /**
     * UserManager constructor.
     * @param Nette\Database\Context $database
     */
    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
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

        if (!Passwords::verify($password, $row[self::COLUMN_PASSWORD_HASH])) {
            throw new Nette\Security\AuthenticationException(
                'The password is incorrect',
                self::INVALID_CREDENTIAL
            );
        }

        if (Passwords::needsRehash($row[self::COLUMN_PASSWORD_HASH])) {
            $row->update([
                self::COLUMN_PASSWORD_HASH => Passwords::hash($password),
            ]);
        }

        $arr = $row->toArray();
        unset($arr[self::COLUMN_PASSWORD_HASH]);
        return new Nette\Security\Identity($row[self::COLUMN_ID], $row[self::COLUMN_ROLE], $arr);
    }


    /**
     * @param string $email
     * @return Nette\Database\Table\ActiveRow
     * @throws UserNotFoundException
     */
    public function getUserByEmail($email): Nette\Database\Table\ActiveRow
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
    public function startReset($email): string
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
    public function setPassword($email, $password): void
    {
        $row = $this->getUserByEmail($email);
        $row->update([
            self::COLUMN_PASSWORD_HASH => Passwords::hash($password),
            self::COLUMN_RESET_TOKEN => null,
        ]);
    }
}
