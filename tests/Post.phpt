<?php

declare(strict_types=1);

/** @testCase */

use App\Entity\Post;
use App\Model\PostManager;
use App\Model\PostNotFoundException;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\DI\Container;
use Nette\Security\SimpleIdentity;
use Nette\Security\User;
use Tester\Assert;
use Tester\TestCase;

$container = require __DIR__ . '/bootstrap.php';

final class PostTest extends TestCase
{
    private Explorer $db;
    private PostManager $posts;
    private string $key;

    public function __construct(
        private readonly Container $container,
    ) {
    }

    protected function setUp(): void
    {
        $this->db = $this->container->getByType(Explorer::class);
        $this->posts = $this->container->getByType(PostManager::class);
        $this->container->getByType(User::class)->login(new SimpleIdentity(1, 'admin'));
        $this->key = 'test' . bin2hex(random_bytes(6));
        $this->db->table('post')->insert([
            'key' => $this->key, 'version' => 'current', 'hash' => 'original',
            'title' => 'Test post', 'content_cs' => 'Český text', 'content_en' => 'English text',
            'published_from' => '2020-01-01 00:00:00', 'published_to' => '2099-01-01 00:00:00',
            'created_by' => 1, 'tech_note' => null,
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->table('post')->where('key', $this->key)->delete();
        new Cache($this->container->getByType(Storage::class), 'post-entity-v1')->remove($this->key);
        $this->container->getByType(User::class)->logout(true);
    }

    public function testHydrationAndPartialUpdate(): void
    {
        $original = $this->posts->getPostByKey($this->key);
        Assert::type(Post::class, $original);
        Assert::type(DateTimeImmutable::class, $original->createdAt);
        Assert::null($original->techNote);
        Assert::same($this->key, $this->posts->getPosts()[$this->key]->key);

        $changes = new Post;
        $changes->contentCs = 'Změna';
        $changes->publishedTo = null;
        $changes->key = 'ignored';
        $changes->createdBy = 999;
        $changes->version = 'ignored';
        $snapshot = $this->posts->save($this->key, $changes);
        $current = $this->posts->getPostByKey($this->key);
        Assert::same('Změna', $current->contentCs);
        Assert::same('English text', $current->contentEn);
        Assert::null($current->publishedTo);
        Assert::same(1, $current->createdBy);
        Assert::same('current', $current->version);
        Assert::same($this->key, $snapshot->key);
        Assert::notSame('current', $snapshot->version);
        Assert::same($current->hash, $snapshot->hash);
        Assert::same('Změna', $this->posts->getPostByKey($this->key, version: $snapshot->version)->contentCs);
        Assert::same(2, $this->db->table('post')->where('key', $this->key)->count('*'));
        Assert::same('Český text', $original->contentCs);
    }

    public function testEmptyUpdateAndRapidHistory(): void
    {
        $this->posts->save($this->key, new Post);
        Assert::same(1, $this->db->table('post')->where('key', $this->key)->count('*'));
        $changes = new Post;
        $changes->contentEn = 'First';
        $first = $this->posts->save($this->key, $changes);
        $changes->contentEn = 'Second';
        $second = $this->posts->save($this->key, $changes);
        Assert::notSame($first->version, $second->version);
        Assert::same(3, $this->db->table('post')->where('key', $this->key)->count('*'));
        Assert::same('First', $this->posts->getPostByKey($this->key, version: $first->version)->contentEn);
        Assert::same('Second', $this->posts->getPostByKey($this->key)->contentEn);
    }

    public function testCacheInvalidation(): void
    {
        Assert::same('Český text', $this->posts->getCachedPublishedPost($this->key)->contentCs);
        $changes = new Post;
        $changes->contentCs = 'Updated';
        $this->posts->save($this->key, $changes);
        Assert::same('Updated', $this->posts->getCachedPublishedPost($this->key)->contentCs);
        $changes = new Post;
        $changes->publishedFrom = null;
        $this->posts->save($this->key, $changes);
        Assert::null($this->posts->getCachedPublishedPost($this->key));
        $changes->publishedFrom = new DateTimeImmutable('2020-01-01');
        $this->posts->save($this->key, $changes);
        Assert::type(Post::class, $this->posts->getCachedPublishedPost($this->key));
    }

    public function testMissingPost(): void
    {
        $this->db->table('post')->where('key', $this->key)->delete();
        Assert::null($this->posts->tryFindPostByKey($this->key));
        $changes = new Post;
        $changes->title = 'Missing';
        Assert::exception(fn() => $this->posts->save($this->key, $changes), PostNotFoundException::class);
        Assert::same(0, $this->db->table('post')->where('key', $this->key)->count('*'));
    }

    /** @dataProvider publicationCases */
    public function testPublication(?string $from, ?string $to, bool $published, bool $planned, bool $expired): void
    {
        $post = new Post;
        $post->publishedFrom = $from === null ? null : new DateTimeImmutable($from);
        $post->publishedTo = $to === null ? null : new DateTimeImmutable($to);
        $now = new DateTimeImmutable('2026-01-01 12:00:00');
        Assert::same($published, $post->isPublished($now));
        Assert::same($planned, $post->isPlanned($now));
        Assert::same($expired, $post->isExpired($now));
    }

    public function publicationCases(): array
    {
        return [
            [null, null, false, false, false],
            ['2026-01-01 12:00:00', null, true, false, false],
            ['2026-01-01 12:00:01', null, false, true, false],
            ['2020-01-01', '2026-01-01 12:00:00', true, false, false],
            ['2020-01-01', '2026-01-01 11:59:59', false, false, true],
        ];
    }
}

new PostTest($container)->run();
