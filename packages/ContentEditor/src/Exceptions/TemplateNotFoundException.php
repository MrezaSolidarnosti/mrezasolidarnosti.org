<?php

namespace Solidarity\ContentEditor\Exceptions;

class TemplateNotFoundException extends \Exception
{
    public function __construct($message = 'Template not found')
    {
        parent::__construct($message);
    }
}