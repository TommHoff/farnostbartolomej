<?php

declare(strict_types=1);

namespace App\UI\Back\Post;

use App\Ui\Back\Post\PostFormFactory;
use App\Forms\StorageManager;
use App\UI\Back\BasePresenter;
use Nette\Utils\Paginator;

final class PostPresenter extends BasePresenter
{

    public function __construct(
        private readonly PostFormFactory $postFormFactory,
        private readonly StorageManager $storageManager
    ) {}

    public function renderDefault(int $page = 1): void
    {
        // Fetch the total count of published articles
        $articlesCount = $this->newsFacade->getPublishedArticlesCount();

        // Create and configure Paginator
        $paginator = new Paginator;
        $paginator->setItemCount($articlesCount); // Total number of articles
        $paginator->setItemsPerPage(20); // Number of articles per page
        $paginator->setPage($page); // Current page number

        // Fetch a limited set of articles using Paginator
        $articles = $this->newsFacade->findPublishedArticles($paginator->getLength(), $paginator->getOffset());

        // Pass data to the template
        $this->template->articles = $articles;
        $this->template->paginator = $paginator;
        $this->template->posts = $articles;
        $this->template->page = $page; // <-- Add this line to pass $page to the template
        $this->template->lastPage = $paginator->getPageCount(); // Pass the last page for pagination
    }
    protected function createComponentPostForm()
    {
        $postId = $this->getParameter('id');
        $postId = $postId !== null ? (int)$postId : null;

        return $this->postFormFactory->create(function () use ($postId): void {
            $this->flashMessage($postId ? 'The post has been updated.' : 'The post has been added.');
            $this->redirect(':Back:Post:default');
        }, $postId);
    }

    public function actionAdd(): void
    {
        $this->getComponent('postForm');
    }

    public function actionEdit(int $id): void
    {
        $record = $this->newsFacade->getPostById($id);
        if (!$record) {
            $this->error();
        }
    
        $this['postForm']->setDefaults($record->toArray());
    }

    public function handleDelete(int $id): void
    {
        $record = $this->newsFacade->getPostById($id);
        if (!$record) {
            $this->error('Record not found');
        }

        // Delete associated image and file if they exist
        if ($record->photo) {
            $this->storageManager->deleteFileOrImage($record->photo);
        }
        if ($record->filePdf) {
            $this->storageManager->deleteFileOrImage($record->filePdf);
        }

        $this->newsFacade->deletePost($id);
        $this->flashMessage('The post has been deleted.');
        $this->redirect('this');
    }
}
