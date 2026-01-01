<?php
namespace App\Twig;

use App\Repository\ContactMessageRepository;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;

readonly class AdminExtension
{
    public function __construct(
        private ContactMessageRepository $contactMessageRepository,
        private TranslatorInterface      $translator,
    ) {
    }
    #[AsTwigFunction('adminNewMessageCheck')]
    public function adminNewMessageCheck(string $type): string
    {
        $unreadMessageCount = $this->contactMessageRepository->count(['messageRead' => false]);

        if (!$unreadMessageCount) {
            return '';
        }
        if ($type === 'user menu') {
            $string = $this->translator->trans($unreadMessageCount > 1 ? 'new messages' : 'new message');
            return '<div class="admin-messages-count">' . $unreadMessageCount . ' ' . $string . '</div>';
        }
        return ' (' . $unreadMessageCount . ')';
    }
}