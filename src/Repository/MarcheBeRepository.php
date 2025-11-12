<?php

namespace App\Repository;

use App\Entity\Document;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MarcheBeRepository
{
    private HttpClientInterface $client;

    public function __construct(?HttpClientInterface $client = null)
    {
        $this->client = $client ?? HttpClient::create();
    }

    /**
     * @return array<int,Document>
     */
    public function getAllPosts(): array
    {
        $documents = [];
        foreach (Theme::getSites() as $siteName) {
            $posts = $this->getPosts($siteName);
            foreach ($posts as $post) {
                $post->categories = $this->getCategoriesByPost($siteName, $post->id);
                $documents[] = Document::createFromPost($post, $siteName);
            }
            $posts = $this->getPosts(2);
            foreach ($posts as $post) {
                $post->categories = $this->getCategoriesByPost($siteName, $post->id);
                $documents[] = Document::createFromPost($post, $siteName);
            }
        }

        return $documents;
    }

    /**
     * https://www.marche.be/sante/wp-json/wp/v2/posts?per_page=100&page=1
     * @param string $siteName
     * @param int $pageNumber
     * @return array
     */
    public function getPosts(string $siteName, int $pageNumber = 1): array
    {
        if ($siteName === 'citoyen') {
            $siteName = '';
        }
        try {
            $response = $this->client->request(
                'GET',
                'https://www.marche.be/'.$siteName.'/wp-json/wp/v2/posts?per_page=100&page='.$pageNumber
            );
        } catch (TransportExceptionInterface $e) {
            return [];
        }
        try {
            $content = $response->getContent();
        } catch (ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface $e) {
            return [];
        }

        try {
            return json_decode($content, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return [];
        }
    }

    /**
     * https://www.marche.be/wp-json/wp/v2/categories?post=12295
     * @param string $siteName
     * @param int $postId
     * @return array
     */
    public function getCategoriesByPost(string $siteName, int $postId): array
    {
        if ($siteName === 'citoyen') {
            $siteName = '';
        }
        try {
            $response = $this->client->request(
                'GET',
                'https://www.marche.be/'.$siteName.'/wp-json/wp/v2/categories?post='.$postId
            );
        } catch (TransportExceptionInterface $e) {
            return [];
        }
        try {
            $content = $response->getContent();
        } catch (ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface $e) {
            return [];
        }

        try {
            return json_decode($content, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return [];
        }
    }
    /**
     * @return array<int,Document>
     */
    public function getAllAttachments(): array
    {
        $documents = [];
        foreach (Theme::getSites() as $siteName) {
            $posts = $this->getAttachments($siteName);
            foreach ($posts as $post) {
                $post->categories = $this->getCategoriesByPost($siteName, $post->id);
                $documents[] = Document::createFromAttachment($post, $siteName);
            }
            $posts = $this->getAttachments(2);
            foreach ($posts as $post) {
                $post->categories = $this->getCategoriesByPost($siteName, $post->id);
                $documents[] = Document::createFromAttachment($post, $siteName);
            }
        }

        return $documents;
    }
    /**
     * https://www.marche.be/sante/wp-json/wp/v2/media?per_page=100&media_type=application
     * @param string $siteName
     * @param int $pageNumber
     * @return array
     */
    public function getAttachments(string $siteName, int $pageNumber = 1): array
    {
        if ($siteName === 'citoyen') {
            $siteName = '';
        }
        try {
            $response = $this->client->request(
                'GET',
                'https://www.marche.be/'.$siteName.'/wp-json/wp/v2/media?per_page=100&media_type=application&page='.$pageNumber
            );
        } catch (TransportExceptionInterface $e) {
            return [];
        }
        try {
            $content = $response->getContent();
        } catch (ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface $e) {
            return [];
        }

        try {
            return json_decode($content, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return [];
        }
    }
}
