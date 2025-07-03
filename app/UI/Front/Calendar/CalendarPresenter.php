<?php

declare(strict_types=1);

namespace App\UI\Front\Calendar;

use App\UI\Front\BasePresenter;
use Nette\Application\UI\Form;
use DateTimeImmutable;
use Nette\Utils\DateTime;

class CalendarPresenter extends BasePresenter
{

    public function renderDefault(): void
    {
        $this->template->events = $this->calendarRepository->getAllEvents()
            ->where('is_visible = 1')
            ->where('date_end > NOW()')
            ->fetchAll();
    }

    public function renderBohosluzby(): void
    {
        $now = new DateTimeImmutable();
        $eightDaysFromNow = $now->modify('+8 days');

        $this->template->Bohosluzby = $this->calendarRepository->getAllEvents()
            ->where('is_visible = 1')
            ->where('Fid_note > 100')
            ->where('date_start BETWEEN ? AND ?', $now, $eightDaysFromNow)
            ->fetchAll();
    }

    public function actionDetail(int $id): void
    {

        $calendarEvent = $this->calendarRepository->getEventById($id);

        if (!$calendarEvent) {
            $this->flashMessage('...zvlášní ale událost zmizela 💨...', 'error');
            $this->redirect(':Front:Calendar:default');
        }


        $this->template->caldetail = $calendarEvent;
    }

    protected function createComponentSignUpForm(): \Nette\Application\UI\Form
    {
    
        $form = new \Nette\Application\UI\Form;
        
        $form->addText('qty', 'počet:')
            ->setDefaultValue(1)
            ->setRequired('kolik Vás dorazí?')
            ->addRule($form::INTEGER, 'tohle není číslo');
            
        $form->addTextArea('vzkaz', 'vzkaz?')
            ->setRequired(FALSE);

        $form->addHidden('eventId', $this->getParameter('id')); // calendar event ID



        $form->addSubmit('submit', 'přihlásit se');
        
        $form->onSuccess[] = [$this, 'signUpFormSucceeded'];
        
        return $form;
    }

    public function signUpFormSucceeded(\Nette\Application\UI\Form $form, \stdClass $values): void
    {
        $eventId = (int) $values->eventId;
        $userId = $this->getUser()->getId();

        if (!$eventId) {
            $this->flashMessage('Event ID is missing.', 'error');
            $this->redirect(':Front:Calendar:Detail', ['id' => $eventId]);
            return;
        }

        $this->calendarRepository->signUpForEvent($eventId, $userId, (int) $values->qty, $values->vzkaz);
        $this->flashMessage('...prima všechno klaplo, uvidíme se na akci...', 'success');
        $this->redirect(':Front:Calendar:Detail', ['id' => $eventId]);

    }

    public function actionLogoutFromEvent(int $eventId): void
    {
        $this->calendarRepository->logoutUserFromEvent($eventId, $this->user->id);

        $this->flashMessage('You have been successfully logged out from the event.', 'success');
        $this->redirect(':Front:Calendar:default'); // Or redirect to the previous page or another relevant page.
    }

}