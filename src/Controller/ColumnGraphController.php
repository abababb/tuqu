<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class ColumnGraphController extends AbstractController
{
    /**
     * @Route("/column/graph", name="column_graph")
     */
    public function index()
    {
        return $this->render('column_graph/index.html.twig', [
            'controller_name' => 'ColumnGraphController',
        ]);
    }
}
