<?php

namespace App\Command;

use App\Entity\Post;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;

use Doctrine\ORM\EntityManagerInterface;
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
        $this->batchSize = 10;
        $this->maxLimit = 1000;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('拉兔区帖子数据')
            ->addArgument('keyword', InputArgument::OPTIONAL, '关键词')
            ->addArgument('page', InputArgument::OPTIONAL, '开始页')
            ->addArgument('max_limit', InputArgument::OPTIONAL, '页数限制')
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

        for ($i = 0; $i <= $maxBatchCount; $i++) {
            $startPage = $page + $i * $this->batchSize;
            $endPage = $startPage + $this->batchSize - 1;
            $resData = $this->batchFetchPage($keyword, $startPage);
            $this->writeln('开始导入第'.$startPage.'-'.$endPage.'页数据');
            $this->saveToDb($resData);
        }

        $this->io->success('成功导入');
    }

    protected function batchFetchPage($keyword, $page)
    {
        $url = 'http://bbs.jjwxc.net/bbsapi.php?action=';
        if ($keyword) {
            $url .= 'search';
        } else {
            $url .= 'board';
        }
        $client = new Client();
        $promises = array_map(function ($page) use ($client, $url, $keyword) {
            $postData = array(
                'board' => '2',
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

    protected function saveToDb($data)
    {
        $data = array_reduce($data, function ($acc, $cur) {
            $acc[$cur['id']] = $cur;
            return $acc;
        }, []);
        $repo = $this->em->getRepository(Post::class);
        $qb = $repo->createQueryBuilder('p');
        $dbIdList = $qb
            ->select('p.postid')
            ->where(
                $qb->expr()->in('p.postid', ':idList')
            )
            ->setParameters(
                array(
                    'idList' => array_keys($data),
                )
            )
            ->getQuery()
            ->getResult()
        ;
        $dbIdList = array_column($dbIdList, 'postid', 'postid');
        $data = array_diff_key($data, $dbIdList);
        $count = 0;
        foreach ($data as $resPost) {
            $count++;
            $post = new Post();
            $post->setSubject($resPost['subject'] ?? '');
            $post->setPostid($resPost['id'] ?? 0);
            $post->setExamineStatus($resPost['examine_status'] ?? 0);
            $post->setReplies($resPost['replies'] ?? 0);
            $post->setAuthor($resPost['author'] ?? '');
            $post->setIdate(new \DateTime($resPost['idate']));
            $post->setNdate(new \DateTime($resPost['ndate']));
            $this->em->persist($post);
        }
        unset($data, $post);
        if ($count) {
            $this->em->flush();
        }
        $this->writeln('导入'.$count.'条数据');
    }

    protected function writeln($text)
    {
        $prefix = "\n[" . (new \DateTime('now'))->format('Y-m-d H:i:s') . "][" . $this->convert(memory_get_usage(true)) . "]\t";
        $this->output->writeln($prefix . $text);
    }

    protected function convert($size)
    {
        $unit = array('b', 'kb', 'mb', 'gb', 'tb', 'pb');
        return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 1) . ' ' . $unit[$i];
    }
}
