<?php

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use BGre\TerminalFun\Terminfo;

class Dump extends Command
{
    protected function configure()
    {
        parent::configure();
        $this->setName('dump');
        $this->setDescription('Dumps all available terminal information');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $termInfo = Terminfo::detect();
        dump($termInfo->all());

        return 0;
    }
}
