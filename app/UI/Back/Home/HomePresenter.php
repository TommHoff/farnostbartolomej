<?php

declare(strict_types=1);

namespace App\UI\Back\Home;

use App\UI\Back\BasePresenter;

class HomePresenter extends BasePresenter
{

    public function renderDefault(): void
    {
        $this->template->eventsSummary = $this->calendarRepository->getEventSummary();
        $this->template->posty = $this->newsFacade->getPostSummary();
    }

    public function renderMembers(): void
    {
        $this->template->members = $this->userRepository->getAllUsers();
    }

    public function handleToggleActive(int $id): void
    {
        $user = $this->userFacade->getAccount($id);
        if ($user) {
            $this->userFacade->updateAccount($id, [
                'is_active' => $user->is_active ? 0 : 1,
            ]);
            $this->flashMessage(
                $user->is_active ? 'Uživatel byl deaktivován.' : 'Uživatel byl aktivován.',
                'success'
            );
        } else {
            $this->flashMessage('Uživatel nenalezen.', 'error');
        }

        if ($this->isAjax()) {
            $this->redrawControl('table');
        } else {
            $this->redirect('this');
        }
    }
}