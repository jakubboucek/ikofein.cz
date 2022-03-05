<?php /** @noinspection PhpCSValidationInspection */

declare(strict_types=1);

namespace App\Model;

class DuplicateNameException extends \Exception
{
}


class PostException extends \Exception
{
}


class PostNotFoundException extends PostException
{
}


class PageNotFoundException extends \RuntimeException
{
}


class UserNotFoundException extends \RuntimeException
{
}
