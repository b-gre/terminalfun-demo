<?php

namespace App\Commands;

use DirectoryIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use BGre\TerminalFun\Menu\Menu;
use BGre\TerminalFun\Menu\PlainItem;
use BGre\TerminalFun\Stty;
use BGre\TerminalFun\Style\Formatter;
use BGre\TerminalFun\Terminal;
use BGre\TerminalFun\Terminfo;

#[AsCommand(name: 'menu:files', description: 'A file simple file browser')]
class MenuFiles extends Command
{
    public const ARG_DIRECTORY = 'directory';

    protected const HINTS = [
        'Up/Down: Select',
        'Right: Enter sub directory',
        'Left: Leave sub directory',
        'Return: Confirm',
        'Esc: Cancel',
    ];

    protected function configure()
    {
        parent::configure();
        $this->addArgument(self::ARG_DIRECTORY, InputArgument::OPTIONAL, 'The initial directory');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $terminfo = Terminfo::detect();
        $formatter = $this->buildFormatter($terminfo);

        Stty::setState('-icanon', '-echo');

        fwrite(STDERR, $terminfo->enterCaMode().$terminfo->clearScreen().$terminfo->cursorInvisible());

        try {
            $path = $this->selectPath($input->getArgument(self::ARG_DIRECTORY) ?? getcwd(), $formatter);
        } finally {
            fwrite(STDERR, $terminfo->exitCaMode().$terminfo->cursorVisible());
        }

        fflush(STDERR); // make sure console restore goes through before we print the path

        if (null !== $path) {
            $output->writeln($path);
        }

        return 0;
    }

    protected function selectPath(string $root, Formatter $formatter): ?string
    {
        $stack = [[$root, $menu = $this->buildMenu($root, $formatter)]];

        $dir = '';

        pcntl_signal(SIGWINCH, function () use (&$stack, &$dir, $formatter) {
            Terminal::updateDimensions();
            fwrite(STDERR, $formatter->getTerminfo()->clearScreen());
            $this->printTopLine($dir, $formatter);
            /** @var Menu $menu */
            foreach ($stack as [, $menu]) {
                $menu->resetDimensions();
                $menu->display();
            }
        });

        $hintLeft = $menu->getIdealWidth() + 2;
        $this->showHints($hintLeft, $formatter->getTerminfo());

        $break = false;
        $abort = false;

        do {
            /** @var string $dir
             * @var Menu $menu
             */
            [$dir, $menu] = $stack[count($stack) - 1];

            $menu->bindKey('Esc', function (Menu $menu) use (&$abort, &$break) {
                $abort = true;
                $break = true;
                $menu->cancel();
                $menu->clear();
            });

            $menu->bindKey('Return', function (Menu $menu) use (&$abort, &$break) {
                $abort = false;
                $break = true;
                $menu->cancel();
                $menu->clear();
            });

            $menu->bindKey('Right', function (Menu $menu) use (&$stack, $dir, $formatter) {
                $item = $menu->getFocusedItem();
                if (!$item instanceof PlainItem) {
                    return;
                }
                $subdir = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$item->getText();
                if (!is_dir($subdir)) {
                    return;
                }
                $menu->clear();
                $menu->setMaxWidth(min(20, $menu->getIdealWidth()));
                $menu->setMenuStyle('menu-inactive');
                $menu->display();
                $menu->cancel();

                $submenu = $this->buildMenu($subdir, $formatter);
                $submenu->setOffsetLeft($menu->getOffsetLeft() + $menu->getWidth());
                $stack[] = [$subdir, $submenu];
            });

            $menu->bindKey('Left', function (Menu $menu) use (&$stack) {
                if (count($stack) <= 1) {
                    return;
                }
                array_pop($stack);
                $menu->clear();
                $menu->cancel();
            });

            $this->printTopLine($dir, $formatter);

            $menu->setMenuStyle('menu');
            $menu->setMaxWidth($menu->getIdealWidth());
            $menu->display();
            $menu->loop();

            if ($hintLeft) {
                $this->clearHints($hintLeft, $formatter->getTerminfo());
                $hintLeft = 0;
            }
        } while (!$break && !empty($stack));

        pcntl_signal(SIGWINCH, SIG_DFL);

        $item = $menu->getFocusedItem();
        if (!$abort && $item instanceof PlainItem) {
            return $dir.'/'.$item->getText();
        }

        return null;
    }

    protected function buildMenu(string $path, Formatter $formatter): Menu
    {
        $menu = new Menu($formatter, STDIN, STDERR);
        $menu->getBorder()->noBorders();
        $menu->setMaximize(true);
        $menu->setOffsetTop(1);
        $menu->setControlCursorVisibility(false);

        $files = [];
        $dirs = [];
        foreach (new DirectoryIterator($path) as $file) {
            if ($file->isDot()) {
                continue;
            }
            if ($file->isDir()) {
                $dirs[] = $file->getFilename();
            } elseif ($file->isFile()) {
                $files[] = $file->getFilename();
            }
        }

        sort($files);
        sort($dirs);

        foreach ($dirs as $dir) {
            $menu->addItem(new PlainItem($dir));
        }
        foreach ($files as $file) {
            $menu->addItem(new PlainItem($file));
        }

        return $menu;
    }

    protected function buildFormatter(Terminfo $terminfo): Formatter
    {
        return Formatter::fromSheet($terminfo, <<<'END'
            topline:
            - pair: rgb(0 0 0) hsv(0.4 0.75 0.75)
            menu:
            - pair: rgb(1 1 1) hsv(0.58 .66 0.3)
            menu focused item, menu-inactive focused item:
            - brighter: 0.25 0.25
            menu disabled item:
            - foreground: rgb(0.5 0.5 0.5)
            menu-inactive:
            - pair: rgb(.6 .6 .6) rgb(.1 .1 .1)
            menu-inactive focused item:
            - pair: rgb(.8 .8 .8) rgb(0.5 0.5 0.5)
            menu quickfilter:
            - pair: rgb(1 1 1) hsv(0.33 0.5 0.5)

            END);
    }

    protected function showHints(int $left, Terminfo $terminfo)
    {
        fwrite(STDERR, $terminfo->saveCursor());
        foreach (self::HINTS as $i => $hint) {
            fwrite(STDERR, $terminfo->cursorAddress($left, $i + 2).$hint);
        }

        fwrite(STDERR, $terminfo->restoreCursor());
    }

    protected function clearHints(int $left, Terminfo $terminfo)
    {
        fwrite(STDERR, $terminfo->saveCursor());
        foreach (self::HINTS as $i => $hint) {
            fwrite(STDERR, $terminfo->cursorAddress($left, $i + 2).$terminfo->eraseChars(mb_strlen($hint)));
        }

        fwrite(STDERR, $terminfo->restoreCursor());
    }

    protected function printTopLine(string $path, Formatter $formatter)
    {
        $w = Terminal::getWidth();
        $line = mb_substr(mb_substr($path, -$w).str_repeat(' ', $w), 0, $w);

        fwrite(STDERR, $formatter->render('<topline>'.$line.'</topline>').$formatter->getTerminfo()->rowAddress(0));
    }
}
