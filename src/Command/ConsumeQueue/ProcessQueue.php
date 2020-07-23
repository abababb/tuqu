<?php
include_once dirname(__FILE__).'/../src/Utils/RedisUtil.php';
use App\Utils\RedisUtil;

class ProcessQueue
{

    public function __construct($argv)
    {
        $this->argv = $argv;
        $this->batchSize = (int) ($argv[1] ?? 500);
        $this->sleepTime = (int) ($argv[3] ?? 30);
        $this->board = (int) ($argv[2] ?? 2);
    }

    public function execute()
    {
        $batchSize = $this->batchSize;
        $board = $this->board;
        $sleepTime = $this->sleepTime;

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

            fclose($redis);

            $this->writeln('成功去重'.($countBefore - $countAfter).'条');
            //echo date('Y-m-d H:i:s').' 成功去重'.($countBefore - $countAfter).'条'."\n";

            sleep($sleepTime);
        }
    }

    protected function writeln($text)
    {
        $prefix = "\n[".(new \DateTime('now'))->format('Y-m-d H:i:s')."][".$this->convert(memory_get_usage()) . "]\t";
        echo $prefix.$text."\n";
    }

    protected function convert($size)
    {
        $unit = array('b', 'kb', 'mb', 'gb', 'tb', 'pb');
        return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 1) . ' ' . $unit[$i];
    }
}

$pq = new ProcessQueue($argv);
$pq->execute();
