<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

final class DebugController extends AbstractController
{
    #[Route('/admin/debug', name: 'admin_debug', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/debug.html.twig', []);
    }

    #[Route('/admin/debug/verify-age-days', name: 'admin_debug_verify_age_days', methods: ['POST'])]
    public function verifyAgeDays(KernelInterface $kernel): Response
    {
        $command = [
            'php',
            $kernel->getProjectDir() . '/bin/console',
            'app:moon:verify-age-days',
        ];

        $process = new Process($command, $kernel->getProjectDir());
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            $message = trim($process->getErrorOutput() ?: $process->getOutput());
            $this->addFlash('error', 'Verification echouee.');
            if ($message !== '') {
                $this->addFlash('debug_output', $message);
            }
        } else {
            $message = trim($process->getOutput());
            $this->addFlash('success', 'Verification terminee.');
            if ($message !== '') {
                $this->addFlash('debug_output', $message);
            }
        }

        return $this->redirectToRoute('admin_debug');
    }
}
