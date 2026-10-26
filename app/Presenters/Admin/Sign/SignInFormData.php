<?php

declare(strict_types=1);

namespace App\Presenters\Admin\Sign;

use SensitiveParameter;

final readonly class SignInFormData
{
    public function __construct(
        public string $email,
        #[SensitiveParameter] public string $password,
        public bool $remember,
    ) {
    }
}
