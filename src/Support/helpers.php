<?php

declare(strict_types=1);

// Fonctions utilisées dans les gabarits.

/** Échappe une valeur pour l'afficher dans du HTML. */
function e(mixed $valeur): string
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Montant en FCFA avec espaces insécables : 15 000 FCFA. */
function fcfa(int $montant): string
{
    return number_format($montant, 0, ',', "\u{202F}") . "\u{00A0}FCFA";
}

/** Numéro béninois lisible : +229 01 97 00 00 00. */
function telephone_lisible(string $numero): string
{
    return '+229 ' . trim(chunk_split($numero, 2, ' '));
}
