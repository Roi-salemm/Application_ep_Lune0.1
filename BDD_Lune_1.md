# BDD lune 1

Objectif: stocker les donnees astronomiques sur 11 ans (horaire), servir une app mobile
avec 3 jours offline, et alimenter un agenda des phases lunaires.

## Tables

- moon_ephemeris_hour (canonique)
  - 1 ligne par heure, geocentrique.
  - Champs utiles: ts_utc, phase_deg, age_days, dist_km, ra_hours, dec_deg,
    elon_deg, elat_deg, sun_elong_deg, sun_target_obs_deg, constellation, run_id.
  - Source: NASA JPL Horizons.
  - Unicite: ts_utc (evite les doublons, upsert par timestamp).

- solar_ephemeris_hour
  - 1 ligne par heure, geocentrique.
  - Champs utiles: ts_utc, ra_hours, dec_deg, elon_deg, elat_deg, dist_au.
  - Sert aux calculs astrologiques (signe, tithi, nakshatra, etc.).
  - Unicite: ts_utc (evite les doublons, upsert par timestamp).

- moon_phase_event
  - Evenements calendaries: new_moon, first_quarter, full_moon, last_quarter.
  - Champs utiles: ts_utc, event_type, phase_deg, precision_sec, source.
  - Precision visee: minute.
  - Unicite: (event_type, ts_utc).

- moon_nasa_import
  - Traque les runs d import (raw_response, dates, status).

## Flux

1) Import Horizons Moon + Sun par mois -> moon_ephemeris_hour + solar_ephemeris_hour.
2) Calcul phases -> moon_phase_event (interpolation minute sur la serie horaire).
3) API mobile: renvoie 3 jours de donnees (DTO slim) et l app garde 72h en local.

## Commandes

- app:ephemeris:bulk-import
  - Import Moon + Sun par mois, stop a 23:00 pour couvrir un mois complet.
  - Options: --start=YYYY-MM-01 --months=1 (par defaut).

- app:moon:compute-phase-events
  - Calcule les evenements de phases (minute) depuis la serie horaire.

## Topocentrique

- Calcul cote app avec la position utilisateur.
- Mode degrade: ville proche ou geocentrique si pas de localisation.
