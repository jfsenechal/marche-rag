<?php

namespace App\Repository;

use App\Entity\Document;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 *
 */
class BottinRepository
{
    private HttpClientInterface $client;

    public function __construct(?HttpClientInterface $client = null)
    {
        $this->client = $client ?? HttpClient::create();
    }

    /**
     * @return array<int,Document>
     */
    public function getBottin(): array
    {
        $documents = [];
        $fiches = $this->getFiches();
        foreach ($fiches as $fiche) {
            $documents[] = Document::createFromFiche($fiche);
        }

        return $documents;
    }

    public function getFiches(): array
    {
        try {
            $response = $this->client->request(
                'GET',
                'https://api.marche.be/bottin/fichesandroid'
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

    public static function getContentFiche(\stdClass $fiche): string
    {
        $parts = [];

        // Company information
        if (!empty($fiche->societe)) {
            $parts[] = "société: {$fiche->societe}";
        }

        // Contact person
        if (!empty($fiche->nom)) {
            $parts[] = "nom: {$fiche->nom}";
        }
        if (!empty($fiche->prenom)) {
            $parts[] = "prénom: {$fiche->prenom}";
        }

        // Email addresses
        if (!empty($fiche->email)) {
            $parts[] = "email: {$fiche->email}";
        }
        if (!empty($fiche->contact_email)) {
            $parts[] = "email contact: {$fiche->contact_email}";
        }

        // Phone numbers
        if (!empty($fiche->telephone)) {
            $parts[] = "téléphone: {$fiche->telephone}";
        }
        if (!empty($fiche->telephone_autre)) {
            $parts[] = "téléphone autre: {$fiche->telephone_autre}";
        }
        if (!empty($fiche->gsm)) {
            $parts[] = "GSM: {$fiche->gsm}";
        }
        if (!empty($fiche->contact_telephone)) {
            $parts[] = "téléphone contact: {$fiche->contact_telephone}";
        }
        if (!empty($fiche->contact_telephone_autre)) {
            $parts[] = "téléphone contact autre: {$fiche->contact_telephone_autre}";
        }
        if (!empty($fiche->contact_gsm)) {
            $parts[] = "GSM contact: {$fiche->contact_gsm}";
        }

        // Web & social media
        if (!empty($fiche->website)) {
            $parts[] = "site web: {$fiche->website}";
        }
        if (!empty($fiche->facebook)) {
            $parts[] = "facebook: {$fiche->facebook}";
        }
        if (!empty($fiche->twitter)) {
            $parts[] = "twitter: {$fiche->twitter}";
        }

        // Comments/descriptions
        if (!empty($fiche->comment1)) {
            $parts[] = "description: {$fiche->comment1}";
        }
        if (!empty($fiche->comment2)) {
            $parts[] = "description 2: {$fiche->comment2}";
        }
        if (!empty($fiche->comment3)) {
            $parts[] = "description 3: {$fiche->comment3}";
        }

        return implode(' ', $parts);
    }
}
