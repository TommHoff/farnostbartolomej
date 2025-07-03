<?php

declare(strict_types=1);

namespace App\UI\Front\News;

use Nette;
use App\Forms\StorageManager;
use App\UI\Front\BasePresenter;

class NewsPresenter extends BasePresenter
{

    public function __construct(
        private readonly StorageManager $storageManager,

    ) {
    }

	public function renderDefault(int $page = 1): void
	{
		// Zjistíme si celkový počet publikovaných článků
		
		$articlesCount = $this->newsFacade->getPublishedArticlesCount();

		// Vyrobíme si instanci Paginatoru a nastavíme jej
		$paginator = new Nette\Utils\Paginator;
		$paginator->setItemCount($articlesCount); // celkový počet článků
		$paginator->setItemsPerPage(10); // počet položek na stránce
		$paginator->setPage($page); // číslo aktuální stránky

		// Z databáze si vytáhneme omezenou množinu článků podle výpočtu Paginatoru
		$articles = $this->newsFacade->findPublishedArticles($paginator->getLength(), $paginator->getOffset());

		// kterou předáme do šablony
		$this->template->articles = $articles;
		// a také samotný Paginator pro zobrazení možností stránkování
		$this->template->paginator = $paginator;
		$this->template->posts = $this->newsFacade->findPublishedArticles($paginator->getLength(), $paginator->getOffset());
	}

	public function actionDetail(int $id): void
	{
		$post = $this->newsFacade->getPostById($id);
	
		if (!$post) {
			$this->flashMessage('...článek s touto adresou neexistuje, ale je tu velké množství dalších...', 'error');
			$this->redirect(':Front:News:default');
		}
	
		$this->template->post = $post;
	}
	
	public function renderBartik(): void
	{
		$this->template->posts = $this->newsFacade->getBartiks();
	}

	public function renderVestnik(): void
	{
		$this->template->posts = $this->newsFacade->getVestniks();
	}

}
