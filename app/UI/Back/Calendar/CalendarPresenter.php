<?php

declare(strict_types=1);

namespace App\UI\Back\Calendar;

use App\Ui\Back\Calendar\CalendarFormFactory;
use App\Ui\Back\Calendar\RepeatedEventsFormFactory;
use App\Model\Calendar\CalendarRepeatFacade;
use App\Forms\StorageManager;
use App\UI\Back\BasePresenter;
use DateTime;
use Nette\Application\UI\Form;

final class CalendarPresenter extends BasePresenter
{

    public function __construct(
        private readonly CalendarFormFactory $calendarFormFactory,
        private readonly RepeatedEventsFormFactory $repeatedEventsFormFactory,
        private readonly StorageManager $storageManager,
        private readonly CalendarRepeatFacade $calendarRepeatFacade
    ) {}

    public function renderDefault(): void
    {
        // Assuming getAllEvents returns Nette\Database\Table\Selection or similar
        $this->template->events = $this->calendarRepository->getAllEvents()
            ->where('is_visible = 1')
            // Ensure date_end is compared correctly for your database (MariaDB DATETIME)
            // Nette Database handles NOW() and DateTime objects correctly in where clauses
            ->where('date_end > NOW()')
            ->fetchAll();

        $this->template->hiddenEvents = $this->calendarRepository->getAllEvents()
            ->where('is_visible = 0')
            // Ensure date_end is compared correctly
            ->where('date_end > NOW()')
            ->fetchAll();
    }

    protected function createComponentCalendarForm(): Form
    {
        $calendarId = $this->getParameter('id');
        $calendarId = $calendarId !== null ? (int)$calendarId : null;

        // Pass the onSuccess callback to the factory
        return $this->calendarFormFactory->create(function () use ($calendarId): void {
            $this->flashMessage($calendarId ? 'Událost byla aktualizována.' : 'Událost byla přidána.', 'success'); // Added messages in Czech
            $this->redirect(':Front:Calendar:default'); // Redirect to the default list view
        }, $calendarId);
    }

    public function actionAdd(): void
    {
        // The component is created by createComponentCalendarForm when called
        $this->getComponent('calendarForm');
    }

    public function actionEdit(int $id): void
    {
        /** @var \Nette\Database\Table\ActiveRow|null $record */
        $record = $this->calendarRepository->getEventById($id);
        if (!$record) {
            $this->error('Záznam události nenalezen.', 404); // Added message in Czech and 404 code
        }

        // Fetch all data as array to pass to setDefaults
        $defaultValues = $record->toArray();

        // Ensure date format is correct for datetime-local input if not already done in factory defaults preparation
        // (CalendarFormFactory handles this preparation, so this might be redundant here)
        if (isset($defaultValues['date_start']) && $defaultValues['date_start'] instanceof \DateTimeInterface) {
            $defaultValues['date_start'] = $defaultValues['date_start']->format('Y-m-d\TH:i');
        }
        if (isset($defaultValues['date_end']) && $defaultValues['date_end'] instanceof \DateTimeInterface) {
            $defaultValues['date_end'] = $defaultValues['date_end']->format('Y-m-d\TH:i');
        }


        $this['calendarForm']->setDefaults($defaultValues); // Use array directly if keys match form fields
    }

    public function actionRepeat(): void
    {
        // The component is created by createComponentRepeatedEventsForm when called
        $this->getComponent('repeatedEventsForm');
    }

    public function renderRepeat(): void
    {
        // Ensure $this->template exists before accessing it
        // Assuming AllCalendarRepeat() fetches data structured appropriately for the template
        // If your template for renderRepeat uses $events, this is correct.
        if (isset($this->template)) {
            $this->template->events = $this->calendarRepository->AllCalendarRepeat();
        }
    }

    protected function createComponentRepeatedEventsForm(): Form
    {
        // Pass the onSuccess callback to the factory
        return $this->repeatedEventsFormFactory->create(function (): void {
            $this->flashMessage('Opakované události byly přidány.', 'success'); // Added period for consistency
            // Changed redirect 'this' to a specific destination to avoid the redirect error
            $this->redirect(':Back:Calendar:default'); // <-- Changed redirect target
        });
    }


