<?php

namespace App\Model;

use Nette,
	Nette\Security\Passwords,
	Nette\Utils\Random;


/**
 * Users management.
 */
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


	public function __construct(Nette\Database\Context $database)
	{
		$this->database = $database;
	}


	/**
	 * Performs an authentication.
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


	public function getUserByEmail($email) {
		return $this->database->table(self::TABLE_NAME)->where(self::COLUMN_EMAIL, $email)->fetch();
	}

	public function startReset($email)
	{
		$row = $this->getUserByEmail($email);

		if(!$row) {
			return FALSE;
		}

		$hash = Random::generate(16);

		$row->update([
			self::COLUMN_RESET_TOKEN => $hash,
		]);

		return $hash;
	}

	public function stopReset($email)
	{
		$row = $this->getUserByEmail($email);

		$row->update([
			self::COLUMN_RESET_TOKEN => NULL,
		]);
	}

	public function setPassword($email, $password)
	{
		$row = $this->getUserByEmail($email);
		$row->update([
			self::COLUMN_PASSWORD_HASH => Passwords::hash($password),
			self::COLUMN_RESET_TOKEN => NULL,
		]);
	}

}



class DuplicateNameException extends \Exception
{}