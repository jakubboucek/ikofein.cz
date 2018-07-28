<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;
use Nette\Forms\Controls;
use Nette\Forms\Rendering\DefaultFormRenderer;

class BootstrapizeForm
{
    /**
     * @param Form $form
     */
    public static function bootstrapize(Form $form): void
    {
        /** @var DefaultFormRenderer $renderer */
        $renderer = $form->getRenderer();
        $renderer->wrappers['error']['container'] = 'ul class="error alert alert-danger"';
        $renderer->wrappers['controls']['container'] = null;
        $renderer->wrappers['pair']['container'] = 'div class=form-group';
        $renderer->wrappers['pair']['.error'] = 'has-error';
        $renderer->wrappers['control']['container'] = 'div class=col-sm-9';
        $renderer->wrappers['label']['container'] = 'div class="col-sm-3 control-label"';
        $renderer->wrappers['control']['description'] = 'span class=help-block';
        $renderer->wrappers['control']['errorcontainer'] = 'span class=help-block';

        // make form and controls compatible with Twitter Bootstrap
        $form->getElementPrototype()->class('form-horizontal');

        foreach ($form->getControls() as $control) {
            if ($control instanceof Controls\Button) {
                $control->getControlPrototype()->addClass(empty($usedPrimary) ? 'btn btn-primary' : 'btn btn-default');
                $usedPrimary = true;
            } elseif ($control instanceof Controls\TextBase
                || $control instanceof Controls\SelectBox
                || $control instanceof Controls\MultiSelectBox) {
                $control->getControlPrototype()->addClass('form-control');
            } elseif ($control instanceof Controls\Checkbox
                || $control instanceof Controls\CheckboxList
                || $control instanceof Controls\RadioList
            ) {
                $control->getSeparatorPrototype()
                    ->setName('div')->addClass($control->getControlPrototype()->type);
            }
        }
    }
}
