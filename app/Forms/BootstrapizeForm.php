<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;
use Nette\Forms\Controls;
use Nette\Forms\Rendering\DefaultFormRenderer;

class BootstrapizeForm
{
    public static function bootstrapize(Form $form): void
    {
        /** @var DefaultFormRenderer $renderer */
        $renderer = $form->getRenderer();
        $renderer->wrappers['error']['container'] = 'ul class="error alert alert-danger"';
        $renderer->wrappers['controls']['container'] = null;
        $renderer->wrappers['pair']['container'] = 'div class=mb-3';
        $renderer->wrappers['control']['container'] = 'div';
        $renderer->wrappers['label']['container'] = null;
        $renderer->wrappers['control']['.error'] = 'is-invalid';
        $renderer->wrappers['control']['description'] = 'div class=form-text';
        $renderer->wrappers['control']['errorcontainer'] = 'div class="invalid-feedback d-block"';

        $usedPrimary = false;
        foreach ($form->getControls() as $control) {
            if ($control instanceof Controls\Button) {
                $control->getControlPrototype()->addClass($usedPrimary ? 'btn btn-secondary' : 'btn btn-primary');
                $usedPrimary = true;
            } elseif ($control instanceof Controls\TextBase) {
                $control->getControlPrototype()->addClass('form-control');
                $control->getLabelPrototype()->addClass('form-label');
            } elseif ($control instanceof Controls\SelectBox
                || $control instanceof Controls\MultiSelectBox) {
                $control->getControlPrototype()->addClass('form-select');
                $control->getLabelPrototype()->addClass('form-label');
            } elseif ($control instanceof Controls\Checkbox) {
                $control->getContainerPrototype()->setName('div')->addClass('form-check');
                $control->getControlPrototype()->addClass('form-check-input');
                $control->getLabelPrototype()->addClass('form-check-label');
            } elseif ($control instanceof Controls\CheckboxList
                || $control instanceof Controls\RadioList
            ) {
                $control->getSeparatorPrototype()->setName('div')->addClass('form-check');
                $control->getControlPrototype()->addClass('form-check-input');
                $control->getItemLabelPrototype()->addClass('form-check-label');
            }
        }
    }
}
