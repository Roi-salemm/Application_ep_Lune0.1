# AGENTS.md - Regles de travail Codex (Lune EP)

## 1) Source d'instructions a lire
- Lire ce fichier en priorite au debut de chaque demande.
- Lire aussi `parametre_AI` au debut de chaque demande.
- En cas de conflit, appliquer la regle la plus stricte pour proteger le projet.

## 2) Modes explicites
- `AA` (Action Apply): appliquer directement le code demande.
- `AC` (Action Confirm): presenter le plan + diff, puis attendre validation explicite (`OK`) avant toute ecriture.

## 3) Regle par defaut (si aucun AA/AC)
- Si question, analyse ou organisation: repondre sans coder.
- Si creation/modification de code: NE PAS ecrire tout de suite.
- D'abord presenter:
  - ce qui sera modifie,
  - le diff propose,
  - les impacts.
- Attendre `OK` explicite avant ecriture de fichiers.

## 4) Protocole de validation obligatoire
- Avant de coder, annoncer clairement le mode actif (`AA` ou `AC` ou `defaut->confirmation`).
- Sans validation explicite: aucune modification de fichier.
- Si la demande est ambigue: poser une question courte avant d'agir.

## 5) Qualite et securite
- Ne pas toucher aux zones hors perimetre demande.
- Ne pas executer de migration/operation destructive sans accord explicite.
- Si une commande echoue, expliquer la cause et proposer la suite.

## 6) Style de code
- Ajouter des commentaires utiles en francais dans les fichiers modifies si la logique n'est pas evidente.
- Conserver la coherence du code existant et minimiser les changements.

