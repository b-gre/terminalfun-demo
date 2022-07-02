<?php

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use BGre\TerminalFun\Terminfo;

class Positioning extends Command
{
    protected function configure()
    {
        parent::configure();
        $this->setName('positioning');
        $this->setDescription('Try out cursor movements');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $termInfo = Terminfo::detect();

        echo $termInfo->getString('clear_screen');
        printf(
            "%sHey, %sfingers off my cookies%s!\n\n",
            $termInfo->cursorAddress(2, 20),
            $termInfo->enterItalicsMode(),
            $termInfo->exitItalicsMode()
        );

        printf(
            '%sMhhh...%sCookies%s',
            $termInfo->cursorAddress(4, 10),
            $termInfo->parmDownCursor(2),
            $termInfo->cursorHome(),
        );

        return 0;
    }
}