    public function handleSetVisible(int $id): void
    {
        // Security check: Ensure user is allowed to perform this action
        if (!$this->getUser()->isAllowed('management')) { // Assuming 'management' resource covers this admin action
            $this->flashMessage('Nemáte oprávnění zviditelnit událost.', 'warning');
            $this->redirect('this'); // Redirect back
            return;
        }

        // Assuming setEventVisibility exists in the injected facade
        $this->calendarFacade->setEventVisibility($id, 1);
        $this->flashMessage('Událost zviditelněna.', 'success');
        $this->redirect('this'); // Redirect back to refresh the list (Ajax or full page)
    }

    public function handleDelete(int $id): void
    {
        $record = $this->calendarRepository->getEventById($id);
        if (!$record) {
            $this->error('Záznam nenalezen.', 404); // Added message in Czech and 404 code
        }

        // Security check: Ensure user is allowed to perform this action
        // This check might be redundant if checkUserAuthentication covers it, but safer here
        if (!$this->getUser()->isAllowed('management')) {
            $this->flashMessage('Nemáte oprávnění smazat událost.', 'warning'); // Added message in Czech
            // Keep the specific message about "not yours" only if you add owner checks
            // $this->flashMessage('...tak za pokus to stálo :-), ale tohle není tvoje událost', 'error');
            $this->redirect(':Back:Calendar:default');
            return; // Stop processing
        }

        // Add an optional check if the user is the owner before deleting (if needed)
        // if ($record->add_by_user_Fid !== $this->user->getId() && !$this->user->isInRole('admin')) {
        //    $this->flashMessage('Můžeš smazat pouze vlastní události.', 'warning');
        //    $this->redirect('this');
        //    return;
        // }


        // Assuming storageManager and facade methods exist and are correctly injected
        // Delete associated files BEFORE deleting the record
        if (!empty($record->photo)) { // Check if photo filename exists
            // Assuming storageManager has fileExists method
            if ($this->storageManager->fileExists($record->photo)) {
                $this->storageManager->deleteFileOrImage($record->photo);
            }
        }

        if (!empty($record->filePdf)) { // Check if filePdf filename exists
            // Assuming storageManager has fileExists method
            if ($this->storageManager->fileExists($record->filePdf)) {
                $this->storageManager->deleteFileOrImage($record->filePdf);
            }
        }

        // Delete the record from the database
        $this->calendarFacade->deleteEvent($id);

        // Notify the user
        $this->flashMessage('Záznam byl smazán.', 'success');

        // Redirect or redraw snippets
        if ($this->isAjax()) {
            // Assuming 'table' is a snippet name covering the events list
            $this->redrawControl('table');
            // You might need to redraw the hidden events list too if it's a separate snippet
            // $this->redrawControl('hiddenEvents');
        } else {
            $this->redirect('this'); // Redirect to refresh the full page
        }
    }

    public function handleDeleteGroup(int $skupina): void
    {
        // Security check: Ensure user is allowed to perform this action
        if (!$this->getUser()->isAllowed('management')) {
            $this->flashMessage('Nemáte oprávnění smazat skupinu událostí.', 'warning'); // Added message in Czech
            $this->redirect('this'); // Redirect back
            return;
        }
        // Add an optional check if the user owns the group (if needed)
        // $groupEvents = $this->calendarRepository->findBy(['skupina' => $skupina]);
        // if (!empty($groupEvents) && $groupEvents[0]->add_by_user_Fid !== $this->user->getId() && !$this->user->isInRole('admin')) {
        //     $this->flashMessage('Můžeš smazat pouze vlastní skupiny událostí.', 'warning');
        //     $this->redirect('this');
        //     return;
        // }


        // Assuming the facade method exists
        $this->calendarRepeatFacade->deleteGroup($skupina);

        // Notify the user
        $this->flashMessage('Skupina událostí byla smazána.', 'success');

        // Redirect or redraw snippets
        if ($this->isAjax()) {
            // Assuming 'table' is a snippet name covering the list of groups or events
            $this->redrawControl('table');
            // You might need to redraw the repeater list as well
            // $this->redrawControl('repeatedGroups');
        } else {
            // Redirect to refresh the full page, maybe the repeat action page?
            $this->redirect('this'); // Or redirect(':Back:Calendar:repeat');
        }
    }
}