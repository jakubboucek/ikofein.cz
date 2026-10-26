<?php

declare(strict_types=1);

namespace App\Presenters\Admin\Post;

final readonly class PostEditFormData
{
    public function __construct(
        public string $key,
        public bool $published,
        public string $published_from,
        public string $published_to,
        public string $content_cs,
        public string $content_en,
    ) {
    }
}
