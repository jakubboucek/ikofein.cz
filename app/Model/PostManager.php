<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Post;
use DateTimeImmutable;
use JakubBoucek\Hydrator\Hydrator;
use JakubBoucek\Hydrator\HydratorFactory;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Security\User;
use Nette\Utils\Random;

class PostManager
{
    private const string CURRENT = 'current';
    private readonly Cache $cache;
    /** @var Hydrator<Post> */
    private readonly Hydrator $hydrator;

    public function __construct(
        private readonly Explorer $database,
        Storage $storage,
        private readonly User $user,
        HydratorFactory $hydrators,
    ) {
        $this->cache = new Cache($storage, 'post-entity-v1');
        $this->hydrator = $hydrators->for(Post::class);
    }

    public function tryFindPostByKey(string $key, bool $publishedOnly = false, string $version = self::CURRENT): ?Post
    {
        try {
            return $this->getPostByKey($key, $publishedOnly, $version);
        } catch (PostNotFoundException) {
            return null;
        }
    }

    public function getPostByKey(string $key, bool $publishedOnly = false, string $version = self::CURRENT): Post
    {
        $row = $this->database->table('post')->where('key', $key)->where('version', $version)->fetch();
        if (!$row) {
            throw new PostNotFoundException('Post not found', 404);
        }
        $post = $this->hydrator->fromData($row);
        if ($publishedOnly && !$post->isPublished()) {
            throw new PostNotFoundException('Post not published', 403);
        }
        return $post;
    }

    /** @return array<string, Post> */
    public function getPosts(bool $publishedOnly = false, string $version = self::CURRENT): array
    {
        $rows = $this->database->table('post')->where('version', $version);
        $posts = [];
        foreach ($this->hydrator->fromDataSet($rows, keyBy: 'key') as $key => $post) {
            if (!$publishedOnly || $post->isPublished()) {
                $posts[$key] = $post;
            }
        }
        return $posts;
    }

    public function getCachedPublishedPost(string $key): ?Post
    {
        // False caches an absent post; Nette Cache treats null as a cache miss.
        $post = $this->cache->load($key, function (&$dependencies) use ($key): Post|false {
            $dependencies[Cache::Expire] = '20 minutes';
            return $this->tryFindPostByKey($key, true) ?? false;
        });
        return $post === false ? null : $post;
    }

    /** Returns the complete historical snapshot created by a non-empty update. */
    public function save(string $key, Post $changes): Post
    {
        $data = array_intersect_key($this->hydrator->toData($changes), array_flip([
            'title', 'tech_note', 'content_cs', 'content_en', 'published_from', 'published_to',
        ]));
        if ($data === []) {
            return $this->getPostByKey($key);
        }
        $snapshot = $this->database->transaction(function () use ($key, $data): Post {
            $row = $this->database->query(
                'SELECT * FROM post WHERE `key` = ? AND version = ? FOR UPDATE', $key, self::CURRENT,
            )->fetch();
            if (!$row) {
                throw new PostNotFoundException('Post not found', 404);
            }
            $now = new DateTimeImmutable;
            $data = [
                'version' => self::CURRENT,
                'hash' => Random::generate(16),
                'created_at' => $now,
                'created_by' => $this->user->getId(),
            ] + $data;
            $this->database->table('post')->where('key', $key)->where('version', self::CURRENT)->update($data);
            $snapshot = $this->getPostByKey($key);
            $snapshot->version = $now->format('Y-m-d\TH:i:s.uP');
            $this->database->table('post')->insert($this->hydrator->toData($snapshot));
            return $snapshot;
        });
        $this->cache->remove($key);
        return $snapshot;
    }
}
