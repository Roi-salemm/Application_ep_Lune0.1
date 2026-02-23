<?php

namespace App\AI\Pipeline;

use App\AI\Client\LocalLlmClientInterface;

final class IntentParser
{
    public function __construct(
        private readonly LocalLlmClientInterface $llm,
        private readonly KnowledgeRepository $knowledgeRepo,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function parse(string $userPrompt, string $language = 'fr'): array
    {
        $allowedKeys = $this->knowledgeRepo->listAllowedKeys($language);
        $allowedKeysJson = json_encode($allowedKeys, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($allowedKeysJson === false) {
            $allowedKeysJson = '[]';
        }
$intentLines = '';
        foreach (IntentNormalizer::ALLOWED_INTENTS as $intent) {
            $intentLines .= '- ' . $intent . "\n";
        }

        $parserRules = <<<TXT
Tu es un routeur (intent router) et normalisateur. Ta tache unique est de produire un JSON STRICT conforme au schema ci-dessous, sans aucun texte autour, sans Markdown, sans commentaires, sans explication, sans backticks.

Objectif:
- Deduire l'intention (intent) de l'utilisateur pour choisir un mode de reponse.
- Extraire les besoins de connaissance (knowledge_keys) et lexique (lexicon_keys) si pertinent.
- Definir des contraintes simples (constraints) adaptees.
- Toujours respecter la liste fermee d'intents autorises.

IMPORTANT:
- Tu dois repondre avec UN SEUL objet JSON valide.
- Aucune autre sortie n'est autorisee (pas de phrase, pas de titre, pas de code fence).
- Toutes les cles exigees doivent etre presentes meme si vides.
- Ne jamais inventer des cles hors schema.

INTENTS AUTORISES (enum):
{$intentLines}

Regles de choix de intent:
- informative: question de connaissance, definition, explication, "c'est quoi", "pourquoi".
- interpretation: symbolisme, lecture, climat, "energie du moment", astrologie, ressenti/lecture.
- instruction: "comment faire", etapes, tutoriel, procedure, implementation, marche a suivre.
- classification: demande de categoriser/diriger/identifier un type de demande (rare, plutot interne).
- creative: demande d'ecriture creative (histoire, poeme, style litteraire).
- refusal: demande interdite, dangereuse, illegale, ou explicitement a refuser.
- other: si ambigu, melange trop large, ou si tu n'es pas sur.

Confidence:
- intent_confidence est un nombre entre 0.0 et 1.0.
- Mets 0.85+ si tres evident.
- Mets 0.55-0.84 si probable.
- Mets <0.55 si ambigu -> dans ce cas privilegie intent="other" sauf si un intent ressort nettement.

Langue:
- language doit etre: "fr" ou "en" (choisir selon la langue du message utilisateur).
- Si doute, "fr".

Ton:
- tone doit etre: "neutral" ou "polite".
- Par defaut "polite".

Contraintes:
- constraints.max_chars est un entier (ex: 200, 400, 800).
- Pour une reponse courte demandee explicitement: 200.
- Sinon: 400 (par defaut).
- Si l'utilisateur demande une reponse detaillee: 800.

Keys:
- needs.knowledge_keys: liste de cles metier (strings) si tu en identifies.
- needs.lexicon_keys: liste de cles lexique si tu en identifies.
- Si aucune: listes vides.

- Tu ne dois JAMAIS inventer de knowledge_keys.
- needs.knowledge_keys et needs.lexicon_keys ne peuvent contenir QUE des valeurs présentes dans allowed_knowledge_keys fourni plus bas.
- Si aucune clé ne correspond clairement, retourne des listes vides.

Securite:
- Ne produis pas la reponse finale a l'utilisateur.
- Ne fais pas de morale, ne donne pas de conseils.
- Tu ne fais que router et structurer.

SCHEMA JSON STRICT A RESPECTER:
{
  "intent": "informative|interpretation|instruction|classification|creative|refusal|other",
  "intent_confidence": 0.0,
  "intent_reason": "string court (max 160 chars)",
  "language": "fr|en",
  "tone": "neutral|polite",
  "constraints": {
    "max_chars": 400
  },
  "needs": {
    "knowledge_keys": [],
    "lexicon_keys": []
  },
  "input": {
    "user_prompt": "string"
  }
}
TXT;

        $prompt = $parserRules
            . "\n\nallowed_knowledge_keys:\n"
            . $allowedKeysJson
            . "\n\nTexte utilisateur:\n"
            . $userPrompt;

        $resp = $this->llm->generate($prompt);
        $raw = trim($resp->text);

        if ($raw === '' && isset($resp->responsePayload['error'])) {
            throw new \RuntimeException(sprintf('Parser LLM error: %s', $resp->responsePayload['error']));
        }

        return $this->decodeJsonFromText($raw);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonFromText(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw new \RuntimeException('Parser JSON invalide: reponse vide.');
        }

        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start === false || $end === false || $end <= $start) {
            throw new \RuntimeException('Parser JSON invalide: pas de bloc JSON.');
        }

        $json = substr($raw, $start, $end - $start + 1);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new \RuntimeException('Parser JSON invalide: json_decode a echoue.');
        }

        return $data;
    }
}
