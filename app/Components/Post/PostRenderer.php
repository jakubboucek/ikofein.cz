<?php

declare(strict_types=1);

namespace App\Component;

use App\Model;
use Nette\Application\ApplicationException;
use Nette\Application\UI\Control;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Caching\Cache;
use Nette\Caching\Storage;

class PostRenderer extends Control
{
    private readonly Model\Post $postModel;
    private readonly Cache $cache;


    public function __construct(Model\Post $postModel, Storage $storage)
    {
        $this->postModel = $postModel;
        $this->cache = new Cache($storage, 'post');
    }


    /**
     * @throws ApplicationException
     */
    public function render(string $key, string $lang, string $templateName = 'post'): void
    {
        $postInfo = $this->getPost($key);
        $post = $postInfo['post'];

        $template = $this->getTemplateFile($templateName);

        $template->isEmpty = $postInfo['isEmpty'];

        $field = 'content_' . $lang;

        $content = '';
        if ($post && isset($post[$field])) {
            $content = $post[$field];
        }
        $template->content = $content;

        $template->render();
    }


    private function getPost(string $key): array
    {
        return $this->cache->load($key, function (&$dependencies) use ($key) {
            $dependencies = [
                Cache::Expire => '20 minutes',
            ];

            $post = $this->postModel->tryFindPostByKey($key, true);
            return [
                'post' => $post,
                'isEmpty' => $post === null
            ];
        });
    }


    /**
     * @throws ApplicationException
     */
    private function getTemplateFile(string $templateName): Template
    {
        if (!preg_match('/^[-.a-z0-9]+$/i', $templateName)) {
            throw new ApplicationException('Invalid template name: ' . $templateName);
        }

        $filename = __DIR__ . '/' . $templateName . '.latte';
        if (!file_exists($filename)) {
            throw new ApplicationException("Template \"$filename\" doesn't exists");
        }

        /** @var Template $template */
        $template = $this->getTemplate();
        $template->setFile($filename);
        return $template;
    }
}
