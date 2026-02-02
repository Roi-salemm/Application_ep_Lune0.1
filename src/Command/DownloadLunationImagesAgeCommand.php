<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:moon:download-lunation-age',
    description: 'Telecharge les images NASA SVS d une lunaison complete et renomme avec age_days et heure UTC.'
)]
class DownloadLunationImagesAgeCommand extends Command
{
    private const YEAR = 2020;
    private const LUNATION_INDEX = 0;
    private const STEP_HOURS = 2;
    private const OUT_DIR = 'var/moon/lunaison_2h_age';
    private const SIZE = '730x730_1x1_30p';
    private const BASE = 'https://svs.gsfc.nasa.gov/vis/a000000/a004700/a004768';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Filesystem $fs,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $year = self::YEAR;
        $lunationIndex = self::LUNATION_INDEX;
        $stepHours = self::STEP_HOURS;
        $outDir = rtrim(self::OUT_DIR, '/');
        $size = self::SIZE;
        $base = rtrim(self::BASE, '/');

        if ($stepHours < 1) {
            $output->writeln('<error>step-hours doit etre >= 1</error>');
            return Command::INVALID;
        }

        $this->fs->mkdir($outDir);

        // 1) Telecharger mooninfo_YYYY.txt si absent
        $moonInfoUrl = sprintf('%s/mooninfo_%d.txt', $base, $year);
        $moonInfoPath = sprintf('%s/mooninfo_%d.txt', $outDir, $year);

        if (!$this->fs->exists($moonInfoPath)) {
            $output->writeln("Telechargement: $moonInfoUrl");
            $content = $this->httpClient->request('GET', $moonInfoUrl)->getContent();
            file_put_contents($moonInfoPath, $content);
        } else {
            $output->writeln("OK: mooninfo deja present: $moonInfoPath");
        }

        // 2) Parser les lignes (1 ligne = 1 heure)
        [$rows, $drops] = $this->parseMoonInfo($moonInfoPath);

        if (count($drops) < ($lunationIndex + 2)) {
            $output->writeln('<error>Impossible de trouver assez de nouvelles lunes dans ce fichier pour cet index de lunaison.</error>');
            $output->writeln('Chutes detectees: ' . count($drops));
            return Command::FAILURE;
        }

        // Chaque chute marque la fin d une lunaison, donc :
        // lunation 0 = intervalle [drop0+1, drop1+1)
        $startIdx = $drops[$lunationIndex] + 1;
        $endIdx = $drops[$lunationIndex + 1] + 1;

        $t0 = $rows[$startIdx]['dt'];
        $t1 = $rows[$endIdx]['dt'];
        $durationSec = $t1->getTimestamp() - $t0->getTimestamp();
        if ($durationSec <= 0) {
            $output->writeln('<error>Intervalle lunaison invalide.</error>');
            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            'Lunaison #%d: t0=%s | t1=%s | duree=%.2f h',
            $lunationIndex,
            $t0->format(DATE_ATOM),
            $t1->format(DATE_ATOM),
            $durationSec / 3600
        ));

        // 3) Selection des indices toutes les 2 heures (donc 1 ligne sur stepHours)
        // Les donnees sont horaires, donc stepHours=2 => +2 lignes
        $selected = [];
        for ($i = $startIdx; $i < $endIdx; $i += $stepHours) {
            $selected[] = $i;
        }

        // 4) Telecharger images + CSV
        $csvPath = $outDir . '/index.csv';
        $csv = fopen($csvPath, 'w');
        fputcsv($csv, ['frame','filename','utc_iso','hour_utc','age_days','phase_percent']);

        $framesBase = sprintf('%s/frames/%s/', $base, $size);

        $downloaded = 0;
        $skipped = 0;

        foreach ($selected as $idx) {
            $row = $rows[$idx];

            $frame = $idx + 1; // frame 0001 = premiere ligne du fichier txt (apres header)
            $srcName = sprintf('moon.%04d.jpg', $frame);
            $srcUrl = $framesBase . $srcName;

            $ageDays = (float) $row['age'];
            $hourUtc = (int) $row['dt']->format('H');

            $dstName = sprintf('moon_f%04d_age%06.2f_h%02d.jpg', $frame, $ageDays, $hourUtc);
            $dstPath = $outDir . '/' . $dstName;

            if ($this->fs->exists($dstPath)) {
                $skipped++;
            } else {
                $ok = $this->downloadWithRetries($srcUrl, $dstPath, 3, 200000);
                if (!$ok) {
                    $output->writeln("<comment>Echec telechargement: $srcUrl</comment>");
                    continue;
                }
                $downloaded++;
            }

            fputcsv($csv, [
                $frame,
                $dstName,
                $row['dt']->format(DATE_ATOM),
                sprintf('%02d', $hourUtc),
                $row['age'],
                $row['phase'],
            ]);
        }

        fclose($csv);

        $output->writeln('Termine.');
        $output->writeln('Images selectionnees: ' . count($selected));
        $output->writeln('Telechargees: ' . $downloaded . ' | Deja presentes: ' . $skipped);
        $output->writeln('CSV: ' . $csvPath);
        $output->writeln('Dossier: ' . $outDir);

        return Command::SUCCESS;
    }

    /**
     * @return array{0: array<int,array{dt:\DateTimeImmutable,age:float,phase:float}>, 1: int[]}
     */
    private function parseMoonInfo(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines || count($lines) < 2) {
            throw new \RuntimeException('Fichier mooninfo vide ou illisible.');
        }

        // Supprime l entete (1ere ligne)
        array_shift($lines);

        $monthMap = [
            'Jan'=>1,'Feb'=>2,'Mar'=>3,'Apr'=>4,'May'=>5,'Jun'=>6,
            'Jul'=>7,'Aug'=>8,'Sep'=>9,'Oct'=>10,'Nov'=>11,'Dec'=>12,
        ];

        $rows = [];
        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            // Format SVS (exemple):
            // 01 Jan 2020 00:00 UT  29.95  5.783 ...
            // parts: [day, mon, year, hh:mm, UT, phase%, age, ...]
            if (!$parts || count($parts) < 7) {
                continue;
            }

            $day = (int) $parts[0];
            $mon = $parts[1];
            $year = (int) $parts[2];
            $hhmm = $parts[3];

            $h = (int) substr($hhmm, 0, 2);
            $m = (int) substr($hhmm, 3, 2);

            $phase = (float) $parts[5];
            $age = (float) $parts[6];

            $month = $monthMap[$mon] ?? null;
            if (!$month) {
                continue;
            }

            $dt = new \DateTimeImmutable(
                sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $h, $m),
                new \DateTimeZone('UTC')
            );

            $rows[] = ['dt' => $dt, 'age' => $age, 'phase' => $phase];
        }

        // Detecte les chutes d age (nouvelle lune)
        $drops = [];
        for ($i = 0; $i < count($rows) - 1; $i++) {
            $diff = $rows[$i + 1]['age'] - $rows[$i]['age'];
            if ($diff < -10.0) {
                $drops[] = $i;
            }
        }

        return [$rows, $drops];
    }

    private function downloadWithRetries(string $url, string $dstPath, int $tries, int $sleepMicroseconds): bool
    {
        for ($i = 1; $i <= $tries; $i++) {
            try {
                $resp = $this->httpClient->request('GET', $url);
                $content = $resp->getContent();
                file_put_contents($dstPath, $content);
                // pause legere (politesse)
                usleep($sleepMicroseconds);
                return true;
            } catch (\Throwable) {
                if ($i === $tries) {
                    return false;
                }
                usleep($sleepMicroseconds);
            }
        }
        return false;
    }
}
