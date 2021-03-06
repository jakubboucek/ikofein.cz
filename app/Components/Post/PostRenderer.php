<?php

declare(strict_types=1);

namespace App\Component;

use App\Model;
use Nette\Application\UI\Control;
use Nette\Caching;

class PostRenderer extends Control
{
    /**
     * @var Model\Post
     */
    private $postModel;
    /**
     * @var Caching\Cache
     */
    private $cache;


    /**
     * PostRenderer constructor.
     * @param Model\Post $postModel
     * @param Caching\IStorage $storage
     */
    public function __construct(Model\Post $postModel, Caching\IStorage $storage)
    {
        $this->postModel = $postModel;
        $this->cache = new Caching\Cache($storage, 'post');
    }


    /**
     * @param string $key
     * @param string $lang
     * @param string $templateName
     * @throws \Nette\Application\ApplicationException
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


    /**
     * @param string $key
     * @return array|null
     */
    private function getPost(string $key): array
    {
        return $this->cache->load($key, function (& $dependencies) use ($key) {
            $dependencies = [
                Caching\Cache::EXPIRE => '20 minutes',
            ];

            $post = $this->postModel->tryFindPostByKey($key, true);
            return [
                'post' => $post,
                'isEmpty' => $post === null
            ];
        });
    }


    /**
     * @param string $templateName
     * @return \Nette\Bridges\ApplicationLatte\Template
     * @throws \Nette\Application\ApplicationException
     */
    private function getTemplateFile(string $templateName): \Nette\Bridges\ApplicationLatte\Template
    {
        if (!preg_match('/^[-.a-z0-9]+$/i', $templateName)) {
            throw new \Nette\Application\ApplicationException('Invalid template name: ' . $templateName);
        }

        $filename = __DIR__ . '/' . $templateName . '.latte';
        if (!file_exists($filename)) {
            throw new \Nette\Application\ApplicationException("Template \"$filename\" doesn't exists");
        }

        /** @var \Nette\Bridges\ApplicationLatte\Template $template */
        $template = $this->getTemplate();
        $template->setFile($filename);
        return $template;
    }
}
