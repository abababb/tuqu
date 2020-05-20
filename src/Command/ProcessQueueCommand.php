<?php

namespace App\Command;

use App\Entity\Post;
use App\Utils\RedisUtil;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Cookie\CookieJar;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ProcessQueueCommand extends BaseCommand
{
    protected static $defaultName = 'app:process:queue';

    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->defaultBatchSize = 500;
        $this->sleepTime = 30;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('处理队列数据(目前只有去重)')
            ->addArgument('batch_size', InputArgument::OPTIONAL, '一次拉多少')
            ->addArgument('board', InputArgument::OPTIONAL, '板块：2兔区，3闲情')
            ->addArgument('sleep_time', InputArgument::OPTIONAL, '等待时间')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
        $batchSize = $input->getArgument('batch_size') ?: $this->defaultBatchSize;
        $board = (int) $input->getArgument('board') ?: 2;
        $sleepTime = (int) $input->getArgument('sleep_time') ?: $this->sleepTime;

        $key = 'tuqu_post:'.$board;
        while (1) {
            $redis = stream_socket_client('tcp://127.0.0.1:6379');
            fwrite($redis, RedisUtil::writeRedisProtocol('SELECT', [1]));

            // 队列不大时不去重，第一次调用会另外返回+OK
            fwrite($redis, RedisUtil::writeRedisProtocol('LLEN', [$key]));
            $line = fgets($redis);
            $line = fgets($redis);
            $queueSize = (int) trim($line, ':');
            if (1.1 * $queueSize < $batchSize) {
                sleep($sleepTime);
                continue;
            }

            $resData = [];
            for ($i = 0; $i < $batchSize; $i++) {
                fwrite($redis, RedisUtil::writeRedisProtocol('BRPOP', [$key, 0]));

                /*
                 * 示例返回
                 *  *2
                 *  $11
                 *  tuqu_post:3
                 *  $16
                 *  3:1943388:1769:5
                 */
                $line = fgets($redis);
                $line = fgets($redis);
                $line = fgets($redis);
                $line = fgets($redis);
                $line = fgets($redis);

                $line = trim($line);
                $resData[] = $line;
            }


            $countBefore = count($resData);
            //$this->writeln('去重前: '.$countBefore."\n");
            $resData = array_unique($resData);
            $countAfter = count($resData);
            //$this->writeln('去重后: '.$countAfter."\n");

            $count = 0;
            foreach ($resData as $line) {
                fwrite($redis, RedisUtil::writeRedisProtocol('LPUSH', [$key, $line]));
                $count++;
            }
            //$this->writeln('成功加入'.$count.'条数据到队列');

            fclose($redis);

            $this->writeln('成功去重'.($countBefore - $countAfter).'条');

            sleep($sleepTime);
        }
    }
}
