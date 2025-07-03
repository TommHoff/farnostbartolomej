<?php

declare(strict_types=1);

namespace App\UI\Front\Ministrans;

use App\UI\Front\BasePresenter;

class MinistransPresenter extends BasePresenter
{

    public function renderDefault(): void
    {
        $this->template->events = $this->calendarRepository->getAllEvents()
            ->where('is_visible = 1')
            ->where('Fid_note = 42') // mininstrants event
            ->where('date_end > NOW()')
            ->fetchAll();
    }

}