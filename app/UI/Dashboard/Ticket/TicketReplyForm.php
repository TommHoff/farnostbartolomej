<?php

declare(strict_types=1);

namespace App\UI\Dashboard\Ticket;

use App\Forms\FormFactory;
use App\Model\Develepment\TicketRepository; // Corrected typo: Development
use Nette\Application\UI\Form;
use Nette\Security\User;
use Nette\Utils\Html;
use Nette\Application\BadRequestException;
use Nette\Application\AbortException;
use Tracy\Debugger;
use Tracy\ILogger;
use Throwable;

class TicketReplyForm
{
    public function __construct(
        private readonly FormFactory      $formFactory,
        private readonly TicketRepository $ticketRepository,
        private readonly User             $user // User might not be strictly needed here unless you add logic based on the replier
    ) {
    }

    public function create(?int $ticketId, callable $onSuccess): Form
    {
        $form = $this->formFactory->create();
        if ($ticketId === null) {
            throw new BadRequestException('Cannot create a reply form without a Ticket ID.');
        }

        $ticket = $this->ticketRepository->getTicket($ticketId);
        if (!$ticket) {
            // Ticket not found, throw exception
            throw new BadRequestException('Whoops! Ticket not found. 🤷‍♀️');
        }

        // --- Refactoring Start ---
        $defaults = [];
        if ($ticket->reply) {
            $defaults['reply'] = $ticket->reply; // Pre-fill with existing reply
        }
        // --- Refactoring End ---

        $form->addTextArea('reply', Html::el()->setHtml('<i class="bi bi-reply-fill me-2"></i>Your Reply:'))
            ->setRequired('Please enter your reply.')
            ->setHtmlAttribute('rows', 8)
            ->setHtmlAttribute('placeholder', 'Enter your reply to the user...')
            ->setHtmlAttribute('class', 'form-control');

        $submitCaption = $ticket?->reply ? '💾 Update Reply' : '✉️ Send Reply'; // Change caption if editing
        $form->addSubmit('send', $submitCaption)
            ->setHtmlAttribute('class', 'btn btn-primary w-100 mt-4');

        $form->addProtection('Security token expired, please submit the form again.');

        // --- Refactoring Start ---
        $form->setDefaults($defaults); // Apply the defaults
        // --- Refactoring End ---


        $form->onSuccess[] = function (Form $form, $values) use ($onSuccess, $ticketId): void {
            $dbData = [
                'reply' => $values->reply
                // You might want to add an 'admin_replied_at' timestamp here as well
                // 'admin_replied_at' => new \Nette\Utils\DateTime(),
                // 'admin_replier_Fid' => $this->user->getId(), // If you track which admin replied
            ];
            $dbOperationSuccess = false;

            try {
                // Ensure reply is not just whitespace before saving
                if (trim($dbData['reply']) !== '') {
                    $this->ticketRepository->updateTicket($ticketId, $dbData);
                    $dbOperationSuccess = true;
                } else {
                    // Instead of error, maybe allow clearing the reply? Or keep the error.
                    $form['reply']->addError('Reply cannot be empty.');
                    return; // Stop processing if reply is empty
                }

            } catch (Throwable $e) {
                Debugger::log('Ticket reply update failed: ' . $e->getMessage(), ILogger::ERROR);
                Debugger::log($e, ILogger::EXCEPTION);
                $form->addError('Oh no! Something went wrong while saving the reply. Please try again.');
            }

            if ($dbOperationSuccess) {
                try {
                    $onSuccess($values);
                } catch (AbortException $ab) {
                    throw $ab;
                } catch (Throwable $e) {
                    Debugger::log('Error occurred within the presenter\'s onSuccess callback after reply: ' . $e->getMessage(), ILogger::ERROR);
                    Debugger::log($e, ILogger::EXCEPTION);
                    $form->addError('Reply saved, but a final step failed. Please check the ticket or contact support.');
                }
            }
        };

        return $form;
    }
}