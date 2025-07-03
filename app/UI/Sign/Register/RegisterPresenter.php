<?php

declare(strict_types=1);

namespace App\UI\Sign\Register;

use App\UI\Front\BasePresenter;
use App\UI\Sign\Register\NewAccountForm;
use Nette\Application\UI\Form;

final class RegisterPresenter extends BasePresenter
{
    public function __construct(
        private readonly NewAccountForm $newAccountForm,
    ) {
        parent::__construct();
    }

    public function actionNew(): void
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->flashMessage('🐬 Hey! You already swim with us!');
            $this->redirect(':Front:Home:default');
        }
    }

    protected function createComponentNewAccountForm(): Form
    {
        return $this->newAccountForm->create(function (Form $form, \stdClass $data): void {
            $this->flashMessage('✨ Congratulations! Your account has been created successfully 🥳', 'success');
            $this->redirect(':Front:Home:default');
        });
    }
}