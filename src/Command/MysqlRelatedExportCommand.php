<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MysqlRelatedExportCommand extends Command
{
    protected static $defaultName = 'app:mysql:related';

    protected function configure()
    {
        $this
            ->setDescription('导出关联数据')
            ->addArgument('table_name', InputArgument::REQUIRED, '表名')
            ->addArgument('column_name', InputArgument::REQUIRED, '字段名')
            ->addArgument('column_value', InputArgument::REQUIRED, '字段值')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //$cmd = "(sleep 60  && kill -9 ".getmypid().") > /dev/null &";
        //exec($cmd); //issue a command to force kill this process in 10 seconds

        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
        $tableName = $input->getArgument('table_name');
        $columnName = $input->getArgument('column_name');
        $columnValue = $input->getArgument('column_value');
        $this->io->note('table_name: '.$tableName.'. column_name: '.$columnName.'. column_value: '.$columnValue.'.');

        $conn = $this->getApplication()->getKernel()->getContainer()->get('doctrine.dbal.xc_connection');
        $result =$conn->fetchAll('SELECT * FROM reservation order by id desc LIMIT 10');
        dump($result);
        $this->io->success('成功导入');
    }

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
