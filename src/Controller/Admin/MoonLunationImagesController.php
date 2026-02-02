<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

final class MoonLunationImagesController extends AbstractController
{
    #[Route('/admin/lunation_images', name: 'admin_lunation_images', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/lunation_images.html.twig', [
            'output_dir' => 'var/moon/lunaison_2h',
            'output_dir_age' => 'var/moon/lunaison_2h_age',
            'year' => 2020,
            'lunation_index' => 0,
            'step_hours' => 2,
        ]);
    }

    #[Route('/admin/lunation_images/run', name: 'admin_lunation_images_run', methods: ['POST'])]
    public function run(KernelInterface $kernel): Response
    {
        $command = [
            'php',
            $kernel->getProjectDir() . '/bin/console',
            'app:moon:download-lunation',
        ];

        $process = new Process($command, $kernel->getProjectDir());
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            $message = trim($process->getErrorOutput() ?: $process->getOutput());
            $this->addFlash('error', 'Telechargement echoue.');
            if ($message !== '') {
                $this->addFlash('lunation_output', $message);
            }
        } else {
            $message = trim($process->getOutput());
            $this->addFlash('success', 'Telechargement termine.');
            if ($message !== '') {
                $this->addFlash('lunation_output', $message);
            }
        }

        return $this->redirectToRoute('admin_lunation_images');
    }

    #[Route('/admin/lunation_images/run-age', name: 'admin_lunation_images_run_age', methods: ['POST'])]
    public function runAge(KernelInterface $kernel): Response
    {
        $command = [
            'php',
            $kernel->getProjectDir() . '/bin/console',
            'app:moon:download-lunation-age',
        ];

        $process = new Process($command, $kernel->getProjectDir());
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            $message = trim($process->getErrorOutput() ?: $process->getOutput());
            $this->addFlash('error', 'Telechargement echoue.');
            if ($message !== '') {
                $this->addFlash('lunation_output', $message);
            }
        } else {
            $message = trim($process->getOutput());
            $this->addFlash('success', 'Telechargement termine.');
            if ($message !== '') {
                $this->addFlash('lunation_output', $message);
            }
        }

        return $this->redirectToRoute('admin_lunation_images');
    }
}
