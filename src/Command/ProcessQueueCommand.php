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

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('处理队列数据(目前只有去重)')
            ->addArgument('batch_size', InputArgument::OPTIONAL, '一次拉多少')
            ->addArgument('board', InputArgument::OPTIONAL, '板块：2兔区，3闲情')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
        $batchSize = $input->getArgument('batch_size') ?: $this->defaultBatchSize;
        $board = (int) $input->getArgument('board') ?: 2;

        $key = 'tuqu_post:'.$board;
        while (1) {
            $redis = stream_socket_client('tcp://127.0.0.1:6379');
            fwrite($redis, RedisUtil::writeRedisProtocol('SELECT', [1]));

            $resData = [];
            for ($i = 0; $i < $batchSize; $i++) {
                fwrite($redis, RedisUtil::writeRedisProtocol('BRPOP', [$key, 0]));

                /*
                 * 示例返回，第一次调用brpop会另外返回+OK
                 *  *2
                 *  $11
                 *  tuqu_post:3
                 *  $16
                 *  3:1943388:1769:5
                 */
                if ($i === 0) {
                    $line = fgets($redis);
                }
                $line = fgets($redis);
                $line = fgets($redis);
                $line = fgets($redis);
                $line = fgets($redis);
                $line = fgets($redis);

                $line = trim($line);
                $resData[] = $line;
            }


            $countBefore = count($resData);
            $this->writeln('去重前: '.$countBefore."\n");
            $resData = array_unique($resData);
            $countAfter = count($resData);
            $this->writeln('去重后: '.$countAfter."\n");

            $count = 0;
            foreach ($resData as $line) {
                fwrite($redis, RedisUtil::writeRedisProtocol('LPUSH', [$key, $line]));
                $count++;
            }
            $this->writeln('成功加入'.$count.'条数据到队列');

            fclose($redis);

            $this->io->success('成功去重'.($countBefore - $countAfter).'条');

            sleep(10);
        }
    }
}
