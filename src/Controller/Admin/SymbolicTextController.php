<?php

namespace App\Controller\Admin;

use App\DTO\Admin\SymbolicTextCreateInput;
use App\Entity\SwContent;
use App\Entity\SwDisplay;
use App\Entity\SwSchedule;
use App\Entity\SwTextVariant;
use App\Form\Admin\SymbolicTextCreateType;
use App\Repository\MsMappingRepository;
use App\Repository\OrbWindowRepository;
use App\Repository\SwContentRepository;
use App\Repository\SwDisplayRepository;
use App\Repository\SwScheduleRepository;
use App\Repository\SwTextVariantRepository;
use App\Service\Moon\OrbWindowParseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Controle l onglet admin Symbolic Text et son CRUD timeline.
 * Pourquoi: relier une timeline visuelle a la base sw_display/sw_content/sw_schedule avec un placement temporel exact.
 * Info: toutes les dates sont manipulees en UTC; la ligne "Family Symbolic / Weather" est alignee sur orb_window (multi_section_phase).
 */
final class SymbolicTextController extends AbstractController
{
    public function __construct(private readonly CsrfTokenManagerInterface $csrfTokenManager)
    {
    }

    /**
     * @return array<string, int>
     */
    private function spanOptions(): array
    {
        return [
            '1h' => 3600,
            '6h' => 21600,
            '12h' => 43200,
            '1j' => 86400,
            '2j' => 172800,
            '4j' => 345600,
            '7j' => 604800,
            '14j' => 1209600,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function rowDefinitions(): array
    {
        return [
            [
                'code' => 'influence_stellar',
                'label' => 'astronomical event',
                'family' => 'symbolic',
                'reading_mode' => 'astronomical_event',
                'schedule_type' => 'influence_window',
                'default_color' => '#5a1b1b',
            ],
            [
                'code' => 'influence_synodic',
                'label' => 'influence',
                'family' => 'symbolic',
                'reading_mode' => 'influence',
                'schedule_type' => 'influence_window',
                'default_color' => '#1f5378',
            ],
            [
                'code' => 'appellation',
                'label' => 'lunationName',
                'family' => 'symbolic',
                'reading_mode' => 'lunation_name',
                'schedule_type' => 'influence_window',
                'default_color' => '#0f5a1b',
            ],
        ];
    }

    #[Route('/admin/symbolic-text', name: 'admin_symbolic_text', methods: ['GET'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        SwDisplayRepository $displayRepository,
        SwScheduleRepository $scheduleRepository,
        SwTextVariantRepository $variantRepository,
        MsMappingRepository $msMappingRepository,
        OrbWindowRepository $orbWindowRepository
    ): Response {
        $utc = new \DateTimeZone('UTC');
        $spanOptions = $this->spanOptions();
        $span = $this->sanitizeSpan((string) $request->query->get('span', '2j'));
        $spanSeconds = $spanOptions[$span];

        $endUtc = $this->parseUtcInput((string) $request->query->get('end_utc', '')) ?? new \DateTimeImmutable('now', $utc);
        $endUtc = $endUtc->setTimezone($utc);
        $startUtc = $endUtc->modify(sprintf('-%d seconds', $spanSeconds));

        $rowDefinitions = $this->rowDefinitions();
        $this->ensureDefaultDisplays($entityManager, $displayRepository, $rowDefinitions);

        $timelinePayload = $this->buildTimelinePayload(
            $startUtc,
            $endUtc,
            $rowDefinitions,
            $scheduleRepository,
            $msMappingRepository,
            $orbWindowRepository
        );
        $weatherVariantOptions = $this->buildWeatherVariantOptions(
            $variantRepository->findValidatedUsedWeatherVariants('symbolic', 'weather')
        );

        $createInput = new SymbolicTextCreateInput();
        $createInput->row_code = 'influence_synodic';
        $createInput->starts_at_utc = $startUtc->format('Y-m-d H:i');
        $createInput->ends_at_utc = $endUtc->format('Y-m-d H:i');
        $createForm = $this->createForm(SymbolicTextCreateType::class, $createInput, [
            'row_definitions' => $rowDefinitions,
            'action' => $this->generateUrl('admin_symbolic_text_create'),
            'method' => 'POST',
        ]);

        return $this->render('admin/symbolic_text.html.twig', [
            'active_menu' => 'symbolic_text',
            'page_title' => 'Symbolic Text',
            'page_subtitle' => sprintf(
                'Timeline UTC de %s a %s',
                $startUtc->format('Y-m-d H:i'),
                $endUtc->format('Y-m-d H:i')
            ),
            'span' => $span,
            'span_options' => array_keys($spanOptions),
            'span_seconds' => $spanSeconds,
            'window_start_utc' => $startUtc,
            'window_end_utc' => $endUtc,
            'window_end_input' => $endUtc->format('Y-m-d H:i'),
            'window_end_ts' => $endUtc->getTimestamp(),
            'row_definitions' => $rowDefinitions,
            'entries_by_row' => $timelinePayload['entries_by_row'],
            'gaps_by_row' => $timelinePayload['gaps_by_row'],
            'astronomy_points' => $timelinePayload['astronomy_points'],
            'symbolic_segments' => $timelinePayload['symbolic_segments'],
            'lunation_segments' => $timelinePayload['lunation_segments'],
            'text_symbolic_zones' => $timelinePayload['text_symbolic_zones'],
            'weather_entries' => $timelinePayload['weather_entries'],
            'day_markers' => $timelinePayload['day_markers'],
            'real_segments' => $timelinePayload['real_segments'],
            'initial_payload' => $timelinePayload,
            'weather_variant_options' => $weatherVariantOptions,
            'create_form' => $createForm->createView(),
        ]);
    }

    #[Route('/admin/symbolic-text/data', name: 'admin_symbolic_text_data', methods: ['GET'])]
    public function data(
        Request $request,
        SwScheduleRepository $scheduleRepository,
        MsMappingRepository $msMappingRepository,
        OrbWindowRepository $orbWindowRepository
    ): JsonResponse {
        $startUtc = $this->parseUtcInput((string) $request->query->get('start_utc', ''));
        $endUtc = $this->parseUtcInput((string) $request->query->get('end_utc', ''));
        if (!$startUtc || !$endUtc || $endUtc <= $startUtc) {
            return $this->json(
                ['error' => 'Parametres start_utc/end_utc invalides.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $rowDefinitions = $this->rowDefinitions();

        $payload = $this->buildTimelinePayload(
            $startUtc,
            $endUtc,
            $rowDefinitions,
            $scheduleRepository,
            $msMappingRepository,
            $orbWindowRepository
        );

        return $this->json($payload);
    }

    /**
     * Parse automatique des textes Weather depuis SWTextVariant vers SWDisplay/SWContent/SWSchedule.
     * Pourquoi: produire soit une lunaison complete, soit une zone unique, avec rotation variant_no coherente.
     */
    #[Route('/admin/symbolic-text/parse-weather-week', name: 'admin_symbolic_text_parse_weather_week', methods: ['POST'])]
    public function parseWeatherWeek(
        Request $request,
        EntityManagerInterface $entityManager,
        SwDisplayRepository $displayRepository,
        SwScheduleRepository $scheduleRepository,
        SwTextVariantRepository $variantRepository,
        OrbWindowRepository $orbWindowRepository
    ): RedirectResponse {
        $redirect = $this->redirectToTimeline($request);
        if (!$this->isCsrfTokenValid('parse_symbolic_text_weather_week', (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour le parse Weather.');
            return $redirect;
        }

        $utc = new \DateTimeZone('UTC');
        $zoneStartUtc = $this->parseUtcInput((string) $request->request->get('zone_start_utc', ''));
        $zoneEndUtc = $this->parseUtcInput((string) $request->request->get('zone_end_utc', ''));
        $isSingleZoneParse = $zoneStartUtc instanceof \DateTimeImmutable && $zoneEndUtc instanceof \DateTimeImmutable;
        $forcedPhaseIndex = null;
        $forcedVariantNo = null;

        if ($isSingleZoneParse) {
            if ($zoneEndUtc <= $zoneStartUtc) {
                $this->addFlash('error', 'Zone Weather invalide.');
                return $redirect;
            }
            $parseStartUtc = $zoneStartUtc;
            $parseEndUtc = $zoneEndUtc;

            $forcedPhaseIndex = $this->phaseIndexFromInfluencePhaseKey((string) $request->request->get('phase_key', ''));
            $forcedVariantNoRaw = trim((string) $request->request->get('variant_no', ''));
            $forcedVariantNo = ctype_digit($forcedVariantNoRaw) ? (int) $forcedVariantNoRaw : null;
            if ($forcedPhaseIndex === null || $forcedVariantNo === null || $forcedVariantNo < 1) {
                $this->addFlash('error', 'Selection phase/variante invalide pour le parse zone.');
                return $redirect;
            }
        } else {
            $parseStartInputUtc = $this->parseUtcInput((string) $request->request->get('parse_start_utc', ''));
            if (!$parseStartInputUtc) {
                $this->addFlash('error', 'Debut de parse invalide.');
                return $redirect;
            }

            $parseEndInputUtc = $this->parseUtcInput((string) $request->request->get('parse_end_utc', ''));
            if ($parseEndInputUtc instanceof \DateTimeImmutable && $parseEndInputUtc > $parseStartInputUtc) {
                $parseStartUtc = $parseStartInputUtc;
                $parseEndUtc = $parseEndInputUtc;
            } else {
                // Compatibilite: fallback legacy (7 jours) si le front n envoie pas encore parse_end_utc.
                $parseStartUtc = $this->normalizeToIsoWeekStart($parseStartInputUtc);
                $parseEndUtc = $parseStartUtc->modify('+7 days');
            }
        }

        $todayStartUtc = (new \DateTimeImmutable('now', $utc))->setTime(0, 0, 0);
        if ($parseEndUtc <= $todayStartUtc) {
            $this->addFlash('warning', 'Parse ignore: plage totalement anterieure a aujourd hui.');
            return $redirect;
        }

        $rowDefinitions = $this->rowDefinitions();
        $this->ensureDefaultDisplays($entityManager, $displayRepository, $rowDefinitions);
        $weatherFamily = 'symbolic';
        $weatherReadingMode = 'weather';
        $weatherScheduleType = 'influence_window';
        $weatherDefaultColor = '#2D66A0';

        $sourceZones = $orbWindowRepository->findTimelineZonesByFamilyAndMethod(
            OrbWindowParseService::FAMILY_INFLUENCE_ORB,
            OrbWindowParseService::METHOD_INFLUENCE_ORB,
            $parseStartUtc,
            $parseEndUtc
        );
        $targetZones = $isSingleZoneParse
            ? $this->selectSingleWeatherParseZone(
                $sourceZones,
                $parseStartUtc,
                $parseEndUtc,
                $todayStartUtc,
                $forcedPhaseIndex,
                $forcedVariantNo
            )
            : $this->selectWeatherParseZones($sourceZones, $parseStartUtc, $parseEndUtc, $todayStartUtc);
        if ($targetZones === []) {
            $this->addFlash('warning', 'Aucune zone Weather eligible sur la plage choisie.');
            return $redirect;
        }

        $variantsByPhase = $this->groupVariantsByPhase($variantRepository->findValidatedUsedWeatherVariants(
            $weatherFamily,
            $weatherReadingMode
        ));

        $rewriteStartTs = null;
        $rewriteEndTs = null;
        foreach ($targetZones as $zone) {
            $zoneStartTs = (int) ($zone['write_start_ts'] ?? 0);
            $zoneEndTs = (int) ($zone['write_end_ts'] ?? 0);
            if ($zoneEndTs <= $zoneStartTs) {
                continue;
            }
            $rewriteStartTs = $rewriteStartTs === null ? $zoneStartTs : min($rewriteStartTs, $zoneStartTs);
            $rewriteEndTs = $rewriteEndTs === null ? $zoneEndTs : max($rewriteEndTs, $zoneEndTs);
        }

        $removedSchedules = 0;
        $trimmedSchedules = 0;
        $removedContents = 0;
        $orphanCandidates = [];

        if ($rewriteStartTs !== null && $rewriteEndTs !== null && $rewriteEndTs > $rewriteStartTs) {
            $rewriteStartUtc = (new \DateTimeImmutable('@' . $rewriteStartTs))->setTimezone($utc);
            $rewriteEndUtc = (new \DateTimeImmutable('@' . $rewriteEndTs))->setTimezone($utc);
            $existing = $scheduleRepository->findTimelineEntriesForAdmin(
                $rewriteStartUtc,
                $rewriteEndUtc,
                [$weatherReadingMode],
                $weatherFamily
            );

            foreach ($existing as $schedule) {
                if (!$schedule instanceof SwSchedule) {
                    continue;
                }
                $display = $schedule->getDisplay();
                if (!$display instanceof SwDisplay) {
                    continue;
                }
                if (
                    strtolower(trim($display->getFamily())) !== strtolower($weatherFamily)
                    || strtolower(trim($display->getReadingMode())) !== strtolower($weatherReadingMode)
                ) {
                    continue;
                }

                $existingStartTs = $schedule->getStartsAtUtc()->getTimestamp();
                $existingEndTs = $schedule->getEndsAtUtc()->getTimestamp();
                if ($existingEndTs <= $todayStartUtc->getTimestamp()) {
                    continue;
                }
                if (!$this->timeRangeOverlaps($existingStartTs, $existingEndTs, $rewriteStartTs, $rewriteEndTs)) {
                    continue;
                }

                if ($existingStartTs < $todayStartUtc->getTimestamp()) {
                    $schedule->setEndsAtUtc($todayStartUtc);
                    $trimmedSchedules++;
                    continue;
                }

                $content = $schedule->getContent();
                $entityManager->remove($schedule);
                $removedSchedules++;
                if ($content instanceof SwContent && $content->getId() !== null) {
                    $orphanCandidates[(string) $content->getId()] = $content;
                }
            }

            if ($removedSchedules > 0 || $trimmedSchedules > 0) {
                $entityManager->flush();
            }

            foreach ($orphanCandidates as $content) {
                if (!$content instanceof SwContent) {
                    continue;
                }
                if ($scheduleRepository->countByContent($content) > 0) {
                    continue;
                }
                $entityManager->remove($content);
                $removedContents++;
            }
            if ($removedContents > 0) {
                $entityManager->flush();
            }
        }

        $rotationBeforeUtc = $rewriteStartTs !== null
            ? (new \DateTimeImmutable('@' . $rewriteStartTs))->setTimezone($utc)
            : $parseStartUtc;
        $historySchedules = $scheduleRepository->findWeatherSchedulesEndingBefore(
            $rotationBeforeUtc,
            4000,
            $weatherFamily,
            $weatherReadingMode
        );
        $lastVariantNoByPhase = $this->buildLastVariantNoByPhaseFromSchedules($historySchedules);

        $created = 0;
        $missingVariantZones = 0;
        $unknownPhaseZones = 0;
        $nowUtc = new \DateTimeImmutable('now', $utc);

        foreach ($targetZones as $zone) {
            $phaseIndex = (int) ($zone['phase_index'] ?? -1);
            if ($phaseIndex < 0 || $phaseIndex > 7) {
                $unknownPhaseZones++;
                continue;
            }

            $phaseVariants = $this->sortVariantsByVariantNo($variantsByPhase[$phaseIndex] ?? []);
            if ($phaseVariants === []) {
                $missingVariantZones++;
                continue;
            }

            $forcedVariantNoForZone = isset($zone['forced_variant_no']) ? (int) $zone['forced_variant_no'] : null;
            $variant = $this->pickNextWeatherVariant(
                $phaseVariants,
                $lastVariantNoByPhase[$phaseIndex] ?? null,
                $forcedVariantNoForZone
            );
            if (!$variant instanceof SwTextVariant) {
                $missingVariantZones++;
                continue;
            }
            $lastVariantNoByPhase[$phaseIndex] = $variant->getVariantNo();

            $writeStartTs = (int) ($zone['write_start_ts'] ?? 0);
            $writeEndTs = (int) ($zone['write_end_ts'] ?? 0);
            if ($writeEndTs <= $writeStartTs) {
                continue;
            }
            $writeStartUtc = (new \DateTimeImmutable('@' . $writeStartTs))->setTimezone($utc);
            $writeEndUtc = (new \DateTimeImmutable('@' . $writeEndTs))->setTimezone($utc);

            $label = trim($variant->getCardText());
            if ($label === '') {
                $missingVariantZones++;
                continue;
            }
            $subtitle = trim((string) ($variant->getTitle() ?? ''));
            $defaultColor = $weatherDefaultColor;
            $autoCode = $this->buildAutoDisplayCode('weather_auto');
            $lunationKey = trim((string) ($zone['lunation_key'] ?? ''));

            $display = new SwDisplay();
            $display->setCode($autoCode);
            $display->setFamily($weatherFamily);
            $display->setReadingMode($weatherReadingMode);
            $display->setLang($variant->getLang() !== '' ? $variant->getLang() : 'fr');
            $display->setIsActive(true);
            $display->setComment($this->nullIfEmpty('Parse auto Weather depuis SWTextVariant.'));

            $contentJson = array_filter([
                'label' => $label,
                'subtitle' => $subtitle,
                'color' => $defaultColor,
                'variant_id' => $variant->getId(),
                'symbolic_weather' => [
                    'phase_key' => $variant->getPhaseKey(),
                    'variant_no' => $variant->getVariantNo(),
                    'variant_id' => $variant->getId(),
                    'is_validated' => $variant->isValidated(),
                    'is_used' => $variant->isUsed(),
                ],
            ], static fn (mixed $value): bool => $value !== null && $value !== '');

            $content = new SwContent();
            $content->setDisplay($display);
            $content->setVersionNo(1);
            $content->setStatus('validated');
            $content->setIsCurrent(true);
            $content->setIsValidated(true);
            $content->setContentJson($contentJson);
            $content->setSchemaVersion('1.0');
            $content->setComment($this->nullIfEmpty($this->truncateForVarchar($label, 255)));
            $content->setEditorialNotes($this->nullIfEmpty('Genere automatiquement depuis SWTextVariant.'));
            $content->setValidatedAtUtc($nowUtc);

            $payloadJson = [
                'source' => 'sw_text_variant',
                'variant_id' => $variant->getId(),
                'phase_key' => $variant->getPhaseKey(),
                'variant_no' => $variant->getVariantNo(),
                'lunation_key' => $lunationKey !== '' ? $lunationKey : null,
                'orb_phase_key' => (string) ($zone['phase_key'] ?? ''),
                'generated_at_utc' => $nowUtc->format('Y-m-d H:i:s'),
            ];

            $schedule = new SwSchedule();
            $schedule->setDisplay($display);
            $schedule->setContent($content);
            $schedule->setScheduleType($this->sanitizeScheduleType($weatherScheduleType));
            $schedule->setStartsAtUtc($writeStartUtc);
            $schedule->setEndsAtUtc($writeEndUtc);
            $schedule->setPriority(100);
            $schedule->setIsPublished(true);
            $schedule->setComment($this->nullIfEmpty($this->truncateForVarchar($label, 255)));
            $schedule->setPayloadJson($payloadJson);

            $entityManager->persist($display);
            $entityManager->persist($content);
            $entityManager->persist($schedule);
            $created++;
        }

        if ($created > 0) {
            $entityManager->flush();
        }

        $this->addFlash(
            'success',
            sprintf(
                'Parse Weather (%s) termine: %d cree(s), %d re-ecrit(s), %d tronque(s), %d contenu(s) orphelin(s) supprime(s).',
                $isSingleZoneParse ? 'zone' : 'lunaison',
                $created,
                $removedSchedules,
                $trimmedSchedules,
                $removedContents
            )
        );

        if ($missingVariantZones > 0 || $unknownPhaseZones > 0) {
            $this->addFlash(
                'warning',
                sprintf(
                    'Zones non remplies: %d sans variante valide/usee, %d avec phase non reconnue.',
                    $missingVariantZones,
                    $unknownPhaseZones
                )
            );
        }

        return $redirect;
    }

    #[Route('/admin/symbolic-text/create', name: 'admin_symbolic_text_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        SwDisplayRepository $displayRepository
    ): RedirectResponse {
        $redirect = $this->redirectToTimeline($request);
        $rowDefinitions = $this->rowDefinitions();

        $input = new SymbolicTextCreateInput();
        $form = $this->createForm(SymbolicTextCreateType::class, $input, [
            'row_definitions' => $rowDefinitions,
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted()) {
            $this->addFlash('error', 'Soumission create invalide.');
            return $redirect;
        }
        if (!$form->isValid()) {
            $this->flashFormErrors($form);
            return $redirect;
        }

        $row = $this->findRowDefinitionByCode($input->row_code, $rowDefinitions);
        if ($row === null) {
            $this->addFlash('error', 'Ligne timeline inconnue.');
            return $redirect;
        }

        $startsAtUtc = $this->parseUtcInput($input->starts_at_utc);
        $endsAtUtc = $this->parseUtcInput($input->ends_at_utc);
        if (!$startsAtUtc || !$endsAtUtc || $endsAtUtc <= $startsAtUtc) {
            $this->addFlash('error', 'Plage horaire UTC invalide.');
            return $redirect;
        }

        $payloadError = null;
        $payloadJson = $this->parseOptionalJson((string) ($input->payload_json ?? ''), $payloadError);
        if ($payloadError !== null) {
            $this->addFlash('error', $payloadError);
            return $redirect;
        }

        $defaultColor = $this->findDefaultColorByCode($input->row_code, $this->rowDefinitions());
        $resolvedLabel = trim((string) ($input->label ?? ''));
        $resolvedSubtitle = trim((string) ($input->subtitle ?? ''));
        $resolvedIcon = trim((string) ($input->icon ?? ''));
        $resolvedEditorialNotes = trim((string) ($input->editorial_notes ?? ''));
        $resolvedColor = $this->sanitizeColor((string) ($input->color ?? ''), $defaultColor);
        $status = $this->sanitizeStatus((string) ($input->status ?? 'draft'));
        $schemaVersion = $this->sanitizeSchemaVersion((string) ($input->schema_version ?? '1.0'));

        $contentJson = [];
        $rawContentJson = trim((string) ($input->content_json ?? ''));
        if ($rawContentJson !== '') {
            $contentError = null;
            $decodedContentJson = $this->parseOptionalJson($rawContentJson, $contentError);
            if ($contentError !== null || $decodedContentJson === null) {
                $this->addFlash('error', $contentError ?? 'content_json invalide.');
                return $redirect;
            }
            $contentJson = $decodedContentJson;
            $resolvedLabel = trim((string) ($contentJson['label'] ?? $contentJson['title'] ?? $resolvedLabel));
            $resolvedSubtitle = trim((string) ($contentJson['subtitle'] ?? $resolvedSubtitle));
            $resolvedIcon = trim((string) ($contentJson['icon'] ?? $resolvedIcon));
            $resolvedEditorialNotes = trim((string) ($contentJson['editorial_notes'] ?? $resolvedEditorialNotes));
            $resolvedColor = $this->sanitizeColor((string) ($contentJson['color'] ?? $contentJson['tone'] ?? $resolvedColor), $defaultColor);
            $input->display_is_active = $this->resolveJsonBool($contentJson, ['is_active', 'isActive'], $input->display_is_active);
            $input->content_is_current = $this->resolveJsonBool($contentJson, ['is_current', 'isCurrent'], $input->content_is_current);
            $input->content_is_validated = $this->resolveJsonBool($contentJson, ['is_validated', 'isValidated'], $input->content_is_validated);
            $input->schedule_is_published = $this->resolveJsonBool($contentJson, ['is_published', 'isPublished'], $input->schedule_is_published);
            $status = $this->sanitizeStatus((string) ($contentJson['status'] ?? $status));
            $schemaVersion = $this->sanitizeSchemaVersion((string) ($contentJson['schema_version'] ?? $contentJson['schemaVersion'] ?? $schemaVersion));
        }

        if ($resolvedLabel === '') {
            $this->addFlash('error', 'Le texte est obligatoire (champ Texte ou content_json.label).');
            return $redirect;
        }

        $display = new SwDisplay();
        $display->setCode($this->buildUniqueDisplayCode($input->row_code, $displayRepository));
        $display->setFamily((string) $row['family']);
        $display->setReadingMode((string) $row['reading_mode']);
        $display->setLang($this->sanitizeLang((string) $input->display_lang));
        $display->setIsActive($input->display_is_active);
        $display->setComment($this->nullIfEmpty((string) ($input->display_comment ?? '')));

        $isCurrent = $input->content_is_current;
        $isValidated = $input->content_is_validated;
        $nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $content = new SwContent();
        $content->setDisplay($display);
        $content->setVersionNo(1);
        $content->setStatus($status);
        $content->setIsCurrent($isCurrent);
        $content->setIsValidated($isValidated);
        $content->setContentJson($contentJson);
        $content->setSchemaVersion($schemaVersion);
        $content->setComment($this->nullIfEmpty($resolvedLabel));
        $content->setEditorialNotes($this->nullIfEmpty($resolvedEditorialNotes));
        $content->setValidatedAtUtc($isValidated ? $nowUtc : null);

        $scheduleComment = trim((string) ($input->schedule_comment ?? ''));
        if ($scheduleComment === '') {
            $scheduleComment = $resolvedLabel;
        }

        $schedule = new SwSchedule();
        $schedule->setDisplay($display);
        $schedule->setContent($content);
        $schedule->setScheduleType($this->sanitizeScheduleType((string) ($row['schedule_type'] ?? 'influence_window')));
        $schedule->setStartsAtUtc($startsAtUtc);
        $schedule->setEndsAtUtc($endsAtUtc);
        $schedule->setPriority((int) $input->priority);
        $schedule->setIsPublished($input->schedule_is_published);
        $schedule->setComment($this->nullIfEmpty($scheduleComment));
        $schedule->setPayloadJson($payloadJson);

        $entityManager->persist($display);
        $entityManager->persist($content);
        $entityManager->persist($schedule);
        $entityManager->flush();

        $this->addFlash('success', 'Element timeline cree (3 tables).');

        return $redirect;
    }

    #[Route('/admin/symbolic-text/{id}/update', name: 'admin_symbolic_text_update', methods: ['POST'])]
    public function update(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
        SwScheduleRepository $scheduleRepository,
        SwDisplayRepository $displayRepository,
        SwContentRepository $contentRepository
    ): RedirectResponse {
        $redirect = $this->redirectToTimeline($request);
        if (!$this->isCsrfTokenValid('update_symbolic_text_' . $id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $redirect;
        }

        $schedule = $scheduleRepository->find($id);
        if (!$schedule instanceof SwSchedule) {
            $this->addFlash('error', 'Element timeline introuvable.');
            return $redirect;
        }

        $content = $schedule->getContent();
        if (!$content instanceof SwContent) {
            $this->addFlash('error', 'Contenu associe introuvable.');
            return $redirect;
        }

        $displayCode = trim((string) $request->request->get('display_code', ''));
        $display = $displayRepository->findOneByCode($displayCode);
        if (!$display instanceof SwDisplay) {
            $this->addFlash('error', 'Ligne timeline inconnue.');
            return $redirect;
        }

        $startsAtUtc = $this->parseUtcInput((string) $request->request->get('starts_at_utc', ''));
        $endsAtUtc = $this->parseUtcInput((string) $request->request->get('ends_at_utc', ''));
        if (!$startsAtUtc || !$endsAtUtc || $endsAtUtc <= $startsAtUtc) {
            $this->addFlash('error', 'Plage horaire UTC invalide.');
            return $redirect;
        }

        $payloadError = null;
        $payloadJson = $this->parseOptionalJson((string) $request->request->get('payload_json', ''), $payloadError);
        if ($payloadError !== null) {
            $this->addFlash('error', $payloadError);
            return $redirect;
        }

        $label = trim((string) $request->request->get('label', ''));
        if ($label === '') {
            $this->addFlash('error', 'Le texte est obligatoire.');
            return $redirect;
        }

        $isCurrent = $this->requestBool($request, 'is_current');
        if ($isCurrent) {
            $contentRepository->clearCurrentForDisplay($display);
        }
        $isValidated = $this->requestBool($request, 'is_validated');

        if ($content->getDisplay()?->getId() !== $display->getId()) {
            $content->setDisplay($display);
            $content->setVersionNo($contentRepository->findMaxVersionNoForDisplay($display) + 1);
        }

        $defaultColor = $this->findDefaultColorByCode($displayCode, $this->rowDefinitions());
        $contentJson = array_filter([
            'label' => $label,
            'subtitle' => trim((string) $request->request->get('subtitle', '')) ?: null,
            'color' => $this->sanitizeColor((string) $request->request->get('color', ''), $defaultColor),
            'icon' => trim((string) $request->request->get('icon', '')) ?: null,
        ], static fn ($value): bool => $value !== null && $value !== '');

        $content->setStatus($this->sanitizeStatus((string) $request->request->get('status', 'validated')));
        $content->setIsCurrent($isCurrent);
        $content->setIsValidated($isValidated);
        $content->setSchemaVersion($this->sanitizeSchemaVersion((string) $request->request->get('schema_version', '1.0')));
        $content->setContentJson($contentJson);
        $content->setComment($this->nullIfEmpty((string) $request->request->get('comment', '')));
        $content->setEditorialNotes($this->nullIfEmpty((string) $request->request->get('editorial_notes', '')));
        $content->setValidatedAtUtc($isValidated ? new \DateTimeImmutable('now', new \DateTimeZone('UTC')) : null);

        $schedule->setDisplay($display);
        $schedule->setScheduleType($this->sanitizeScheduleType((string) $request->request->get('schedule_type', 'influence_window')));
        $schedule->setStartsAtUtc($startsAtUtc);
        $schedule->setEndsAtUtc($endsAtUtc);
        $schedule->setPriority((int) $request->request->get('priority', 100));
        $schedule->setIsPublished($this->requestBool($request, 'is_published'));
        $schedule->setComment($this->nullIfEmpty((string) $request->request->get('comment', '')));
        $schedule->setPayloadJson($payloadJson);

        $entityManager->flush();

        $this->addFlash('success', 'Element timeline modifie.');

        return $redirect;
    }

    #[Route('/admin/symbolic-text/{id}/delete', name: 'admin_symbolic_text_delete', methods: ['POST'])]
    public function delete(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
        SwScheduleRepository $scheduleRepository
    ): RedirectResponse {
        $redirect = $this->redirectToTimeline($request);
        if (!$this->isCsrfTokenValid('delete_symbolic_text_' . $id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $redirect;
        }

        $schedule = $scheduleRepository->find($id);
        if (!$schedule instanceof SwSchedule) {
            $this->addFlash('error', 'Element timeline introuvable.');
            return $redirect;
        }

        $content = $schedule->getContent();
        $entityManager->remove($schedule);
        $entityManager->flush();

        if ($content instanceof SwContent && $scheduleRepository->countByContent($content) === 0) {
            $entityManager->remove($content);
            $entityManager->flush();
        }

        $this->addFlash('success', 'Element timeline supprime.');

        return $redirect;
    }

    /**
     * @param array<int, array<string, string>> $rowDefinitions
     * @return array<string, mixed>
     */
    private function buildTimelinePayload(
        \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc,
        array $rowDefinitions,
        SwScheduleRepository $scheduleRepository,
        MsMappingRepository $msMappingRepository,
        OrbWindowRepository $orbWindowRepository
    ): array {
        $readingModes = array_values(array_unique(array_map(
            static fn (array $row): string => (string) ($row['reading_mode'] ?? ''),
            $rowDefinitions
        )));
        $readingModes = array_values(array_filter($readingModes, static fn (string $value): bool => $value !== ''));

        $schedules = $scheduleRepository->findTimelineEntriesForAdmin($startUtc, $endUtc, $readingModes);

        $durationSeconds = max(1, $endUtc->getTimestamp() - $startUtc->getTimestamp());
        $entriesByRow = $this->buildEntriesByRow($schedules, $rowDefinitions, $startUtc, $endUtc, $durationSeconds);
        $gapsByRow = $this->buildGapsByRow($entriesByRow, $rowDefinitions, $startUtc, $endUtc, $durationSeconds);

        $phaseEvents = $this->normalizePhaseEvents(
            $msMappingRepository->findPhaseEventsForTimeline($startUtc, $endUtc, 10000)
        );
        $astronomyPoints = $this->buildAstronomyPoints($phaseEvents, $startUtc, $endUtc, $durationSeconds);
        $symbolicSegments = $this->buildInfluenceOrbSegmentsFromOrbWindow(
            $orbWindowRepository,
            $startUtc,
            $endUtc,
            $durationSeconds
        );
        $lunationSegments = $this->buildLunationYearSegments($phaseEvents, $startUtc, $endUtc, $durationSeconds);
        $textSymbolicZones = $this->buildTextSymbolicZonesFromOrbWindow(
            $orbWindowRepository,
            $startUtc,
            $endUtc,
            $durationSeconds
        );

        $weatherSchedules = $scheduleRepository->findTimelineEntriesForAdmin(
            $startUtc,
            $endUtc,
            ['weather']
        );
        $weatherEntriesByRow = $this->buildEntriesByRow(
            $weatherSchedules,
            [[
                'code' => 'family_symbolic_weather',
                'label' => 'Weather',
                'family' => 'symbolic',
                'reading_mode' => 'weather',
                'schedule_type' => 'influence_window',
                'default_color' => '#2D66A0',
            ]],
            $startUtc,
            $endUtc,
            $durationSeconds
        );
        $weatherEntries = $weatherEntriesByRow['family_symbolic_weather'] ?? [];

        $dayMarkers = $this->buildDayMarkers($startUtc, $endUtc, $durationSeconds);
        $realSegments = $this->buildRealSegments($dayMarkers, $startUtc, $endUtc, $durationSeconds);

        return [
            'window_start_ts' => $startUtc->getTimestamp(),
            'window_end_ts' => $endUtc->getTimestamp(),
            'window_start_utc' => $startUtc->format('Y-m-d H:i'),
            'window_end_utc' => $endUtc->format('Y-m-d H:i'),
            'row_definitions' => $rowDefinitions,
            'entries_by_row' => $entriesByRow,
            'gaps_by_row' => $gapsByRow,
            'astronomy_points' => $astronomyPoints,
            'symbolic_segments' => $symbolicSegments,
            'lunation_segments' => $lunationSegments,
            'text_symbolic_zones' => $textSymbolicZones,
            'weather_entries' => $weatherEntries,
            'day_markers' => $dayMarkers,
            'real_segments' => $realSegments,
        ];
    }

    /**
     * Prepare les options de variantes Weather pour le modal de parse zone.
     *
     * @param SwTextVariant[] $variants
     * @return array<int, array<string, mixed>>
     */
    private function buildWeatherVariantOptions(array $variants): array
    {
        $options = [];
        foreach ($variants as $variant) {
            if (!$variant instanceof SwTextVariant) {
                continue;
            }
            $label = trim($variant->getCardText());
            if ($label === '') {
                $label = trim((string) ($variant->getTitle() ?? ''));
            }
            $options[] = [
                'id' => $variant->getId(),
                'phase_key' => $variant->getPhaseKey(),
                'variant_no' => $variant->getVariantNo(),
                'label' => $this->truncateForVarchar($label, 160),
            ];
        }

        usort(
            $options,
            static fn (array $a, array $b): int =>
                ((int) ($a['phase_key'] ?? 0) <=> (int) ($b['phase_key'] ?? 0))
                ?: (((int) ($a['variant_no'] ?? 0) <=> (int) ($b['variant_no'] ?? 0))
                    ?: ((string) ($a['id'] ?? '') <=> (string) ($b['id'] ?? '')))
        );

        return $options;
    }

    /**
     * @param array<int, array<string, string>> $rowDefinitions
     */
    private function ensureDefaultDisplays(
        EntityManagerInterface $entityManager,
        SwDisplayRepository $displayRepository,
        array $rowDefinitions
    ): void {
        $codes = array_map(static fn (array $row): string => $row['code'], $rowDefinitions);
        $existing = $displayRepository->findByCodesIndexed($codes);
        $changed = false;

        foreach ($rowDefinitions as $row) {
            if (isset($existing[$row['code']])) {
                $display = $existing[$row['code']];
                $expectedFamily = (string) ($row['family'] ?? 'symbolic');
                $expectedMode = (string) ($row['reading_mode'] ?? 'weather');
                $currentFamily = strtolower(trim($display->getFamily()));
                $currentMode = strtolower(trim($display->getReadingMode()));

                if ($currentFamily !== strtolower($expectedFamily)) {
                    $display->setFamily($expectedFamily);
                    $changed = true;
                }
                if ($currentMode !== strtolower($expectedMode)) {
                    $display->setReadingMode($expectedMode);
                    $changed = true;
                }
                continue;
            }

            $display = new SwDisplay();
            $display->setCode($row['code']);
            $display->setFamily($row['family']);
            $display->setReadingMode($row['reading_mode']);
            $display->setLang('fr');
            $display->setComment('Cree automatiquement par l onglet Symbolic Text.');
            $display->setIsActive(true);
            $entityManager->persist($display);
            $changed = true;
        }

        if ($changed) {
            $entityManager->flush();
        }
    }

    /**
     * @param SwSchedule[] $schedules
     * @param array<int, array<string, string>> $rowDefinitions
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildEntriesByRow(
        array $schedules,
        array $rowDefinitions,
        \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc,
        int $durationSeconds
    ): array {
        $startTs = $startUtc->getTimestamp();
        $endTs = $endUtc->getTimestamp();
        $rowMap = [];
        foreach ($rowDefinitions as $row) {
            $rowMap[$row['code']] = [];
        }

        foreach ($schedules as $schedule) {
            if (!$schedule instanceof SwSchedule) {
                continue;
            }

            $display = $schedule->getDisplay();
            $content = $schedule->getContent();
            if (!$display instanceof SwDisplay || !$content instanceof SwContent) {
                continue;
            }

            $rowCode = $this->resolveRowCodeForDisplay($display, $rowDefinitions);
            if ($rowCode === null || !array_key_exists($rowCode, $rowMap)) {
                continue;
            }

            $itemStartTs = max($startTs, $schedule->getStartsAtUtc()->getTimestamp());
            $itemEndTs = min($endTs, $schedule->getEndsAtUtc()->getTimestamp());
            if ($itemEndTs <= $itemStartTs) {
                continue;
            }

            $json = $content->getContentJson();
            $defaultColor = $this->findDefaultColorByCode($rowCode, $rowDefinitions);
            $colorRaw = is_array($json) ? (string) ($json['color'] ?? $json['tone'] ?? '') : '';
            $color = $this->sanitizeColor($colorRaw, $defaultColor);
            $label = is_array($json) ? trim((string) ($json['label'] ?? $json['title'] ?? '')) : '';
            $subtitle = is_array($json) ? trim((string) ($json['subtitle'] ?? '')) : '';
            if ($label === '') {
                $fallbackComment = trim((string) ($schedule->getComment() ?? ($display->getComment() ?? '')));
                $label = $fallbackComment !== '' ? $fallbackComment : $display->getReadingMode();
            }

            $isReady = $display->isActive() && $content->isValidated() && $content->isCurrent() && $schedule->isPublished();

            $rowMap[$rowCode][] = [
                'id' => (string) $schedule->getId(),
                'left' => $this->positionPercent($itemStartTs, $startTs, $durationSeconds),
                'width' => $this->widthPercent($itemStartTs, $itemEndTs, $durationSeconds),
                'start_ts' => $itemStartTs,
                'end_ts' => $itemEndTs,
                'label' => $label,
                'subtitle' => $subtitle,
                'color' => $color,
                'is_ready' => $isReady,
                'start_at_input' => $schedule->getStartsAtUtc()->format('Y-m-d H:i'),
                'end_at_input' => $schedule->getEndsAtUtc()->format('Y-m-d H:i'),
                'priority' => $schedule->getPriority(),
                'schedule_type' => $schedule->getScheduleType(),
                'is_published' => $schedule->isPublished(),
                'status' => $content->getStatus(),
                'is_validated' => $content->isValidated(),
                'is_current' => $content->isCurrent(),
                'schema_version' => $content->getSchemaVersion(),
                'comment' => $schedule->getComment() ?? ($content->getComment() ?? ''),
                'editorial_notes' => $content->getEditorialNotes() ?? '',
                'payload_json' => $schedule->getPayloadJson() !== null
                    ? (string) json_encode($schedule->getPayloadJson(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                    : '',
                'icon' => is_array($json) ? (string) ($json['icon'] ?? '') : '',
                'symbolic_weather' => (is_array($json) && isset($json['symbolic_weather']) && is_array($json['symbolic_weather']))
                    ? $json['symbolic_weather']
                    : null,
                'variant_id' => is_array($json) ? ($json['variant_id'] ?? null) : null,
                'phase_key' => is_array($json) ? ($json['phase_key'] ?? null) : null,
                'variant_no' => is_array($json) ? ($json['variant_no'] ?? null) : null,
                'update_token' => $this->csrfTokenManager->getToken('update_symbolic_text_' . (string) $schedule->getId())->getValue(),
                'delete_token' => $this->csrfTokenManager->getToken('delete_symbolic_text_' . (string) $schedule->getId())->getValue(),
            ];
        }

        foreach ($rowMap as $code => $entries) {
            usort(
                $entries,
                static fn (array $a, array $b): int => ($a['start_ts'] <=> $b['start_ts']) ?: ($a['end_ts'] <=> $b['end_ts'])
            );
            $rowMap[$code] = $entries;
        }

        return $rowMap;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $entriesByRow
     * @param array<int, array<string, string>> $rowDefinitions
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildGapsByRow(
        array $entriesByRow,
        array $rowDefinitions,
        \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc,
        int $durationSeconds
    ): array {
        $startTs = $startUtc->getTimestamp();
        $endTs = $endUtc->getTimestamp();
        $minGapSeconds = max(900, (int) floor($durationSeconds / 160));

        $result = [];
        foreach ($rowDefinitions as $row) {
            $code = $row['code'];
            $entries = $entriesByRow[$code] ?? [];
            $gaps = [];
            $cursor = $startTs;

            foreach ($entries as $entry) {
                $entryStart = (int) ($entry['start_ts'] ?? $startTs);
                $entryEnd = (int) ($entry['end_ts'] ?? $startTs);
                if ($entryStart - $cursor >= $minGapSeconds) {
                    $gaps[] = $this->buildGap($cursor, $entryStart, $startTs, $durationSeconds);
                }
                $cursor = max($cursor, $entryEnd);
            }

            if ($endTs - $cursor >= $minGapSeconds) {
                $gaps[] = $this->buildGap($cursor, $endTs, $startTs, $durationSeconds);
            }

            $result[$code] = $gaps;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGap(int $gapStartTs, int $gapEndTs, int $timelineStartTs, int $durationSeconds): array
    {
        $left = $this->positionPercent($gapStartTs, $timelineStartTs, $durationSeconds);
        $width = $this->widthPercent($gapStartTs, $gapEndTs, $durationSeconds);
        $center = max(0.0, min(100.0, $left + ($width / 2)));

        return [
            'left' => $left,
            'width' => $width,
            'center' => $center,
            'start_at_input' => gmdate('Y-m-d H:i', $gapStartTs),
            'end_at_input' => gmdate('Y-m-d H:i', $gapEndTs),
        ];
    }

    /**
     * @param array<int, array{phase:int, phase_hour:\DateTimeImmutable}> $phaseEvents
     * @return array<int, array{phase:int, phase_hour:\DateTimeImmutable}>
     */
    private function normalizePhaseEvents(array $phaseEvents): array
    {
        $utc = new \DateTimeZone('UTC');
        $normalized = [];

        foreach ($phaseEvents as $event) {
            $phase = (int) ($event['phase'] ?? -1);
            $phaseHour = $event['phase_hour'] ?? null;
            if (!$phaseHour instanceof \DateTimeImmutable || $phase < 0 || $phase > 7) {
                continue;
            }

            $phaseHourUtc = $phaseHour->setTimezone($utc);
            $normalized[] = [
                'phase' => $phase,
                'phase_hour' => $phaseHourUtc,
            ];
        }

        usort(
            $normalized,
            static fn (array $a, array $b): int =>
                ($a['phase_hour']->getTimestamp() <=> $b['phase_hour']->getTimestamp()) ?: ($a['phase'] <=> $b['phase'])
        );

        $deduped = [];
        $seenTimestamps = [];
        foreach ($normalized as $event) {
            $ts = $event['phase_hour']->getTimestamp();
            if (isset($seenTimestamps[$ts])) {
                continue;
            }
            $seenTimestamps[$ts] = true;
            $deduped[] = $event;
        }

        return $deduped;
    }

    /**
     * @param array<int, array{phase:int, phase_hour:\DateTimeImmutable}> $phaseEvents
     * @return array<int, array<string, mixed>>
     */
    private function buildAstronomyPoints(
        array $phaseEvents,
        \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc,
        int $durationSeconds
    ): array {
        $startTs = $startUtc->getTimestamp();
        $endTs = $endUtc->getTimestamp();
        $points = [];

        foreach ($phaseEvents as $event) {
            $ts = $event['phase_hour']->getTimestamp();
            if ($ts < $startTs || $ts > $endTs) {
                continue;
            }
            $meta = $this->phaseMeta((int) $event['phase']);
            $points[] = [
                'ts' => $ts,
                'left' => $this->positionPercent($ts, $startTs, $durationSeconds),
                'label' => $meta['label'],
                'phase' => (int) $event['phase'],
                'time_label' => $event['phase_hour']->format('d/m/Y H:i') . ' UTC',
            ];
        }

        return $points;
    }

    /**
     * @param array<int, array{phase:int, phase_hour:\DateTimeImmutable}> $phaseEvents
     * @return array<int, array<string, mixed>>
     */
    private function buildInfluenceOrbSegmentsFromOrbWindow(
        OrbWindowRepository $orbWindowRepository,
        \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc,
        int $durationSeconds
    ): array {
        $sourceZones = $orbWindowRepository->findTimelineZonesByFamilyAndMethod(
            OrbWindowParseService::FAMILY_INFLUENCE_ORB,
            OrbWindowParseService::METHOD_INFLUENCE_ORB,
            $startUtc,
            $endUtc
        );

        if ($sourceZones === []) {
            return [];
        }

        $windowStartTs = $startUtc->getTimestamp();
        $windowEndTs = $endUtc->getTimestamp();
        $segments = [];

        foreach ($sourceZones as $zone) {
            $fullStartTs = $zone['starts_at_utc']->getTimestamp();
            $fullEndTs = $zone['ends_at_utc']->getTimestamp();
            if ($fullEndTs <= $fullStartTs) {
                continue;
            }
            if ($fullEndTs <= $windowStartTs || $fullStartTs >= $windowEndTs) {
                continue;
            }

            $visibleStartTs = max($fullStartTs, $windowStartTs);
            $visibleEndTs = min($fullEndTs, $windowEndTs);
            if ($visibleEndTs <= $visibleStartTs) {
                continue;
            }

            $phaseKey = (string) ($zone['phase_key'] ?? '');
            $meta = $this->influenceOrbMetaFromPhaseKey($phaseKey);

            $segments[] = [
                'start_ts' => $visibleStartTs,
                'end_ts' => $visibleEndTs,
                'full_start_ts' => $fullStartTs,
                'full_end_ts' => $fullEndTs,
                'left' => $this->positionPercent($visibleStartTs, $windowStartTs, $durationSeconds),
                'width' => $this->widthPercent($visibleStartTs, $visibleEndTs, $durationSeconds),
                'label' => $meta['label'],
                'color' => $meta['color'],
                'phase_key' => $phaseKey,
            ];
        }

        usort(
            $segments,
            static fn (array $a, array $b): int =>
                ($a['full_start_ts'] <=> $b['full_start_ts'])
                ?: ($a['full_end_ts'] <=> $b['full_end_ts'])
        );

        return $segments;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildDayMarkers(
        \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc,
        int $durationSeconds
    ): array {
        $startTs = $startUtc->getTimestamp();
        $endTs = $endUtc->getTimestamp();
        $markerTimestamps = [$startTs];

        $cursor = $startUtc->setTime(0, 0, 0);
        if ($cursor <= $startUtc) {
            $cursor = $cursor->modify('+1 day');
        }

        while ($cursor < $endUtc) {
            $markerTimestamps[] = $cursor->getTimestamp();
            $cursor = $cursor->modify('+1 day');
        }

        if (end($markerTimestamps) !== $endTs) {
            $markerTimestamps[] = $endTs;
        }

        $markers = [];
        foreach ($markerTimestamps as $timestamp) {
            $markers[] = [
                'left' => $this->positionPercent((int) $timestamp, $startTs, $durationSeconds),
                'timestamp' => (int) $timestamp,
            ];
        }

        return $markers;
    }

    /**
     * @param array<int, array<string, mixed>> $markers
     * @return array<int, array<string, mixed>>
     */
    private function buildRealSegments(
        array $markers,
        \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc,
        int $durationSeconds
    ): array {
        $segments = [];
        $startTs = $startUtc->getTimestamp();
        $endTs = $endUtc->getTimestamp();

        if (count($markers) < 2) {
            return $segments;
        }

        for ($i = 0, $max = count($markers) - 1; $i < $max; $i++) {
            $segmentStartTs = (int) $markers[$i]['timestamp'];
            $segmentEndTs = (int) $markers[$i + 1]['timestamp'];
            if ($segmentEndTs <= $segmentStartTs) {
                continue;
            }

            $labelDate = (new \DateTimeImmutable('@' . $segmentStartTs))
                ->setTimezone(new \DateTimeZone('UTC'));
            $segments[] = [
                'start_ts' => $segmentStartTs,
                'end_ts' => min($segmentEndTs, $endTs),
                'left' => $this->positionPercent($segmentStartTs, $startTs, $durationSeconds),
                'width' => $this->widthPercent($segmentStartTs, min($segmentEndTs, $endTs), $durationSeconds),
                'label' => $this->formatDayLabel($labelDate),
            ];
        }

        return $segments;
    }

    /**
     * @return array{label:string,color:string}
     */
    private function phaseMeta(int $phase): array
    {
        return match ($phase) {
            0 => ['label' => 'Nouvelle lune', 'color' => '#3a4669'],
            1 => ['label' => 'Premier croissant', 'color' => '#2f5f96'],
            2 => ['label' => 'Premier quartier', 'color' => '#287b95'],
            3 => ['label' => 'Gibbeuse croissante', 'color' => '#2b8d67'],
            4 => ['label' => 'Pleine lune', 'color' => '#5f5bc4'],
            5 => ['label' => 'Gibbeuse decroissante', 'color' => '#6f4faf'],
            6 => ['label' => 'Dernier quartier', 'color' => '#9f3f74'],
            7 => ['label' => 'Dernier croissant', 'color' => '#84455d'],
            default => ['label' => 'Phase ' . $phase, 'color' => '#325a80'],
        };
    }

    /**
     * @param array<int, array{phase:int, phase_hour:\DateTimeImmutable}> $phaseEvents
     * @return array<int, array<string, mixed>>
     */
    private function buildLunationYearSegments(
        array $phaseEvents,
        \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc,
        int $durationSeconds
    ): array {
        $newMoons = array_values(
            array_filter(
                $phaseEvents,
                static fn (array $event): bool => ((int) ($event['phase'] ?? -1)) === 0
            )
        );

        if (count($newMoons) < 2) {
            return [];
        }

        $startTs = $startUtc->getTimestamp();
        $endTs = $endUtc->getTimestamp();
        $palette = ['#d0801d', '#1f8a6a', '#7d56d8', '#c13e69', '#2b78c2', '#8e7e26', '#b35f2f', '#3f8f8f'];
        $segments = [];
        $countPerYear = [];

        for ($i = 0, $max = count($newMoons) - 1; $i < $max; $i++) {
            $fullStartTs = $newMoons[$i]['phase_hour']->getTimestamp();
            $fullEndTs = $newMoons[$i + 1]['phase_hour']->getTimestamp();
            if ($fullEndTs <= $fullStartTs) {
                continue;
            }

            $year = (int) gmdate('Y', $fullStartTs);
            if (!isset($countPerYear[$year])) {
                $countPerYear[$year] = 0;
            }
            $countPerYear[$year]++;
            $lunationNo = $countPerYear[$year];

            if ($fullEndTs <= $startTs || $fullStartTs >= $endTs) {
                continue;
            }

            $visibleStartTs = max($fullStartTs, $startTs);
            $visibleEndTs = min($fullEndTs, $endTs);

            $segments[] = [
                'start_ts' => $visibleStartTs,
                'end_ts' => $visibleEndTs,
                'full_start_ts' => $fullStartTs,
                'full_end_ts' => $fullEndTs,
                'left' => $this->positionPercent($visibleStartTs, $startTs, $durationSeconds),
                'width' => $this->widthPercent($visibleStartTs, $visibleEndTs, $durationSeconds),
                'color' => $palette[($lunationNo - 1) % count($palette)],
                'label' => sprintf('Lune %d', $lunationNo),
                'year' => $year,
                'lunation_no' => $lunationNo,
            ];
        }

        return $segments;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildTextSymbolicZonesFromOrbWindow(
        OrbWindowRepository $orbWindowRepository,
        \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc,
        int $durationSeconds
    ): array {
        $sourceZones = $orbWindowRepository->findTimelineZonesByFamilyAndMethod(
            OrbWindowParseService::FAMILY_MULTI_SECTION_PHASE,
            OrbWindowParseService::METHOD_MULTI_SECTION_PHASE,
            $startUtc,
            $endUtc
        );

        if ($sourceZones === []) {
            return [];
        }

        $windowStartTs = $startUtc->getTimestamp();
        $windowEndTs = $endUtc->getTimestamp();
        $zones = [];

        foreach ($sourceZones as $zone) {
            $fullStartTs = $zone['starts_at_utc']->getTimestamp();
            $fullEndTs = $zone['ends_at_utc']->getTimestamp();
            if ($fullEndTs <= $fullStartTs) {
                continue;
            }
            if ($fullEndTs <= $windowStartTs || $fullStartTs >= $windowEndTs) {
                continue;
            }

            $visibleStartTs = max($fullStartTs, $windowStartTs);
            $visibleEndTs = min($fullEndTs, $windowEndTs);
            if ($visibleEndTs <= $visibleStartTs) {
                continue;
            }

            $phaseKey = (string) $zone['phase_key'];
            $zones[] = [
                'zone_key' => sprintf('%s:%d:%d:%d', $phaseKey, $fullStartTs, $fullEndTs, (int) $zone['id']),
                'type' => 'orb_window',
                'tone' => $this->textZoneToneFromPhaseKey($phaseKey),
                'label' => $phaseKey,
                'phase_key' => $phaseKey,
                'start_ts' => $visibleStartTs,
                'end_ts' => $visibleEndTs,
                'full_start_ts' => $fullStartTs,
                'full_end_ts' => $fullEndTs,
                'left' => $this->positionPercent($visibleStartTs, $windowStartTs, $durationSeconds),
                'width' => $this->widthPercent($visibleStartTs, $visibleEndTs, $durationSeconds),
                'event_ts' => $zone['event_at_utc']->getTimestamp(),
                'sequence_no' => $zone['sequence_no'],
                'lunation_key' => $zone['lunation_key'],
            ];
        }

        usort(
            $zones,
            static fn (array $a, array $b): int =>
                ($a['full_start_ts'] <=> $b['full_start_ts'])
                ?: (($a['full_end_ts'] <=> $b['full_end_ts'])
                    ?: ((int) ($a['sequence_no'] ?? 0) <=> (int) ($b['sequence_no'] ?? 0)))
        );

        return $zones;
    }

    private function textZoneToneFromPhaseKey(string $phaseKey): string
    {
        if (str_starts_with($phaseKey, 'influence_')) {
            return 'shade-3';
        }
        if (str_contains($phaseKey, 'core_')) {
            return 'shade-2';
        }

        return 'shade-1';
    }

    /**
     * @return array{label:string,color:string}
     */
    private function influenceOrbMetaFromPhaseKey(string $phaseKey): array
    {
        $key = strtolower(trim($phaseKey));

        return match ($key) {
            'new_moon' => ['label' => 'Nouvelle lune', 'color' => '#6A7078'],
            'waxing_crescent' => ['label' => 'Premier croissant', 'color' => '#727983'],
            'first_quarter' => ['label' => 'Premier quartier', 'color' => '#7A818A'],
            'waxing_gibbous' => ['label' => 'Gibbeuse croissante', 'color' => '#848A92'],
            'full_moon' => ['label' => 'Pleine lune', 'color' => '#8D929A'],
            'waning_gibbous' => ['label' => 'Gibbeuse decroissante', 'color' => '#777E88'],
            'last_quarter' => ['label' => 'Dernier quartier', 'color' => '#6F7680'],
            'waning_crescent' => ['label' => 'Dernier croissant', 'color' => '#666E78'],
            default => ['label' => $phaseKey !== '' ? $phaseKey : 'Influence', 'color' => '#737A84'],
        };
    }

    /**
     * @param array<int, array<string, string>> $rowDefinitions
     * @return array<string, string>|null
     */
    private function findRowDefinitionByCode(string $code, array $rowDefinitions): ?array
    {
        $needle = strtolower(trim($code));
        foreach ($rowDefinitions as $row) {
            $rowCode = strtolower(trim((string) ($row['code'] ?? '')));
            if ($rowCode === $needle) {
                return $row;
            }
        }

        return null;
    }

    private function buildUniqueDisplayCode(string $rowCode, SwDisplayRepository $displayRepository): string
    {
        $prefix = preg_replace('/[^a-z0-9_]+/', '_', strtolower(trim($rowCode)));
        $prefix = trim((string) $prefix, '_');
        if ($prefix === '') {
            $prefix = 'symbolic';
        }

        for ($i = 0; $i < 8; $i++) {
            try {
                $suffix = substr(bin2hex(random_bytes(4)), 0, 8);
            } catch (\Throwable) {
                $suffix = substr(md5(uniqid((string) mt_rand(), true)), 0, 8);
            }
            $candidate = sprintf('%s_%s_%s', $prefix, gmdate('YmdHis'), $suffix);
            $candidate = substr($candidate, 0, 150);
            if (!$displayRepository->findOneBy(['code' => $candidate]) instanceof SwDisplay) {
                return $candidate;
            }
        }

        try {
            $tail = bin2hex(random_bytes(12));
        } catch (\Throwable) {
            $tail = md5(uniqid((string) mt_rand(), true));
        }

        return substr($prefix . '_' . $tail, 0, 150);
    }

    /**
     * @param array<string, mixed> $json
     * @param string[] $keys
     */
    private function resolveJsonBool(array $json, array $keys, bool $fallback): bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $json)) {
                continue;
            }
            $value = $json[$key];
            if (is_bool($value)) {
                return $value;
            }
            if (is_int($value) || is_float($value)) {
                return (int) $value !== 0;
            }
            if (is_string($value)) {
                $normalized = strtolower(trim($value));
                if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                    return true;
                }
                if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                    return false;
                }
            }
        }

        return $fallback;
    }

    private function sanitizeLang(string $lang): string
    {
        $value = strtolower(trim($lang));
        if ($value === '') {
            return 'fr';
        }

        return substr($value, 0, 10);
    }

    private function flashFormErrors(\Symfony\Component\Form\FormInterface $form): void
    {
        foreach ($form->getErrors(true, true) as $error) {
            $message = trim($error->getMessage());
            if ($message !== '') {
                $this->addFlash('error', $message);
            }
        }
    }

    private function sanitizeSpan(string $span): string
    {
        $span = trim($span);
        $options = $this->spanOptions();
        return array_key_exists($span, $options) ? $span : '2j';
    }

    private function parseUtcInput(string $raw): ?\DateTimeImmutable
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        $utc = new \DateTimeZone('UTC');
        $formats = ['Y-m-d H:i', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d\TH:i:s'];
        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $value, $utc);
            if ($dt instanceof \DateTimeImmutable) {
                return $dt->setTimezone($utc);
            }
        }

        try {
            return (new \DateTimeImmutable($value, $utc))->setTimezone($utc);
        } catch (\Throwable) {
            return null;
        }
    }

    private function requestBool(Request $request, string $field): bool
    {
        return in_array((string) $request->request->get($field, ''), ['1', 'true', 'on', 'yes'], true);
    }

    private function sanitizeStatus(string $status): string
    {
        $value = strtolower(trim($status));
        $allowed = ['draft', 'review', 'validated', 'archived'];
        return in_array($value, $allowed, true) ? $value : 'validated';
    }

    private function sanitizeScheduleType(string $scheduleType): string
    {
        $value = strtolower(trim($scheduleType));
        $allowed = ['phase_window', 'influence_window'];
        return in_array($value, $allowed, true) ? $value : 'influence_window';
    }

    private function sanitizeSchemaVersion(string $schemaVersion): string
    {
        $value = trim($schemaVersion);
        return $value !== '' ? substr($value, 0, 20) : '1.0';
    }

    private function sanitizeColor(string $color, string $fallback): string
    {
        $value = trim($color);
        if ($value === '') {
            return $fallback;
        }
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1) {
            return strtoupper($value);
        }
        return $fallback;
    }

    /**
     * Normalise une date UTC vers le debut de semaine ISO (lundi 00:00:00 UTC).
     * Pourquoi: le parse Weather doit toujours etre cale sur des semaines reelles lundi->dimanche.
     */
    private function normalizeToIsoWeekStart(\DateTimeImmutable $value): \DateTimeImmutable
    {
        $utc = new \DateTimeZone('UTC');
        $utcValue = $value->setTimezone($utc);
        $weekDay = (int) $utcValue->format('N'); // 1=lundi ... 7=dimanche

        return $utcValue
            ->setTime(0, 0, 0)
            ->modify(sprintf('-%d days', $weekDay - 1));
    }

    /**
     * Selectionne une zone unique de parse Weather (bouton + sur Family Symbolic).
     * Pourquoi: parser strictement la zone cliquee avec la variante choisie dans le modal.
     *
     * @param array<int, array{
     *   id:int,
     *   phase_key:string,
     *   starts_at_utc:\DateTimeImmutable,
     *   ends_at_utc:\DateTimeImmutable,
     *   event_at_utc:\DateTimeImmutable,
     *   sequence_no:int|null,
     *   lunation_key:string|null
     * }> $sourceZones
     * @return array<int, array<string, mixed>>
     */
    private function selectSingleWeatherParseZone(
        array $sourceZones,
        \DateTimeImmutable $parseStartUtc,
        \DateTimeImmutable $parseEndUtc,
        \DateTimeImmutable $todayStartUtc,
        ?int $forcedPhaseIndex,
        ?int $forcedVariantNo
    ): array {
        $parseStartTs = $parseStartUtc->getTimestamp();
        $parseEndTs = $parseEndUtc->getTimestamp();
        $todayStartTs = $todayStartUtc->getTimestamp();

        if ($parseEndTs <= $parseStartTs || $parseEndTs <= $todayStartTs) {
            return [];
        }

        $matched = null;
        foreach ($sourceZones as $zone) {
            $zoneStartTs = $zone['starts_at_utc']->getTimestamp();
            $zoneEndTs = $zone['ends_at_utc']->getTimestamp();
            if ($zoneStartTs === $parseStartTs && $zoneEndTs === $parseEndTs) {
                $matched = $zone;
                break;
            }
        }

        $phaseIndex = $forcedPhaseIndex;
        $phaseKey = '';
        $zoneId = 0;
        $lunationKey = '';
        if ($matched !== null) {
            $phaseKey = (string) ($matched['phase_key'] ?? '');
            $zoneId = (int) ($matched['id'] ?? 0);
            $lunationKey = isset($matched['lunation_key']) ? (string) $matched['lunation_key'] : '';
            if ($phaseIndex === null) {
                $phaseIndex = $this->phaseIndexFromInfluencePhaseKey($phaseKey);
            }
        }

        if ($phaseIndex === null || $phaseIndex < 0 || $phaseIndex > 7) {
            return [];
        }

        $writeStartTs = max($parseStartTs, $todayStartTs);
        $writeEndTs = $parseEndTs;
        if ($writeEndTs <= $writeStartTs) {
            return [];
        }

        return [[
            'id' => $zoneId,
            'phase_key' => $phaseKey,
            'phase_index' => $phaseIndex,
            'lunation_key' => $lunationKey,
            'full_start_ts' => $parseStartTs,
            'full_end_ts' => $parseEndTs,
            'write_start_ts' => $writeStartTs,
            'write_end_ts' => $writeEndTs,
            'forced_variant_no' => $forcedVariantNo,
        ]];
    }

    /**
     * Construit le dernier variant_no connu par phase a partir de l historique Weather.
     *
     * @param SwSchedule[] $schedules
     * @return array<int, int>
     */
    private function buildLastVariantNoByPhaseFromSchedules(array $schedules): array
    {
        $state = [];
        foreach ($schedules as $schedule) {
            if (!$schedule instanceof SwSchedule) {
                continue;
            }
            $meta = $this->extractWeatherVariantMetaFromSchedule($schedule);
            if ($meta === null) {
                continue;
            }
            $phase = (int) $meta['phase_key'];
            $variantNo = (int) $meta['variant_no'];
            if ($phase < 0 || $phase > 7 || $variantNo < 1) {
                continue;
            }
            if (!array_key_exists($phase, $state)) {
                $state[$phase] = $variantNo;
            }
        }

        return $state;
    }

    /**
     * @param SwTextVariant[] $variants
     * @return SwTextVariant[]
     */
    private function sortVariantsByVariantNo(array $variants): array
    {
        usort(
            $variants,
            static fn (SwTextVariant $a, SwTextVariant $b): int =>
                ($a->getVariantNo() <=> $b->getVariantNo()) ?: ((string) ($a->getId() ?? '') <=> (string) ($b->getId() ?? ''))
        );

        return $variants;
    }

    /**
     * @param SwTextVariant[] $phaseVariants
     */
    private function pickNextWeatherVariant(array $phaseVariants, ?int $lastVariantNo, ?int $forcedVariantNo = null): ?SwTextVariant
    {
        if ($phaseVariants === []) {
            return null;
        }

        if ($forcedVariantNo !== null) {
            foreach ($phaseVariants as $candidate) {
                if ($candidate->getVariantNo() === $forcedVariantNo) {
                    return $candidate;
                }
            }

            return null;
        }

        if ($lastVariantNo === null || $lastVariantNo < 1) {
            return $phaseVariants[0];
        }

        $lastIndex = null;
        foreach ($phaseVariants as $index => $candidate) {
            if ($candidate->getVariantNo() === $lastVariantNo) {
                $lastIndex = $index;
            }
        }
        if ($lastIndex === null) {
            return $phaseVariants[0];
        }

        $nextIndex = ($lastIndex + 1) % count($phaseVariants);

        return $phaseVariants[$nextIndex] ?? $phaseVariants[0];
    }

    /**
     * Extrait (phase_key, variant_no) depuis les metadonnees d une entree Weather existante.
     * Pourquoi: garantir la rotation variant_no par rapport a l historique deja publie.
     *
     * @return array{phase_key:int,variant_no:int}|null
     */
    private function extractWeatherVariantMetaFromSchedule(SwSchedule $schedule): ?array
    {
        $content = $schedule->getContent();
        if (!$content instanceof SwContent) {
            return null;
        }

        $contentJson = $content->getContentJson();
        if (is_array($contentJson)) {
            $symbolicWeather = $contentJson['symbolic_weather'] ?? null;
            if (is_array($symbolicWeather)) {
                $phase = $this->phaseIndexFromInfluencePhaseKey((string) ($symbolicWeather['phase_key'] ?? ''));
                $variantNoRaw = (string) ($symbolicWeather['variant_no'] ?? '');
                if ($phase !== null && ctype_digit($variantNoRaw) && (int) $variantNoRaw > 0) {
                    return [
                        'phase_key' => $phase,
                        'variant_no' => (int) $variantNoRaw,
                    ];
                }
            }

            $phaseLegacy = $this->phaseIndexFromInfluencePhaseKey((string) ($contentJson['phase_key'] ?? ''));
            $variantLegacyRaw = (string) ($contentJson['variant_no'] ?? '');
            if ($phaseLegacy !== null && ctype_digit($variantLegacyRaw) && (int) $variantLegacyRaw > 0) {
                return [
                    'phase_key' => $phaseLegacy,
                    'variant_no' => (int) $variantLegacyRaw,
                ];
            }

            $subtitle = (string) ($contentJson['subtitle'] ?? '');
            if ($subtitle !== '') {
                if (preg_match('/phase_key\s*=\s*(\d+)\s*,\s*variant_no\s*=\s*(\d+)/i', $subtitle, $m) === 1) {
                    $phaseLegacy2 = (int) ($m[1] ?? -1);
                    $variantLegacy2 = (int) ($m[2] ?? 0);
                    if ($phaseLegacy2 >= 0 && $phaseLegacy2 <= 7 && $variantLegacy2 > 0) {
                        return [
                            'phase_key' => $phaseLegacy2,
                            'variant_no' => $variantLegacy2,
                        ];
                    }
                }
            }
        }

        $payload = $schedule->getPayloadJson();
        if (is_array($payload)) {
            $phaseFromPayload = $this->phaseIndexFromInfluencePhaseKey((string) ($payload['phase_key'] ?? ''));
            $variantFromPayloadRaw = (string) ($payload['variant_no'] ?? '');
            if ($phaseFromPayload !== null && ctype_digit($variantFromPayloadRaw) && (int) $variantFromPayloadRaw > 0) {
                return [
                    'phase_key' => $phaseFromPayload,
                    'variant_no' => (int) $variantFromPayloadRaw,
                ];
            }
        }

        return null;
    }

    /**
     * Construit la liste des zones de la semaine qui peuvent etre parsees.
     * Pourquoi: inclure le chevauchement au debut, exclure celui de fin, et interdire toute ecriture dans le passe.
     *
     * @param array<int, array{
     *   id:int,
     *   phase_key:string,
     *   starts_at_utc:\DateTimeImmutable,
     *   ends_at_utc:\DateTimeImmutable,
     *   event_at_utc:\DateTimeImmutable,
     *   sequence_no:int|null,
     *   lunation_key:string|null
     * }> $sourceZones
     * @return array<int, array<string, mixed>>
     */
    private function selectWeatherParseZones(
        array $sourceZones,
        \DateTimeImmutable $parseStartUtc,
        \DateTimeImmutable $parseEndUtc,
        \DateTimeImmutable $todayStartUtc
    ): array {
        $parseStartTs = $parseStartUtc->getTimestamp();
        $parseEndTs = $parseEndUtc->getTimestamp();
        $todayStartTs = $todayStartUtc->getTimestamp();
        $zones = [];

        foreach ($sourceZones as $zone) {
            $fullStartTs = $zone['starts_at_utc']->getTimestamp();
            $fullEndTs = $zone['ends_at_utc']->getTimestamp();
            if ($fullEndTs <= $fullStartTs) {
                continue;
            }
            if ($fullEndTs <= $parseStartTs || $fullStartTs >= $parseEndTs) {
                continue;
            }

            // Regle metier: la zone qui depasse en fin de semaine est laissee pour la semaine suivante.
            if ($fullEndTs > $parseEndTs) {
                continue;
            }

            // Regle metier: aucune ecriture avant le debut du jour UTC courant.
            if ($fullEndTs <= $todayStartTs) {
                continue;
            }

            $phaseIndex = $this->phaseIndexFromInfluencePhaseKey((string) ($zone['phase_key'] ?? ''));
            $writeStartTs = max($fullStartTs, $todayStartTs);
            $writeEndTs = $fullEndTs;
            if ($writeEndTs <= $writeStartTs) {
                continue;
            }

            $zones[] = [
                'id' => (int) ($zone['id'] ?? 0),
                'phase_key' => (string) ($zone['phase_key'] ?? ''),
                'phase_index' => $phaseIndex,
                'lunation_key' => isset($zone['lunation_key']) ? (string) $zone['lunation_key'] : '',
                'full_start_ts' => $fullStartTs,
                'full_end_ts' => $fullEndTs,
                'write_start_ts' => $writeStartTs,
                'write_end_ts' => $writeEndTs,
            ];
        }

        usort(
            $zones,
            static fn (array $a, array $b): int =>
                ($a['full_start_ts'] <=> $b['full_start_ts'])
                ?: (($a['full_end_ts'] <=> $b['full_end_ts'])
                    ?: ((int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0)))
        );

        return $zones;
    }

    private function phaseIndexFromInfluencePhaseKey(string $phaseKey): ?int
    {
        $normalized = strtolower(trim($phaseKey));
        if ($normalized === '') {
            return null;
        }

        if (ctype_digit($normalized)) {
            $asInt = (int) $normalized;
            return ($asInt >= 0 && $asInt <= 7) ? $asInt : null;
        }
        if (str_starts_with($normalized, 'phase_')) {
            $tail = substr($normalized, 6);
            if ($tail !== '' && ctype_digit($tail)) {
                $asInt = (int) $tail;
                return ($asInt >= 0 && $asInt <= 7) ? $asInt : null;
            }
        }

        return match ($normalized) {
            'new_moon' => 0,
            'waxing_crescent' => 1,
            'first_quarter' => 2,
            'waxing_gibbous' => 3,
            'full_moon' => 4,
            'waning_gibbous' => 5,
            'last_quarter' => 6,
            'waning_crescent' => 7,
            default => null,
        };
    }

    /**
     * @param SwTextVariant[] $variants
     * @return array<int, SwTextVariant[]>
     */
    private function groupVariantsByPhase(array $variants): array
    {
        $grouped = [];
        foreach ($variants as $variant) {
            if (!$variant instanceof SwTextVariant) {
                continue;
            }
            $phase = $variant->getPhaseKey();
            if ($phase < 0 || $phase > 7) {
                continue;
            }
            if (!isset($grouped[$phase])) {
                $grouped[$phase] = [];
            }
            $grouped[$phase][] = $variant;
        }

        return $grouped;
    }

    private function buildAutoDisplayCode(string $prefix): string
    {
        return str_replace('.', '_', uniqid($prefix . '_', true));
    }

    private function timeRangeOverlaps(int $startA, int $endA, int $startB, int $endB): bool
    {
        return $startA < $endB && $endA > $startB;
    }

    private function truncateForVarchar(string $value, int $maxLength): string
    {
        $trimmed = trim($value);
        if ($trimmed === '' || $maxLength <= 0) {
            return '';
        }
        if (mb_strlen($trimmed) <= $maxLength) {
            return $trimmed;
        }
        if ($maxLength <= 1) {
            return mb_substr($trimmed, 0, $maxLength);
        }
        if ($maxLength <= 3) {
            return mb_substr($trimmed, 0, $maxLength);
        }

        return mb_substr($trimmed, 0, $maxLength - 3) . '...';
    }

    private function positionPercent(int $timestamp, int $startTs, int $durationSeconds): float
    {
        $ratio = ($timestamp - $startTs) / max(1, $durationSeconds);
        return max(0.0, min(100.0, round($ratio * 100, 4)));
    }

    private function widthPercent(int $startTs, int $endTs, int $durationSeconds): float
    {
        $width = (($endTs - $startTs) / max(1, $durationSeconds)) * 100;
        return max(0.65, round($width, 4));
    }

    private function nullIfEmpty(string $value): ?string
    {
        $trimmed = trim($value);
        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * @param array<int, array<string, string>> $rowDefinitions
     */
    private function resolveRowCodeForDisplay(SwDisplay $display, array $rowDefinitions): ?string
    {
        $displayFamily = strtolower(trim($display->getFamily()));
        $displayMode = strtolower(trim($display->getReadingMode()));

        foreach ($rowDefinitions as $row) {
            $family = strtolower(trim((string) ($row['family'] ?? '')));
            $mode = strtolower(trim((string) ($row['reading_mode'] ?? '')));
            if ($family === $displayFamily && $mode === $displayMode) {
                return (string) ($row['code'] ?? '');
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, string>> $rowDefinitions
     */
    private function findDefaultColorByCode(string $code, array $rowDefinitions): string
    {
        foreach ($rowDefinitions as $row) {
            if (($row['code'] ?? '') === $code) {
                return (string) ($row['default_color'] ?? '#315a7b');
            }
        }
        return '#315a7b';
    }

    private function formatDayLabel(\DateTimeImmutable $date): string
    {
        $days = [
            'Monday' => 'Lundi',
            'Tuesday' => 'Mardi',
            'Wednesday' => 'Mercredi',
            'Thursday' => 'Jeudi',
            'Friday' => 'Vendredi',
            'Saturday' => 'Samedi',
            'Sunday' => 'Dimanche',
        ];
        $months = [
            1 => 'Janvier',
            2 => 'Fevrier',
            3 => 'Mars',
            4 => 'Avril',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juillet',
            8 => 'Aout',
            9 => 'Septembre',
            10 => 'Octobre',
            11 => 'Novembre',
            12 => 'Decembre',
        ];

        $dayName = $days[$date->format('l')] ?? $date->format('l');
        $month = $months[(int) $date->format('n')] ?? $date->format('F');

        return sprintf('%s %d %s', $dayName, (int) $date->format('j'), $month);
    }

    private function redirectToTimeline(Request $request): RedirectResponse
    {
        $params = [
            'span' => $this->sanitizeSpan((string) $request->request->get('span', '2j')),
        ];
        $endInput = trim((string) $request->request->get('end_utc', ''));
        if ($endInput !== '') {
            $params['end_utc'] = $endInput;
        }

        return $this->redirectToRoute('admin_symbolic_text', $params);
    }

    /**
     * @param-out ?string $error
     */
    private function parseOptionalJson(string $json, ?string &$error): ?array
    {
        $error = null;
        $raw = trim($json);
        if ($raw === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $error = 'payload_json invalide: JSON non lisible.';
            return null;
        }

        if (!is_array($decoded)) {
            $error = 'payload_json invalide: objet/tableau JSON attendu.';
            return null;
        }

        return $decoded;
    }
}
