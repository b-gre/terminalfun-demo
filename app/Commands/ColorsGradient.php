<?php

namespace App\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use BGre\TerminalFun\Color\Color;
use BGre\TerminalFun\Color\HSV;
use BGre\TerminalFun\Color\RGB;
use BGre\TerminalFun\Terminal;
use BGre\TerminalFun\Terminfo;

#[AsCommand('colors:gradient', 'Show some color gradients using different color spaces')]
class ColorsGradient extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $terminfo = Terminfo::detect();

        $output->writeln('This command generates color gradients using different methods');
        $output->writeln(' 4: True color output (if supported) (ESC[{3|4}8;2;{r};{g};{b}m codes)');
        $output->writeln(' 3: 256 colors (if supported) (ESC[{3|4}8;5;{c}m codes on most terminals. Takes terminfo into account)');
        $output->writeln(' 2: 16 colors (if supported) (codes depend on terminfo)');
        $output->writeln(' 1: 8 colors (ESC[{3|4}{c}m codes)');
        $output->writeln('The actual visual representation of methods 1 and 2 depend on the terminal configuration.');

        $width = Terminal::getWidth() - 3;

        $gradients = [];

        $fakes = [
            [false, 256],
            [false, 16],
            [false, 8],
        ];

        $fakes = array_filter($fakes, fn ($f) => $f[1] <= $terminfo->maxColors());

        if (Color::isTrueColorEnabled()) {
            array_unshift($fakes, [true, 256]);
        }

        $dot = fn (Color $c) => $c->render(false, $terminfo).'.';

        $foreground = new RGB(1, 1, 1);

        foreach ($fakes as [$trueColor, $maxColors]) {
            $prop = (new \ReflectionObject($terminfo))->getProperty('numbers');
            $prop->setAccessible(true);
            $prop->setValue($terminfo, ['max_colors' => $maxColors] + $prop->getValue($terminfo));
            Color::enableTrueColor($trueColor);

            $bluegreen = $foreground->render(true, $terminfo);
            $yellowmagenta = $foreground->render(true, $terminfo);
            $reds = $foreground->render(true, $terminfo);
            $greys = $foreground->render(true, $terminfo);
            $sunrise = $foreground->render(true, $terminfo);
            $exp = $foreground->render(true, $terminfo);
            $hsv = $foreground->render(true, $terminfo);

            foreach (range(0, $width - 1) as $v) {
                $bluegreen .= $dot(new RGB(0, $v / $width, 1 - $v / $width));
                $yellowmagenta .= $dot(new RGB(1, 1 - $v / $width, $v / $width));
                $reds .= $dot(new RGB($v / $width, 0, 0));
                $greys .= $dot(new RGB($v / $width, $v / $width, $v / $width));
                $sunrise .= $dot(new RGB(
                    2 * $v / $width,
                    2 * $v / $width - 0.5,
                    2 * $v / $width - 1
                ));
                $exp .= $dot(new RGB(
                    pow(1 - 2 * $v / $width, 2),
                    pow($v / $width, 2),
                    1 - pow($v / $width, 2)
                ));
                $hsv .= $dot(new HSV(1.5 - $v / $width, 1, 0.5 + 0.5 * $v / $width));
            }

            $level = $trueColor ? '4' : [256 => 3, 16 => 2, 8 => 1][$terminfo->maxColors()];

            $gradients['Blue to Green'][$level] = $bluegreen;
            $gradients['Yellow to Magenta'][$level] = $yellowmagenta;
            $gradients['Shades of Red'][$level] = $reds;
            $gradients['Grey scale'][$level] = $greys;
            $gradients['Sunrise'][$level] = $sunrise;
            $gradients['Exponential gradient'][$level] = $exp;
            $gradients['HSV'][$level] = $hsv;
        }

        foreach ($gradients as $name => $lines) {
            $output->writeln('');
            $output->writeln($name.':');
            foreach ($lines as $level => $line) {
                $output->writeln($level.' '.$line.$terminfo->origPair());
            }
        }

        return 0;
    }
}
