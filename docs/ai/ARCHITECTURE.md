<!--
Ce document reprend l architecture et les decisions des specifications AI locales.
Pourquoi : fournir une reference stable pour l equipe.
Informations specifiques : contenu derive des specs v0 et v1, sans ajout.
-->

# Architecture AI locale (v0)

## But du document
Ce document decrit exactement l architecture, les dossiers, les responsabilites, le workflow et les contrats JSON pour implementer v0 de l IA locale dans Symfony.
V0 couvre uniquement `symbolic_weather` avec la tradition `western_astrology`. Les autres contextes et traditions sont prevus par la structure mais non implementes en v0.

## Objectifs v0
- Integrer un LLM local via Ollama (modele : `llama3.1:8b`).
- Exposer un endpoint Symfony : `POST /api/ai/weather`.
- Generer une meteo symbolique descriptive, impersonnelle, sans injonction et sans syncretisme.
- Sortie strictement en JSON conforme a un JSON Schema.
- Symfony orchestre :
  - construction du prompt
  - appel Ollama
  - parsing JSON
  - validation JSON Schema
  - guardrails (anti injonction, anti syncretisme)
  - logs et audit
  - reponse JSON a l app

## Contraintes editoriales v0
- Texte descriptif, impersonnel, style meteo symbolique (type Yi Jing).
- Interdiction d injonctions (pas de tu dois, il faut, imperatif, etc.).
- Interdiction de syncretisme : si cadre `western_astrology`, pas d emprunts a d autres systemes.
- Si vocabulaire utilisateur mixte : reponse dans une tradition unique et signalement dans `assumptions`.

## Decision v0
- Pas de rewrite ou regen automatique.
- Si JSON invalide ou guardrails echouent : erreur claire cote Symfony et log.

## Choix technique
- Option B : structured outputs via le champ `format` d Ollama.
- Symfony envoie un JSON Schema dans `format`.
- Symfony revalide la reponse via Opis JSON Schema.

## Contrat JSON (v0.1)
- Le `message` utilisateur peut etre vide en v0.
- `lunar_snapshot` est requis en v0.
- Symfony renvoie le JSON produit par le modele apres validation et guardrails.
- Champs principaux : `meta`, `routing`, `safety_and_limits`, `content`, `data_used`, `style_contract`.
- `routing.context` est fixe a `symbolic_weather`.
- `routing.tradition` est `western_astrology`.

## JSON Schema
Fichier : `config/ai/schemas/symbolic_weather.schema.json`
Contraintes attendues :
- `$schema` en draft moderne (2020-12 recommande).
- `type: object` et `additionalProperties: false` partout.
- `required` doit inclure au minimum : `meta`, `routing`, `safety_and_limits`, `content`, `style_contract`, `data_used`.
- `routing.context` = const `symbolic_weather`.
- `routing.tradition` = enum `[western_astrology]`.
- `meta.language` = enum `[fr]`.
- `safety_and_limits.action.mode` = enum `[allow, soft_refusal, refusal, safety_redirect]`.
- `content.text` avec min et max length (ex : 80 a 900).

## Prompts versionnes
Dossier : `config/ai/prompts/`
Fichiers requis v0 :
- `base_system.md`
- `contexts/symbolic_weather.md`
- `traditions/western_astrology.yml`

Regles globales :
- JSON strict conforme au schema
- pas d injonctions
- pas de syncretisme
- pas de diagnostic medical ou juridique
- ton impersonnel
- mentionner le cadre dans `assumptions` si vocabulaire utilisateur mixte

## Appel Ollama (contrat technique)
Variables attendues :
- `OLLAMA_BASE_URL` (ex : http://127.0.0.1:11434)
- `OLLAMA_MODEL` (ex : `llama3.1:8b`)

Payload attendu (structure) :
- `model`, `stream`, `messages`, `format`, `options`
- `format` recoit le JSON Schema complet
- la reponse JSON est dans `response.message.content` et doit etre decodee

## Workflow exact v0
1. Le controller recoit la requete.
2. Le DTO Request valide les champs essentiels (`lunar_snapshot` present).
3. `SymbolicWeatherService` orchestre :
   - charge le JSON Schema
   - charge les prompts
   - construit les messages systeme et utilisateur
   - appelle Ollama via `OllamaClient`
   - recupere `message.content` puis `json_decode`
   - valide le JSON Schema (Opis)
   - applique les guardrails (StyleGuard, TraditionGuard, SafetyGuard)
   - logue via `AiLogService`
4. Symfony renvoie le JSON valide.

## Structure de code v0
- `src/AI/Controller/` : controleurs API de l IA
- `src/AI/DTO/` : DTO de request et reponse (validation legere)
- `src/AI/Client/` : clients LLM (v0 : `OllamaClient`)
- `src/AI/Prompt/` : lecture et assemblage des prompts
- `src/AI/Domain/` : services metier IA
- `src/AI/Guardrails/` : validations et garde fous
- `src/AI/Observability/` : logs et instrumentation
- `src/AI/Routing/` : reserve pour le futur routing
- `config/ai/` : prompts et schemas
- `docs/ai/` : documentation interne

## Hors scope v0
- Multi contextes autres que `symbolic_weather`
- Multi traditions autres que `western_astrology`
- Routing automatique
- Rewrite ou regen automatique
- RAG ou base de connaissances
- Memoire utilisateur

## Plan de validation v0
- Test JSON valide : passe.
- Test JSON invalide (champ manquant, enum invalide, additionalProperties) : echoue avec erreurs lisibles.
- Test d appel Ollama reel : la sortie passe le schema.

# Reference V1 (evolutions prevues)

## Ajouts fonctionnels
- Multi contextes : `symbolic_weather`, `symbolic_reading`, `personal_question`, `divination_interpretation`, `non_duality`, `technical_science`, `other`.
- Multi traditions : `western_astrology`, `jyotish`, `yijing`, `tarot_marseille`, `neutral_symbolic`.
- Selection manuelle ou detection automatique du contexte et de la tradition.
- Fallback et annonce claire du cadre si question confuse.
- Safety renforce (refus doux, redirection).
- Rewrite ou regen controle si JSON invalide ou guardrails echouent.
- Glossary optionnel (court, max 5 items).
- Observabilite plus complete (audit, metriques, versions).

## API V1
- `POST /api/ai/weather` (specialise `symbolic_weather`).
- `POST /api/ai/chat` (general, routing auto ou manuel).

## Schémas et prompts V1
- Schéma dedie `symbolic_weather.schema.json`.
- Schéma generique `chat_response.schema.json` pour `/api/ai/chat`.
- Prompts additionnels : `routing/router_instructions.md`, `safety/safety_policy.md`.

## Routing V1
- `ContextRouter` et `TraditionRouter`.
- Strategie hybride possible : heuristiques + mini appel LLM router.

## Safety V1
- `action.mode` : `allow`, `soft_refusal`, `refusal`, `safety_redirect`.
- Symfony garde la decision finale via `SafetyGuard`.

## Observabilite V1
- request_id, user_id ou fingerprint, endpoint, context/tradition, selection_mode, confidence, prompt_version, latences, erreurs.
- Option : stocker le pack de prompt utilise.
