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
                'name' => $cat->getName(),
//                'name' => $this->translator->trans($cat->getName()),
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

    public function parseLink(string $userLink): ?string
    {
        // userlinkk may be a video link (11 characters)
        if (strlen($userLink) === 11) {
            return $userLink;
        }
        // Check if the link is a valid YouTube URL and extract the video ID
        $pattern = '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
        preg_match($pattern, $userLink, $matches);
        if (key_exists(1, $matches) && strlen($matches[1]) === 11) {
            $videoLink = $matches[1];
        } else {
            // And another pattern for YouTube short links: https://youtube.com/shorts/VsMVTAOY9h4?si=NLj0Ztc-WtneY5yG
            $pattern = '/https?:\/\/(?:www\.)?youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/';
            preg_match($pattern, $userLink, $matches);
            $videoLink = $matches[1] ?? null;
        }
        return $videoLink;
    }
}