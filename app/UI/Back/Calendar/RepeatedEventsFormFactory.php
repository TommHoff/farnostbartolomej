<?php

namespace App\UI\Back\Calendar;

use App\Forms\FormFactory;
use App\Model\Calendar\CalendarRepeatFacade;
use Nette\Application\UI\Form;
use App\Model\Calendar\CalendarRepository;
use Nette\Security\User;

class RepeatedEventsFormFactory
{
    public function __construct(
        private readonly FormFactory $formFactory,
        private readonly CalendarRepeatFacade $facade,
        private readonly CalendarRepository $calendarRepository,
        private readonly User $user
    ) {}

    public function create(callable $onSuccess): Form
    {
        $form = $this->formFactory->create();

        $form->addText('title', 'Název:')
            ->setHtmlAttribute('class', 'my-3');

        $form->addText('note', 'Poznámka:')
            ->setHtmlAttribute('class', 'my-3')
            ->setRequired(false);

        $row = $form->addRow();
        $row->addCell(6)
            ->addText('date_from', 'První den:')
            ->setHtmlType('date')
            ->setRequired();
        $row->addCell(6)
            ->addText('time_start', 'Čas začátku:')
            ->setHtmlType('time')
            ->setRequired();

        $row = $form->addRow();
        $row->addCell(6)
            ->addText('date_till', 'Poslední den:')
            ->setHtmlType('date')
            ->setRequired();
        $row->addCell(6)
            ->addText('time_end', 'Čas konce:')
            ->setHtmlType('time')
            ->setRequired();

        $row = $form->addRow();
        $row->addCell(4)
            ->addCheckboxList('days_of_week', 'Dny v týdnu:', [
                '1' => 'Pondělí',
                '2' => 'Úterý',
                '3' => 'Středa',
                '4' => 'Čtvrtek',
                '5' => 'Pátek',
                '6' => 'Sobota',
                '0' => 'Neděle',
            ])->setRequired('Musíš vybrat alespoň jeden den v týdnu.');

        $calNotes = $this->calendarRepository->getCalNote();
        $row->addCell(4)
            ->addSelect('Fid_note', 'Druh akce:', $calNotes)
            ->setPrompt('Vyberte druh akce')
            ->setHtmlAttribute('class', 'my-3')
            ->setRequired();

        $calPlaces = $this->calendarRepository->getCalPlace();
        $row->addCell(4)
            ->addSelect('Fid_place', 'Místo:', $calPlaces)
            ->setPrompt('Vyberte místo')
            ->setHtmlAttribute('class', 'my-3')
            ->setRequired();

        $form->addHidden('skupina')
            ->setDefaultValue(random_int(100000, 999999999));

        $form->addSubmit('submit', 'Uložit')
            ->setHtmlAttribute('class', 'my-3')
            ->setHtmlAttribute('class', 'btn btn-primary');

        $form->onSuccess[] = function (Form $form, \stdClass $values) use ($onSuccess) {
            if (!$this->user->isLoggedIn()) {
                $form->addError("Pro přidání opakovaných událostí se musíte přihlásit.");
                return;
            }
            $addedByUserId = $this->user->getId();

            $daysOfWeekStrings = array_map('strval', $values->days_of_week);

            try {
                $this->facade->addRepeatedEvents(
                    $values->date_from,
                    $values->date_till,
                    $values->time_start,
                    $values->time_end,
                    $values->Fid_note,
                    $values->Fid_place,
                    $daysOfWeekStrings,
                    $values->title,
                    $values->note,
                    $addedByUserId,
                    $values->skupina   // přidej tento parametr!
                );

                $onSuccess();

            } catch (\RuntimeException $e) {
                $form->addError("Chyba při přidávání opakovaných událostí: " . $e->getMessage());
                return;
            }
        };

        return $form;
    }
}