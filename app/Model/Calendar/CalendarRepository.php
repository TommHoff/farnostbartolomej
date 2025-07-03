<?php

declare(strict_types=1);

namespace App\Model\Calendar;

use Nette\Database\Explorer;
use Nette\Utils\DateTime;

class CalendarRepository
{
    public function __construct(private Explorer $database)
    {
    }

    public function getAllEvents()
    {
        return $this->database->table('calendar')
            ->order('date_start');
    }

    public function getEventSummary(): array
    {
        return $this->database->query(
            'SELECT n.note_poznamka, COUNT(*) as qty 
         FROM calendar AS c
         LEFT JOIN calendar_note AS n ON c.Fid_note = n.id_note
         WHERE c.date_end >= ?
         AND c.is_visible = 1
         GROUP BY n.note_poznamka', 
        new DateTime() // Pass a DateTime object for comparison
    )->fetchAll();
}

    public function getEventById(int $id)
    {
        return $this->database->table('calendar')
            ->where('id', $id)
            ->fetch();
    }

    public function getCalNote(): array
    {
        return $this->database->table('calendar_note')
            ->fetchPairs('id_note', 'note_poznamka'); // Correct column names
    }

    public function getCalPlace(): array
    {
        return $this->database->table('place')
            ->fetchPairs('id_place', 'nazev'); // Correct column names
    }

    // repeated events
    public function AllCalendarRepeat()
    {
        return $this->database->query(
            "SELECT 
            c.skupina, 
            MIN(c.date_start) AS date_start, 
            MAX(c.date_end) AS date_end, 
            n.note_poznamka, 
            p.nazev, 
            c.title, 
            c.note
        FROM 
            calendar AS c
        LEFT JOIN 
            calendar_note AS n ON c.Fid_note = n.id_note
        LEFT JOIN   
            place AS p ON c.Fid_place = p.id_place
        WHERE
            c.event = 0
        AND c.skupina IS NOT NULL
        GROUP BY 
            c.skupina
        ORDER BY 
            date_start DESC"
        )->fetchAll();
    }

public function getLastEventDate()
{
    // Returns the latest event that started today or earlier (not in the future)
    return $this->database->table('calendar')
        ->where('start_date <= ?', new \DateTime())
        ->order('start_date DESC')
        ->limit(1);
}
}