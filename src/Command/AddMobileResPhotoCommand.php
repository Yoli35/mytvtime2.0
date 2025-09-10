<?php

namespace App\Command;

use App\Entity\Photo;
use App\Repository\PhotoRepository;
use App\Service\DateService;
use App\Service\ImageService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:photo:mobile-res',
    description: 'Add mobile resolution photos (max 640px on the longest side)',
)]
class AddMobileResPhotoCommand
{
    private SymfonyStyle $io;
    private float $t0;

    public function __construct(
        private readonly DateService     $dateService,
        private readonly ImageService    $imageService,
        private readonly PhotoRepository $photoRepository,
    )
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $this->io = $io;

        $this->commandStart();

        $photoPath = './public/albums/';
        $mobileResPath = '/mobile';
        $thumbnailResPath = '/thumbnail';

        $photos = $this->photoRepository->photoAll();

//        $userIds = array_unique(array_column($photos, 'user_id'));
        // on vérifie que les dossiers de vignettes existent pour chaque utilisateur
//        foreach ($userIds as $userId) {
//            $path = $photoPath . $userId . $thumbnailResPath;
//            $io->writeln("Checking path: $path");
//            $this->imageService->checkForPaths([$path]);
//        }
//        return Command::SUCCESS;

        // on crée un tableau des noms de fichiers pour vérifier à la fin les photos en trop
        $photoFilenames = array_column($photos, 'imagePath');
        $photoMissingLowRes = [];
        $total = count($photos);
        $this->io->writeln("Total photos: $total");
        $i = 0;
        foreach ($photos as $photo) {
            $i++;
            $filename = $photo['image_path'];
            $this->io->writeln("Processing photo $i/$total: " . $filename);
            $userId = $photo['user_id'];

            // Check if mobile resolution photo already exists
            if (file_exists($photoPath . $userId . $thumbnailResPath . $filename)) {
                $this->io->writeln("Thumbnail resolution photo already exists for [" . $photoPath . $userId . $thumbnailResPath . $filename . "], skipping.");
                continue;
            }
            // Check if low resolution photo exists
            if (!file_exists($photoPath . $userId . $mobileResPath . $filename)) {
                $this->io->writeln("Mobile resolution photo does not exist for [". $photoPath . $userId . $mobileResPath . $filename."], skipping.");
                $photoMissingLowRes[] = $filename;
                continue;
            }
            // Create mobile resolution photo
            if ($this->imageService->resizeWebpImage(
                $photoPath . $userId . $mobileResPath . $filename,
                $photoPath . $userId . $thumbnailResPath . $filename,
                320
            )) {
                $this->io->writeln("Mobile resolution photo created for $filename.");
            } else {
                $this->io->writeln("Failed to create mobile resolution photo for $filename.");
            }
            // Retire filename de photoFilenames
            $key = array_search($filename, $photoFilenames);
            if ($key !== false) {
                unset($photoFilenames[$key]);
            }
        }
        // on affiche les photos manquantes
        $this->io->newLine(2);
        if (count($photoMissingLowRes) > 0) {
            $this->io->writeln('Photos missing low resolution:');
            foreach ($photoMissingLowRes as $missing) {
                $this->io->writeln($missing);
            }
        } else {
            $this->io->writeln('No photos are missing low resolution.');
        }
        // on affiche les photos en trop
        $this->io->newLine(2);
        if (count($photoFilenames) > 0) {
            $this->io->writeln('Photos in database but not in filesystem:');
            foreach ($photoFilenames as $extra) {
                $this->io->writeln($extra);
            }
        } else {
            $this->io->writeln('No photos are in database but not in filesystem.');
        }

        $this->commandEnd();

        return Command::SUCCESS;
    }

    public
    function commandStart(): void
    {
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->newLine(2);
        $this->io->title('📸 Add mobile resolution photos command');
        $this->io->writeln('Command started at ' . $now->format('Y-m-d H:i:s'));
        $this->t0 = microtime(true);
    }

    public
    function commandEnd(): void
    {
        $t1 = microtime(true);
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->newLine(2);
        $this->io->writeln('Command ended at ' . $now->format('Y-m-d H:i:s'));
        $this->io->writeln(sprintf('Execution time: %.2f seconds', ($t1 - $this->t0)));
        $this->io->newLine(2);
    }
}
