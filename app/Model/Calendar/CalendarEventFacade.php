<?php

declare(strict_types=1);

namespace App\Model\Calendar;

use Nette\Database\Explorer;

class CalendarEventFacade
{
    public function __construct(private Explorer $database)
    {
    }

    public function getUserOnEvent(int $eventId, int $userId)
    {
        return $this->database->table('calendar_event')
            ->where('calendar_Fid', $eventId)
            ->where('user_Fid', $userId)
            ->fetch();
    }

    public function getUsersOnEvent(int $id)
    {
        return $this->database->table('calendar_event')
            ->where('calendar_Fid', $id)
            ->fetchAll();
    }

    public function getUserById(int $userId)
    {
        return $this->database->table('members')
            ->select('jmeno, prijmeni, email, phone')
            ->where('members_id', $userId)  // Updated this line
            ->fetch();
    }

    public function getUsersOnEventCount(int $eventId): int
    {
        return $this->database->table('calendar_event')
            ->where('calendar_Fid', $eventId)
            ->count('*');
    }

    public function signUpForEvent(int $calendarId, int $userId, string $vzkaz): void
    {
        $existingEvent = $this->database->table('calendar_event')
            ->where('calendar_Fid', $calendarId)
            ->where('members_Fid', $userId)
            ->fetch();

        if ($existingEvent) {
            $existingEvent->update([
                'vzkaz' => $vzkaz
            ]);
        } else {
            $this->database->table('calendar_event')->insert([
                'calendar_Fid' => $calendarId,
                'members_Fid' => $userId,
                'vzkaz' => $vzkaz
            ]);
        }
    }

    public function logoutUserFromEvent(int $eventId, int $userId): void
    {
        $this->database->table('calendar_event')
            ->where('calendar_Fid', $eventId)
            ->where('members_Fid', $userId)
            ->delete();
    }
}