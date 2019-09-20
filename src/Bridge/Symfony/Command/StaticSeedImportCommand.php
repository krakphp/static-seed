<?php

namespace Krak\StaticSeed\Bridge\Symfony\Command;

use Doctrine\DBAL\Connection;
use Krak\StaticSeed\Import;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class StaticSeedImportCommand extends Command
{
    private $conn;
    private $seedDir;

    public function __construct(Connection $conn, string $seedDir) {
        parent::__construct();
        $this->conn = $conn;
        $this->seedDir = $seedDir;
    }

    protected function configure() {
        $this->setName('krak:static-seed:import')
            ->setDescription('Import static seeds to database')
            ->addArgument('path', InputArgument::OPTIONAL, 'sub path to load from');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $pathPart = $input->getArgument('path');
        $import = new Import($this->conn);

        $seedPath = $pathPart ? $this->seedDir . '/' . $pathPart : $this->seedDir;
        $import->import($seedPath);
    }
}
