<?php

declare(strict_types=1);

namespace App\Model\Bell;

use Nette\Database\Explorer;
use Nette\Utils\DateTime;
use App\Forms\StorageManager;

class BellRepository
{
    public function __construct(
        private Explorer $database,
        private StorageManager $storageManager
    ) {
    }

    public function getAllWorshops()
    {
        return $this->database->table('bell_workshop')
            ->order('workshop_name');
    }

    public function addWorkshop(array $data): void
    {
        unset($data['workshop_id']);
        $this->database->table('bell_workshop')->insert($data);
    }

    public function getWorkshopById(int $id)
    {
        return $this->database->table('bell_workshop')
            ->where('workshop_id', $id)
            ->fetch();
    }

    public function updateWorkshop(int $id, array $data): void
    {
        unset($data['workshop_id']);
        $this->database->table('bell_workshop')
            ->where('workshop_id', $id)
            ->update($data);
    }

    public function getAllBells()
    {
        return $this->database->table('bell');
    }

    public function getCalPlace(): array
    {
        return $this->database->table('place')
            ->fetchPairs('id_place', 'nazev');
    }

    public function getBellById(int $id)
    {
        return $this->database->table('bell')
            ->where('bell_id', $id)
            ->fetch();
    }

    public function addBell(array $data): void
    {
        unset($data['bell_id']);
        $this->database->table('bell')->insert($data);
    }

    public function updateBell(int $id, array $data): void
    {
        unset($data['bell_id']);
        $this->database->table('bell')
            ->where('bell_id', $id)
            ->update($data);
    }

    public function deleteBell(int $id): void
    {
        $bell = $this->getBellById($id);

        if ($bell) {
            if (!empty($bell->bell_photo_path)) {
                if ($this->storageManager->fileExists($bell->bell_photo_path)) {
                    $this->storageManager->deleteFileOrImage($bell->bell_photo_path);
                }
            }

            $this->database->table('bell')
                ->where('bell_id', $id)
                ->delete();
        }
    }
}