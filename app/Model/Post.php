<?php

declare(strict_types=1);

namespace App\Model;

use DateTime;
use Nette;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Security\User;

class Post
{
    private const CURRENT = 'current';
    private const TABLE_NAME = 'post';
    private const KEY_COLUMN = 'key';
    private const VERSION_COLUMN = 'version';


    private Explorer $database;
    private Cache $cache;
    private User $user;


    public function __construct(Explorer $database, Storage $storage, User $user)
    {
        $this->database = $database;
        $this->cache = new Cache($storage, 'post');
        $this->user = $user;
    }


    public function tryFindPostByKey(string $key, bool $publishedOnly = false, string $version = self::CURRENT): ?array
    {
        try {
            return $this->getPostByKey($key, $publishedOnly, $version);
        } catch (PostNotFoundException $e) {
            return null;
        }
    }


    /**
     * @throws PostNotFoundException
     */
    public function getPostByKey(string $key, bool $publishedOnly = false, string $version = self::CURRENT): array
    {
        $row = $this->database->table(self::TABLE_NAME)
            ->where(self::KEY_COLUMN, $key)
            ->where(self::VERSION_COLUMN, $version)
            ->fetch();

        if (!$row) {
            throw new PostNotFoundException('Post not found', 404);
        }

        $post = $this->preparePost($row);

        if ($publishedOnly && ($post['info']['isPublished'] === false)) {
            throw new PostNotFoundException('Post not published', 403);
        }

        return $post;
    }


    public function getPosts(bool $publishedOnly = false, string $version = self::CURRENT): array
    {
        $selection = $this->database->table(self::TABLE_NAME)
            ->where(self::VERSION_COLUMN, $version);

        $posts = [];

        /** @var ActiveRow $post */
        foreach ($selection as $post) {
            $post = $this->preparePost($post);

            if ($publishedOnly && (!$post['info']['isPublished'])) {
                continue;
            }

            $posts[$post['key']] = $post;
        }

        return $posts;
    }


    public function save(string $key, array $data): array
    {
        $data = [
                'version' => self::CURRENT,
                'hash' => $this->getHash(),
                'created_at' => new DateTime(),
                'created_by' => $this->user->getId(),
            ] + $data;

        $this->database->table(self::TABLE_NAME)
            ->where(self::KEY_COLUMN, $key)
            ->where(self::VERSION_COLUMN, self::CURRENT)
            ->update($data);

        //Create version
        $current = $this->database->table(self::TABLE_NAME)
            ->where(self::KEY_COLUMN, $key)
            ->where(self::VERSION_COLUMN, self::CURRENT)
            ->fetch()->toArray();

        $current['version'] = (new DateTime)->format(DateTime::ATOM);

        $this->database->table(self::TABLE_NAME)
            ->insert($current);

        $this->cache->remove($key);

        return $current;
    }


    private function getHash(): string
    {
        return Nette\Utils\Random::generate(16);
    }


    private function preparePost(ActiveRow $row): array
    {
        $post = $row->toArray();

        $post['info'] = [
            'isPublished' => $this->isPublished($post),
            'isPlanned' => $this->isPlanned($post),
            'isExpired' => $this->isExpired($post),
        ];

        return $post;
    }


    public function isPublished(array $post): bool
    {
        if (empty($post['published_from'])) {
            return false;
        }

        if ($this->isPlanned($post) || $this->isExpired($post)) {
            return false;
        }

        return true;
    }


    public function isPlanned(array $post): bool
    {
        $current = new DateTime();

        return (bool)$post['published_from'] &&
            $post['published_from'] > $current;
    }


    public function isExpired(array $post): bool
    {
        $current = new DateTime();

        return (bool)$post['published_to'] &&
            $post['published_to'] < $current;
    }
}
