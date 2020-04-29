<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;

class BaseCommand extends Command
{
    protected static $defaultName = '';

    protected function writeln($text)
    {
        $prefix = "\n[" . (new \DateTime('now'))->format('Y-m-d H:i:s') . "][" . $this->convert(memory_get_usage()) . "]\t";
        $this->output->writeln($prefix . $text);
    }

    protected function convert($size)
    {
        $unit = array('b', 'kb', 'mb', 'gb', 'tb', 'pb');
        return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 1) . ' ' . $unit[$i];
    }
}
