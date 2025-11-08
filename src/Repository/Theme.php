<?php

namespace App\Repository;

enum Theme: int
{
    case CITOYEN = 1;
    case ADMINISTRATION = 2;
    case ECONOMIE = 3;
    case TOURISME = 4;
    case SPORT = 5;
    case SANTE = 6;
    case SOCIAL = 7;
    case MARCHOIS = 8;
    case CULTURE = 11;
    case ROMAN = 12;
    case ENFANCE = 14;

    public function getSiteName(): string
    {
        return match ($this) {
            self::CITOYEN => 'citoyen',
            self::ADMINISTRATION => 'administration',
            self::ECONOMIE => 'economie',
            self::TOURISME => 'tourisme',
            self::SPORT => 'sport',
            self::SANTE => 'sante',
            self::SOCIAL => 'social',
            self::MARCHOIS => 'marchois',
            self::CULTURE => 'culture',
            self::ROMAN => 'roman',
            self::ENFANCE => 'enfance',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function getSites(): array
    {
        $sites = [];
        foreach (self::cases() as $case) {
            $sites[$case->value] = $case->getSiteName();
        }
        return $sites;
    }
}