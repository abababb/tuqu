<?php

namespace App\Command;

use App\Entity\Post;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Cookie\CookieJar;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FetchPostsCommand extends Command
{
    protected static $defaultName = 'app:fetch:posts';

    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->batchSize = 3;
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
        //$cmd = "(sleep 60  && kill -9 ".getmypid().") > /dev/null &";
        //exec($cmd); //issue a command to force kill this process in 10 seconds

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
                $this->batchSaveReplies($resData, $board);
                unset($resData);
            }
        }

        $this->io->success('成功导入');
    }

    protected function batchSaveReplies($resData, $board)
    {
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
            $crawler = new Crawler();
            $crawler->addHTMLContent($html, 'gbk');

            $contents = $crawler->filter('.read')->each(function (Crawler $node, $i) {
                $text = trim($node->text());
                return $text;
            });
            if (!$contents) {
                return $acc;
            }

            // todo 针对有引用的楼层, contents分层找父级
            
            $authors = $crawler->filter('.authorname')->each(function (Crawler $node, $i) {
                $text = trim($node->text());
                return $text;
            });
            if (!$authors) {
                return $acc;
            }
            $authors[0] = explode("\n", $authors[0])[0] ?? $authors[0];
            $authornamePattern = '/№(?P<reply_no>\d+) ☆☆☆(?P<full_author>.*)于(?P<reply_time>.*)留言☆☆☆　/';
            for ($i = 0; $i < count($contents); $i++) {
                $matches = [];
                preg_match($authornamePattern, $authors[$i], $matches);
                $authorInfo = explode('|', $matches['full_author']);
                if (!isset($matches['reply_no']) || $matches['reply_no'] === null) {
                    continue;
                }
                $acc[] = [
                    'post_id' => $res[0]['db_id'] ?? '',
                    'raw_content' => $contents[$i],
                    'raw_authorname' => $authors[$i],
                    'reply_no' => $matches['reply_no'],
                    'author' => $authorInfo[0] ?? '',
                    'author_code' => $authorInfo[1] ?? '',
                    'reply_time' => $matches['reply_time'],
                ];
            }
            return $acc;
        }, []);
        $this->writeln('获取'.count($htmlList).'条回复');
        $this->saveRepliesToDb($htmlList);
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

    protected function saveRepliesToDb($htmlList)
    {
        $conn = $this->em->getConnection();
        $postReplies = array_reduce($htmlList, function ($acc, $cur) {
            $acc[$cur['post_id']] = $acc[$cur['post_id']] ?? [];
            $acc[$cur['post_id']][] = $cur;
            return $acc;
        }, []);

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

        $count = count($insertReplies);

        if ($count) {
            $insertData = array_map(function ($reply) use ($conn) {
                $reply = array_map(function ($s) use ($conn) {
                    return $conn->quote($s);
                }, $reply);
                return "({$reply['raw_content']}, {$reply['raw_authorname']}, {$reply['post_id']}, {$reply['reply_no']}, {$reply['author']}, {$reply['author_code']}, {$reply['reply_time']})";
            }, $insertReplies);
            $insertPostsStr = implode(',', $insertData);
            $sql = "INSERT INTO reply (raw_content, raw_authorname, post_id, reply_no, author, author_code, reply_time) VALUES $insertPostsStr";
            $conn->query($sql);
            $this->writeln('插入'.$count.'条新回复');
        }
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
