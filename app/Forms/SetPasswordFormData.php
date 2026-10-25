<?php

declare(strict_types=1);

namespace App\Forms;

use SensitiveParameter;

final readonly class SetPasswordFormData
{
    public function __construct(
        #[SensitiveParameter] public string $token,
        #[SensitiveParameter] public string $password,
        #[SensitiveParameter] public string $password2,
    ) {
    }
}
