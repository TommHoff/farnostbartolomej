<?php

declare(strict_types=1);

namespace App\UI\Sign\Password;

use App\Model\User\UserFacade;
use App\UI\Front\BasePresenter;
use App\UI\Sign\Password\PasswordChangeForm;
use App\UI\Sign\Password\PasswordResetForm;
use Nette\Application\UI\Form;
use Nette\Security\Passwords;
use Nette\Utils\DateTime;

final class PasswordPresenter extends BasePresenter
{
    public function __construct(
        private readonly UserFacade           $userFacade,
        private readonly Passwords            $passwords,
        private readonly PasswordChangeForm   $passwordChangeForm,
        private readonly PasswordResetForm    $passwordResetFormFactory,
    ) {
        parent::__construct();
    }

    public function renderLost(): void
    {
    }

    protected function createComponentPasswordResetForm(): Form
    {
        return $this->passwordResetFormFactory->create(function (Form $form, \stdClass $data): void {
            $this->flashMessage('📡 Vysíláme pokyny pro obnovu hesla! Zkontrolujte komunikační příjem!', 'success');
            $this->redirect(':Sign:Auth:in');
        });
    }

    public function actionReset(string $token): void
    {
        $user = $this->userFacade->getUserByToken($token);

        if (!$user) {
            $this->flashMessage('Neplatný nebo vypršelý kód pro obnovu signálu 🔒', 'danger');
            $this->redirect(':Sign:Password:lost');
            return;
        }

        $this->template->token = $token;
    }

    public function actionPassword(): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->flashMessage('Pro změnu hesla musíte být přihlášeni na palubě 🔑', 'warning');
            $this->redirect(':Sign:Auth:in');
        }
    }

    protected function createComponentPasswordChangeForm(): Form
    {
        $token = $this->template->token ?? null;

        return $this->passwordChangeForm->create(function (Form $form, \stdClass $data, ?string $callbackToken): void {
            $userId = null;

            if ($callbackToken) {
                $user = $this->userFacade->getUserByToken($callbackToken);
                if (!$user) {
                    $this->flashMessage('Neplatná nebo vypršelá relace pro obnovu signálu.', 'danger');
                    $this->redirect(':Sign:Password:lost');
                    return;
                }
                $userId = $user->id;
            } elseif ($this->getUser()->isLoggedIn()) {
                $userId = $this->getUser()->getId();
            } else {
                $this->flashMessage('Nelze změnit heslo. Relace neplatná.', 'danger');
                $this->redirect(':Sign:Auth:in');
                return;
            }

            $hashedPassword = $this->passwords->hash($data->password);
            $this->userFacade->updateUserPassword($userId, $hashedPassword);

            $this->flashMessage('Vaše heslo bylo úspěšně změněno 🔐. Nyní můžete navázat spojení.', 'success');
            $this->redirect(':Sign:Auth:in');

        }, $token);
    }
}