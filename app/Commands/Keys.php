<?php

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use BGre\TerminalFun\KeyStroke;
use BGre\TerminalFun\Stty;
use BGre\TerminalFun\Terminfo;

class Keys extends Command
{
    protected function configure()
    {
        parent::configure();
        $this->setName('keys');
        $this->setDescription('Wait for key strokes and dump key info');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Stty::setState('-icanon', '-echo');

        $terminfo = Terminfo::detect();

        $replace = array_combine(
            array_map(fn ($i) => chr($i), range(0, 31)),
            array_map(fn ($i) => sprintf('\\%03o', $i), range(0, 31))
        );
        // $replace["\e"] = "\\e";
        // $replace["\007"] = "\\a";

        do {
            $key = KeyStroke::read(STDIN, $terminfo);
            $output->writeln('Raw: '.strtr($key->getRaw(), $replace));
            $output->writeln('Terminfo: '.$terminfo->findString($key->getRaw()));
            $output->writeln(sprintf(
                'TerminalFun: %s %s',
                $key->isLetter() ? 'Letter' : 'Special',
                $key->isLetter() ? $key->getRaw() : $key->getSpecialName()
            ));
            $output->writeln('');
        } while ('Esc' !== $key->getSpecialName());

        return 0;
    }
}
