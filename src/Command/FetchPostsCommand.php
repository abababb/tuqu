<?php

namespace App\Command;

use App\Entity\Post;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FetchPostsCommand extends ContainerAwareCommand
{
    protected static $defaultName = 'app:fetch:posts';

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
        $this->io = new SymfonyStyle($input, $output);
        $keyword = $input->getArgument('keyword');
        $page = $input->getArgument('page');
        $maxLimit = $input->getArgument('max_limit');

        if ($keyword) {
            $this->io->note(sprintf('搜索关键字: %s', $keyword));
        }

        $currentPage = 0;
        if ($page) {
            $this->io->note(sprintf('初始页数: %s', $page));
            $currentPage = (int) $page;
        }
        $resData = $this->fetchPage($keyword, $currentPage);
        $count = 0;

        if (isset($resData['data'])) {
            $maxPage = $resData['pages'];
            $this->saveToDb($resData['data']);
            while ($currentPage < $maxPage) {
                $count++;
                if ($maxLimit && $count > $maxLimit) {
                    break;
                }
                $currentPage++;
                $this->io->note('开始导入第'.$currentPage.'页数据');
                $resData = $this->fetchPage($keyword, $currentPage);
                if (isset($resData['data']) && is_array($resData['data'])) {
                    $this->saveToDb($resData['data']);
                }
            }
        }
        $this->io->success('成功导入');
    }

    protected function fetchPage($keyword, $page)
    {
        $client = new \GuzzleHttp\Client();
        $boardUrl = 'http://bbs.jjwxc.net/bbsapi.php?action=board';
        $searchUrl = 'http://bbs.jjwxc.net/bbsapi.php?action=search';
        $postData = array(
            'board' => '2',
            'page' => $page,
            'sign' => 't1y30KJlifEX7XJeoSv3NvZifLl08tdwcBxJKi130qUIi1mJOpMGV7om4rii/AzhM3h3RhnMFS4%3D',
            'source' => 'IOS',
            'versionCode' => '195',
        );
        $url = $boardUrl;
        if ($keyword) {
            $postData['keyword'] = $keyword;
            $postData['topic'] = '3';
            $url = $searchUrl;
        }
        $res = $client->request('POST', $url, array(
            'form_params' => $postData,
        ));
        $res = $res->getBody();
        $resData = json_decode($res, true);
        // dump($resData);
        return $resData ?? [];
    }

    protected function saveToDb($data)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $repo = $em->getRepository(Post::class);
        $count = 0;
        foreach ($data as $resPost) {
            if (!$repo->findOneBy(['postid' => $resPost['id']])) {
                $count++;
                $post = new Post();
                $post->setSubject($resPost['subject'] ?? '');
                $post->setPostid($resPost['id'] ?? 0);
                $post->setExamineStatus($resPost['examine_status'] ?? 0);
                $post->setReplies($resPost['replies'] ?? 0);
                $post->setAuthor($resPost['author'] ?? '');
                $post->setIdate(new \DateTime($resPost['idate']));
                $post->setNdate(new \DateTime($resPost['ndate']));
                $em->persist($post);
            }
        }
        $em->flush();
        $this->io->note('导入'.$count.'条数据');
    }
}
