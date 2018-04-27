<?php

namespace App\Model;

use Nette;
use Nette\Caching;

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
     * @param $key
     * @param bool $publishedOnly
     * @param string $version
     * @return bool|mixed|Nette\Database\Table\IRow|string
     */
    public function tryFindPostByKey($key, $publishedOnly = false, $version = self::CURRENT)
    {
        try {
            return $this->getPostByKey($key, $publishedOnly, $version);
        } catch (PostNotFoundException $e) {
            return '';
        }
    }


    /**
     * @param $key
     * @param bool $publishedOnly
     * @param string $version
     * @return bool|mixed|Nette\Database\Table\IRow
     * @throws PostNotFoundException
     */
    public function getPostByKey($key, $publishedOnly = false, $version = self::CURRENT)
    {
        $post = $this->database->table(self::TABLE_NAME)
            ->where(self::KEY_COLUMN, $key)
            ->where(self::VERSION_COLUMN, $version)
            ->fetch();

        if (!$post) {
            throw new PostNotFoundException("Post not found", 404);
        }

        $post = $this->preparePost($post);

        if ($publishedOnly && (false == $post['info']['isPublished'])) {

            throw new PostNotFoundException("Post not published", 403);
        }

        return $post;
    }


    /**
     * @param bool $publishedOnly
     * @param string $version
     * @return array
     */
    public function getPosts($publishedOnly = false, $version = self::CURRENT)
    {
        $selection = $this->database->table(self::TABLE_NAME)
            ->where(self::VERSION_COLUMN, $version);

        $posts = [];
        foreach ($selection as $post) {
            $post = $this->preparePost($post);

            if ($publishedOnly &&  (!$post['info']['isPublished'])) {
                continue;
            }

            $posts[$post['key']] = $post;
        }

        return $posts;
    }


    /**
     * @param $key
     * @param $data
     * @return mixed
     */
    public function save($key, $data)
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
     * @param $post
     * @return mixed
     */
    private function preparePost($post)
    {
        $post = $post->toArray();

        $post['info'] = [
            'isPublished' => $this->isPublished($post),
            'isPlanned' => $this->isPlanned($post),
            'isExpired' => $this->isExpired($post),
        ];

        return $post;
    }


    /**
     * @param $post
     * @return bool
     */
    public function isPublished($post)
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
     * @param $post
     * @return bool
     */
    public function isPlanned($post)
    {
        $current = new \DateTime();

        return (bool)$post['published_from'] &&
            $post['published_from'] > $current;
    }


    /**
     * @param $post
     * @return bool
     */
    public function isExpired($post)
    {
        $current = new \DateTime();

        return (bool)$post['published_to'] &&
            $post['published_to'] < $current;
    }

}

