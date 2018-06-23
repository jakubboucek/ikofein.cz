<?php

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

