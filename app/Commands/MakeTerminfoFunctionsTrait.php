<?php

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use BGre\TerminalFun\Terminfo;

class MakeTerminfoFunctionsTrait extends Command
{
    protected const HEADER = <<<'END'
        <?php

        namespace TerminalFun\Traits;

        trait TerminfoFunctions
        {

        END;

    protected const BOOL_FUNCTION = <<<'END'
            public function F_NAME(): ?bool
            {
                return $this->bools['K_NAME'] ?? null;
            }

        END;

    protected const NUMBER_FUNCTION = <<<'END'
            public function F_NAME(): ?int
            {
                return $this->numbers['K_NAME'] ?? null;
            }

        END;

    protected const STRING_FUNCTION = <<<'END'
            public function F_NAME(): ?string
            {
                return $this->strings['K_NAME'] ?? null;
            }

        END;

    protected const COMPILE_FUNCTION = <<<'END'
        public function F_NAME(ARGS): ?string
        {
            if (!array_key_exists('K_NAME', $this->strings)) {
                return null;
            }

            return ($this->compiler)($this->strings['K_NAME'], ARG_NAMES);
        }

    END;

    protected const FOOTER = "}\n";

    protected const STRING_FUNC_ARGS = [
        'change_scroll_region' => ['$top' => 'int', '$bottom' => 'int'],
        // /usr/lib/terminfo/t/tmux-256color
        'column_address' => ['$column' => 'int'],
        // /usr/lib/terminfo/w/wsvt25
        'cursor_address' => ['$column' => 'int', '$row' => 'int'],
        // /usr/lib/terminfo/w/wsvt25
        'erase_chars' => ['$count' => 'int'],
        // /usr/lib/terminfo/r/rxvt-unicode
        'initialize_color' => ['$color' => 'int', '$red' => 'int', '$green' => 'int', '$blue' => 'int'],
        // /usr/lib/terminfo/w/wsvt25
        'parm_dch' => ['$count' => 'int'],
        // /usr/lib/terminfo/w/wsvt25
        'parm_delete_line' => ['$count' => 'int'],
        // /usr/lib/terminfo/w/wsvt25
        'parm_down_cursor' => ['$count' => 'int'],
        // /usr/lib/terminfo/w/wsvt25
        'parm_ich' => ['$count' => 'int'],
        // /usr/lib/terminfo/t/tmux-256color
        'parm_index' => ['$lines' => 'int'],
        // /usr/lib/terminfo/w/wsvt25
        'parm_insert_line' => ['$count' => 'int'],
        // /usr/lib/terminfo/w/wsvt25
        'parm_left_cursor' => ['$count' => 'int'],
        // /usr/lib/terminfo/w/wsvt25
        'parm_right_cursor' => ['$count' => 'int'],
        // /usr/lib/terminfo/r/rxvt-unicode
        'parm_rindex' => ['$lines' => 'int'],
        // /usr/lib/terminfo/w/wsvt25
        'parm_up_cursor' => ['$count' => 'int'],
        // /usr/lib/terminfo/x/xterm
        'repeat_char' => ['$char' => 'string', '$count' => 'int'],
        // /usr/lib/terminfo/t/tmux-256color
        'row_address' => ['$row' => 'int'],
        // /usr/lib/terminfo/w/wsvt25
        'set_a_background' => ['$color' => 'int'],
        // /usr/lib/terminfo/w/wsvt25
        'set_a_foreground' => ['$color' => 'int'],
        // /usr/lib/terminfo/r/rxvt-unicode
        'set_background' => ['$color' => 'int'],
        // /usr/lib/terminfo/r/rxvt-unicode
        'set_foreground' => ['$color' => 'int'],
        // /usr/lib/terminfo/x/xterm
        'set_left_margin_parm' => ['$p1' => 'int'],
        // /usr/lib/terminfo/x/xterm
        'set_lr_margin' => ['$p1' => 'int', '$p2' => 'int'],
        // /usr/lib/terminfo/x/xterm
        'set_right_margin_parm' => ['$p1' => 'int'],
    ];

    protected const DISABLED_FUNCTIONS = [
        // setf/setb are legacy functions. Use setaf/setab instead
        'set_foreground',
        'set_background',
    ];

    protected function configure()
    {
        parent::configure();
        $this->setName('make:terminfo-functions-trait');
        $this->setDescription('Generate the TerminfoFunctions trait');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $code =
            self::HEADER.
            $this->generateFunctions(self::BOOL_FUNCTION, $this->getNames('BOOL_NAMES')).
            $this->generateFunctions(self::NUMBER_FUNCTION, $this->getNames('NUMBER_NAMES')).
            $this->generateStringFunctions($this->getNames('STRING_NAMES')).
            self::FOOTER;

        $output->writeln($code);

        return 0;
    }

    private function getNames(string $constName): array
    {
        return array_diff(
            (new \ReflectionClass(Terminfo::class))->getConstant($constName),
            self::DISABLED_FUNCTIONS
        );
    }

    private function generateFunctions(string $template, array $names): string
    {
        return implode("\n", array_map(
            fn ($name) => strtr($template, [
                'F_NAME' => $this->camel($name),
                'K_NAME' => $name,
            ]),
            $names
        ))."\n";
    }

    private function generateStringFunctions(array $names): string
    {
        return implode("\n", array_map(
            fn ($name) => array_key_exists($name, self::STRING_FUNC_ARGS)
                ? strtr(self::COMPILE_FUNCTION, [
                    'F_NAME' => $this->camel($name),
                    'K_NAME' => $name,
                    'ARG_NAMES' => implode(', ', array_keys(self::STRING_FUNC_ARGS[$name])),
                    'ARGS' => implode(', ', array_map(
                        static fn ($t, $n) => $t.' '.$n,
                        self::STRING_FUNC_ARGS[$name],
                        array_keys(self::STRING_FUNC_ARGS[$name])
                    )),
                ])
                : strtr(self::STRING_FUNCTION, [
                    'F_NAME' => $this->camel($name),
                    'K_NAME' => $name,
                ]),
            $names
        ));
    }

    private function camel(string $name): string
    {
        return lcfirst(implode(array_map(
            static fn ($s) => ucfirst($s),
            explode('_', $name)
        )));
    }
}
