<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class SessionDebugController extends AbstractController
{
    #[Route('/_session-test', name: 'session_test')]
    public function sessionTest(Request $request): Response
    {
        $session = $request->getSession();
        $session->start();

        $count = $session->get('debug_count', 0);
        $count++;
        $session->set('debug_count', $count);

        $content = sprintf(
            "Symfony session test<br>\nSession ID: %s<br>\nCount: %d<br>\nsave_path (ini): %s\n",
            $session->getId(),
            $count,
            ini_get('session.save_path')
        );

        return new Response($content);
    }
}
