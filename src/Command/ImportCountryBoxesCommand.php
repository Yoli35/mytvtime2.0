<?php

namespace App\Command;

use App\Entity\Country;
use App\Repository\CountryRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:country-boxes',
    description: 'Import country boxes from a json file',
)]
class ImportCountryBoxesCommand extends Command
{
    public function __construct(private readonly CountryRepository $countryRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'json file to import');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $json = $input->getArgument('arg1');

        if (!$json) {
            $json = $io->ask('Which json file would you like to import?');
        }
        if (!$json) {
            $io->error('You must provide a json file to import');
            return Command::FAILURE;
        }
        $io->note(sprintf('Importing %s', $json));

        // open the file
        $file = fopen($json, 'r');
        if (!$file) {
            $io->error('Could not open the file');
            return Command::FAILURE;
        }
        // read the file
        $data = fread($file, filesize($json));
        fclose($file);
        // decode the json
        $data = json_decode($data, true);

        // check if the data is valid
        if (!$data) {
            $io->error('The json file is not valid');
            return Command::FAILURE;
        }

        // display the data
        foreach ($data as $code => $info) {
            $io->write(sprintf('Country: %s - %-27s : ', $code, $info[0]));
            $box = $info[1];
            $io->writeln(sprintf('[%.02f, %.02f], [%.02f, %.02f]', $box[0], $box[1], $box[2], $box[3]));
            $country = $this->countryRepository->findOneBy(['code' => $code]);
            if ($country) {
                $country->setEnglishName($info[0]);
                $country->setLat1($box[0]);
                $country->setLng1($box[1]);
                $country->setLat2($box[2]);
                $country->setLng2($box[3]);
            } else {
                $country = new Country($code, $info[0], $box[0], $box[1], $box[2], $box[3]);
            }
            $this->countryRepository->save($country);
        }
        $this->countryRepository->flush();

        $io->success('Data imported successfully');

        return Command::SUCCESS;
    }
}
