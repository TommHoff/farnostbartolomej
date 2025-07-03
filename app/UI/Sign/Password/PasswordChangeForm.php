<?php

declare(strict_types=1);

namespace App\UI\Sign\Password;

use App\Forms\FormFactory;
use App\Model\User\UserFacade;
use Nette\Application\UI\Form;
use Nette\Security\Passwords;
use Nette\Security\User;

class PasswordChangeForm
{
    private const PASSWORD_MIN_LENGTH = 9;

    public function __construct(
        private readonly FormFactory $factory,
        private readonly User $user,
        private readonly Passwords $passwords,
        private readonly UserFacade $userFacade
    ) {}

    public function create(callable $onSuccess, ?string $token = null): Form
    {
        $form = $this->factory->create();

        $form->addPassword('password', 'New Password:')
            ->setRequired('Please enter your new password.')
            ->addRule(Form::MIN_LENGTH, 'Password must be at least %d characters long.', self::PASSWORD_MIN_LENGTH)
            ->setHtmlAttribute('class', 'form-control');

        // Token hidden field
        if ($token) {
            $form->addHidden('token', $token);
        }

        $form->addSubmit('changePassword', 'Change Password')
            ->setHtmlAttribute('class', 'btn btn-light mt-3');

        $form->onSuccess[] = function (Form $form, \stdClass $values) use ($onSuccess, $token): void {
            $onSuccess($form, $values, $token);
        };

        return $form;
    }
}