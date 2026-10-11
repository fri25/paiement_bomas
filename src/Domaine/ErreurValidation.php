<?php

declare(strict_types=1);

namespace App\Domaine;

final class ErreurValidation extends \DomainException
{
    /** @param array<string, string> $erreurs message par champ (ex. "eleves.0.nom") */
    public function __construct(public readonly array $erreurs)
    {
        $nombre = count($erreurs);
        parent::__construct($nombre === 1
            ? 'Un champ est à corriger avant de payer.'
            : "$nombre champs sont à corriger avant de payer.");
    }
}
