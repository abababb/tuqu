<?php

namespace App\Command\ConsumeQueue;

use App\Entity\Post;
use App\Utils\RedisUtil;
use App\Command\BaseCommand;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Cookie\CookieJar;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FetchRepliesCommand extends BaseCommand
{
    protected static $defaultName = 'app:consume:fetch:replies';

    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->defaultBatchSize = 10;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('消耗队列，根据帖子ID拉回复')
            ->addArgument('batch_size', InputArgument::OPTIONAL, '一次拉多少')
            ->addArgument('board', InputArgument::OPTIONAL, '板块')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
        $batchSize = $input->getArgument('batch_size') ?: $this->defaultBatchSize;
        $board = $input->getArgument('board') ?: 2;

        $this->popFromQueue($board, $batchSize);
        $this->io->success('成功导入');
    }

    protected function popFromQueue($board, $batchSize)
    {
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
            fclose($redis);

            //$this->writeln('去重前: '.count($resData)."\n");
            $resData = array_unique($resData);
            //$this->writeln('去重后: '.count($resData)."\n");

            if ($resData) {
                $resData = array_map(function ($line) {
                    list($postid, $dbid, $pages) = explode(':', $line);
                    return [
                        'id' => $postid,
                        'db_id' => $dbid,
                        'pages' => $pages,
                    ];
                }, $resData);
                try {
                    $this->batchSaveReplies($resData, $board);
                } catch (\Exception $e) {
                    $this->writeln($e->getMessage());
                }
            }

            sleep(5);
        }
    }

    protected function batchSaveReplies($resData, $board)
    {
        //$this->writeln('开始拉取'.count($resData).'个页面');
        $cookieJar = CookieJar::fromArray([
            'bbstoken' => 'MjA5OTQ3OTFfMF84YWQxZmI1ZGM3ZDk0NTIwZmUxNTMyNjIzNjc2Y2M0ZV8xX18%3D',
            'bbsnicknameAndsign' => '2%257E%2529%2524zzz',
        ], 'bbs.jjwxc.net');

        $client = new \GuzzleHttp\Client();
        $promises = array_map(function ($post) use ($client, $board, $cookieJar) {
            $page = $post['pages'] ?: 1;
            $page--;
            $postid = $post['id'];
            $url = 'http://bbs.jjwxc.net/showmsg.php?board='.$board.'&boardpagemsg=1&page='.$page.'&id='.$postid;
            return $client->getAsync($url, ['cookies' => $cookieJar]);
        }, $resData);
        $results = Promise\unwrap($promises);
        $results = array_map(null, $resData, $results);
        $htmlList = array_reduce($results, function ($acc, $res) {
            if (!$res[1] instanceof \GuzzleHttp\Psr7\Response) {
                return $acc;
            }
            $html = (string) $res[1]->getBody();
            $htmlInfo = $this->parseHtml($html, ($res[0]['db_id'] ?? ''));

            unset($html);
            $acc = array_merge($htmlInfo, $acc);
            return $acc;
        }, []);
        unset($results);

        $this->writeln('获取'.count($htmlList).'条回复');
        $this->saveRepliesToDb($htmlList);
    }

    protected function getElementByClass($class, $html, $getImg = false)
    {
        $pattern = '#<td class="'.$class.'"\s*>\s*(?P<content>.*?)\s*</td>#s';
        $matches = [];
        preg_match_all($pattern, $html, $matches);

        if (!isset($matches['content'])) {
            return;
        }
        $content = array_map(function ($line) use ($getImg) {
            $reply = [
                'content' => '',
                'images' => '',
            ];
            $trimPattern = '#<[^>]+>#';
            $reply['content'] = preg_replace($trimPattern, '', $line);

            if ($getImg) {
                $matches = [];
                $pattern = '#<img src="(?P<image>.*?)".*/>#s';
                preg_match_all($pattern, $line, $matches);
                if (isset($matches['image'])) {
                    $reply['images'] = implode('|', $matches['image']);
                }
            }
            unset($matches);
            return $reply;
        }, $matches['content']);

        unset($matches);
        return $content;
    }

    protected function parseHtml($html, $dbId)
    {
        $htmlInfo = [];
        if (!$dbId) {
            return $htmlInfo;
        }
        $html = mb_convert_encoding($html, 'utf-8', 'gbk');

        $contents = $this->getElementByClass('read', $html, true);
        $authors = $this->getElementByClass('authorname', $html);

        if (!$contents || !$authors) {
            return $htmlInfo;
        }

        $authors = array_column($authors, 'content');
        
        $authors[0] = explode("\n", $authors[0])[0] ?? $authors[0];
        $authornamePattern = '/№(?P<reply_no>\d+) ☆☆☆(?P<full_author>.*)于(?P<reply_time>.*)留言☆☆☆　/';
        for ($i = 0; $i < count($contents); $i++) {
            $matches = [];
            preg_match($authornamePattern, $authors[$i], $matches);
            $authorInfo = explode('|', $matches['full_author']);
            if (!isset($matches['reply_no']) || $matches['reply_no'] === null) {
                continue;
            }
            $htmlInfo[] = [
                'post_id' => $dbId,
                'raw_content' => $contents[$i]['content'],
                'raw_authorname' => $authors[$i],
                'reply_no' => $matches['reply_no'],
                'author' => $authorInfo[0] ?? '',
                'author_code' => $authorInfo[1] ?? '',
                'reply_time' => $matches['reply_time'],
                'images' => $contents[$i]['images'],
            ];
        }
        unset($html);
        unset($contents);
        unset($authors);
        unset($authorInfo);
        return $htmlInfo;
    }

    protected function saveRepliesToDb($htmlList)
    {
        $conn = $this->em->getConnection();
        $postReplies = array_reduce($htmlList, function ($acc, $cur) {
            $acc[$cur['post_id']] = $acc[$cur['post_id']] ?? [];
            $acc[$cur['post_id']][] = $cur;
            return $acc;
        }, []);
        unset($htmlList);

        $idListStr = implode(',', array_keys($postReplies));
        $sql = "SELECT post_id, MAX(reply_no) AS max_reply FROM reply WHERE post_id IN ($idListStr) GROUP BY post_id";
        $dbIdList = $conn->query($sql)->fetchAll();
        $dbIdList = array_column($dbIdList, 'max_reply', 'post_id');
        $postReplies = array_map(function ($replies) use ($dbIdList) {
            $replies = array_filter($replies, function ($reply) use ($dbIdList) {
                $dbPostMaxReplyNo = $dbIdList[$reply['post_id']] ?? null;
                if ($dbPostMaxReplyNo === null) {
                    return true;
                }
                return ($reply['reply_no'] > $dbPostMaxReplyNo);
            });
            return $replies;
        }, $postReplies);
        $insertReplies = array_reduce($postReplies, function ($acc, $cur) {
            $acc = array_merge($cur, $acc);
            return $acc;
        }, []);
        unset($postReplies);

        $count = count($insertReplies);

        if ($count) {
            $insertData = array_map(function ($reply) use ($conn) {
                $reply = array_map(function ($s) use ($conn) {
                    return $conn->quote($s);
                }, $reply);
                return "({$reply['raw_content']}, {$reply['raw_authorname']}, {$reply['post_id']}, {$reply['reply_no']}, {$reply['author']}, {$reply['author_code']}, {$reply['reply_time']}, {$reply['images']})";
            }, $insertReplies);
            $insertPostsStr = implode(',', $insertData);
            $sql = "INSERT INTO reply (raw_content, raw_authorname, post_id, reply_no, author, author_code, reply_time, images) VALUES $insertPostsStr";
            $conn->query($sql);
            $this->writeln('插入'.$count.'条新回复');
            unset($insertReplies);
            unset($insertPostsStr);
        }
    }

}
