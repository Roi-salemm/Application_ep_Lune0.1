<?php

namespace App\Service;

use App\Entity\SwSchedule;
use App\Entity\SwSnapshot;
use App\Repository\SwScheduleRepository;
use App\Repository\SwSnapshotRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Synchronise la projection sw_snapshot depuis les tables parentes.
 * Pourquoi: garantir une lecture front rapide avec une ligne unique par texte.
 * Info: si le display passe inactif, la ligne snapshot associee est supprimee.
 */
final class SwSnapshotSyncService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SwSnapshotRepository $snapshotRepository,
        private readonly SwScheduleRepository $scheduleRepository
    ) {
    }

    public function syncByDisplayId(string $displayId): bool
    {
        $schedules = $this->scheduleRepository->findByDisplayId($displayId);
        if ($schedules === []) {
            return $this->removeByDisplayId($displayId) > 0;
        }

        $changed = false;
        foreach ($schedules as $schedule) {
            if (!$schedule instanceof SwSchedule) {
                continue;
            }
            $changed = $this->syncBySchedule($schedule) || $changed;
        }

        return $changed;
    }

    public function syncBySchedule(SwSchedule $schedule): bool
    {
        $scheduleId = $schedule->getId();
        if ($scheduleId === null) {
            return false;
        }

        $display = $schedule->getDisplay();
        $content = $schedule->getContent();
        if ($display === null || $content === null || !$display->isActive()) {
            return $this->removeByScheduleId((string) $scheduleId) > 0;
        }

        $snapshot = $this->snapshotRepository->findOneByScheduleId((string) $scheduleId);
        $isNew = false;
        if (!$snapshot instanceof SwSnapshot) {
            $snapshot = new SwSnapshot();
            $snapshot->setSwSchedule($schedule);
            $isNew = true;
        }

        $contentJson = $content->getContentJson();
        if (!is_array($contentJson)) {
            $contentJson = [];
        }

        $cardTitle = $this->resolveCardTitle($contentJson);
        $cardText = $this->resolveCardText($contentJson);
        if ($cardText === '') {
            $cardText = trim((string) ($content->getComment() ?? $schedule->getComment() ?? ''));
        }

        $changed = false;
        $changed = $this->assignIfChanged($snapshot->getSwDisplay()?->getId(), $display->getId(), fn () => $snapshot->setSwDisplay($display)) || $changed;
        $changed = $this->assignIfChanged($snapshot->getSwContent()?->getId(), $content->getId(), fn () => $snapshot->setSwContent($content)) || $changed;
        $changed = $this->assignIfChanged($snapshot->getLang(), $display->getLang(), fn () => $snapshot->setLang($display->getLang())) || $changed;
        $changed = $this->assignIfChanged($snapshot->getFamily(), $display->getFamily(), fn () => $snapshot->setFamily($display->getFamily())) || $changed;
        $changed = $this->assignIfChanged($snapshot->getReadingMode(), $display->getReadingMode(), fn () => $snapshot->setReadingMode($display->getReadingMode())) || $changed;
        $changed = $this->assignIfChanged($snapshot->getCardTitle(), $cardTitle, fn () => $snapshot->setCardTitle($cardTitle)) || $changed;
        $changed = $this->assignIfChanged($snapshot->getCardText(), $cardText, fn () => $snapshot->setCardText($cardText)) || $changed;
        $changed = $this->assignIfChanged($snapshot->getContentJson(), $contentJson, fn () => $snapshot->setContentJson($contentJson)) || $changed;
        $changed = $this->assignIfChanged($snapshot->getStartsAt()->getTimestamp(), $schedule->getStartsAtUtc()->getTimestamp(), fn () => $snapshot->setStartsAt($schedule->getStartsAtUtc())) || $changed;
        $changed = $this->assignIfChanged($snapshot->getEndsAt()->getTimestamp(), $schedule->getEndsAtUtc()->getTimestamp(), fn () => $snapshot->setEndsAt($schedule->getEndsAtUtc())) || $changed;
        $changed = $this->assignIfChanged($snapshot->isActive(), $display->isActive(), fn () => $snapshot->setIsActive($display->isActive())) || $changed;

        if ($isNew) {
            $this->entityManager->persist($snapshot);
            return true;
        }

        return $changed;
    }

    public function removeByDisplayId(string $displayId): int
    {
        $snapshots = $this->snapshotRepository->findByDisplayId($displayId);
        $removed = 0;

        foreach ($snapshots as $snapshot) {
            if (!$snapshot instanceof SwSnapshot) {
                continue;
            }
            $this->entityManager->remove($snapshot);
            $removed++;
        }

        return $removed;
    }

    public function removeByScheduleId(string $scheduleId): int
    {
        $snapshot = $this->snapshotRepository->findOneByScheduleId($scheduleId);
        if (!$snapshot instanceof SwSnapshot) {
            return 0;
        }

        $this->entityManager->remove($snapshot);

        return 1;
    }

    private function resolveCardTitle(array $contentJson): ?string
    {
        $title = trim((string) ($contentJson['title'] ?? $contentJson['subtitle'] ?? ''));

        return $title !== '' ? $title : null;
    }

    private function resolveCardText(array $contentJson): string
    {
        return trim((string) ($contentJson['card_text'] ?? $contentJson['cardText'] ?? $contentJson['label'] ?? $contentJson['title'] ?? ''));
    }

    private function assignIfChanged(mixed $current, mixed $target, \Closure $assign): bool
    {
        if ($current === $target) {
            return false;
        }

        $assign();

        return true;
    }
}

