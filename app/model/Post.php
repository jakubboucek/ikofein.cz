<?php

namespace App\Model;

use Nette,
	Nette\Caching;


/**
 * Users management.
 */
class Post
{

	const CURRENT = 'current';
	const TABLE_NAME = 'post';
	const KEY_COLUMN = 'key';
	const VERSION_COLUMN = 'version';


	/** @var Nette\Database\Context */
	private $database;
	private $cache;
	private $user;


	public function __construct(Nette\Database\Context $database, Caching\IStorage $storage, Nette\Security\User $user)
	{
		$this->database = $database;
		$this->cache = new Caching\Cache($storage, 'post');
		$this->user = $user;
	}


	public function tryFindPostByKey( $key, $publishedOnly = FALSE, $version = self::CURRENT ) {
		try {
			return $this->getPostByKey( $key, $publishedOnly, $version );
		}
		catch(PostNotFoundException $e) {
			return NULL;
		}
	}


	public function getPostByKey( $key, $publishedOnly = FALSE, $version = self::CURRENT ) {
		$post = $this->database->table(self::TABLE_NAME)
						->where(self::KEY_COLUMN, $key)
						->where(self::VERSION_COLUMN, $version)
						->fetch();

		if( !$post ) {
			throw new PostNotFoundException("Post not found", 404);
		}

		$post = $this->preparePost($post);

		if( $publishedOnly && (FALSE == $post['info']['isPublished']) ) {

			throw new PostNotFoundException("Post not published", 403);
		}

		return $post;
	}


	public function getPosts( $publishedOnly = FALSE, $version = self::CURRENT ) {
		$selection = $this->database->table(self::TABLE_NAME)
						->where(self::VERSION_COLUMN, $version);

		$posts = [];
		foreach($selection as $post) {
			$post = $this->preparePost($post);

			if( $publishedOnly && (! $post['info']['isPublished'] ) ) {
				continue;
			}

			$posts[$post['key']] = $post;
		}

		return $posts;
	}


	public function save($key, $data) {
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

	private function getHash() {
		return \Nette\Utils\Random::generate(16);
	}

	private function preparePost($post) {
		$post = $post->toArray();

		$post['info'] = [
			'isPublished' => $this->isPublished($post),
			'isPlanned' => $this->isPlanned($post),
			'isExpired' => $this->isExpired($post),
		];

		return $post;
	}

	public function isPublished( $post ) {
		if( empty( $post['published_from'] ) ) {
			return FALSE;
		}

		if( $this->isPlanned($post) || $this->isExpired($post) ) {
			return FALSE;
		}

		return TRUE;
	}

	public function isPlanned( $post ) {
		$current = new \DateTime();

		return (bool) $post['published_from'] &&
			$post['published_from'] > $current;
	}

	public function isExpired( $post ) {
		$current = new \DateTime();

		return (bool) $post['published_to'] &&
			$post['published_to'] < $current;
	}

}

class PostException extends \Exception
{}

class PostNotFoundException extends PostException
{}
