<?php

declare(strict_types=1);

/** @testCase */

use App\Forms\PostEditFormData;
use App\Forms\SetPasswordFormData;
use App\Forms\SignInFormData;
use Nette\Application\IPresenterFactory;
use Nette\DI\Container;
use Tester\Assert;
use Tester\TestCase;

$container = require __DIR__ . '/bootstrap.php';

final class FormDataTest extends TestCase
{
    public function __construct(
        private readonly Container $container,
    ) {
    }

    /** @dataProvider forms */
    public function testMapping(string $presenterName, string $factory, string $dataClass, array $values): void
    {
        $presenter = $this->container->getByType(IPresenterFactory::class)->createPresenter($presenterName);
        $form = $presenter->$factory();
        $form->setValues($values);
        $handler = new ReflectionFunction($form->onSuccess[0]);
        Assert::same($dataClass, $handler->getParameters()[1]->getType()->getName());

        // Exercise Nette's mapping of actual controls without invoking database writes or redirects.
        $data = $form->getUntrustedValues($dataClass);
        Assert::type($dataClass, $data);
        Assert::equal($values, (array) $data);
    }

    public function forms(): array
    {
        return [
            ['Admin:Post', 'createComponentPostEditForm', PostEditFormData::class, [
                'key' => 'lunch', 'published' => false, 'published_from' => '', 'published_to' => '',
                'content_cs' => '', 'content_en' => 'English content',
            ]],
            ['Admin:Sign', 'createComponentSignInForm', SignInFormData::class, [
                'email' => 'form-test@example.test', 'password' => 'Test-password!', 'remember' => false,
            ]],
            ['Admin:Sign', 'createComponentSetPasswordForm', SetPasswordFormData::class, [
                'token' => 'test-token', 'password' => 'Long-test-password!', 'password2' => 'Long-test-password!',
            ]],
        ];
    }
}

new FormDataTest($container)->run();
