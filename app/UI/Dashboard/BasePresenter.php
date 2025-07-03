<?php

declare(strict_types=1);

namespace App\UI\Dashboard;

use Nette\DI\Attributes\Inject;
use Nette\Application\UI\Presenter;

class BasePresenter extends Presenter
{

    #[Inject]
    public \Texy\Texy $texy;
    #[Inject]
    public \App\Model\Develepment\UniRepository $uniRepository;

    public function startup(): void
    {
        parent::startup();
        $this->checkUserAuthentication();
    }

    protected function checkUserAuthentication(): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->flashMessage('Hold your 🦄 unicorns! Please log in first! 🚀😄', 'warning');
            $this->redirect(':Sign:Auth:In', ['backlink' => $this->storeRequest()]);
        } elseif (!$this->getUser()->isAllowed('dashboard')) {
            $this->flashMessage('🤖 Access denied! This area is for 🛸 dashboard users! 🚫✨', 'warning');
            $this->redirect(':Front:Home:default');
        }
    }

    public function beforeRender(): void
    {
        parent::beforeRender();
        $this->template->getLatte()->setLocale('cs_CZ');

        $this->template->uniRev = $this->uniRepository->getUniRev();

        $texy = new \Texy\Texy;
        $latte = new \Latte\Engine;
        $latte->addExtension(new \Texy\Bridges\Latte\TexyExtension($texy));

        $this->template->addFilter('texy', [$this->texy, 'process']);
    }
}

