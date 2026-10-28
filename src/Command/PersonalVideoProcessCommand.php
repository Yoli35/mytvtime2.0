<?php

namespace App\Command;

use App\Entity\PersonalVideo;
use App\Repository\PersonalVideoRepository;
use App\Service\DateService;
use App\Service\PersonalVideoService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Réencode les vidéos personnelles en attente, une à la fois.
 * Cron (IONOS) : chaque minute → php bin/console app:videos:process --env=prod
 * Un verrou empêche deux exécutions simultanées : un seul ffmpeg tourne à la fois (limite de 42 processus / threads).
 */
#[AsCommand(
    name: 'app:videos:process',
    description: 'Transcode pending personal videos to MP4 (H.264 / AAC), one at a time',
)]
class PersonalVideoProcessCommand
{
    private SymfonyStyle $io;
    private float $t0;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string                  $projectDir,
        private readonly DateService             $dateService,
        private readonly PersonalVideoRepository $personalVideoRepository,
        private readonly PersonalVideoService    $personalVideoService,
    )
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $this->io = $io;

        $lockDir = $this->projectDir . '/var/videos';
        if (!is_dir($lockDir)) {
            mkdir($lockDir, 0755, true);
        }
        $lock = fopen($lockDir . '/process.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            $io->writeln('Another process is already running.');
            return Command::SUCCESS;
        }

        // Le verrou étant libre, une vidéo « en cours » depuis longtemps a été interrompue (processus tué…).
        // Les plus récentes peuvent être en train d'être converties par une requête d'upload (changement de conteneur).
        $before = $this->dateService->getNowImmutable('UTC')->modify('-15 minutes');
        foreach ($this->personalVideoRepository->findProcessingSince($before) as $video) {
            $this->personalVideoService->fail($video, 'Processing interrupted');
        }

        $video = $this->personalVideoRepository->findNextPending();
        if (!$video) {
            flock($lock, LOCK_UN);
            return Command::SUCCESS;
        }

        $this->commandStart();
        do {
            $this->io->writeln(sprintf('Processing video %d: %s', $video->getId(), $video->getTitle()));
            $this->personalVideoService->process($video, true);
            if ($video->getStatus() === PersonalVideo::STATUS_READY) {
                $this->io->writeln('→ ' . $video->getFilename());
            } else {
                $this->io->error($video->getError() ?? $video->getStatus());
            }
        } while ($video = $this->personalVideoRepository->findNextPending());
        $this->commandEnd();

        flock($lock, LOCK_UN);

        return Command::SUCCESS;
    }

    public function commandStart(): void
    {
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->newLine(2);
        $this->io->title('🎬 Personal videos processing command');
        $this->io->writeln('Command started at ' . $now->format('Y-m-d H:i:s'));
        $this->t0 = microtime(true);
    }

    public function commandEnd(): void
    {
        $t1 = microtime(true);
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->newLine(2);
        $this->io->writeln('Command ended at ' . $now->format('Y-m-d H:i:s'));
        $this->io->writeln(sprintf('Execution time: %.2f seconds', ($t1 - $this->t0)));
        $this->io->newLine(2);
    }
}
