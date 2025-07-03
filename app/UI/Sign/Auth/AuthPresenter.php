<?php

declare(strict_types=1);

namespace App\UI\Sign\Auth;

use App\Model\User\DuplicateNameException;
use App\Model\User\UserFacade;
use App\UI\Front\BasePresenter;
use App\UI\Sign\Auth\SignForm;
use Contributte\OAuth2Client\Flow\Google\GoogleAuthCodeFlow;
use Contributte\OAuth2Client\UI\Components\GenericAuthControl;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Token\AccessToken;
use Nette\Application\UI\Form;
use Nette\Application\AbortException;
use Nette\Security\AuthenticationException;
use Nette\Security\SimpleIdentity;
use Nette\Utils\Random;
use Tracy\Debugger;
use Tracy\ILogger;

final class AuthPresenter extends BasePresenter
{
    public function __construct(
        private readonly SignForm           $signForm,
        private readonly UserFacade         $userFacade,
        private readonly GoogleAuthCodeFlow $googleAuthCodeFlow
    ) {
        parent::__construct();
    }

    public function actionIn(): void
    {
        if ($this->getUser()->isLoggedIn()) {
            // Translated: You are already logged in on board! (Removed unicorn)
            $this->flashMessage('Jste již přihlášeni na palubě!');
            $this->redirect(':Front:Home:default');
        }
    }

    public function actionOut(): void
    {
        $this->getUser()->logout();
        // Translated: 🐒 will wait for your return... (Kept theme)
        $this->flashMessage('🐒 bude čekat na váš návrat...', 'info');
        $this->redirect(':Front:Home:default');
    }

    protected function createComponentSignForm(): Form
    {
        return $this->signForm->create(function (Form $form, \stdClass $data): void {
            $user = null;
            if ($this->getUser()->isLoggedIn()) {
                $user = $this->userFacade->getAccount($this->getUser()->getId());
            }
            $user_name = $user?->user_name ?: 'Mighty Astronaut'; // Changed default name slightly to fit theme
            // Translated: Welcome back on board, %s! Prepare for the mission! 🚀 (Removed unicorn/magic)
            $this->flashMessage(sprintf('Vítej zpátky na palubě, %s! Připravte se na misi! 🚀', htmlspecialchars($user_name)), 'success');
            $this->redirect(':Front:Home:default');
        });
    }


    public function actionGoogleLogin(): void
    {
        $this['googleLoginButton']->authenticate();
    }

    public function actionGoogleCallback(): void
    {
        $this['googleLoginButton']->authorize();
    }

    protected function createComponentGoogleLoginButton(): GenericAuthControl
    {
        $control = new GenericAuthControl(
            $this->googleAuthCodeFlow,
            $this->link('//googleCallback')
        );

        $control->onAuthenticated[] = function (AccessToken $accessToken, GoogleUser $googleUser): void {
            $email = $googleUser->getEmail();
            if (!$email) {
                // Translated: Could not retrieve email from Google. (Kept neutral)
                $this->flashMessage('Nepodařilo se získat e-mail z Google.', 'danger');
                $this->redirect('in');
                return;
            }

            $localUser = $this->userFacade->findByEmail($email);
            $googleName = $googleUser->getName();
            $avatarUrl = $googleUser->getAvatar();

            if ($localUser) {
                // --- EXISTING USER LOGIN ---
                try {
                    $identity = new SimpleIdentity(
                        $localUser->id,
                        explode(',', $localUser->roles ?: 'gamer'),
                        [
                            'email' => $localUser->email,
                            'name' => $googleName,
                            'avatar' => $avatarUrl,
                        ]
                    );
                    $this->getUser()->login($identity);

                    // Translated: 🚀 Logged in successfully via Google as %s! (Kept theme)
                    $this->flashMessage(sprintf('🚀 Úspěšně přihlášen přes Google jako %s!', htmlspecialchars($googleName)), 'success');
                    $this->redirect(':Front:Home:default');

                } catch (AuthenticationException $e) {
                    Debugger->log($e, ILogger::EXCEPTION);
                    // Translated: ⚠️ Google login failed: %s (Kept neutral, standard error)
                    $this->flashMessage('⚠️ Přihlášení přes Google selhalo: ' . $e->getMessage(), 'danger');
                    $this->redirect('in');
                }
                // NO generic catch block here

            } else {
                // --- NEW USER REGISTRATION VIA GOOGLE ---
                try {
                    $password = Random::generate(20);
                    $userName = $googleName ?? ('User_' . Random::generate(6));

                    $newUser = $this->userFacade->add(
                        userName: $userName,
                        email: $email,
                        password: $password
                    );

                    $identity = new SimpleIdentity(
                        $newUser->id,
                        explode(',', $newUser->roles ?: 'gamer'),
                        [
                            'email' => $newUser->email,
                            'name' => $newUser->user_name,
                            'avatar' => $avatarUrl,
                        ]
                    );
                    $this->getUser()->login($identity);

                    // Translated: ✨ Welcome aboard, %s! Your account has been created via Google! (Kept theme)
                    $this->flashMessage(sprintf('✨ Vítejte na palubě, %s! Váš účet byl vytvořen přes Google!', htmlspecialchars($newUser->user_name)), 'success');
                    $this->redirect(':Front:Home:default'); // Throws AbortException

                } catch (DuplicateNameException $e) {
                    // Translated: ⚠️ %s Please try to re-establish connection. (Kept space theme)
                    $this->flashMessage('⚠️ '. $e->getMessage() . ' Prosím, zkuste se znovu navázat spojení.', 'warning'); // Show specific duplicate message
                    $this->redirect('in');
                } catch (AuthenticationException $e) {
                    Debugger->log($e, ILogger::WARNING);
                    // Translated: ✅ Logbook created, but automatic connection failed. Please try to connect manually. (Using "Logbook" for account, "establish connection" for login)
                    $this->flashMessage('✅ Lodní deník vytvořen, ale automatické navázání spojení selhalo. Prosím, zkuste navázat spojení ručně.', 'info');
                    $this->redirect('in');
                } catch (\RuntimeException $e) {
                    // Catch specific RuntimeException potentially thrown by UserFacade::add for DB errors
                    Debugger->log($e, ILogger::EXCEPTION);
                    // Translated: 💥 A database error occurred while creating your logbook. Please try again. (Using "Logbook" for account)
                    $this->flashMessage('💥 Při vytváření vašeho lodního deníku došlo k chybě databáze. Prosím, zkuste to znovu.', 'danger');
                    $this->redirect('in');
                }
                // REMOVED catch (\Throwable $e) - Let AbortException propagate
            }
        };

        $control->onFailed[] = function (): void {
            // Translated: ⚠️ Google authentication failed or was cancelled. Please try to establish connection again. (Using "establish connection")
            $this->flashMessage('⚠️ Autentizace Google selhala nebo byla zrušena. Prosím, zkuste navázat spojení znovu.', 'danger');
            $this->redirect('in');
        };

        return $control;
    }
}