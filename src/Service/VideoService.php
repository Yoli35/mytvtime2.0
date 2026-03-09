<?php

namespace App\Service;

use App\Repository\VideoCategoryRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class VideoService
{
    public function __construct(
        private TranslatorInterface $translator,
        private VideoCategoryRepository  $categoryRepository,
    )
    {
    }

    public function getCategories() :array
    {
        $categories = array_map(function ($cat) {
            return [
                'id' => $cat->getId(),
                'name' => $this->translator->trans($cat->getName()),
                'color' => $cat->getColor(),
            ];
        }, $this->categoryRepository->findAll());

        usort($categories, function ($a, $b) {
            // Remplacer les accents pour une comparaison correcte
            $a['name'] = preg_replace('/[ÉÈÊË]/u', 'E', $a['name']);
            $b['name'] = preg_replace('/[ÉÈÊË]/u', 'E', $b['name']);
            $a['name'] = preg_replace('/[éèêë]/u', 'e', $a['name']);
            $b['name'] = preg_replace('/[éèêë]/u', 'e', $b['name']);
            // Comparer les noms des catégories
            return strcmp($a['name'], $b['name']);
        });

        return $categories;
    }
}