<?php

namespace App\Command;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use GuzzleHttp\Cookie\CookieJar;

class ReadPostCommand extends Command
{
    protected static $defaultName = 'app:read:post';

    protected function configure()
    {
        $this
            ->setDescription('获取一个帖子数据')
            ->addArgument('postid', InputArgument::OPTIONAL, '帖子ID')
            ->addArgument('page', InputArgument::OPTIONAL, '页数')
            ->addArgument('board', InputArgument::OPTIONAL, '板块')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $page = $input->getArgument('page') ?: 1;
        $postid = $input->getArgument('postid') ?: 0;
        $board = $input->getArgument('board') ?: 2;
        $html = $this->fetchPage($postid, $page, $board);

        //dump($html);
        $crawler = new Crawler($html);
        $contents = $crawler->filter('.read')->each(function (Crawler $node, $i) {
            $replaceStr = '留言☆☆☆';
            $text = trim($node->text());
            $text = str_replace($replaceStr, $replaceStr."\r\n   ", $text);
            return $text."\r\n";
        });
        $authors = $crawler->filter('.authorname')->each(function (Crawler $node, $i) {
            return $node->text();
        });
        $posts = [];
        for ($i = 0; $i < count($contents); $i++) {
            $posts[] = $contents[$i].$authors[$i];
        }
        $io->listing($posts);
    }

    protected function fetchPage($postid, $page, $board)
    {
        $client = new \GuzzleHttp\Client();
        $page--;
        $url = 'http://bbs.jjwxc.net/showmsg.php?board='.$board.'&boardpagemsg=1&page='.$page.'&id='.$postid;
        $cookieJar = CookieJar::fromArray([
            'bbstoken' => 'MjA5OTQ3OTFfMF84YWQxZmI1ZGM3ZDk0NTIwZmUxNTMyNjIzNjc2Y2M0ZV8xX18%3D',
            'bbsnicknameAndsign' => '2%257E%2529%2524zzz',
        ], 'bbs.jjwxc.net');

        $res = $client->request('GET', $url, [
            'cookies' => $cookieJar,
        ]);
        $res = $res->getBody()->getContents();
        return $res ?? '';
    }
}
