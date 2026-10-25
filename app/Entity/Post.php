<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use JakubBoucek\Hydrator\Entity;

final class Post implements Entity
{
    public string $key;
    public string $version;
    public string $hash;
    public ?string $title;
    public ?string $contentCs;
    public ?string $contentEn;
    public ?DateTimeImmutable $publishedFrom;
    public ?DateTimeImmutable $publishedTo;
    public DateTimeImmutable $createdAt;
    public int $createdBy;
    public ?string $techNote;

    public function isPublished(?DateTimeImmutable $time = null): bool
    {
        $time ??= new DateTimeImmutable;
        return $this->publishedFrom !== null && !$this->isPlanned($time) && !$this->isExpired($time);
    }

    public function isPlanned(?DateTimeImmutable $time = null): bool
    {
        return $this->publishedFrom !== null && $this->publishedFrom > ($time ?? new DateTimeImmutable);
    }

    public function isExpired(?DateTimeImmutable $time = null): bool
    {
        return $this->publishedTo !== null && $this->publishedTo < ($time ?? new DateTimeImmutable);
    }

    public function getContent(string $language): string
    {
        return match ($language) {
            'cs' => $this->contentCs ?? '',
            'en' => $this->contentEn ?? '',
            default => '',
        };
    }
}
