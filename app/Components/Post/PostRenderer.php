<?php

declare(strict_types=1);

namespace App\Component;

use App\Model;
use Nette\Application\ApplicationException;
use Nette\Application\UI\Control;
use Nette\Bridges\ApplicationLatte\DefaultTemplate;
use Nette\Bridges\ApplicationLatte\Template;

class PostRenderer extends Control
{
    public function __construct(
        private readonly Model\PostManager $postManager,
    ) {
    }


    /**
     * @throws ApplicationException
     */
    public function render(string $key, string $lang, string $templateName = 'post'): void
    {
        $post = $this->postManager->getCachedPublishedPost($key);

        /** @var DefaultTemplate $template */
        $template = $this->getTemplateFile($templateName);

        $template->isEmpty = $post === null;

        $template->content = $post?->getContent($lang) ?? '';

        $template->render();
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
