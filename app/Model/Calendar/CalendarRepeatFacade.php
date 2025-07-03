<?php

declare(strict_types=1);

namespace App\Model\Calendar;

use Nette\Database\Explorer;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

class CalendarRepeatFacade
{
    public function __construct(
        private readonly Explorer $database
    ) {
    }

    public function addRepeatedEvents(
        string $dateFromString,
        string $dateTillString,
        string $timeStartString,
        string $timeEndString,
        int $fidNote,
        int $fidPlace,
        array $daysOfWeek,
        ?string $title,
        ?string $note,
        int $addedByUserId,
        string $skupina
    ): void {
     
        try {
            $startDate = new DateTimeImmutable($dateFromString);
            $endDate = (new DateTimeImmutable($dateTillString))->modify('+1 day');
            $interval = new DateInterval('P1D');
            $period = new DatePeriod($startDate, $interval, $endDate);

            $this->database->beginTransaction();

            $inserted = 0;

            foreach ($period as $currentDate) {
                $dayOfWeekString = $currentDate->format('w');

                if (!in_array($dayOfWeekString, $daysOfWeek, true)) {
                    continue;
                }

                $eventStartDateTime = \DateTime::createFromFormat(
                    'Y-m-d H:i:s',
                    $currentDate->format('Y-m-d') . ' ' . $timeStartString . ':00'
                );
                $eventEndDateTime = \DateTime::createFromFormat(
                    'Y-m-d H:i:s',
                    $currentDate->format('Y-m-d') . ' ' . $timeEndString . ':00'
                );

                if ($eventStartDateTime === false || $eventEndDateTime === false) {
                    $this->database->rollBack();
                    throw new RuntimeException("Failed to parse date/time for repeated event on " . $currentDate->format('Y-m-d') . ": {$timeStartString} - {$timeEndString}");
                }

                if ($eventEndDateTime < $eventStartDateTime) {
                    $eventEndDateTime->modify('+1 day');
                }

                $duplicateCheckCriteria = [
                    'date_start' => $eventStartDateTime,
                    'date_end' => $eventEndDateTime,
                    'Fid_note' => $fidNote,
                    'Fid_place' => $fidPlace,
                    'title' => $title,
                ];
                $existing = $this->database->table('calendar')->where($duplicateCheckCriteria)->fetch();

                if ($existing) {
                    continue;
                }

                $this->database->table('calendar')->insert([
                    'date_start' => $eventStartDateTime,
                    'date_end' => $eventEndDateTime,
                    'Fid_note' => $fidNote,
                    'Fid_place' => $fidPlace,
                    'skupina' => $skupina,
                    'title' => $title,
                    'note' => $note,
                    'is_visible' => 1,
                    'add_by_user_Fid' => $addedByUserId,
                ]);
                $inserted++;
            }

            $this->database->commit();

            if ($inserted === 0) {
                throw new RuntimeException('Žádné události nebyly přidány. Zkontrolujte výběr dnů a interval.');
            }

        } catch (Throwable $e) {
            if ($this->database->getConnection()->getPdo()->inTransaction()) {
                $this->database->rollBack();
            }
            throw new RuntimeException("Failed to add repeated events: " . $e->getMessage(), 0, $e);
        }
    }

    public function deleteGroup(int $skupina): void
    {
        try {
            $deletedCount = $this->database->table('calendar')
                ->where('skupina', $skupina)
                ->delete();
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to delete event group: " . $e->getMessage(), 0, $e);
        }
    }
}