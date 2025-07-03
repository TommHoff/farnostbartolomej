<?php

declare(strict_types=1);

namespace App\UI\Sign\Auth;

use App\Forms\FormFactory;
use Nette\Application\UI\Form;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\User;
use Nette\Utils\Html;
use Tracy\Debugger;
use Tracy\ILogger;

final class SignForm
{
    public function __construct(
        private readonly FormFactory $formFactory,
        private readonly User        $user,
    ) {
    }

    public function create(callable $onSuccess): Form
    {
        $form = $this->formFactory->create();

        // Create HTML labels with icons
        $emailLabel = Html::el('span')->setHtml('🧑‍💻 login:');
        $passwordLabel = Html::el('span')->setHtml('🔑 heslo:');

        $form->addEmail('email', $emailLabel)
            ->setRequired('Please enter your email.');

        $form->addPassword('password', $passwordLabel)
            ->setRequired('Please enter your password.');

        $buttonLabel = Html::el('span')->setHtml('🔑 přihlásit');
        $form->addSubmit('send', $buttonLabel)
            ->setHtmlAttribute('class', 'btn btn-warning shadow mt-3');

        $form->onSuccess[] = function (Form $form, \stdClass $data) use ($onSuccess): void {
            try {
                $this->user->login($data->email, $data->password);

            } catch (AuthenticationException $e) {
                switch ($e->getCode()) {
                    case Authenticator::IDENTITY_NOT_FOUND:
                    case Authenticator::INVALID_CREDENTIAL:
                        $form->addError('🙈 Oops! Invalid username or password entered.');
                        break;

                    case Authenticator::NOT_APPROVED:
                        $form->addError('😴 Zzz... Your account is not active yet.');
                        break;

                    default:
                        // Generic error for unexpected issues
                        $form->addError('🤖 Whoops! An unexpected error occurred during login.');
                        // Log the original exception for debugging
                        Debugger::log($e, ILogger::EXCEPTION);
                        break;
                }
                return; // Prevent the $onSuccess callback from running if login failed
            }

            $onSuccess($form, $data); // Call the success handler only if login succeeds
        };

        return $form;
    }
}