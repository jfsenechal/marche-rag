<?php

namespace App\Repository;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class MarcheBeRepository
{
    private HttpClientInterface $client;

    public function __construct(?HttpClientInterface $client = null)
    {
        $this->client = $client ?? HttpClient::create();
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
}
