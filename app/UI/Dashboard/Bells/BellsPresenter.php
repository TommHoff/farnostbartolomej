<?php

declare(strict_types=1);

namespace App\UI\Dashboard\Bells;

use App\UI\Dashboard\BasePresenter;
use App\Model\Bell\BellRepository;
use App\Model\User\UserFacade;
use App\UI\Dashboard\Bells\WorkshopForm;
use App\UI\Dashboard\Bells\BellsFormFactory; // Use the correct Factory name
use Nette\Application\UI\Form;
use Nette\Application\BadRequestException;
use Nette\Application\AbortException;
use Throwable;


final class BellsPresenter extends BasePresenter
{
    public function __construct(
        private readonly UserFacade     $userFacade,
        private readonly BellRepository $bellRepository,
        private readonly WorkshopForm   $workshopFormFactory,
        private readonly BellsFormFactory $bellsFormFactory
    ) {
        parent::__construct();
    }

    public function startup(): void
    {
        parent::startup();
        $this->checkUserAuthentication();
    }

    protected function checkUserAuthentication(): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->flashMessage('Hold your 🦄 unicorns! Please log in first! 🚀😄', 'warning');
            $this->redirect(':Sign:Auth:in', ['backlink' => $this->storeRequest()]);
        } elseif (!$this->getUser()->isAllowed('bells')) {
            $this->flashMessage('...tohle místo je spíš pro zkušené zvoníky 🔔', 'warning');
            $this->redirect(':Dashboard:Home:default');
        }
    }

    public function renderDefault(): void
    {
        $this->template->bells = $this->bellRepository->getAllBells();
        $this->template->workshops = $this->bellRepository->getAllWorshops();
    }

    public function actionAddworkshop(): void
    {
        $this->template->isEdit = false;
        $this->template->pageTitle = 'Přidat nového autora:';
    }

    public function renderAddworkshop():void
    {

    }

    public function actionEditworkshop(int $id): void
    {
        $workshop = $this->bellRepository->getWorkshopById($id);
        if (!$workshop) {
            $this->flashMessage('autor nebyl nalezen.', 'error');
            $this->redirect('default');
        }
        $this->template->isEdit = true;
        $this->template->pageTitle = 'Upravit autora: ' . $workshop->workshop_name;
        $this->template->workshop = $workshop;
    }

    public function renderEditWorkshop(?int $id = null): void
    {

    }

    protected function createComponentWorkshopForm(): Form
    {
        $workshopId = $this->getParameter('id');
        $id = $workshopId ? (int)$workshopId : null;

        return $this->workshopFormFactory->create($id, function ($values) use ($id): void {
            $message = $id
                ? 'Autor byl úspěšně upraven. ✨'
                : 'Nový autor byl úspěšně přidán. 🎉';
            $this->flashMessage($message, 'success');
            $this->redirect('default');
        });
    }

    public function handleDeleteorkshop(int $id): void
    {
        if (!$this->getUser()->isAllowed('bells', 'delete')) {
            $this->flashMessage('Nemáte oprávnění mazat autora.', 'danger');
            $this->redirect('default');
        }

        try {
            $workshop = $this->bellRepository->getWorkshopById($id);
            if (!$workshop) {
                $this->flashMessage('Autor k odstranění nebyl nalezen.', 'warning');
            } else {
                $this->bellRepository->deleteWorkshop($id); // Assumes deleteWorkshop exists and handles photo deletion
                $this->flashMessage("Autor '{$workshop->workshop_name}' byl odstraněn.", 'success');
            }
        } catch (Throwable $e) {
            $this->flashMessage('Při odstraňování autora došlo k chybě.', 'danger');
        }
        $this->redirect('default');
    }

    // Bells Management Actions

    public function actionAddbell(): void
    {
        $this->template->isEdit = false;
        $this->template->pageTitle = 'Přidat nový zvon';
    }

    public function actionEditbell(int $id): void
    {
        $bell = $this->bellRepository->getBellById($id);
        if (!$bell) {
            $this->flashMessage('Zvon nebyl nalezen.', 'error');
            $this->redirect('default');
        }
        $this->template->isEdit = true;
        $this->template->pageTitle = 'Upravit zvon: ' . $bell->bell_name;
        $this->template->bell = $bell;
    }

    public function renderAddbell(): void
    {
        // Uses addbell.latte template
    }

    public function renderEditbell(?int $id = null): void
    {
        // Uses editbell.latte template
    }


    protected function createComponentBellsForm(): Form
    {
        $bellId = $this->getParameter('id');
        $id = $bellId ? (int)$bellId : null;

        return $this->bellsFormFactory->create($id, function ($values) use ($id): void {
            $message = $id
                ? 'Zvon byl úspěšně upraven. ✨'
                : 'Nový zvon byl úspěšně přidán. 🎉';
            $this->flashMessage($message, 'success');
            $this->redirect('default');
        });
    }

    public function handleDeletebell(int $id): void
    {
        if (!$this->getUser()->isAllowed('bells', 'delete')) {
            $this->flashMessage('Nemáte oprávnění mazat zvony.', 'danger');
            $this->redirect('default');
        }

        try {
            $bell = $this->bellRepository->getBellById($id);
            if (!$bell) {
                $this->flashMessage('Zvon k odstranění nebyl nalezen.', 'warning');
            } else {
                $this->bellRepository->deleteBell($id); // Assumes deleteBell exists and handles photo deletion
                $this->flashMessage("Zvon '{$bell->bell_name}' byl odstraněn.", 'success');
            }
        } catch (Throwable $e) {
            $this->flashMessage('Při odstraňování zvonu došlo k chybě.', 'danger');
        }
        $this->redirect('default');
    }
}