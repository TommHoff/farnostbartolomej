<?php

declare(strict_types=1);

namespace App\UI\Sign\Register;

use App\Forms\FormFactory;
use App\Model\User\DuplicateNameException;
use App\Model\User\UserFacade;
use Nette\Application\UI\Form;
use Nette\Security\User;
use Nette\Utils\Html; // Ensure Html is imported if needed for button

final class NewAccountForm
{
    // Minimum password length for registration
    private const PASSWORD_MIN_LENGTH = 9;

    public function __construct(
        private readonly FormFactory $factory,
        private readonly UserFacade  $userFacade,
        private readonly User        $user, // Nette\Security\User for auto-login
    ) {}

    public function create(callable $onSuccess): Form
    {
        $form = $this->factory->create();

        // Themed Email Input
        $form->addEmail('email', '📧 Your Comms Channel (Email):')
            ->setRequired('Please enter your primary comms channel (email)!')
            ->addRule(Form::EMAIL, 'Please enter a valid email address format.')
            ->setHtmlAttribute('placeholder', 'cadet@starfleet.org') // Optional themed placeholder
            ->setHtmlAttribute('class', 'form-control'); // Keep Bootstrap class

        // Themed Username Input
        $form->addText('user_name', '🧑‍🚀 Choose Your Callsign:')
            ->setRequired('Every astronaut needs a unique callsign (username)!')
            ->setHtmlAttribute('placeholder', 'StarLord_123') // Optional themed placeholder
            ->setHtmlAttribute('class', 'form-control'); // Keep Bootstrap class

        // Themed Password Input
        $form->addPassword('password', '🔑 Set Your Secure Access Code:')
            ->setRequired('Set a secure access code (password) for your account.')
            ->addRule(Form::MIN_LENGTH, 'Security protocols require access codes to be at least %d characters long.', self::PASSWORD_MIN_LENGTH)
            // Optional: Add password strength rules if desired
            // ->addRule(Form::PATTERN, 'Password must contain letters and numbers.', '^(?=.*[A-Za-z])(?=.*\d).+$')
            ->setHtmlAttribute('class', 'form-control'); // Keep Bootstrap class

        // CONSIDER ADDING: Password confirmation field for better UX
        // $form->addPassword('password_confirm', '🔑 Confirm Access Code:')
        //     ->setRequired('Please re-enter your access code to confirm.')
        //     ->addRule(Form::EQUAL, 'Access codes do not match!', $form['password'])
        //     ->setOmitted(); // Don't pass this value to the onSuccess handler data

        // Themed Submit Button
        // $buttonLabel = Html::el('span')->setHtml('🚀 Register Profile!'); // Use if adding icons
        $form->addSubmit('register', '🚀 Register Profile!') // Simple text + emoji is fine here
        // Style to match other themed buttons (yellow, full-width, bold)
        ->setHtmlAttribute('class', 'btn btn-warning mt-3 w-100 fw-bold');

        $form->onSuccess[] = function (Form $form, \stdClass $values) use ($onSuccess): void {
            $presenter = $form->getPresenter(); // Get presenter for redirects/flash messages
            if (!$presenter) {
                // Log internal error, show generic message
                \Tracy\Debugger::log('Presenter not found in NewAccountForm onSuccess', \Tracy\ILogger::ERROR);
                $form->addError('A system error occurred. Please try again later.');
                return;
            }

            try {
                // Determine phone number (null if not available from this form)
                // In a real scenario, you might add a phone field to this form
                // or handle it differently if registration ONLY happens via Google sometimes.
                // Assuming manual registration doesn't collect phone here.
                $phoneNumber = null;

                // Add new user using the facade
                $newUser = $this->userFacade->add(
                    userName: $values->user_name,
                    email: $values->email,
                    password: $values->password,
                    isActive: true, // Usually activate immediately on registration
                    phoneNumber: $phoneNumber // Pass phone if collected
                );

                // Automatically log in the newly registered user
                // Use the UserFacade to find the user data needed for identity if login requires more than email/pass
                // Or simpler: just use email/password if Authenticator allows it
                $this->user->login($values->email, $values->password);

                // Call the success callback (likely sets flash message and redirects)
                $onSuccess($form, $values);

            } catch (DuplicateNameException $ex) {
                // Themed message for duplicate email
                $presenter->flashMessage('🦜 Uh oh! That comms channel (email) is already assigned to another cadet. Try logging in instead!', 'warning'); // Use warning or info
                $presenter->redirect(':Sign:Auth:in'); // Redirect to login page

            } catch (\Nette\Security\AuthenticationException $e) {
                // Handle potential login failure right after registration
                \Tracy\Debugger::log($e, \Tracy\ILogger::WARNING); // Log it
                $presenter->flashMessage('✅ Account created, but auto-login failed. Please try logging in manually.', 'info');
                $presenter->redirect(':Sign:Auth:in'); // Redirect to login even if auto-login fails

            } catch (\Throwable $ex) {
                // Log unexpected errors
                \Tracy\Debugger::log($ex, \Tracy\ILogger::EXCEPTION);
                // Themed generic error
                $form->addError('💥 Houston, we have a problem! An unexpected error occurred during registration. Please try again or contact support.');
            }
        };

        return $form;
    }
}