<?php

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use BGre\TerminalFun\Terminfo;

class ColorsList extends Command
{
    protected function configure()
    {
        parent::configure();
        $this->setName('colors:list');
        $this->setDescription('Show supported terminal colors');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $termInfo = Terminfo::detect();

        $maxColors = $termInfo->maxColors();

        if (!$maxColors) {
            $output->writeln('Unable to detect color support');

            return 0;
        }

        $output->writeln("The terminal supports {$maxColors} colors");

        for ($i = 0; $i < $maxColors; ++$i) {
            $output->writeln(sprintf(
                '%6d %s   %s%s',
                $i,
                $termInfo->setABackground($i),
                $termInfo->clrEol(),
                $termInfo->origPair()
            ));
        }

        return 0;
    }
}
