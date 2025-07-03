<?php

declare(strict_types=1);

namespace App\UI\Admin\Feast;

use App\Model\Catholic\FeastRepository;
use App\UI\Admin\BasePresenter;
use Nette\Application\UI\Form;

final class FeastPresenter extends BasePresenter
{
    public function __construct(
        private readonly FeastFormFactory $feastFormFactory,
        private readonly FeastRepository $feastFacade
    ) {}

    public function renderDefault(): void
    {
        $this->template->feasts = $this->feastFacade->getAllFeasts();
    }

    protected function createComponentFeastForm(): Form
    {
        // Capture feastId as a local variable
        $feastId = (int) $this->getParameter('id');

        // Pass feastId as a fixed variable to the FeastFormFactory
        $form = $this->feastFormFactory->create(function () use ($feastId): void {
            $this->flashMessage($feastId ? 'The Feast has been updated.' : 'The Feast has been added.');
            $this->redirect(':Admin:Feast:default');
        }, $feastId);

        // If editing, load the record and set defaults
        if ($feastId > 0) {
            $record = $this->feastFacade->getFeastWithDetails($feastId);
            if (!$record) {
                $this->error('Feast not found');
                return $form;
            }
            $form->setDefaults((array) $record);
        }

        return $form;
    }

    public function handleDeleteFeast(int $id): void
    {
        try {
            $this->feastFacade->deleteFeast($id);
            $this->flashMessage('Feast deleted successfully.', 'success');
        } catch (\Exception $e) {
            $this->flashMessage('Error deleting Feast: ' . $e->getMessage(), 'danger');
        }
        $this->redirect('this');
    }


}
