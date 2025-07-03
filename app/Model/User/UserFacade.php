<?php

declare(strict_types=1);

namespace App\Model\User;

use Nette;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Security\Passwords;
use Tracy\Debugger;
use Tracy\ILogger;

final class UserFacade
{
    use Nette\SmartObject;

    public const PasswordMinLength = 6;

    private const
        TableName = 'users',
        ColumnId = 'id',
        ColumnUserName = 'user_name',
        ColumnEmail = 'email',
        ColumnPhone = 'phone',
        ColumnPasswordHash = 'password',
        ColumnRole = 'roles',
        ColumnActive = 'is_active',
        ColumnPasswordResetToken = 'password_reset_token',
        ColumnTokenExpiration = 'token_expiration',
        ColumnUserNote = 'user_note';

    public function __construct(private Explorer $database, private Passwords $passwords)
    {
    }

    public function add(
        string $userName,
        string $email,
        string $password,
        string $roles = 'member',
        bool $isActive = true
    ): ActiveRow {

        $existingUser = $this->database->table(self::TableName)->where(self::ColumnEmail, $email)->fetch();
        if ($existingUser) {
            throw new DuplicateNameException("This email address is already registered.");
        }

        try {
            return $this->database->table(self::TableName)->insert([
                self::ColumnUserName => $userName,
                self::ColumnEmail => $email,
                self::ColumnPasswordHash => $this->passwords->hash($password),
                self::ColumnRole => $roles,
                self::ColumnActive => $isActive ? 1 : 0,
            ]);
        } catch (Nette\Database\UniqueConstraintViolationException $e) {
            if (str_contains(strtolower($e->getMessage()), self::ColumnEmail)) {
                throw new DuplicateNameException("This email address is already registered.");
            } elseif (str_contains(strtolower($e->getMessage()), self::ColumnUserName)) {
                throw new DuplicateNameException("This username ('" . htmlspecialchars($userName) . "') is already taken.");
            } else {
                Debugger::log($e, ILogger::WARNING);
                throw new DuplicateNameException("An account with these details might already exist.", 0, $e);
            }
        } catch (\Throwable $e) {
            Debugger::log($e, ILogger::EXCEPTION);
            throw new \RuntimeException("Failed to register user due to a database error. Check logs for details.", 0, $e);
        }
    }

    public function getAccount(int $id): ?ActiveRow
    {
        return $this->database->table(self::TableName)
            ->where(self::ColumnId, $id)
            ->fetch() ?: null;
    }

    public function updateAccount(int $id, array $data): void
    {
        $this->database->table(self::TableName)
            ->where(self::ColumnId, $id)
            ->update($data);
    }

    public function updateUserPassword(int $userId, string $hashedPassword): void
    {
        $this->database->table(self::TableName)
            ->where(self::ColumnId, $userId)
            ->update([
                self::ColumnPasswordHash => $hashedPassword,
                self::ColumnPasswordResetToken => null,
                self::ColumnTokenExpiration => null,
            ]);
    }

    public function generatePasswordResetToken(ActiveRow $user): string
    {
        $token = bin2hex(random_bytes(16));
        $expiration = (new \DateTimeImmutable())->modify("+1 day"); // Use DateTimeImmutable

        $this->database->table(self::TableName)
            ->where(self::ColumnId, $user->{self::ColumnId}) // Access ID via property
            ->update([
                self::ColumnPasswordResetToken => $token,
                self::ColumnTokenExpiration => $expiration, // Store DateTimeImmutable directly if DB supports TIMESTAMP
            ]);

        return $token;
    }

    public function getUserByToken(string $token): ?ActiveRow
    {
        $user = $this->database->table(self::TableName)
            ->where(self::ColumnPasswordResetToken, $token)
            ->where(self::ColumnTokenExpiration . ' >= ?', new \DateTimeImmutable()) // Use DateTimeImmutable
            ->fetch();

        return $user ?: null;
    }

    public function findByEmail(string $email): ?ActiveRow
    {
        return $this->database->table(self::TableName)
            ->where(self::ColumnEmail, $email) // Use constant
            ->fetch() ?: null;
    }
}

class DuplicateNameException extends \Exception
{
}