<?php

declare(strict_types=1);

namespace App\Model\Calendar;

use Nette\Database\Explorer;
use Psr\Log\LoggerInterface;

class CalendarFacade
{
    public function __construct(
        private readonly Explorer $database
    ) {
    }

    public function addEvent($values)
    {
        $this->database->table('calendar')->insert($values);
    }

    public function updateEvent($values, int $id)
    {
        $this->database->table('calendar')
            ->where('id', $id)
            ->update($values);
    }

    public function deleteEvent(int $id): void
    {
        $this->database->table('calendar')
            ->where('id', $id)
            ->delete();
    }

    public function setEventVisibility(int $id, int $isVisible): void
    {
        $this->database->table('calendar')
            ->where('id', $id)
            ->update([
                'is_visible' => $isVisible,
            ]);
    }
}