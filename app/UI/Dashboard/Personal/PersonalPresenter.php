<?php

declare(strict_types=1);

namespace App\UI\Dashboard\Personal;

use App\Model\User\UserFacade;
use App\UI\Dashboard\BasePresenter;

final class PersonalPresenter extends BasePresenter
{
    // Inject UserFacade
    public function __construct(private UserFacade $userFacade) // <-- Inject UserFacade
    {
    }

    public function renderDefault(): void
    {
        // Get the logged-in user's ID
        $userId = $this->getUser()->getId();

        // Fetch the user's data using the facade
        $userData = $this->userFacade->getAccount($userId);

        // Check if data was found (user should be logged in, but good practice)
        if (!$userData) {
            $this->error('User not found.'); // Or handle appropriately
        }

        // Pass the data to the template
        $this->template->userData = $userData;
    }
}