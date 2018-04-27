<?php

namespace App\Model;

use Nette;
use Nette\Security\Passwords;
use Nette\Utils\Random;


class UserManager implements Nette\Security\IAuthenticator
{
    use Nette\SmartObject;

    const
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
    public function authenticate(array $credentials)
    {
        list($email, $password) = $credentials;

        $row = $this->getUserByEmail($email);


        if (!$row) {
            throw new Nette\Security\AuthenticationException('The email is incorrect.', self::IDENTITY_NOT_FOUND);

        } elseif (!Passwords::verify($password, $row[self::COLUMN_PASSWORD_HASH])) {
            throw new Nette\Security\AuthenticationException('The password is incorrect.', self::INVALID_CREDENTIAL);

        } elseif (Passwords::needsRehash($row[self::COLUMN_PASSWORD_HASH])) {
            $row->update([
                self::COLUMN_PASSWORD_HASH => Passwords::hash($password),
            ]);
        }

        $arr = $row->toArray();
        unset($arr[self::COLUMN_PASSWORD_HASH]);
        return new Nette\Security\Identity($row[self::COLUMN_ID], $row[self::COLUMN_ROLE], $arr);
    }


    /**
     * @param $email
     * @return bool|mixed|Nette\Database\Table\IRow
     */
    public function getUserByEmail($email)
    {
        return $this->database->table(self::TABLE_NAME)->where(self::COLUMN_EMAIL, $email)->fetch();
    }


    /**
     * @param $email
     * @return bool|string
     */
    public function startReset($email)
    {
        $row = $this->getUserByEmail($email);

        if (!$row) {
            return false;
        }

        $hash = Random::generate(16);

        $row->update([
            self::COLUMN_RESET_TOKEN => $hash,
        ]);

        return $hash;
    }


    /**
     * @param $email
     */
    public function stopReset($email)
    {
        $row = $this->getUserByEmail($email);

        $row->update([
            self::COLUMN_RESET_TOKEN => null,
        ]);
    }


    /**
     * @param $email
     * @param $password
     */
    public function setPassword($email, $password)
    {
        $row = $this->getUserByEmail($email);
        $row->update([
            self::COLUMN_PASSWORD_HASH => Passwords::hash($password),
            self::COLUMN_RESET_TOKEN => null,
        ]);
    }

}
