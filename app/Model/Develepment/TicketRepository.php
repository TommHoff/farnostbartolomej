<?php

declare(strict_types=1);

namespace App\Model\Develepment;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;
use Nette\Database\DriverException; // Import DriverException for transaction handling

class TicketRepository
{
    public function __construct(private Explorer $database) {}

    public function addTicket(array $ticketData): void
    {
        $this->database->table('admin_tickets')->insert($ticketData);
    }

    public function updateTicket(int $requestId, array $values): void
    {
        $this->database->table('admin_tickets')
            ->where('id', $requestId)
            ->update($values);
    }

    public function getTicket(int $id): ?ActiveRow
    {
        return $this->database->table('admin_tickets')->get($id);
    }

    public function getAllTickets(): Selection
    {
        return $this->database->table('admin_tickets')->order('is_priority DESC');
    }

    public function doneTicket(int $id): void
    {
        // Assuming 'done' means setting is_done=1 and finish_ticket=now
        // Also unset priority when archiving
        $this->database->table('admin_tickets')->where('id', $id)->update([
            'is_done' => 1,
            'finish_ticket' => new DateTime(),
            'is_priority' => 0 // Ensure archived tickets are not priority
        ]);
    }

    /**
     * Sets a specific open ticket as the priority ticket.
     * Ensures only one open ticket can have priority at a time.
     *
     * @param int $id The ID of the ticket to set as priority.
     * @throws \Nette\Database\DriverException If the database operation fails.
     * @throws \Exception For other potential errors during the transaction.
     */
    public function setPriorityTicket(int $id): void
    {
        $this->database->beginTransaction();
        try {
            // Step 1: Unset priority for all *open* tickets
            $this->database->table('admin_tickets')
                ->where('is_done', 0) // Only affect open tickets
                ->update(['is_priority' => 0]);

            // Step 2: Set priority for the specified *open* ticket
            // Add an extra check to ensure we only set priority on an open ticket
            $updatedRows = $this->database->table('admin_tickets')
                ->where('id', $id)
                ->where('is_done', 0) // Make sure the target ticket is actually open
                ->update(['is_priority' => 1]);

            // Optional: Check if the target ticket was actually updated (i.e., it exists and is open)
            if ($updatedRows === 0) {
                 // This could happen if the ticket was closed or deleted between the presenter check and this call
                 // Or if the ID doesn't exist. The presenter already checks for existence, but not closed status race condition.
                 throw new \Exception("Ticket ID {$id} not found or is already closed. Cannot set priority.");
            }

            $this->database->commit();
        } catch (\Throwable $e) {
            $this->database->rollBack();
            // Re-throw the exception to be caught by the presenter
            throw $e;
        }
    }


    public function countTicketsByStatus(int $status): int
    {
        return $this->database->table('admin_tickets')
            ->where('is_done', $status)
            ->count('*');
    }


