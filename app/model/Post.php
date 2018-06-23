<?php

namespace App\Model;

use Nette;
use Nette\Caching;
use Nette\Database\Table\ActiveRow;

class Post
{

    const CURRENT = 'current';
    const TABLE_NAME = 'post';
    const KEY_COLUMN = 'key';
    const VERSION_COLUMN = 'version';


    /** @var Nette\Database\Context */
    private $database;
    /**
     * @var Caching\Cache
     */
    private $cache;
    /**
     * @var Nette\Security\User
     */
    private $user;


    /**
     * Post constructor.
     * @param Nette\Database\Context $database
     * @param Caching\IStorage $storage
     * @param Nette\Security\User $user
     */
    public function __construct(Nette\Database\Context $database, Caching\IStorage $storage, Nette\Security\User $user)
    {
        $this->database = $database;
        $this->cache = new Caching\Cache($storage, 'post');
        $this->user = $user;
    }


    /**
     * @param string $key
     * @param bool $publishedOnly
     * @param string $version
     * @return array|null
     */
    public function tryFindPostByKey($key, $publishedOnly = false, $version = self::CURRENT): ?array
    {
        try {
            return $this->getPostByKey($key, $publishedOnly, $version);
        } catch (PostNotFoundException $e) {
            return null;
        }
    }


    /**
     * @param string $key
     * @param bool $publishedOnly
     * @param string $version
     * @return array
     * @throws PostNotFoundException
     */
    public function getPostByKey($key, $publishedOnly = false, $version = self::CURRENT): array
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


    /**
     * @param bool $publishedOnly
     * @param string $version
     * @return array
     */
    public function getPosts($publishedOnly = false, $version = self::CURRENT): array
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


    /**
     * @param string $key
     * @param array $data
     * @return array
     */
    public function save($key, $data): array
    {
        $data = [
                'version' => self::CURRENT,
                'hash' => $this->getHash(),
                'created_at' => new \DateTime(),
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

        $current['version'] = (new \DateTime)->format(\DateTime::ATOM);

        $this->database->table(self::TABLE_NAME)
            ->insert($current);

        $this->cache->remove($key);

        return $current;
    }


    /**
     * @return string
     */
    private function getHash()
    {
        return \Nette\Utils\Random::generate(16);
    }


    /**
     * @param ActiveRow $row
     * @return array
     */
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


    /**
     * @param array $post
     * @return bool
     */
    public function isPublished($post): bool
    {
        if (empty($post['published_from'])) {
            return false;
        }

        if ($this->isPlanned($post) || $this->isExpired($post)) {
            return false;
        }

        return true;
    }


    /**
     * @param array $post
     * @return bool
     */
    public function isPlanned($post): bool
    {
        $current = new \DateTime();

        return (bool)$post['published_from'] &&
            $post['published_from'] > $current;
    }


    /**
     * @param array $post
     * @return bool
     */
    public function isExpired($post)
    {
        $current = new \DateTime();

        return (bool)$post['published_to'] &&
            $post['published_to'] < $current;
    }

}

