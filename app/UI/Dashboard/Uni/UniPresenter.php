<?php

namespace App\UI\Dashboard\Uni;

use App\UI\Admin\BasePresenter;
use Nette\Utils\Paginator;

class UniPresenter extends BasePresenter
{

    private const ITEMS_PER_PAGE = 10;

    public function renderDefault(int $page = 1): void
    {
        // Get total number of entries
        $entriesCount = $this->uniRepository->getEntriesCount();

        // Create Paginator instance and configure it
        $paginator = new Paginator();
        $paginator->setItemCount($entriesCount); // Total number of items
        $paginator->setItemsPerPage(self::ITEMS_PER_PAGE); // Items per page
        $paginator->setPage($page); // Current page

        // Fetch entries for the current page
        $entries = $this->uniRepository->findEntries($paginator->getLength(), $paginator->getOffset());

        $this->template->entries = $entries;
        $this->template->paginator = $paginator;
        $this->template->uniRev = $this->uniRepository->getUniRev();
    }
}