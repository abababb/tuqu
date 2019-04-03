<?php

namespace App\Controller;

use App\Entity\Post;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class ColumnGraphAPIController extends AbstractController
{
    /**
     * @Route("/column/graph/api", name="column_graph_api")
     */
    public function index(Request $request)
    {
        try {
            $keywords = $request->get('keywords');
            $keywords = json_decode($keywords, true);

            $em = $this->get('doctrine')->getEntityManager();
            $repo = $em->getRepository(Post::class);
            $qb = $repo->createQueryBuilder('p');
            $orX = $qb->expr()->orX();
            foreach ($keywords as $key => $keyword) {
                $orX->add($qb->expr()->like('p.subject', ':keyword'.$key));
                $qb->setParameter('keyword'.$key, '%'.$keyword.'%');
            }
            if (!$keywords) {
                throw new \Exception('无关键词');
            }
            $startDate = new \DateTime('-6 month');
            $posts = $qb
                ->select('p.idate')
                ->where($orX)
                ->andWhere($qb->expr()->gte('p.idate', ':date'))
                ->setParameter('date', $startDate)
                ->getQuery()
                ->getResult()
                ;
            $posts = array_reduce($posts, function ($acc, $cur) {
                $date = $cur['idate']->format('Y-m-d');
                $acc[$date] = $acc[$date] ?? 0;
                $acc[$date]++;
                return $acc;
            }, []);
            $range = [];
            for ($i = $startDate; $i <= new \DateTime(); $i->add(new \DateInterval('P1D'))) {
                $date = $i->format('Y-m-d');
                $range[$date] = $posts[$date] ?? 0;
            }
            foreach ($range as $day => $count) {
                $result[] = [$day, $count];
            }
        } catch (\Exception $e) {
            $result = [];
        }
        return new JsonResponse($result);
    }
}
