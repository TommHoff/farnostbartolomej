<?php

declare(strict_types=1);

namespace App\UI\Dashboard\Ticket;

use Nette\Application\UI\Form;
use App\UI\Dashboard\BasePresenter;
use App\Model\Develepment\TicketRepository;
use App\Model\User\UserFacade;
use App\UI\Dashboard\Ticket\TicketForm;
use App\UI\Dashboard\Ticket\TicketReplyForm;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Nette\Application\BadRequestException;


class TicketPresenter extends BasePresenter
{
    public function __construct(
        private TicketForm    $ticketForm,
        private readonly TicketRepository $ticketRepository,
        private TicketReplyForm $ticketReplyForm,
        private readonly Mailer        $mailer,
        private readonly UserFacade    $memberFacade
    ) {
    }

    public function renderDefault(): void
    {
        $query = $this->ticketRepository->getAllTickets()
            ->order('receive_ticket ASC');

        if ($this->user->isInRole('admin')) {
            $query->where('is_done', 0);
        } else {
            $userId = $this->user->getId();
            $query->where('user_Fid', $userId)
                ->where('is_done', 0);
        }
        $this->template->tickets = $query->fetchAll();


        $this->template->countOpen = $this->ticketRepository->countTicketsByStatus(0);
        $this->template->avgOpenTime = $this->ticketRepository->getFormattedAverageOpenTicketDuration();
    }

    public function renderArchive(): void
    {
        $query = $this->ticketRepository->getAllTickets()
            ->order('receive_ticket DESC');

        if ($this->user->isInRole('admin')) {
            $query->where('is_done', 1);
        } else {
            $userId = $this->user->getId();
            $query->where('user_Fid', $userId)
                ->where('is_done', 1);
        }
        $this->template->tickets = $query->fetchAll();

        $this->template->countFinish = $this->ticketRepository->countTicketsByStatus(1);
        $this->template->avgFinishTime = $this->ticketRepository->getFormattedAverageFinishTicketDuration();
    }


        function actionNewticket(): void
        {
            if (!$this->user->isLoggedIn()) {
                $this->flashMessage('Whoa there! 🤠 You gotta be logged in to see this page!', 'danger');
                $this->redirect(':Sign:Auth:in');
            }
        }

    public function actionEdit(int $id): void
    {
        if (!$this->user->isLoggedIn()) {
            $this->flashMessage('Hey! 👋 Log in first to edit tickets.', 'warning');
            $this->redirect(':Sign:Auth:in');
        }

        $ticket = $this->ticketRepository->getTicket($id);
        if (!$ticket) {
            throw new \Nette\Application\BadRequestException('Oops! 🤷‍♀️ Ticket not found.');
        }

        if ($ticket->is_done) {
            $this->flashMessage('Hold up! ✋ This ticket is already closed and cannot be edited.', 'info');
            $this->redirect(':Dashboard:Ticket:default');
        }

        $userId = $this->user->getId();
        if ($ticket->user_Fid !== $userId) {
            $this->flashMessage('Hands off! 🙅‍♂️ This isn\'t your ticket to edit.', 'danger');
            $this->redirect(':Dashboard:Ticket:default');
        }
    }

    public function renderEdit(int $id): void
    {
        $ticket = $this->ticketRepository->getTicket($id);
        if (!$ticket) {
            throw new BadRequestException('Oops! 🤷‍♀️ Ticket vanished between checks.');
        }
        $this->template->ticket = $ticket;
    }

    protected function createComponentTicketForm(): Form
    {
        return $this->ticketForm->create(null, function (): void {
            $this->flashMessage('Woohoo! 🎉 Ticket added successfully.', 'success');
            $this->redirect(':Dashboard:Ticket:default');
        });
    }

    public function createComponentEditTicketForm(): Form
    {
        $ticketId = $this->getParameter('id');
        if (!$ticketId) {
            $this->error('Uh oh! 😵 Missing the ticket ID for editing.');
        }

        return $this->ticketForm->create((int)$ticketId, function ($values): void {
            $this->flashMessage('Awesome! 👍 Ticket updated successfully.', 'success');
            $this->redirect(':Dashboard:Ticket:default');
        });
    }

    public function handleSetPriority(int $id): void
    {
        if (!$this->user->isLoggedIn()) {
            $this->flashMessage('Please log in first.', 'warning');
            $this->redirect(':Sign:Auth:in');
        }

        if (!$this->user->isInRole('admin') && !$this->user->isInRole('leader')) {
            $this->flashMessage('Access Denied! 🚫 You do not have permission to set priority.', 'danger');
            $this->redirect('default');
        }

        $ticket = $this->ticketRepository->getTicket($id);
        if (!$ticket) {
            $this->flashMessage('Oops! 🤷‍♀️ Ticket not found.', 'danger');
            $this->redirect('default');
            return;
        }

        if ($ticket->is_done) {
            $this->flashMessage('Cannot set priority on an archived ticket.', 'warning');
            $this->redirect('default');
            return;
        }

        try {
            $this->ticketRepository->setPriorityTicket($id);
            $this->flashMessage("Ticket #{$id} is now the priority ticket! ⭐", 'success');
        } catch (\Throwable $e) {
            \Tracy\Debugger::log('Failed to set ticket priority: ' . $e->getMessage(), \Tracy\ILogger::ERROR);
            $this->flashMessage('Oh no! 💥 Could not set priority due to a server error.', 'danger');
        }

        $this->redirect('default');
    }


