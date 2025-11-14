<?php

namespace App\Repository;

use App\Entity\Document;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TaxeRepository
{
    private HttpClientInterface $client;

    public function __construct(?HttpClientInterface $client = null)
    {
        $this->client = $client ?? HttpClient::create();
    }

    /**
     * @return array<int,Document>
     */
    public function getAllTaxes(): array
    {
        $documents = [];
        foreach ($this->fetchAll() as $nomenclature) {
            foreach ($nomenclature->taxes as $taxe) {
                $documents[] = Document::createFromTaxe($taxe);
            }
        }

        return $documents;
    }

    /**
     * https://extranet.marche.be/taxes/api2
     * @return array
     */
    private function fetchAll(): array
    {
        try {
            $response = $this->client->request(
                'GET',
                'https://extranet.marche.be/taxes/api2'
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
