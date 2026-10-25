<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Model\InvalidPasswordResetException;
use App\Model\PasswordReset;
use App\Model\UserManager;
use Nette\Database\Explorer;
use Nette\Security\Passwords;
use Nette\Security\SimpleIdentity;

$container = App\Bootstrap::boot()->createContainer();
$db = $container->getByType(Explorer::class);
$reset = $container->getByType(PasswordReset::class);
$passwords = $container->getByType(Passwords::class);
$users = $container->getByType(UserManager::class);

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rejected(callable $action): void
{
    try {
        $action();
    } catch (InvalidPasswordResetException) {
        return;
    }
    throw new RuntimeException('Invalid token was accepted');
}

if (($argv[1] ?? '') === 'worker') {
    $token = trim(stream_get_contents(STDIN));
    try {
        $reset->complete($token, 'Concurrent-reset-password!', $argv[2]);
        echo 'accepted';
    } catch (InvalidPasswordResetException) {
        echo 'rejected';
    }
    exit;
}

$suffix = bin2hex(random_bytes(8));
$email = 'reset-test-' . $suffix . '@example.test';
$ip = 'test-' . $suffix;
$row = $db->table('user')->insert([
    'email' => $email, 'name' => 'Reset integration test', 'role' => 'admin',
    'password' => $passwords->hash('Original-test-password!'),
]);
$id = $row->id;
$request = fn(string $email, string $ip): ?string => $reset->request($email, $ip)['token'] ?? null;
$keys = array_map(fn(string $scope): string => hash('sha256', $scope), [
    'request-ip:' . $ip, 'verify-ip:' . $ip, 'request-account:' . $id,
    'request-account:missing-' . $email,
]);
$refresh = function () use ($db, $id, $keys): void {
    $db->table('user')->wherePrimary($id)->update(['reset_requested_at' => null]);
    $db->table('password_reset_limit')->where('key', $keys)->delete();
};
try {
    $identity = $users->authenticate($email, 'Original-test-password!');
    check($users->isIdentityCurrent($identity), 'Current identity rejected');
    check(!$users->isIdentityCurrent(new SimpleIdentity($id, 'admin')), 'Legacy identity accepted');
    $issued = $reset->request(strtoupper($email), $ip);
    check($issued['email'] === $email, 'Reset recipient must use the stored address');
    $token = $issued['token'];
    check(is_string($token) && strlen($token) === 97, 'Token format');
    check($request($email, $ip) === null, 'Cooldown did not apply');
    check($request('missing-' . $email, $ip) === null, 'Unknown account accepted');
    $stored = $db->table('user')->get($id);
    check(strlen($stored->reset_hash) === 32 && !str_contains($token, bin2hex($stored->reset_hash)), 'Secret storage');
    check(!array_key_exists('reset_hash', $identity->getData()), 'Reset data in identity');
    rejected(fn() => $reset->verify('malformed', $ip));
    rejected(fn() => $reset->verify(substr($token, 0, 33) . str_repeat('0', 64), $ip));
    $reset->verify($token, $ip);
    $db->table('user')->wherePrimary($id)->update(['reset_expires_at' => gmdate('Y-m-d H:i:s', time() - 1)]);
    rejected(fn() => $reset->complete($token, 'Expired-test-password!', $ip));
    $refresh();
    $old = $request($email, $ip);
    $refresh();
    $token = $request($email, $ip);
    rejected(fn() => $reset->verify($old, $ip));
    $reset->cancel($token, $ip);
    rejected(fn() => $reset->verify($token, $ip));
    $refresh();
    $token = $request($email, $ip);
    check($reset->complete($token, 'Changed-test-password!', $ip) === $email, 'Wrong account');
    rejected(fn() => $reset->complete($token, 'Reused-test-password!', $ip));
    check(!$users->isIdentityCurrent($identity), 'Old session survived');
    $users->authenticate($email, 'Changed-test-password!');
    $stored = $db->table('user')->get($id);
    check($stored->reset_hash === null && $stored->reset_selector === null && $stored->reset_expires_at === null, 'Token not cleared');

    $refresh();
    for ($i = 0; $i < 5; $i++) {
        $db->table('user')->wherePrimary($id)->update(['reset_requested_at' => null]);
        check($request($email, $ip) !== null, 'Account limit too early');
    }
    check($request($email, $ip) === null, 'Account limit missing');
    $refresh();
    $token = $request($email, $ip);
    for ($i = 0; $i < 60; $i++) {
        $reset->verify($token, $ip);
    }
    rejected(fn() => $reset->verify($token, $ip));

    $refresh();
    for ($i = 0; $i < 20; $i++) {
        $db->table('user')->wherePrimary($id)->update(['reset_requested_at' => null]);
        $db->table('password_reset_limit')->wherePrimary(hash('sha256', 'request-account:' . $id))->delete();
        check($request($email, $ip) !== null, 'IP limit too early');
    }
    check($request($email, $ip) === null, 'IP limit missing');

    $refresh();
    $token = $request($email, $ip);
    $workers = [];
    for ($i = 0; $i < 2; $i++) {
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $ip], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        check(is_resource($process), 'Cannot start worker');
        fwrite($pipes[0], $token);
        fclose($pipes[0]);
        $workers[] = [$process, $pipes];
    }
    $results = [];
    foreach ($workers as [$process, $pipes]) {
        $results[] = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        check(proc_close($process) === 0, 'Worker failed: ' . $errors);
    }
    sort($results);
    check($results === ['accepted', 'rejected'], 'Concurrent reset not single-use: ' . implode(', ', $results));
    echo "OK: token storage, expiry, replacement, cancellation, replay, limits, sessions and concurrent reset\n";
} finally {
    $db->table('user')->wherePrimary($id)->delete();
    $db->table('password_reset_limit')->where('key', $keys)->delete();
}
