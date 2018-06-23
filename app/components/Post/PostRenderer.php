<?php

namespace App\Component;

use App\Model;
use Nette\Application\UI\Control;
use Nette\Caching;
use Template;

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
    public function render($key, $lang, $templateName = 'post'): void
    {
        $post = $this->getPost($key);

        $template = $this->getTemplateFile($templateName);

        $template->isEmpty = ($post === null);

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
    private function getPost($key): ?array
    {
        return $this->cache->load($key, function (& $dependencies) use ($key) {
            $dependencies = [
                Caching\Cache::EXPIRE => '20 minutes',
            ];

            return $this->postModel->tryFindPostByKey($key, true);
        });
    }


    /**
     * @param string $templateName
     * @return \Nette\Bridges\ApplicationLatte\Template
     * @throws \Nette\Application\ApplicationException
     */
    private function getTemplateFile($templateName): \Nette\Bridges\ApplicationLatte\Template
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
