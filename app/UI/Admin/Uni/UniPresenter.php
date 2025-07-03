<?php

namespace App\UI\Admin\Uni;

use App\UI\Admin\BasePresenter;
use Nette\Application\UI\Form;
use App\Forms\FormFactory;
use Nette\Utils\Paginator;

class UniPresenter extends BasePresenter
{

    private const ITEMS_PER_PAGE = 10;

    public function __construct(
        private FormFactory $formFactory)
    {
    }

    public function renderDefault(int $page = 1): void
    {
        // Get total number of entries
        $entriesCount = $this->uniRepository->getEntriesCount();

        // Create Paginator instance and configure it
        $paginator = new Paginator();
        $paginator->setItemCount($entriesCount); // Total number of items
        $paginator->setItemsPerPage(self::ITEMS_PER_PAGE); // Items per page
        $paginator->setPage($page); // Current page

        // Fetch entries for the current page
        $entries = $this->uniRepository->findEntries($paginator->getLength(), $paginator->getOffset());

        $this->template->entries = $entries;
        $this->template->paginator = $paginator;
        $this->template->uniRev = $this->uniRepository->getUniRev();
    }
    public function actionAdd(): void
    {

        $this['webForm']->setDefaults(['id' => null]);
    }

    public function actionEdit(int $id): void
    {

        $entry = $this->uniRepository->getEntryById($id);

        if (!$entry) {
            $this->flashMessage('Entry not found', 'error');
            $this->redirect('default');
        }

        // Create an array from the ActiveRow data
        $entryData = $entry->toArray();

        // Format the release_date to match the expected format of the 'datetime-local' input
        $entryData['release_date'] = $entry->release_date instanceof \DateTimeInterface
            ? $entry->release_date->format('Y-m-d\TH:i')
            : (new \DateTime($entry->release_date))->format('Y-m-d\TH:i');

        $this['webForm']->setDefaults($entryData);
    }

    protected function createComponentWebForm(): Form
    {
        $form = $this->formFactory->create();
        $form->addHidden('id');

        $form->addGroup(false)->setOption('col', 'div class="col-4"');

        $form->addInteger('major', 'Major revision:')
            ->setRequired()
            ->setHtmlAttribute('min', 0);
        $form->addInteger('minor', 'Minor revision:')
            ->setRequired()
            ->setHtmlAttribute('min', 0);
        $form->addInteger('bugfix', 'Bugfix revision:')
            ->setRequired()
            ->setHtmlAttribute('min', 0);

        $form->addGroup(false)->setOption('col', 'div class="col-12"');
        $form->addTextArea('comment', 'Comment:')
            ->setRequired('Please enter a comment.')
            ->setHtmlAttribute('rows', 10);

        $form->addText('release_date', 'Release date:')
            ->setType('datetime-local') // Changed type to datetime-local
            ->setHtmlAttribute('class', 'datetimepicker') // You might need to adjust your JS datetimepicker library if it was specific to 'date'
            ->setRequired('Please enter the release date and time.') // Updated requirement message
            ->setDefaultValue(date('Y-m-d\TH:i')); // Updated default value format

        $form->addSubmit('save', 'Save');
        $form->addProtection();

        $form->onSuccess[] = [$this, 'webFormSucceeded'];
        return $form;
    }

    public function webFormSucceeded(Form $form, \stdClass $values): void
    {
        $entryId = $values->id ? (int)$values->id : null;

        if ($entryId) {
            $this->uniRepository->updateEntry($entryId, (array)$values);
            $this->flashMessage('Entry updated successfully.', 'success');
        } else {
            $this->uniRepository->addEntry((array)$values);
            $this->flashMessage('Entry added successfully.', 'success');
        }

        $this->redirect('default');
    }

    public function handleDelete(int $id): void
    {
        $this->uniRepository->deleteEntryById($id);
        $this->flashMessage('Entry deleted successfully', 'success');
        $this->redirect('this');
    }
}