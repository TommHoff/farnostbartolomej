<?php

declare(strict_types=1);

namespace App\Forms;

use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Enums\RenderMode;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;

class FormFactory
{
    public function create(): BootstrapForm
    {
        // Switch to Bootstrap 5
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form = new BootstrapForm;
        $form->renderMode = RenderMode::VERTICAL_MODE;

        return $form;
    }
}
