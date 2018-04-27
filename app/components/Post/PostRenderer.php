<?php

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
     * @param $key
     * @param $lang
     * @param string $template
     * @throws \Nette\Application\ApplicationException
     */
    public function render($key, $lang, $template = 'post')
    {
        $post = $this->getPost($key);


        $template = $this->getTemplateFile($template);

        $template->isEmpty = empty($post);

        $content = '';
        if (isset($post['content_' . $lang])) {
            $content = $post['content_' . $lang];
        }
        $template->content = $content;

        $template->render();
    }


    /**
     * @param $key
     * @return mixed
     */
    private function getPost($key)
    {
        return $this->cache->load($key, function (& $dependencies) use ($key) {
            $dependencies = [
                Caching\Cache::EXPIRE => '20 minutes',
            ];

            $post = $this->postModel->tryFindPostByKey($key, true);

            return $post;
        });
    }


    /**
     * @param $templateName
     * @return \Nette\Application\UI\ITemplate|\Nette\Bridges\ApplicationLatte\Template|\stdClass
     * @throws \Nette\Application\ApplicationException
     */
    private function getTemplateFile($templateName)
    {
        if (!preg_match('/^[-.a-z0-9]+$/i', $templateName)) {
            throw new \Nette\Application\ApplicationException('Invalid template name: ' . $templateName);
        }

        $filename = __DIR__ . '/' . $templateName . '.latte';
        if (!file_exists($filename)) {
            throw new \Nette\Application\ApplicationException("Template \"$filename\" doesn't exists");
        }

        $template = $this->template;
        $template->setFile($filename);
        return $template;
    }
}
