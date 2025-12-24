<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserListRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class UserListService
{
    public function __construct(
        private TranslatorInterface $translator,
        private UserListRepository  $userListRepository,
    )
    {
    }

    public function getUserList(User $user, int $id, string $locale): array
    {
        $listContent = array_map(function ($s) use ($locale) {
            $s['poster_path'] = $s['poster_path'] ? '/series/posters' . $s['poster_path'] : null;
            $s['sln_name'] = $s['localized_name'] ?: $s['name'];
            $s['sln_slug'] = $s['localized_slug'] ?: $s['slug'];
            $s['url'] = '/' . $locale . '/series/show/' . $s['id'] . '-' . $s['sln_slug'];
            $s['is_series_in_list'] = true;
            return $s;
        }, $this->userListRepository->getListContent($user, $id, $user->getPreferredLanguage() ?? $locale));

        $years = array_unique(array_values(array_column($listContent, 'air_year')));
        rsort($years);
        $count = count($listContent);
        $userList = $this->userListRepository->find($id);

        return [
            'userList' => $userList ? [
                'id' => $userList->getId(),
                'name' => $userList->getName(),
                'description' => $userList->getDescription(),
                'count' => $count . ' <span class="count-label">' . $this->translator->trans($count > 1 ? 'seriess' : 'series') . '</span>',
                'total_results' => $count,
            ] : null,
            'userListContent' => $listContent,
            'count' => $count,
            'years' => $years,
        ];
    }
}