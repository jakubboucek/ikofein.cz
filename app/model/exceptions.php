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



class PageNotFound extends \Exception
{
}