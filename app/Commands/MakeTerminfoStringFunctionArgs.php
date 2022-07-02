<?php

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use BGre\TerminalFun\Terminfo;

class MakeTerminfoStringFunctionArgs extends Command
{
    protected const ARG_PATH = 'path';

    protected function configure()
    {
        parent::configure();
        $this->setName('make:terminfo-string-function-args');
        $this->setDescription('Generate parameter list for terminfo string functions (like cursor_address)');
        $this->addArgument(
            self::ARG_PATH,
            InputArgument::REQUIRED,
            'Directory with terminfo files to be analyzed'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $files = (new Finder())
            ->files()
            ->in($input->getArgument(self::ARG_PATH));

        $allParams = [];

        foreach ($files as $file) {
            $termInfo = Terminfo::load($file->getPathname());

            foreach ($this->detectParams($termInfo) as $name => $params) {
                $allParams[$name][json_encode($params)] = $file->getPathname();
            }
        }

        ksort($allParams);

        foreach ($allParams as $funName => $a) {
            foreach ($a as $def => $location) {
                $output->writeln('// '.$location);
                $def = json_decode($def, true);
                $phpcode = "'{$funName}' => [".
                    implode(', ', array_map(
                        fn ($k, $v) => "'{$k}' => '{$v}'",
                        array_keys($def),
                        $def
                    )).'], ';
                $output->writeln($phpcode);
            }
        }

        return 0;
    }

    protected function detectParams(Terminfo $terminfo): \Generator
    {
        foreach ($terminfo->all() as $k => $v) {
            if (is_string($v) && !empty($params = $this->getParamsFromTemplate($v))) {
                yield $k => $params;
            }
        }
    }

    protected function getParamsFromTemplate(string $template): ?array
    {
        $pattern = '/%p(?<argno>\d).*?%(?<type>[csd])/';
        if (!preg_match_all($pattern, $template, $matches, PREG_SET_ORDER)) {
            return null;
        }

        $result = [];

        foreach ($matches as $match) {
            $result['$p'.$match['argno']] = ['s' => 'string', 'c' => 'string', 'd' => 'int'][$match['type']];
        }

        return $result;
    }
}
