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

class FetchPostsCommand extends BaseCommand
{
    protected static $defaultName = 'app:fetch:posts';

    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->batchSize = 2;
        $this->maxLimit = 99 * $this->batchSize;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('拉兔区帖子数据')
            ->addArgument('keyword', InputArgument::OPTIONAL, '关键词')
            ->addArgument('page', InputArgument::OPTIONAL, '开始页')
            ->addArgument('max_limit', InputArgument::OPTIONAL, '页数限制')
            ->addArgument('board', InputArgument::OPTIONAL, '板块：2兔区，3闲情')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
        $keyword = $input->getArgument('keyword');
        $page = (int) $input->getArgument('page');
        $maxLimit = (int) $input->getArgument('max_limit');
        $board = (int) $input->getArgument('board') ?: 2;

        if ($keyword) {
            $this->io->note(sprintf('搜索关键字: %s', $keyword));
        }

        if ($maxLimit > 0) {
            $this->maxLimit = min($this->maxLimit, $maxLimit);
        }
        $maxBatchCount = (int) $this->maxLimit/$this->batchSize;

        if ($page <= 1) {
            $page = 1;
        }
        $this->io->note(sprintf('初始页数: %s', $page));

        for ($i = 0; $i < $maxBatchCount; $i++) {
            $startPage = $page + $i * $this->batchSize;
            $endPage = $startPage + $this->batchSize - 1;
            $resData = $this->batchFetchPage($keyword, $startPage, $board);
            $this->writeln('开始导入第'.$startPage.'-'.$endPage.'页数据');
            $resData = $this->saveToDb($resData, $board);

            if ($resData) {
                $this->addToQueue($resData, $board);
                unset($resData);
            }
        }

        $this->io->success('成功导入');
    }

    protected function addToQueue($resData, $board)
    {
        $redis = stream_socket_client('tcp://127.0.0.1:6379');
        fwrite($redis, RedisUtil::writeRedisProtocol('SELECT', [1]));

        $key = 'tuqu_post:'.$board;
        $count = 0;
        foreach ($resData as $postData) {
            if (isset($postData['id']) && isset($postData['db_id']) && isset($postData['pages']) && $postData['id'] && $postData['db_id'] && $postData['pages']) {
                fwrite($redis, RedisUtil::writeRedisProtocol('LPUSH', [$key, $postData['id'].':'.$postData['db_id'].':'.$postData['pages']]));
                $count++;
            }
        }
        fclose($redis);
        $this->writeln('成功加入'.$count.'条数据到队列');
    }

    protected function batchFetchPage($keyword, $page, $board)
    {
        $url = 'http://bbs.jjwxc.net/bbsapi.php?action=';
        if ($keyword) {
            $url .= 'search';
        } else {
            $url .= 'board';
        }
        $client = new Client();
        $promises = array_map(function ($page) use ($client, $url, $keyword, $board) {
            $postData = array(
                'board' => $board,
                'page' => $page,
                'sign' => 't1y30KJlifEX7XJeoSv3NvZifLl08tdwcBxJKi130qUIi1mJOpMGV7om4rii/AzhM3h3RhnMFS4%3D',
                'source' => 'IOS',
                'versionCode' => '195',
                'keyword' => $keyword,
                'topic' => 3,
            );
            return $client->postAsync($url, array(
                'form_params' => $postData,
            ));
        }, range($page, $page + $this->batchSize - 1));
        $results = Promise\unwrap($promises);
        $results = array_reduce($results, function ($acc, $res) {
            if (!$res instanceof \GuzzleHttp\Psr7\Response) {
                return $acc;
            }
            $res = $res->getBody();
            $resData = @json_decode($res, true);
            $resData['data'] = $resData['data'] ?? [];
            $acc = array_merge($resData['data'], $acc);
            return $acc;
        }, []);
        return $results;
    }

    protected function saveToDb($data, $board)
    {
        if (!$data) {
            $this->io->note('无数据');
            return;
        }
        $data = array_reduce($data, function ($acc, $cur) {
            $acc[$cur['id']] = $cur;
            return $acc;
        }, []);
        $conn = $this->em->getConnection();
        $idListStr = implode(',', array_keys($data));
        $sql = "SELECT id, postid FROM post WHERE postid IN ($idListStr)";
        $dbIdList = $conn->query($sql)->fetchAll();
        $dbIdList = array_column($dbIdList, 'id', 'postid');

        $postidMap = $dbIdList;

        $insertData = array_diff_key($data, $dbIdList);
        $count = count($insertData);
        if ($count) {
            $insertData = array_map(function ($resPost) use ($conn, $board) {
                $resPost['author'] = $resPost['author'] ?? '= =';
                $resPost['idate'] = ($resPost['idate'] == '00-00-00 00:00') ? '71-01-01 00:00' : $resPost['idate'];
                $resPost['ndate'] = ($resPost['ndate'] == '00-00-00 00:00') ? '71-01-01 00:00' : $resPost['idate'];
                $resPost['author'] = mb_substr($resPost['author'], 0, 40);
                $resPost = array_map(function ($s) use ($conn) {
                    return $conn->quote($s);
                }, $resPost);
                return "({$resPost['subject']}, {$resPost['id']}, {$resPost['examine_status']}, {$resPost['author']}, {$resPost['idate']}, {$resPost['ndate']}, {$board})";
            }, $insertData);
            $insertPostsStr = implode(',', $insertData);
            $sql = "INSERT INTO post (subject, postid, examine_status, author, idate, ndate, board) VALUES $insertPostsStr";
            $conn->query($sql);

            $lastInsertId = $conn->lastInsertId();
            $insertIds = range($lastInsertId, $lastInsertId + $count - 1);
            $insertMap = array_combine(array_keys($insertData), array_map(function ($id) { return (string) $id; }, $insertIds));
            $postidMap += $insertMap;
        }
        $postidMap = array_map(function ($postInfo, $key) use ($postidMap) {
            $postInfo['db_id'] = $postidMap[$key];
            return $postInfo;
        }, $data, array_column($data, 'id'));
        unset($data, $dbIdList, $sql, $idListStr, $insertPostsStr);
        $this->writeln('导入'.$count.'条新帖');
        return $postidMap;
    }
}
