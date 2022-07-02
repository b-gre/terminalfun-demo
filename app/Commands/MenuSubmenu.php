<?php

namespace App\Commands;

use ArrayObject;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use BGre\TerminalFun\Menu\CheckboxItem;
use BGre\TerminalFun\Menu\Divider;
use BGre\TerminalFun\Menu\Menu;
use BGre\TerminalFun\Menu\PlainItem;
use BGre\TerminalFun\Menu\RadioItem;
use BGre\TerminalFun\Stty;
use BGre\TerminalFun\Style\Formatter;
use BGre\TerminalFun\Terminfo;

#[AsCommand(name: 'menu:submenu', description: 'Show a simple menu')]
class MenuSubmenu extends Command
{
    public const OPT_MAXIMIZE = 'maximize';
    protected const COOKING_METHODS = [
        'cooked',
        'pan fried',
        'deep fried',
        'steamed',
        'baked',
    ];

    protected const STUFF = [
        'vegetables',
        'vegetables',
        'vegetables',
        'fish',
        'pork',
        'poultry',
    ];

    protected function configure()
    {
        parent::configure();
        $this->addOption(self::OPT_MAXIMIZE, 'm', InputOption::VALUE_NONE, 'Make it big');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $terminfo = Terminfo::detect();

        Stty::setState('-icanon', '-echo');

        $formatter = Formatter::fromSheet($terminfo, <<<'END'
            menu:
                - pair: rgb(1 1 1) hsv(0.58 .66 0.3)

            menu focused item, menu-inactive focused item:
                - brighter: 0.25 0.25

            menu disabled item:
                - foreground: hsv(0 0 0.5)

            menu-inactive:
                - pair: hsv(0 0 .5) hsv(0 0 .2)

            menu quickfilter:
                - pair: rgb(1 1 1) hsv(0.33 0.5 0.5)

            END);

        $output->writeln('<info>Hit Alt+C to configure the menu</info>');

        $mainMenu = $this->buildMainMenu($formatter);
        if ($input->getOption(self::OPT_MAXIMIZE)) {
            $mainMenu->setMaxHeight();
            $mainMenu->setMaximize(true);
        }

        $configMenu = new Menu($formatter, STDIN, STDOUT);
        $configMenu
            ->setTitle('Configuration')
            ->setQuickFilterEnabled(false)
            ->setOffsetTop(1)
            ->setOffsetLeft(10);

        $borderConfigGroup = new ArrayObject();

        $configMenu->addItem($doubleBorderItem = new RadioItem('Double border', $borderConfigGroup, true));
        $configMenu->addItem($singleBorderItem = new RadioItem('Single border', $borderConfigGroup));
        $configMenu->addItem(new Divider());
        $configMenu->addItem($headerDecoItem = new CheckboxItem('Header decoration'));
        $configMenu->addItem($roundCornersItem = new CheckboxItem('Round corners'));

        $roundCornersItem->setEnabled(false);

        $doubleBorderItem->onToogle(function () use ($configMenu, $doubleBorderItem, $roundCornersItem) {
            $roundCornersItem->setEnabled(!$doubleBorderItem->isChecked());
            $configMenu->redrawItems($roundCornersItem);
        });

        $mainMenu->bindKey('Alt+C', function () use (
            $mainMenu,
            $configMenu,
            $singleBorderItem,
            $doubleBorderItem,
            $headerDecoItem,
            $roundCornersItem
        ) {
            $mainMenu->setMenuStyle('menu-inactive');
            $mainMenu->display();

            if ($configMenu->run()) {
                if ($doubleBorderItem->isChecked()) {
                    $mainMenu->getBorder()->doubleBorders($headerDecoItem->isChecked());
                } elseif ($singleBorderItem->isChecked()) {
                    $mainMenu->getBorder()->singleBorders($headerDecoItem->isChecked(), $roundCornersItem->isChecked());
                }
            }
            $mainMenu->setMenuStyle('menu');
            $mainMenu->display();
        });

        $item = $mainMenu->run();

        if ($item instanceof PlainItem) {
            $output->writeln(sprintf('<info>%s</info>, an excellent choice', $item->getText()));
        } else {
            $output->writeln('So, nothing then');
        }

        return 0;
    }

    private function buildMainMenu(Formatter $formatter): Menu
    {
        $menu = new Menu($formatter, STDIN, STDERR);
        $menu->setTitle('The menu');

        for ($i = 0; $i < 20; ++$i) {
            $dish = ($i + 1).') '
                .self::COOKING_METHODS[mt_rand(0, count(self::COOKING_METHODS) - 1)]
                .' '
                .self::STUFF[mt_rand(0, count(self::STUFF) - 1)]
                .' with '
                .self::STUFF[mt_rand(0, count(self::STUFF) - 1)];
            $menu->addItem(new PlainItem($dish));
        }

        $menu->setMaxHeight(10);

        return $menu;
    }
}
