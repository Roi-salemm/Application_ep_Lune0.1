<?php

namespace App\EventSubscriber;

use App\Entity\SwContent;
use App\Entity\SwDisplay;
use App\Entity\SwSchedule;
use App\Service\SwSnapshotSyncService;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;

/**
 * Synchronisation automatique de sw_snapshot apres ecriture des tables parentes.
 * Pourquoi: garder la table snapshot strictement alignee sans multiplier les appels dans chaque controleur.
 * Info: la synchro est executee en postFlush pour travailler sur des IDs stables.
 */
final class SwSnapshotSyncSubscriber implements EventSubscriber
{
    /** @var array<string, true> */
    private array $displayIdsToSync = [];

    /** @var array<string, true> */
    private array $displayIdsToDelete = [];

    /** @var array<string, true> */
    private array $scheduleIdsToDelete = [];

    private bool $isProcessing = false;

    public function __construct(private readonly SwSnapshotSyncService $snapshotSyncService)
    {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::postRemove,
            Events::postFlush,
        ];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->collectEntityChange($args->getObject(), false);
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->collectEntityChange($args->getObject(), false);
    }

    public function postRemove(LifecycleEventArgs $args): void
    {
        $this->collectEntityChange($args->getObject(), true);
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->isProcessing) {
            return;
        }
        if ($this->displayIdsToSync === [] && $this->displayIdsToDelete === [] && $this->scheduleIdsToDelete === []) {
            return;
        }

        $this->isProcessing = true;
        try {
            $objectManager = $args->getObjectManager();
            if (!$objectManager instanceof EntityManagerInterface) {
                $this->resetQueues();
                return;
            }

            $changed = false;

            foreach (array_keys($this->scheduleIdsToDelete) as $scheduleId) {
                $changed = $this->snapshotSyncService->removeByScheduleId($scheduleId) > 0 || $changed;
            }

            foreach (array_keys($this->displayIdsToDelete) as $displayId) {
                $changed = $this->snapshotSyncService->removeByDisplayId($displayId) > 0 || $changed;
            }

            foreach (array_keys($this->displayIdsToSync) as $displayId) {
                $changed = $this->snapshotSyncService->syncByDisplayId($displayId) || $changed;
            }

            $this->resetQueues();

            if ($changed) {
                $objectManager->flush();
            }
        } finally {
            $this->isProcessing = false;
        }
    }

    private function collectEntityChange(object $entity, bool $isRemoval): void
    {
        if ($entity instanceof SwDisplay) {
            $displayId = $entity->getId();
            if ($displayId === null) {
                return;
            }
            if ($entity->isActive() && !$isRemoval) {
                $this->displayIdsToSync[(string) $displayId] = true;
            } else {
                $this->displayIdsToDelete[(string) $displayId] = true;
            }
            return;
        }

        if ($entity instanceof SwSchedule) {
            $scheduleId = $entity->getId();
            if ($isRemoval && $scheduleId !== null) {
                $this->scheduleIdsToDelete[(string) $scheduleId] = true;
            }

            $display = $entity->getDisplay();
            $displayId = $display?->getId();
            if ($displayId === null) {
                return;
            }
            if ($display !== null && $display->isActive() && !$isRemoval) {
                $this->displayIdsToSync[(string) $displayId] = true;
            } else {
                $this->displayIdsToDelete[(string) $displayId] = true;
            }
            return;
        }

        if ($entity instanceof SwContent) {
            $display = $entity->getDisplay();
            $displayId = $display?->getId();
            if ($displayId === null) {
                return;
            }
            if ($display !== null && $display->isActive() && !$isRemoval) {
                $this->displayIdsToSync[(string) $displayId] = true;
            } else {
                $this->displayIdsToDelete[(string) $displayId] = true;
            }
        }
    }

    private function resetQueues(): void
    {
        $this->displayIdsToSync = [];
        $this->displayIdsToDelete = [];
        $this->scheduleIdsToDelete = [];
    }
}

