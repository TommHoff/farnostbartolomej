<?php
declare(strict_types=1);

namespace App\Model\User;

use Nette\Database\Explorer;

final class UserRepository
{
    public function __construct(private Explorer $database)
    {
    }

    public function getAllUsers(): array
    {
        return $this->database->table('users')
            ->order('id ASC')
            ->fetchAll();
    }

    public function findActiveMembersForList(): array
    {
        return $this->database->table('users')
            ->where('is_active', 1)
            ->fetchAll();
    }

}