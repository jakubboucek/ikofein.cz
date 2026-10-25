<?php

declare(strict_types=1);

/** @testCase */

use App\Model\InvalidPasswordResetException;
use App\Model\PasswordReset;
use App\Model\UserManager;
use Nette\Database\Explorer;
use Nette\DI\Container;
use Nette\Security\Passwords;
use Nette\Security\SimpleIdentity;
use Tester\Assert;
use Tester\HttpAssert;
use Tester\TestCase;

$container = require __DIR__ . '/bootstrap.php';

final class PasswordResetTest extends TestCase
{
    private Explorer $db;
    private PasswordReset $reset;
    private UserManager $users;
    private int $id;
    private string $email;
    private string $ip;
    private array $keys;

    public function __construct(
        private readonly Container $container,
    ) {
    }

    protected function setUp(): void
    {
        $this->db = $this->container->getByType(Explorer::class);
        $this->reset = $this->container->getByType(PasswordReset::class);
        $this->users = $this->container->getByType(UserManager::class);
        $suffix = bin2hex(random_bytes(8));
        $this->email = 'reset-test-' . $suffix . '@example.test';
        $this->ip = 'test-' . $suffix;
        $row = $this->db->table('user')->insert([
            'email' => $this->email, 'name' => 'Reset integration test', 'role' => 'admin',
            'password' => $this->container->getByType(Passwords::class)->hash('Original-test-password!'),
        ]);
        $this->id = $row->id;
        $this->keys = array_map(fn(string $scope): string => hash('sha256', $scope), [
            'request-ip:' . $this->ip, 'verify-ip:' . $this->ip,
            'request-account:' . $this->id, 'request-account:missing-' . $this->email,
            'verify-ip:127.0.0.1',
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->table('user')->wherePrimary($this->id)->delete();
        $this->db->table('password_reset_limit')->where('key', $this->keys)->delete();
    }

    private function issue(): string
    {
        $request = $this->reset->request($this->email, $this->ip);
        Assert::notNull($request);
        return $request['token'];
    }

    private function clearCooldown(): void
    {
        $this->db->table('user')->wherePrimary($this->id)->update(['reset_requested_at' => null]);
    }

    public function testReadableStorageAndCanonicalRecipient(): void
    {
        $issued = $this->reset->request(strtoupper($this->email), $this->ip);
        Assert::same($this->email, $issued['email']);
        Assert::match('~\A[A-Za-z0-9_-]{22}\.[A-Za-z0-9_-]{43}\z~', $issued['token']);
        [$selector, $verifier] = explode('.', $issued['token']);
        $row = $this->db->table('user')->get($this->id);
        Assert::same($selector, $row->reset_selector);
        Assert::match('~\A[A-Za-z0-9_-]{43}\z~', $row->reset_hash);
        Assert::notSame($verifier, $row->reset_hash);
        $bytes = base64_decode(strtr($verifier, '-_', '+/'), true);
        Assert::same(rtrim(strtr(base64_encode(hash('sha256', $bytes, true)), '+/', '-_'), '='), $row->reset_hash);
        Assert::noError(fn() => $this->reset->verify($issued['token'], $this->ip));
    }

    public function testCooldownAndUnknownAccount(): void
    {
        $this->issue();
        Assert::null($this->reset->request($this->email, $this->ip));
        Assert::null($this->reset->request('missing-' . $this->email, $this->ip));
    }

    public function invalidTokens(): array
    {
        return [
            [''], ['malformed'], [str_repeat('a', 32) . '.' . str_repeat('a', 64)],
            [str_repeat('A', 22) . '=.' . str_repeat('A', 43)],
            [str_repeat('A', 22) . '.' . str_repeat('+', 43)],
            [str_repeat('A', 21) . 'B.' . str_repeat('A', 43)],
            [str_repeat('A', 22) . '.' . str_repeat('A', 42) . 'B'],
            [str_repeat('A', 22) . '.' . str_repeat('A', 43) . "\n"],
        ];
    }

    /** @dataProvider invalidTokens */
    public function testMalformedTokens(string $token): void
    {
        Assert::exception(fn() => $this->reset->verify($token, $this->ip), InvalidPasswordResetException::class);
    }

    public function testWrongVerifierDoesNotInvalidateToken(): void
    {
        $token = $this->issue();
        [$selector] = explode('.', $token);
        Assert::exception(fn() => $this->reset->verify($selector . '.' . str_repeat('A', 43), $this->ip), InvalidPasswordResetException::class);
        Assert::noError(fn() => $this->reset->verify($token, $this->ip));
    }

    public function tokenParts(): array
    {
        return [[0], [1]];
    }

    /** @dataProvider tokenParts */
    public function testNonCanonicalEncoding(int $part): void
    {
        $token = $this->issue();
        $parts = explode('.', $token);
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
        $last = strlen($parts[$part]) - 1;
        $index = strpos($alphabet, $parts[$part][$last]);
        $parts[$part][$last] = $alphabet[$index + 1];
        Assert::exception(fn() => $this->reset->verify(implode('.', $parts), $this->ip), InvalidPasswordResetException::class);
        Assert::noError(fn() => $this->reset->verify($token, $this->ip));
    }

    public function expirationOffsets(): array
    {
        return [[-3600], [-1], [0]];
    }

    /** @dataProvider expirationOffsets */
    public function testExpiredToken(int $offset): void
    {
        $token = $this->issue();
        $this->db->table('user')->wherePrimary($this->id)->update(['reset_expires_at' => gmdate('Y-m-d H:i:s', time() + $offset)]);
        Assert::exception(fn() => $this->reset->complete($token, 'Expired-test-password!', $this->ip), InvalidPasswordResetException::class);
        Assert::noError(fn() => $this->users->authenticate($this->email, 'Original-test-password!'));
    }

    public function testReplacement(): void
    {
        $old = $this->issue();
        $this->clearCooldown();
        $token = $this->issue();
        Assert::exception(fn() => $this->reset->verify($old, $this->ip), InvalidPasswordResetException::class);
        Assert::noError(fn() => $this->reset->verify($token, $this->ip));
    }

    public function testCancellation(): void
    {
        $token = $this->issue();
        $this->reset->cancel($token, $this->ip);
        Assert::exception(fn() => $this->reset->verify($token, $this->ip), InvalidPasswordResetException::class);
        Assert::noError(fn() => $this->users->authenticate($this->email, 'Original-test-password!'));
    }

    public function testCompletionRevokesSessionsAndPreventsReplay(): void
    {
        $identity = $this->users->authenticate($this->email, 'Original-test-password!');
        Assert::true($this->users->isIdentityCurrent($identity));
        Assert::false($this->users->isIdentityCurrent(new SimpleIdentity($this->id, 'admin')));
        foreach (['password', 'reset_hash', 'reset_selector', 'reset_expires_at', 'reset_requested_at'] as $field) {
            Assert::hasNotKey($field, $identity->getData());
        }
        $token = $this->issue();
        Assert::same($this->email, $this->reset->complete($token, 'Changed-test-password!', $this->ip));
        Assert::exception(fn() => $this->reset->complete($token, 'Reused-test-password!', $this->ip), InvalidPasswordResetException::class);
        Assert::false($this->users->isIdentityCurrent($identity));
        Assert::noError(fn() => $this->users->authenticate($this->email, 'Changed-test-password!'));
        $row = $this->db->table('user')->get($this->id);
        Assert::null($row->reset_hash);
        Assert::null($row->reset_selector);
        Assert::null($row->reset_expires_at);
    }

    public function requestLimits(): array
    {
        return [[5, false], [20, true]];
    }

    /** @dataProvider requestLimits */
    public function testRequestLimits(int $limit, bool $clearAccountLimit): void
    {
        for ($i = 0; $i < $limit; $i++) {
            $this->clearCooldown();
            if ($clearAccountLimit) {
                $this->db->table('password_reset_limit')->wherePrimary(hash('sha256', 'request-account:' . $this->id))->delete();
            }
            $this->issue();
        }
        Assert::null($this->reset->request($this->email, $this->ip));
    }

    public function testVerificationLimit(): void
    {
        $token = $this->issue();
        for ($i = 0; $i < 60; $i++) {
            $this->reset->verify($token, $this->ip);
        }
        Assert::exception(fn() => $this->reset->verify($token, $this->ip), InvalidPasswordResetException::class);
    }

    public function testConcurrentCompletion(): void
    {
        $token = $this->issue();
        $workers = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $process = proc_open([PHP_BINARY, __DIR__ . '/helpers/reset-worker.php', $this->ip], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
                Assert::type('resource', $process);
                $workers[] = [$process, $pipes];
                Assert::same("ready\n", fgets($pipes[1]), 'Worker must finish bootstrapping before the concurrent reset starts.');
            }
            foreach ($workers as [$process, $pipes]) {
                fwrite($pipes[0], $token);
                fclose($pipes[0]);
            }
            $results = [];
            foreach ($workers as [$process, $pipes]) {
                $results[] = stream_get_contents($pipes[1]);
                $errors = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                Assert::same(0, proc_close($process), $errors);
            }
            sort($results);
            Assert::same(['accepted', 'rejected'], $results);
        } finally {
            foreach ($workers as [$process, $pipes]) {
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
            }
        }
    }

    /** @dataProvider formOrigins */
    public function testCancellationFormOrigin(?string $site, bool $legacyCookie, bool $accepted): void
    {
        $token = $this->issue();
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
        if ($site !== null) {
            $headers['Sec-Fetch-Site'] = $site;
        }
        HttpAssert::fetch(
            'http://localhost/admin/sign/cancel-reset?token=' . rawurlencode($token),
            method: 'POST',
            headers: $headers,
            cookies: $legacyCookie ? ['_nss' => '1'] : [],
            body: http_build_query(['token' => $token, '_do' => 'cancelResetForm-submit', 'send' => 'Cancel']),
        )->expectCode(303);

        $selector = $this->db->table('user')->get($this->id)->reset_selector;
        Assert::same($accepted ? null : explode('.', $token)[0], $selector);
    }

    protected function formOrigins(): array
    {
        return [
            'same origin' => ['same-origin', false, true],
            'same site is not same origin' => ['same-site', true, false],
            'cross site' => ['cross-site', true, false],
            'direct POST' => ['none', true, false],
            'invalid metadata' => ['invalid', true, false],
            'legacy browser with strict cookie' => [null, true, true],
            'legacy browser without strict cookie' => [null, false, false],
        ];
    }

    public function testEmailNavigationDoesNotConsumeToken(): void
    {
        $token = $this->issue();
        foreach (['change-password', 'cancel-reset'] as $action) {
            HttpAssert::fetch(
                'http://localhost/admin/sign/' . $action . '?token=' . rawurlencode($token),
                headers: ['Sec-Fetch-Site' => 'cross-site', 'Sec-Fetch-Mode' => 'navigate'],
            )->expectCode(200)->expectHeader('Cache-Control', 'no-store')->expectHeader('Referrer-Policy', 'no-referrer');
            Assert::same(explode('.', $token)[0], $this->db->table('user')->get($this->id)->reset_selector);
        }
    }
}

(new PasswordResetTest($container))->run();
