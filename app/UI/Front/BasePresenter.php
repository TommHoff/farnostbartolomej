<?php

declare(strict_types=1);

namespace App\UI\Front;

use Nette\DI\Attributes\Inject;
use Nette\Application\UI\Presenter;

class BasePresenter extends Presenter
{

    #[Inject]
    public \Texy\Texy $texy;
    #[Inject]
    public \App\Model\Develepment\UniRepository $uniRepository;

    #[Inject]
    public \App\Model\Calendar\CalendarRepository $calendarRepository;

    #[Inject]
    public \App\Model\News\NewsFacade $newsFacade;
    #[Inject]
    public \App\Model\Bell\BellRepository $bellRepository;




    public function beforeRender(): void
    {
        parent::beforeRender();
        $this->template->getLatte()->setLocale('cs_cz');

        $this->template->uniRev = $this->uniRepository->getUniRev();
        $this->template->FooterPosts = $this->newsFacade->getAllPosts()->limit(5)->fetchAll();
        $this->template->SmallCalendar = $this->calendarRepository->getAllEvents()
            ->where('is_visible = 1')
            ->where('date_end > NOW()')
            ->limit(5)
            ->fetchAll();

        $texy = new \Texy\Texy;
        $latte = new \Latte\Engine;
        $latte->addExtension(new \Texy\Bridges\Latte\TexyExtension($texy));

        $this->template->addFilter('texy', [$this->texy, 'process']);
    }
}

