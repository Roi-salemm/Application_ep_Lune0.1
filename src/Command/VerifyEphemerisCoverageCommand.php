<?php

namespace App\Command;

use App\Service\Ephemeris\EphemerisCoverageVerifierService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:ephemeris:verify-coverage',
    description: 'Verifie les trous ou doublons dans les tables ephemerides.'
)]
final class VerifyEphemerisCoverageCommand extends Command
{
    public function __construct(private readonly EphemerisCoverageVerifierService $verifier)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('table', null, InputOption::VALUE_REQUIRED, 'moon', 'moon')
            ->addOption('step', null, InputOption::VALUE_REQUIRED, 'Pas attendu (ex: 1h, 30m)', '1h')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max anomalies affichees', 20);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stepSeconds = $this->parseStepToSeconds((string) $input->getOption('step'));
        $limit = max(1, (int) $input->getOption('limit'));
        $tableOption = strtolower((string) $input->getOption('table'));
        if ($tableOption !== 'moon') {
            $output->writeln('<error>Seule la table moon est supportee.</error>');
            return Command::FAILURE;
        }

        if ($stepSeconds <= 0) {
            $output->writeln('<error>Pas attendu invalide.</error>');
            return Command::FAILURE;
        }

        $tables = ['moon_ephemeris_hour' => 'Moon'];

        foreach ($tables as $table => $label) {
            try {
                $result = $this->verifier->verifyTable($table, $label, $stepSeconds, $limit);
            } catch (\Throwable $e) {
                $output->writeln(sprintf('<error>Erreur verification %s: %s</error>', $label, $e->getMessage()));
                return Command::FAILURE;
            }
            $output->writeln('');
            $output->writeln($this->verifier->formatReport($result));
        }

        return Command::SUCCESS;
    }

    private function parseStepToSeconds(string $step): int
    {
        $value = trim($step);
        if ($value === '') {
            return 0;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        if (preg_match('/^(\d+)\s*([hms])$/i', $value, $matches) !== 1) {
            return 0;
        }

        $amount = (int) $matches[1];
        $unit = strtolower($matches[2]);

        return match ($unit) {
            'h' => $amount * 3600,
            'm' => $amount * 60,
            's' => $amount,
            default => 0,
        };
    }
}
