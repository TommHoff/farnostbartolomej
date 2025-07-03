<?php

declare(strict_types=1);

namespace App\Model\Catholic;

use Nette\Database\Explorer;
use Nette\Utils\ArrayHash;


final class FeastRepository
{
    public function __construct(private readonly Explorer $database)
    {
    }

    public function getAllFeasts(): array
    {
        return $this->database->table('feast')->fetchAll();
    }

    public function getFeast(int $id): ?ArrayHash
    {
        $item = $this->database->table('feast')->get($id);
        return $item ? ArrayHash::from($item->toArray()) : null;
    }

    public function getFeastsForToday(): array
    {
        // Format today's date as 'mm-dd'
        $todayDate = date('m-d');

        // Compare directly with feast_date if it's stored in 'mm-dd' format as a string
        return $this->database->table('feast')
            ->where('feast_date = ?', $todayDate)
            ->order('feast_levelus_Fid')
            ->fetchAll();
    }

    public function getFeastLevels(): array
    {
        return $this->database->table('feast_levelus')->fetchPairs('feast_levelus_id', 'levelus_description');
    }

    public function getFeastSpecies(): array
    {
        return $this->database->table('feast_species')->fetchPairs('feast_species_id', 'note');
    }

    public function getFeastWithDetails(int $id): ?ArrayHash
    {
        $feast = $this->database->table('feast')->get($id);
        if ($feast) {
            $feastData = ArrayHash::from($feast->toArray());

            $feastData->feast_levelus_description = $feast->ref('feast_levelus', 'feast_levelus_Fid')->levelus_description ?? '';
            $feastData->feast_species_description = $feast->ref('feast_species', 'feast_species_Fid')->note ?? '';
    
            return $feastData;
        }
        return null;
    }

    public function addFeast(ArrayHash $values): void
    {
        $this->database->table('Feast')->insert($values);
    }

    public function updateFeast(int $id, ArrayHash $values): void
    {
        if (!$id) {
            throw new \InvalidArgumentException("Feast ID must be provided for update.");
        }
        
        $this->database->table('feast')->where('feast_id', $id)->update($values);
    }

    public function deleteFeast(int $id): void
    {
        $this->database->table('feast')->where('feast_id', $id)->delete();
    }

}
