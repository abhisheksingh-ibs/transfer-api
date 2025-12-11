<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class TestRouteController extends AbstractController
{
    public function index(): JsonResponse
    {
        return $this->json(['status' => 'OK']);
    }
}
