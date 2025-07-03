<?php
declare(strict_types=1);

namespace App\Model\Develepment;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class UniRepository
{
    public function __construct(private Explorer $database) {}

    public function getUniChange(): array
    {
        return $this->database->table('admin_releases')
            ->order('release_date DESC, id ASC')
            ->fetchAll();
    }


    public function addEntry(array $data): void
    {
        $this->database->table('admin_releases')->insert($data);
    }

    public function updateEntry(int $id, array $data): void
    {
        $this->database->table('admin_releases')
            ->where('id', $id)
            ->update($data);
    }

    public function getEntryById(int $id): ?ActiveRow
    {
        return $this->database->table('admin_releases')->get($id);
    }

    public function deleteEntryById(int $id): void
    {
        $this->database->table('admin_releases')
            ->where('id', $id)
            ->delete();
    }

    public function getUniRev()
    {
        return $this->database->table('admin_releases')
            ->limit(1)
            ->order('release_date DESC')
            ->fetch();
    }

    public function getEntriesCount(): int
    {
        return $this->database->table('admin_releases')->count('*');
    }

    public function findEntries(int $limit, int $offset): array
    {
        return $this->database->table('admin_releases')
            ->order('release_date DESC')
            ->limit($limit, $offset)
            ->fetchAll();
    }
}
