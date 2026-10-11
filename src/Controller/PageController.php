<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Csrf;
use App\Http\Response;
use App\Support\Vue;

final class PageController
{
    /** @param array<string, mixed> $ecole contenu de config/ecole.php */
    public function __construct(
        private readonly Vue $vue,
        private readonly array $ecole,
    ) {
    }

    public function formulaire(): Response
    {
        // Seules les données utiles à l'affichage partent vers le navigateur.
        $configFront = [
            'annee'         => $this->ecole['annee'],
            'classes'       => $this->ecole['classes'],
            'contributions' => array_map(static fn (array $c): array => [
                'id'       => $c['id'],
                'libelle'  => $c['libelle'],
                'detail'   => $c['detail'],
                'montant'  => $c['montant'],
                'parEleve' => $c['par_eleve'],
            ], $this->ecole['contributions']),
            'montantLibre' => [
                'actif'   => $this->ecole['montant_libre']['actif'],
                'libelle' => $this->ecole['montant_libre']['libelle'],
                'detail'  => $this->ecole['montant_libre']['detail'],
                'minimum' => $this->ecole['montant_libre']['minimum'],
            ],
            'maxEleves' => $this->ecole['max_eleves'],
        ];

        return Response::html($this->vue->rendre('formulaire', [
            'chapo'       => "Réglez la contribution de vos enfants en quelques minutes, par Mobile Money ou carte bancaire. Un reçu s'affiche à la fin, à imprimer ou à garder en capture d'écran.",
            'configFront' => $configFront,
            'csrf'        => Csrf::jeton(),
        ]));
    }
}
