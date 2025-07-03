<?php

declare(strict_types=1);

namespace App\Model\News;

use Nette\Database\Explorer;

final class NewsFacade
{
    public function __construct(
        private readonly Explorer $posts
    ) {}

    public function addPost($values)
    {
        $this->posts->table('posts')
                 ->insert($values);
    }

    public function editPost($values, int $id)
    {
        $this->posts->table('posts')
                 ->where('id', $id)
                 ->update($values);
    }

    public function deletePost(int $id)
    {
        $this->posts->table('posts')
                 ->where('id', $id)
                 ->delete();
    }

    public function getAllPosts()
    {
        return $this->posts->table('posts')
                        ->order('published_at DESC');
    }

	public function getBartiks()
    {
        return $this->posts->table('posts')
						->where('show_bartik', 1)
						->limit (20)
                        ->order('published_at DESC');
    }


	public function getVestniks()
    {
        return $this->posts->table('posts')
						->where('show_bartik', 2)
						->limit (20)
                        ->order('published_at DESC');
    }

    public function getPostById(int $id)
    {
        return $this->getAllPosts()
                    ->where('id', $id)
                    ->fetch();
    }

	public function findPublishedArticles(int $limit, int $offset): \Nette\Database\Table\Selection
	{
		return $this->posts->table('posts')
			->order('published_at DESC')
			->limit($limit, $offset);
	}
	
	public function getPublishedArticlesCount(): int
	{
		return $this->posts->table('posts')
						->count();
	}

    public function getPostSummary()
    {
        return $this->posts->query(
            "SELECT 
            YEAR(p.published_at) AS post_year,
            COUNT(*) AS post_count
        FROM 
            posts p
        GROUP BY 
            post_year
        ORDER BY 
            post_year DESC, 
            post_count DESC"
        )->fetchAll();
    }


}