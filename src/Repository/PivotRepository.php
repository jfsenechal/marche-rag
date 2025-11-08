<?php

namespace App\Repository;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class PivotRepository
{
    private HttpClientInterface $client;

    public function __construct(?HttpClientInterface $client = null)
    {
        $this->client = $client ?? HttpClient::create();
    }

    public function loadEvents(): ?\stdClass
    {
        try {
            $response = $this->client->request(
                'GET',
                'https://marchenew.marche.be/wp-json/pivot/events'
            );
        } catch (TransportExceptionInterface $e) {
            return null;
        }
        try {
            $content = $response->getContent();
        } catch (ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface $e) {
            return null;
        }

        try {
            return json_decode($content, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return null;
        }
    }

    public static function getContentEvent(\stdClass $event): string
    {
        $parts = [];

        // Add basic event fields with labels
        if (!empty($event->nom)) {
            $parts[] = "nom: {$event->nom}";
        }
        if (!empty($event->codeCgt)) {
            $parts[] = "code: {$event->codeCgt}";
        }

        // Add dates information
        if (!empty($event->dates)) {
            foreach ($event->dates as $date) {
                $dateParts = [];
                if (!empty($date->dateBegin)) {
                    $dateParts[] = "début: {$date->dateBegin->date}";
                }
                if (!empty($date->dateEnd)) {
                    $dateParts[] = "fin: {$date->dateEnd->date}";
                }
                if (!empty($date->dateRange)) {
                    $dateParts[] = "période: {$date->dateRange}";
                }
                if (!empty($date->ouvertureDetails)) {
                    $dateParts[] = "horaires: {$date->ouvertureDetails}";
                }
                if (!empty($dateParts)) {
                    $parts[] = implode(' ', $dateParts);
                }
            }
        }

        // Add address information
        if (!empty($event->adresse1)) {
            $address = $event->adresse1;
            $addressParts = [];

            if (!empty($address->rue)) {
                $addressParts[] = "rue: {$address->rue}";
            }
            if (!empty($address->numero)) {
                $addressParts[] = "numéro: {$address->numero}";
            }
            if (!empty($address->cp)) {
                $addressParts[] = "code postal: {$address->cp}";
            }
            if (!empty($address->localite)) {
                foreach ($address->localite as $loc) {
                    if (!empty($loc->value)) {
                        $addressParts[] = "localité: {$loc->value}";
                        break; // Use first locality
                    }
                }
            }
            if (!empty($addressParts)) {
                $parts[] = implode(' ', $addressParts);
            }
        }

        // Add coordinates
        if (!empty($event->latitude)) {
            $parts[] = "latitude: {$event->latitude}";
        }
        if (!empty($event->longitude)) {
            $parts[] = "longitude: {$event->longitude}";
        }

        // Add communication information
        if (!empty($event->communication)) {
            $comm = $event->communication;
            $commFields = [
                'mail1' => 'email',
                'mail2' => 'email2',
                'phone1' => 'téléphone',
                'phone2' => 'téléphone2',
                'mobile1' => 'mobile',
                'mobile2' => 'mobile2',
                'website' => 'site web',
                'facebook' => 'facebook',
                'pinterest' => 'pinterest',
                'youtube' => 'youtube',
                'flickr' => 'flickr',
                'instagram' => 'instagram',
            ];

            foreach ($commFields as $field => $label) {
                if (!empty($comm->$field)) {
                    $parts[] = "{$label}: {$comm->$field}";
                }
            }
        }

        return implode(' ', $parts);
    }
}