    public function actionAdminreply(int $id): void
    {
        if (!$this->user->isLoggedIn()) {
            $this->flashMessage('Hey! 👋 Please log in to manage tickets.', 'warning');
            $this->redirect(':Sign:Auth:in');
        }

        if (!$this->user->isInRole('admin')) {
            $this->flashMessage('Access Denied! 🚫 You do not have permission to reply as admin.', 'danger');
            $this->redirect(':Dashboard:Home:default');
        }

        $ticket = $this->ticketRepository->getTicket($id);
        if (!$ticket) {
            throw new BadRequestException('Oops! 🤷‍♀️ Ticket not found.');
        }
    }

    public function renderAdminreply(int $id): void
    {
        $ticket = $this->ticketRepository->getTicket($id);
        if (!$ticket) {
            throw new BadRequestException('Oops! 🤷‍♀️ Ticket vanished between checks.');
        }
        $this->template->ticket = $ticket;
    }

    public function createComponentTicketReplyForm(): Form
    {
        $ticketId = $this->getParameter('id');
        if (!$ticketId) {
            $this->error('Uh oh! 😵 Missing the ticket ID for editing.');
        }

        return $this->ticketReplyForm->create((int)$ticketId, function ($values): void {
            $this->flashMessage('Awesome! 👍 Ticket updated successfully.', 'success');
            $this->redirect(':Dashboard:Ticket:default');
        });
    }



    public function handleArchived(int $id): void
    {
        $ticket = $this->ticketRepository->getTicket($id);
        if (!$ticket) {
            $this->flashMessage('Oops! 🤷‍♀️ Ticket not found.', 'danger');
            $this->redirect('default');
            return;
        }

        if (!$this->user->isInRole('admin')) {
            $this->flashMessage('Access Denied! 🚫 You can\'t archive tickets.', 'danger');
            $this->redirect('default');
            return;
        }

        if ($ticket->reply === null || trim((string)$ticket->reply) === '') {
            $this->flashMessage("Hold on! ✋ Ticket #{$id} needs a reply before it can be archived.", 'warning');
            // Consider adding AJAX handling
            $this->redirect('default');
            return; // Stop execution if there's no reply
        }


        $this->ticketRepository->doneTicket($id);
        $this->flashMessage('Filed away! 🗄️ Ticket archived.', 'success');

        $member = $this->memberFacade->getAccount((int)$ticket->user_Fid);
        if (!$member || !$member->email) {
            \Tracy\Debugger::log("Could not send archive notification: Email address not found for user ID {$ticket->user_Fid}.", \Tracy\ILogger::WARNING);
            $this->flashMessage('Archived! ✅ But couldn\'t find the user\'s email for a heads-up. 📧❓', 'warning');
            $this->redirect('default');
            return;
        }

        try {
            $mail = new Message();
            $mail->setFrom('salve@farnostbartolomej.cz')
                ->addTo($member->email)
                ->setSubject('✅ Váš ticket byl archivován!')
                ->setBody(
                    "Dobrý den, {$member->user_name}! 👋\n\n".
                    "🎉 Skvělá zpráva! Váš ticket (ID: #{$id}) byl úspěšně archivován správcem. 🗂️\n\n".
                    "📃 Aktuální stav: Vyřízeno ✅\n\n".
                    "Zobrazit ticket: 🚀 " . $this->link('//:Dashboard:Ticket:archive') . "\n\n".
                    "Pokud máte další otázky nebo potřebujete pomoc, neváhejte nás kontaktovat. 💬\n\n".
                    "Děkujeme, že využíváte naše služby! 🙏\n\n".
                    "S pozdravem,\n".
                    "Podpora Seto 🚀"
                );

            $this->mailer->send($mail);
        } catch (\Throwable $e) {
            \Tracy\Debugger::log('Failed to send ticket archived notification email: ' . $e->getMessage(), \Tracy\ILogger::ERROR);
            $this->flashMessage('Archived! ✅ But the email notification failed to send. 😢', 'danger');
        }

        $this->redirect('default');
    }

    public function handleDeleteTicket(int $id): void
    {
        if (!$this->user->isLoggedIn()) {
            $this->flashMessage('Please log in to delete this ticket.', 'warning');
            $this->redirect(':Sign:Auth:in');
        }

        $ticket = $this->ticketRepository->getTicket($id);
        if (!$ticket) {
            $this->flashMessage('Oops! Ticket not found.', 'danger');
            $this->redirect('default');
            return;
        }

        $userId = $this->user->getId();
        if ($ticket->user_Fid !== $userId) {
            $this->flashMessage('Access Denied! You can only delete your own tickets.', 'danger');
            $this->redirect('default');
            return;
        }

        try {
            $this->ticketRepository->deleteTicket($id);
            $this->flashMessage("Ticket #{$id} has been successfully deleted.", 'success');
        } catch (\Throwable $e) {
            \Tracy\Debugger::log('Failed to delete ticket: ' . $e->getMessage(), \Tracy\ILogger::ERROR);
            $this->flashMessage('An error occurred while trying to delete the ticket.', 'danger');
        }

        $this->redirect('default');
    }
}