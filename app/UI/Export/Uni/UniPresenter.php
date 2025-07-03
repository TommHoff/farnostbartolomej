<?php

declare(strict_types=1);

namespace App\UI\Export\Uni;

use App\Model\Develepment\UniRepository;
use App\Model\User\UserRepository;
use Nette\Application\Responses\TextResponse;
use Nette\Application\UI\Presenter;
use Nette\Http\IResponse;

final class UniPresenter extends Presenter
{
    public function __construct(
        private readonly UniRepository $uniRepository,
        private readonly UserRepository $userRepository,
        private readonly IResponse $httpResponse
    ) {
    }

    public function renderDefault(): void
    {
        // Get the latest revision data
        $uniRev = $this->uniRepository->getUniRev();
        
        // Count active users
        $activeUsersCount = count($this->userRepository->findActiveMembersForList());
        
        // Prepare the JSON data
        $jsonData = [
            'release_date' => $uniRev ? $uniRev->release_date : null,
            'major' => $uniRev ? $uniRev->major : null,
            'minor' => $uniRev ? $uniRev->minor : null,
            'bugfix' => $uniRev ? $uniRev->bugfix : null,
            'info' => $activeUsersCount
        ];
        
        // Convert to JSON
        $json = json_encode($jsonData, JSON_PRETTY_PRINT);
        
        // Set content type and send response
        $this->httpResponse->setContentType('application/json');
        $this->sendResponse(new TextResponse($json));
    }
}