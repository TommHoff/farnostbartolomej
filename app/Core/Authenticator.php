<?php

declare(strict_types=1);

namespace App\Core;

use Nette;
use Nette\Database\Explorer;
use Nette\Security\Authenticator as NetteAuthenticator;
use Nette\Security\Passwords;
use Nette\Security\SimpleIdentity;
use Nette\Security\IIdentity;
use Nette\Security\IdentityHandler;

final class Authenticator implements NetteAuthenticator, IdentityHandler
{
    use Nette\SmartObject;

    private const
        TableName = 'users',
        ColumnId = 'id',
        ColumnPasswordHash = 'password',
        ColumnRole = 'roles',
        ColumnActive = 'is_active';

    public function __construct(
        private readonly Explorer $database,
        private readonly Passwords $passwords
    ) {}

    public function authenticate(string $email, string $password): IIdentity
    {
        $row = $this->database->table(self::TableName)
            ->where('email', $email)
            ->fetch();

        if (!$row) {
            throw new Nette\Security\AuthenticationException('The username is incorrect.', self::IDENTITY_NOT_FOUND);
        } elseif (!$row[self::ColumnActive]) {
            throw new Nette\Security\AuthenticationException('The account is not active.', self::NOT_APPROVED);
        } elseif (!$this->passwords->verify($password, $row[self::ColumnPasswordHash])) {
            throw new Nette\Security\AuthenticationException('The password is incorrect.', self::INVALID_CREDENTIAL);
        } elseif ($this->passwords->needsRehash($row[self::ColumnPasswordHash])) {
            $row->update([
                self::ColumnPasswordHash => $this->passwords->hash($password),
            ]);
        }

        $data = $row->toArray();
        unset($data[self::ColumnPasswordHash]);

        return new SimpleIdentity(
            $row[self::ColumnId],
            explode(',', $row[self::ColumnRole]),
            $data
        );
    }

    /**
     * Executed when storing the identity into storage (session).
     */
    public function sleepIdentity(IIdentity $identity): IIdentity
    {
        // No special handling needed, just return stored identity
        return $identity;
    }

    /**
     * Executed when restoring the identity from storage (session).
     * Update roles from the database on each session load.
     */
    public function wakeupIdentity(IIdentity $identity): ?IIdentity
    {
        $userId = $identity->getId();
        $row = $this->database->table(self::TableName)->get($userId);

        if (!$row || !$row[self::ColumnActive]) {
            // User no longer exists or isn't active anymore; log out user automatically for safety purposes.
            return null;
        }

        $identityData = $row->toArray();
        unset($identityData[self::ColumnPasswordHash]);

        return new SimpleIdentity(
            $userId,
            explode(',', $row[self::ColumnRole]),
            $identityData
        );
    }
}