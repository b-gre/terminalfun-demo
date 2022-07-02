<?php

namespace App\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use BGre\TerminalFun\KeyStroke;
use BGre\TerminalFun\Menu\DataItem;
use BGre\TerminalFun\Menu\DataItemDisplay;
use BGre\TerminalFun\Menu\DataItemDisplay\ArrayData;
use BGre\TerminalFun\Menu\DataItemDisplay\BoolData;
use BGre\TerminalFun\Menu\DataItemDisplay\Literal;
use BGre\TerminalFun\Menu\DataItemDisplay\StringData;
use BGre\TerminalFun\Menu\Menu;
use BGre\TerminalFun\Stty;
use BGre\TerminalFun\Style\Formatter;
use BGre\TerminalFun\Terminfo;

#[AsCommand('menu:data', description: 'Shows usage of DataItem and performance of a large menu with 45,430 entries')]
class MenuData extends Command
{
    public const OPT_NO_INFO = 'no-info';

    protected const INFO_TEXT = <<< END
        Start typing to perform a quick search.
        Actors are not displayed in the menu but can be searched.
        Try searching for the name of an actor.

        Press any key to continue.
        END;

    protected const XDEBUG_WARNING = <<< END
        You have xdebug enabled.
        Xdebug has a negative impact on performance.

        END;

    protected function configure()
    {
        parent::configure();
        $this->addOption(self::OPT_NO_INFO, 'I', InputOption::VALUE_NONE, 'Do not display information before showing the menu');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $terminfo = Terminfo::detect();

        $formatter = Formatter::fromSheet($terminfo, <<<'END'
            menu:
                - pair: hsv(0 0 1) hsv(0.58 .66 0.3)

            menu focused item:
                - brighter: 0.25 0.25

            menu item not-available:
                - brighter: -0.25 0

            menu item genres action:
                - saturation: 1 null
                - hue: 0.16 null
            menu item genres adventure:
                - saturation: 1 null
                - hue: 0.11 null
            menu item genres comedy:
                - saturation: 0.5 null
                - hue: 0.82 null
            menu item genres crime:
                - foreground: hsv(0 0 0)
            menu item genres documentary:
                - saturation: 1 null
                - hue: 0.41 null
            menu item genres drama:
                - saturation: 0.5 null
                - hue: 0.77 null
            menu item genres fantasy:
                - saturation: 1 null
                - hue: 0.27 null
            menu item genres history:
                - saturation: 0.5 null
                - hue: 0.07 null
                - brighter: -0.125 0
            menu item genres horror:
                - saturation: 0.5 null
                - hue: 0 null
                - brighter: -0.75 0
            menu item genres mystery:
                - saturation: 0.25 null
                - hue: 0.08 null
            menu item genres romance:
                - saturation: 0.5 null
                - hue: 0.916 null
            menu item genres sciencefiction:
                - saturation: 1 null
                - hue: 0.49 null
            menu item genres thriller:
                - saturation: 0.64 null
                - hue: 0.114 null
            menu item genres western:
                - saturation: 0.3 null
                - hue: 0.15 null

            menu quickfilter:
                - pair: rgb(1 1 1) hsv(0.33 0.5 0.5)

            END);

        Stty::setState('-icanon', '-echo');

        $showInfo = !$input->getOption(self::OPT_NO_INFO);

        try {
            fwrite(STDOUT, $terminfo->enterCaMode().$terminfo->clearScreen());

            if ($showInfo) {
                if (extension_loaded('xdebug')) {
                    fwrite(STDOUT, $terminfo->setAForeground(1).self::XDEBUG_WARNING.$terminfo->origPair()."\n");
                }
                fwrite(STDOUT, self::INFO_TEXT);
            }

            $menu = $this->buildMenu($formatter);

            if ($showInfo) {
                KeyStroke::read(STDIN, $terminfo);
            }

            fwrite(STDOUT, $terminfo->clearScreen());
            $item = $menu->run();
        } finally {
            fwrite(STDOUT, $terminfo->clearScreen().$terminfo->exitCaMode());
        }

        if ($item instanceof DataItem) {
            $output->writeln('<comment>Selected movie:</comment>');
            $output->writeln('    <info>ID:</info> '.$item->getData('id'));
            $output->writeln('    <info>Title:</info> '.$item->getData('title'));
            $output->writeln('    <info>Genres:</info> ');
            foreach ($item->getData('genres') as $genre) {
                $output->writeln('      - '.$genre);
            }
            $output->writeln('    <info>Cast:</info> ');
            foreach ($item->getData('cast') ?? [] as $actor) {
                $output->writeln('      - '.$actor);
            }
        }

        return 0;
    }

    protected function buildMenu(Formatter $formatter): Menu
    {
        $movies = json_decode(file_get_contents(__DIR__.'/../../movies.json'), true, 512, JSON_THROW_ON_ERROR);

        $menu = new Menu($formatter, STDIN, STDOUT);
        $menu
            ->setRightPadding(' ')
            ->setFocusedRightPadding(' ');

        $diDisplay = new DataItemDisplay();
        $diDisplay
            ->addDisplay(new BoolData('available', "\u{25fc}", "\u{25fb}"))
            ->addDisplay(new Literal(' ', minWidth: 1))
            ->addDisplay(new StringData('title'), true)
            ->addDisplay(new ArrayData(
                key: 'genres',
                prefix: '[',
                suffix: ']',
                joiner: '/',
                tag: 'genres',
                valueTags: true
            ));

        $diDisplay->addWrapper(function (DataItem $item, string $markup): string {
            return $item->getData('available') ? $markup : "<not-available>{$markup}</not-available>";
        });

        foreach ($movies as $movie) {
            $movie['available'] = 0 === mt_rand(0, 5);
            $menu->addItem(new DataItem($movie, $diDisplay));
        }

        return $menu;
    }
}
