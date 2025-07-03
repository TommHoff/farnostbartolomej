<?php

declare(strict_types=1);

namespace App\UI\Back;


use Nette\DI\Attributes\Inject;
use Nette\Application\UI\Presenter;

class BasePresenter extends Presenter
{

    #[Inject]
    public \Texy\Texy $texy;
    #[Inject]
    public \App\Model\Develepment\UniRepository $uniRepository;

    #[Inject]
    public \App\Model\Calendar\CalendarFacade $calendarFacade;
    #[Inject]
    public \App\Model\Calendar\CalendarRepository $calendarRepository;

    #[Inject]
    public \App\Model\News\NewsFacade $newsFacade;

    #[Inject]
    public \App\Model\User\UserFacade $userFacade;
    #[Inject]
    public \App\Model\User\userRepository $userRepository;


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
        }

        if ($this->getUser()->isLoggedIn() && !$this->getUser()->isAllowed('management')) {
            $this->flashMessage('🤖 Access denied! This area is for admin robots only! 🚫✨', 'warning');
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