    /**
     * Calculates the average duration of completed tickets and returns it as a formatted string.
     * Example format: "1 day, 2 hours, 30 minutes", "5 hours, 10 minutes", "15 minutes", "Less than 1 minute".
     *
     * Note: The exact SQL function for time difference (e.g., TIMESTAMPDIFF)
     * might need adjustment based on your specific database system (MySQL, PostgreSQL, etc.).
     * This example assumes a MySQL-like syntax.
     *
     * @return string|null Formatted average duration string, or null if no completed tickets exist or calculation fails.
     */
    public function getFormattedAverageFinishTicketDuration(): ?string
    {
        // Adjust the TIMESTAMPDIFF function based on your database (e.g., for PostgreSQL use EXTRACT(EPOCH FROM (finish_ticket - receive_ticket)))
        $result = $this->database->table('admin_tickets')
            ->select('AVG(TIMESTAMPDIFF(SECOND, receive_ticket, finish_ticket)) AS average_duration')
            ->where('is_done', 1)
            ->where('receive_ticket IS NOT NULL') // Correct Nette syntax
            ->where('finish_ticket IS NOT NULL') // Correct Nette syntax
            ->fetch();

        // fetch() returns an ActiveRow or false. Check if calculation was successful.
        if (!$result || $result->average_duration === null) {
            return null; // No completed tickets or failed query
        }

        $averageSeconds = (float) $result->average_duration;

        // Round to the nearest second
        $totalSeconds = round($averageSeconds);

        // Handle zero or negative average (though should not be negative with valid dates)
        if ($totalSeconds <= 0) {
            return "0 minutes";
        }

        // Define time units in seconds
        $secondsInMinute = 60;
        $secondsInHour = 3600; // 60 * 60
        $secondsInDay = 86400; // 3600 * 24

        // Calculate days, hours, and minutes
        $days = floor($totalSeconds / $secondsInDay);
        $remainingSeconds = $totalSeconds % $secondsInDay;

        $hours = floor($remainingSeconds / $secondsInHour);
        $remainingSeconds %= $secondsInHour;

        $minutes = floor($remainingSeconds / $secondsInMinute);

        // Build the formatted string
        $parts = [];
        if ($days > 0) {
            $parts[] = "{$days} " . ($days === 1 ? 'day' : 'days');
        }
        if ($hours > 0) {
            $parts[] = "{$hours} " . ($hours === 1 ? 'hour' : 'hours');
        }
        if ($minutes > 0) {
            $parts[] = "{$minutes} " . ($minutes === 1 ? 'minute' : 'minutes');
        }

        // Handle cases where the duration is less than a minute but greater than 0 seconds
        if (empty($parts)) {
            return "Less than 1 minute";
        }

        return implode(', ', $parts);
    }

    public function getFormattedAverageOpenTicketDuration(): ?string
    {
        // Adjust the TIMESTAMPDIFF function and NOW() based on your database
        // e.g., for PostgreSQL use EXTRACT(EPOCH FROM (NOW() - receive_ticket))
        $result = $this->database->table('admin_tickets')
            ->select('AVG(TIMESTAMPDIFF(SECOND, receive_ticket, NOW())) AS average_duration') // Changed finish_ticket to NOW()
            ->where('is_done', 0) // Filter for open tickets
            ->where('receive_ticket IS NOT NULL')
            ->fetch();

        // fetch() returns an ActiveRow or false. Check if calculation was successful.
        if (!$result || $result->average_duration === null) {
            return null; // No open tickets or failed query
        }

        $averageSeconds = (float) $result->average_duration;

        // Round to the nearest second
        $totalSeconds = round($averageSeconds);

        // Handle zero or negative average
        if ($totalSeconds <= 0) {
            // If the average is slightly negative due to clock sync issues, treat as 0
            // If it's exactly 0, it's 0 minutes
            // If it's positive but rounds down to 0, it will be handled later
             return "0 minutes";
        }

        // Define time units in seconds
        $secondsInMinute = 60;
        $secondsInHour = 3600; // 60 * 60
        $secondsInDay = 86400; // 3600 * 24

        // Calculate days, hours, and minutes
        $days = floor($totalSeconds / $secondsInDay);
        $remainingSeconds = $totalSeconds % $secondsInDay;

        $hours = floor($remainingSeconds / $secondsInHour);
        $remainingSeconds %= $secondsInHour;

        $minutes = floor($remainingSeconds / $secondsInMinute);

        // Build the formatted string
        $parts = [];
        if ($days > 0) {
            // Use 1.0 for float comparison to be safe, though floor returns float/int based on PHP version/input
            $parts[] = "{$days} " . ($days === 1.0 ? 'day' : 'days');
        }
        if ($hours > 0) {
            $parts[] = "{$hours} " . ($hours === 1.0 ? 'hour' : 'hours');
        }
        if ($minutes > 0) {
            $parts[] = "{$minutes} " . ($minutes === 1.0 ? 'minute' : 'minutes');
        }

        // Handle cases where the duration is less than a minute but greater than 0 seconds
        if (empty($parts)) {
             // $totalSeconds is already checked > 0 earlier, so if parts is empty, it must be < 60 seconds
             return "Less than 1 minute";
        }

        return implode(', ', $parts);
    }

    public function deleteTicket(int $id): void
    {
        $this->database->table('admin_tickets')->where('id', $id)->delete();
    }

}