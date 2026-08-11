<?php

namespace Solidarity\ContentEditor;

use League\Plates\Engine;
use Solidarity\ContentEditor\Contracts\BlockViewFilterInterface;
use Solidarity\ContentEditor\Exceptions\TemplateNotFoundException;

class View
{
    const TEMPLATE_DIR = __DIR__ . '/templates/';

    protected array $viewFilters = [];

    public function __construct(protected Engine $template, protected ?string $overrideTemplateDir = null)
    {
    }

    public function getView(array $data = []): string
    {
        $content = '';
        foreach($data as $block) {
            if(!isset($block['type'])) {
                continue;
            }
            if(isset($this->viewFilters[$block['type']])) {
                $block = $this->viewFilters[$block['type']]->filter($block);
            }
            $templatePath = self::TEMPLATE_DIR . $block['type'] . '.php';
            if($this->overrideTemplateDir) {
                $templatePath = $this->overrideTemplateDir . '/' . $block['type'] . '.php';
                if(!file_exists($templatePath)) {
                    $templatePath = self::TEMPLATE_DIR . $block['type'] . '.php';
                }
            }
            if(!file_exists($templatePath)) {
                throw new TemplateNotFoundException('Template not found: ' . $templatePath);
            }
            $originalDirectory = $this->template->getDirectory();
            $this->template->setDirectory($this->overrideTemplateDir ?? self::TEMPLATE_DIR);
            $content .= $this->template->render($block['type'], ['block' => $block]);
            $this->template->setDirectory($originalDirectory);
        }
        return $content;
    }
    public function registerViewFilter(string $name, BlockViewFilterInterface $blockViewFilter): void
    {
        $this->viewFilters[$name] = $blockViewFilter;
    }
}