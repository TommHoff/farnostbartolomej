<?php

declare(strict_types=1);

namespace App\Model\News;

use Nette\Database\Explorer;

final class NewsRepository
{
    public function __construct(private Explorer $database) {}

    public function getLastNews()
    {
        return $this->database->table('posts')
            ->order('published_at DESC')
            ->limit(1);
    }
}