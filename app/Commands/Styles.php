<?php

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use BGre\TerminalFun\Style\Formatter;
use BGre\TerminalFun\Terminfo;

class Styles extends Command
{
    protected const OPT_BENCHMARK = 'benchmark';

    protected function configure()
    {
        $this->setName('styles');
        $this->setDescription('Demonstrate the abilites of styles');
        $this->addOption(self::OPT_BENCHMARK, 'b', InputOption::VALUE_NONE, 'Run benchmark');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $styleSheet = <<< 'END'
            important:
            - pair: rgb(1 1 .25) rgb(.5 0 0)

            a:
            - background: rgb(0.33 0 0)

            b:
            - background: rgb(0.66 0 .5)

            a d:
            - background: rgb(0.85 0.5 0)

            c > d:
            - italic
            - pair: rgb(0 0 0) rgb(1 1 1)

            emphasis:
            - italic

            sun:
            - brighter: -0.2 0.1
            - hue: 0.15 null
            - saturation: 0.25 1
        END;

        $formatter = Formatter::fromSheet(Terminfo::detect(), $styleSheet);

        $examples = [
            'Nothing styled',
            'Using <unknown>unknwon</unknown> tags',
            '<important>Some very important information</important>',
            'Nesting: <d>(d outside)</d> <a>(a <b>(b <d>(d)</d>)</b>)</a> <c>(c <a>(a <d>(d)</d>)</a>)</c> <a>(a <c>(c <d>(d)</d>)</c>)</a>',
            'A fixed bug: <emphasis>This <emphasis>was italic</emphasis>, but this was not</emphasis> (normal again)',
            'A bit of fading: <a><sun>~~<sun>~~<sun>~~<sun>~~</sun></sun></sun></sun></a>',
        ];

        foreach ($examples as $example) {
            $rendered = $formatter->render($example);
            $output->writeln($example);
            $output->writeln($rendered);
            $output->writeln('');
        }

        if ($input->getOption(self::OPT_BENCHMARK)) {
            $output->writeln('Benchmarking (takes 4 seconds)');
            for ($end = microtime(true) + 1; microtime(true) < $end;) {
                pow(M_PI, mt_rand(-5, 5)); // @phpstan-ignore-line
            }
            for ($end = microtime(true) + 2, $i = 0; microtime(true) < $end; ++$i) {
                foreach ($examples as $example) {
                    $formatter->render($example);
                }
            }

            $output->writeln(sprintf('Rendering all examples took %.2fms in average', 2000 / $i));
        }

        $output->writeln('The following style sheet was used to configure the formatter:');
        $output->writeln($styleSheet);

        return 0;
    }
}
