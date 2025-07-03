<?php

declare(strict_types=1);

namespace App\UI\Front\Place;

use App\UI\Front\BasePresenter;
use Nette\Application\BadRequestException; // Import BadRequestException

class PlacePresenter extends BasePresenter
{

    public function renderDefault()
    {

    }

    public function renderKatedrala()
    {

    }

    public function renderKatedralazvony()
    {
        $this->template->bells = $this->bellRepository->getAllBells()->where('place_Fid', 2); // Assuming place_Fid 1 is Katedrála
    }

    /**
     * Renders the detail page for a specific bell.
     * @param int $bellId The ID of the bell to display.
     * @throws BadRequestException If the bell is not found.
     */
    public function renderZvon(int $bellId): void
    {
        $bell = $this->bellRepository->getBellById($bellId);

        if (!$bell) {
            // Throw a 404 error if the bell with the given ID doesn't exist
            $this->error('Zvon nebyl nalezen.'); // Or throw new BadRequestException('Zvon nebyl nalezen.');
        }

        // Pass the bell data to the template (zvon.latte)
        $this->template->bell = $bell;
    }


    public function renderNpm()
    {

    }

    public function renderNpmzvony()
    {
        $this->template->bells = $this->bellRepository->getAllBells()->where('place_Fid', 1);
    }

    public function renderKnihovna()
    {

    }


}