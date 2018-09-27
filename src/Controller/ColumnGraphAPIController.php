<?php

namespace App\Controller;

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
        $keywords = $request->get('keywords');
        $keywords = json_decode($keywords, true);
        return new JsonResponse($keywords);
    }
}
