<?php
declare(strict_types=1);

namespace App\UI\Sign\Password;

use App\Forms\FormFactory;
use App\Model\User\UserFacade;
use Nette\Application\UI\Form;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Tracy\Debugger;
use Nette\Utils\Html; // Ensure Html class is imported

final class PasswordResetForm
{
    public function __construct(
        private readonly FormFactory $formFactory,
        private readonly UserFacade  $userFacade,
        private readonly Mailer      $mailer,
    ) {
    }

    public function create(callable $onSuccess): Form
    {
        $form = $this->formFactory->create();

        // Themed Email Label
        $form->addEmail('email', '📧 Comms Channel (Email):') // Use emoji in the label string
        ->setRequired('We need your registered email to send the recovery link!') // Updated required message
        ->setHtmlAttribute('placeholder', 'your.email@galaxy.net') // Optional: Themed placeholder
        ->addRule(Form::EMAIL, 'Please enter a valid email address format.');

        // Themed Button
        $buttonLabel = Html::el('span')->setHtml('📡 Transmit Reset Link'); // Themed text and emoji
        $form->addSubmit('send', $buttonLabel)
            // Use btn-warning for theme consistency (yellow), make full width
            ->setHtmlAttribute('class', 'btn btn-warning mt-3 w-100 fw-bold');

        $form->onSuccess[] = function (Form $form, \stdClass $data) use ($onSuccess): void {
            $presenter = $form->getPresenter();
            if (!$presenter) {
                // Keep internal error logging generic
                Debugger::log('Presenter not found in PasswordResetForm onSuccess', Debugger::ERROR);
                $form->addError('A system error occurred. Please try again later.');
                return;
            }

            $user = $this->userFacade->findByEmail($data->email);

            if (!$user) {
                // Themed error message
                $form->addError('🧑‍🚀❓ Hmm, that comms channel (email) isn\'t registered in our star charts. Please double-check.');
                return;
            }

            // --- Token Generation ---
            try {
                $token = $this->userFacade->generatePasswordResetToken($user);
            } catch (\Throwable $e) {
                Debugger::log($e, Debugger::ERROR);
                $form->addError('💥 Failed to generate recovery signal. Please try again.');
                return;
            }
            // --- End Token Generation ---


            $resetLink = $presenter->link('//:Sign:Password:reset', ['token' => $token]);

            // Prepare clearly branded and slightly themed email
            $mail = new Message();
            $mail->setFrom('SETO Řídící Středisko 🚀 <salve@farnostbartolomej.cz>') // Themed sender in Czech
                ->addTo($data->email)
                ->setSubject('🛰️ Obnovení přístupového kódu - SETO 🚀') // Themed subject in Czech
                ->setHtmlBody("
                    <p>Zdravíme, Kadete!</p>
                    <p>Obdrželi jsme žádost z vašich souřadnic o obnovení přístupového kódu (hesla) do systému SETO 🚀.</p>
                    <p>Aktivujte následující hyperprostorový odkaz a nastavte si nový kód:</p>
                    <p><a href='{$resetLink}' style='padding: 10px 15px; background-color: #ffc107; color: #333; text-decoration: none; border-radius: 5px; font-weight: bold;'>Nastavit nový přístupový kód</a></p>
                    <p>(Odkaz pro případ, že tlačítko selže: {$resetLink})</p>
                    <p><strong>🚨 Tento odkaz pro obnovení se za 24 hodin pozemského času sám zničí (vyprší platnost).</strong></p>
                    <br>
                    <p>Pokud jste o toto obnovení nežádali, tuto zprávu ignorujte. Váš aktuální přístupový kód zůstává bezpečný.</p>
                    <br>
                    <p>Bezpečný let,<br>SETO Řídící Středisko 🚀</p>
                ");

            // Try to send email through injected mailer
            try {
                $this->mailer->send($mail);
            } catch (\Throwable $e) {
                Debugger::log($e, Debugger::ERROR);
                // Themed error message
                $form->addError('📡 Transmission failed! Couldn\'t send the recovery email. Please contact support or try again later.');
                return;
            }

            // Perform success callback (e.g. flash message/redirection)
            $onSuccess($form, $data);
        };

        return $form;
    }
}