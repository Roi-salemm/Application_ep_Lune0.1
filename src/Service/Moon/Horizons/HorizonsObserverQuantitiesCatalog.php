<?php

namespace App\Service\Moon\Horizons;

final class HorizonsObserverQuantitiesCatalog
{
    /**
     * Data plan (default is GEO; TOPO when a topocentric CENTER is used).
     *
     * GEO (geocentrique):
     * - Horizons direct:
     *   - Elongation (23), Phase angle (24), Illumination fraction (10)
     *   - Range & range-rate (20), Light-time (21), Angular diameter (13)
     *   - Heliocentric ecliptic lon/lat (18), Constellation (29)
     *   - Sub-observer / sub-solar lon/lat (14, 15), L_s (44), Delta-T (30)
     * - A calculer:
     *   - Signe zodiacal (tropical/sideral) depuis longitude ecliptique
     *   - Tithi, Nakshatra (longitude siderale + ayanamsa)
     *   - Numero de lunaison dans l'annee (a partir des nouvelles lunes)
     *
     * TOPO (topocentrique, position locale):
     * - Horizons direct:
     *   - Azimut/Elv (4), rates (5), Airmass/extinction (8)
     *   - Local sidereal time (7), Local solar time (34), Hour angle (42)
     *   - Sky motion (47), Lunar sky brightness/SNR (48)
     *   - Range/range-rate (20), Light-time (21), Elongation (23), Phase (24)
     * - A calculer:
     *   - Lever/coucher/culmination (depuis series topo ou algorithme)
     *   - Signe zodiacal, tithi, nakshatra (idem GEO mais topocentrique si voulu)
     *
     * Notes:
     * - % illumination: utiliser 10 (Illuminated fraction) ou deduire de 24.
     * - Donnees GEO/TOPO dependent du CENTER (geocentrique vs site/lat-lon).
     *
     * Calculs app -> donnees Horizons requises (validation g/t/O):
     * - Phase/illumination/age: 24 (Phase angle S-T-O) OU 23 + /r (Elongation + trail)
     *   OU (Moon 18 - Sun 18) si coordonnees ecliptiques dispo.
     * - Evenements de phases: meme prerequis que la phase (ci-dessus).
     * - Diametre apparent: 20 (Observer range) -> dist_km.
     * - Constellation (affichage direct): 29.
     * - Zodiac/tithi/nakshatra: Moon 18 + Sun 18 + ayanamsa (ayanamsa = source externe).
     * - Topocentrique (azimut/hauteur, lever/coucher, angle horaire):
     *   2 (RA/DEC) + 20 (Range) + 30 ou 49 (Delta-T/DUT1) +
     *   position observateur (lat/lon/alt) + pression/temperature (sources externes).
     * - Selenographie/libration (si utilise): 14 (Sub-observer lon/lat) + 15 (Sub-solar lon/lat).
     *
     * Flags (catalogue):
     * - default: quantite mise en avant dans cette page
     * - stat: quantite statistique (covariance requise)
     */

    /**
     * @return array<int, array{
     *     code:int,
     *     label:string,
     *     label_fr:string,
     *     description_fr:string,
     *     flags:array<string,bool>,
     *     validated:bool,
     *     ia?:string
     * }>
     */
    public function all(): array
    {
        return [
            [
                'code' => 1,
                'label' => 'Astrometric RA & DEC',
                'label_fr' => 'AD/DEC astrometriques',
                'description_fr' => 'Coordonnees astrometriques sans corrections apparentes.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 2,
                'label' => 'Apparent RA & DEC',
                'label_fr' => 'AD/DEC apparentes',
                'description_fr' => 'Coordonnees apparentes avec corrections (aberration, etc.).',
                'flags' => ['default' => true],
                'validated' => false,
                'ia' => 'gto',
            ],
            [
                'code' => 3,
                'label' => 'Rates; RA & DEC',
                'label_fr' => 'Vitesses AD/DEC',
                'description_fr' => 'Derivees temporelles des coordonnees RA/DEC.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 4,
                'label' => 'Apparent AZ & EL',
                'label_fr' => 'Azimut/Hauteur apparents',
                'description_fr' => 'Coordonnees horizontales apparentes.',
                'flags' => ['default' => true],
                'validated' => false,
            ],
            [
                'code' => 5,
                'label' => 'Rates; AZ & EL',
                'label_fr' => 'Vitesses Az/Hauteur',
                'description_fr' => 'Vitesses angulaires en azimut et hauteur.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 6,
                'label' => 'Satellite X & Y, position angle',
                'label_fr' => 'X/Y satellite, angle de position',
                'description_fr' => 'Coordonnees X/Y d un satellite et angle de position.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 7,
                'label' => 'Local apparent sidereal time',
                'label_fr' => 'Temps sideral local apparent',
                'description_fr' => 'Temps sideral local pour l observateur.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 8,
                'label' => 'Airmass and Visual Magnitude Extinction',
                'label_fr' => 'Masse d air et extinction',
                'description_fr' => 'Masse d air et extinction de magnitude visuelle.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 9,
                'label' => 'Visual magnitude & surface Brightness',
                'label_fr' => 'Magnitude et brillance',
                'description_fr' => 'Magnitude visuelle et brillance de surface.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 10,
                'label' => 'Illuminated fraction',
                'label_fr' => 'Fraction illuminee',
                'description_fr' => 'Pourcentage du disque eclaire (0-100%).',
                'flags' => [],
                'validated' => true,
            ],
            [
                'code' => 11,
                'label' => 'Defect of illumination',
                'label_fr' => 'Defaut d illumination',
                'description_fr' => 'Defaut d illumination du disque.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 12,
                'label' => 'Satellite angle of separation/visibility code',
                'label_fr' => 'Separation satellite / visibilite',
                'description_fr' => 'Angle de separation et code de visibilite.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 13,
                'label' => 'Target angular diameter',
                'label_fr' => 'Diametre angulaire',
                'description_fr' => 'Diametre apparent de la cible.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 14,
                'label' => 'Observer sub-longitude & sub-latitude',
                'label_fr' => 'Sub-observateur lon/lat',
                'description_fr' => 'Longitude/latitude sub-observateur.',
                'flags' => [],
                'validated' => false,
                'ia' => 'go',
            ],
            [
                'code' => 15,
                'label' => 'Sun sub-longitude & sub-latitude',
                'label_fr' => 'Sub-solaire lon/lat',
                'description_fr' => 'Longitude/latitude sub-solaire.',
                'flags' => [],
                'validated' => false,
                'ia' => 'go',
            ],
            [
                'code' => 16,
                'label' => 'Sub-Sun position angle & distance from disc center',
                'label_fr' => 'Angle sub-solaire + distance',
                'description_fr' => 'Angle de position sub-solaire et distance au centre.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 17,
                'label' => 'North pole position angle & distance from disc center',
                'label_fr' => 'Angle du pole nord + distance',
                'description_fr' => 'Angle de position du pole nord et distance au centre.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 18,
                'label' => 'Heliocentric ecliptic longitude & latitude',
                'label_fr' => 'Lon/Lat ecliptique heliocentrique',
                'description_fr' => 'Longitude/latitude ecliptiques heliocentriques.',
                'flags' => [],
                'validated' => false,
                'ia' => 'go',
            ],
            [
                'code' => 19,
                'label' => 'Heliocentric range & range-rate',
                'label_fr' => 'Distance heliocentrique',
                'description_fr' => 'Distance au Soleil et vitesse radiale.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 20,
                'label' => 'Observer range & range-rate',
                'label_fr' => 'Distance observateur',
                'description_fr' => 'Distance observateur-cible et vitesse radiale.',
                'flags' => [],
                'validated' => false,
                'ia' => 'gto',
            ],
            [
                'code' => 21,
                'label' => 'One-way down-leg light-time',
                'label_fr' => 'Temps-lumiere aller',
                'description_fr' => 'Temps de propagation aller (one-way).',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 22,
                'label' => 'Speed of target with respect to Sun & observer',
                'label_fr' => 'Vitesse relative',
                'description_fr' => 'Vitesse de la cible vs Soleil et observateur.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 23,
                'label' => 'Sun-Observer-Target elongation angle',
                'label_fr' => 'Elongation S-O-T',
                'description_fr' => 'Elongation Soleil-Observateur-Cible.',
                'flags' => [],
                'validated' => true,
                'ia' => 'go',
            ],
            [
                'code' => 24,
                'label' => 'Sun-Target-Observer phase angle',
                'label_fr' => 'Angle de phase S-T-O',
                'description_fr' => 'Angle de phase Soleil-Cible-Observateur.',
                'flags' => [],
                'validated' => true,
                'ia' => 'go',
            ],
            [
                'code' => 25,
                'label' => 'Target-Observer-Moon/Illumination%',
                'label_fr' => 'T-O-Lune / % illum',
                'description_fr' => 'Angle cible-observateur-lune + % illumination.',
                'flags' => [],
                'validated' => true,
            ],
            [
                'code' => 26,
                'label' => 'Observer-Primary-Target angle',
                'label_fr' => 'Angle Observateur-Prim-Cible',
                'description_fr' => 'Angle observateur-primaire-cible.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 27,
                'label' => 'Position Angles; radius & -velocity',
                'label_fr' => 'Angles de position',
                'description_fr' => 'Angles de position, rayon et vitesse.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 28,
                'label' => 'Orbit plane angle',
                'label_fr' => 'Angle du plan orbital',
                'description_fr' => 'Angle du plan orbital de la cible.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 29,
                'label' => 'Constellation Name',
                'label_fr' => 'Constellation',
                'description_fr' => 'Nom de la constellation.',
                'flags' => [],
                'validated' => false,
                'ia' => 'o',
            ],
            [
                'code' => 30,
                'label' => 'Delta-T (TDB - UT)',
                'label_fr' => 'Delta-T (TDB-UT)',
                'description_fr' => 'Ecart entre temps dynamique et temps universel.',
                'flags' => [],
                'validated' => false,
                'ia' => 't',
            ],
            [
                'code' => 31,
                'label' => 'Observer-centered Earth ecliptic longitude & latitude',
                'label_fr' => 'Lon/Lat ecliptique Terre (obs)',
                'description_fr' => 'Longitude/latitude ecliptique de la Terre centree observateur.',
                'flags' => ['default' => true],
                'validated' => false,
            ],
            [
                'code' => 32,
                'label' => 'North pole RA & DEC',
                'label_fr' => 'AD/DEC pole nord',
                'description_fr' => 'Coordonnees du pole nord de la cible.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 33,
                'label' => 'Galactic longitude and latitude',
                'label_fr' => 'Lon/Lat galactique',
                'description_fr' => 'Longitude et latitude galactiques.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 34,
                'label' => 'Local apparent SOLAR time',
                'label_fr' => 'Temps solaire local',
                'description_fr' => 'Temps solaire local apparent.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 35,
                'label' => 'Earth->Site light-time',
                'label_fr' => 'Temps-lumiere Terre->site',
                'description_fr' => 'Temps de propagation Terre vers site.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 36,
                'label' => 'RA & DEC uncertainty',
                'label_fr' => 'Incertitude AD/DEC',
                'description_fr' => 'Incertitudes sur RA/DEC (si disponibles).',
                'flags' => ['stat' => true],
                'validated' => false,
            ],
            [
                'code' => 37,
                'label' => 'Plane-of-sky (POS) error ellipse',
                'label_fr' => 'Ellipse d erreur (plan du ciel)',
                'description_fr' => 'Ellipse d erreur projetee sur le plan du ciel.',
                'flags' => ['stat' => true],
                'validated' => false,
            ],
            [
                'code' => 38,
                'label' => 'Plane-of-sky (POS) uncertainty (RSS)',
                'label_fr' => 'Incertitude POS (RSS)',
                'description_fr' => 'Incertitude combinee sur le plan du ciel.',
                'flags' => ['stat' => true],
                'validated' => false,
            ],
            [
                'code' => 39,
                'label' => 'Range & range-rate sigma',
                'label_fr' => 'Sigma distance/vitesse',
                'description_fr' => 'Sigmas sur distance et vitesse radiale.',
                'flags' => ['stat' => true],
                'validated' => false,
            ],
            [
                'code' => 40,
                'label' => 'Doppler/delay sigmas',
                'label_fr' => 'Sigma Doppler/delai',
                'description_fr' => 'Sigmas Doppler et delai.',
                'flags' => ['stat' => true],
                'validated' => false,
            ],
            [
                'code' => 41,
                'label' => 'True anomaly angle',
                'label_fr' => 'Anomalie vraie',
                'description_fr' => 'Angle d anomalie vraie de l orbite.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 42,
                'label' => 'Local apparent hour angle',
                'label_fr' => 'Angle horaire local',
                'description_fr' => 'Angle horaire local apparent.',
                'flags' => ['default' => true],
                'validated' => false,
            ],
            [
                'code' => 43,
                'label' => 'Phase angle & bisector',
                'label_fr' => 'Angle de phase + bissectrice',
                'description_fr' => 'Angle de phase et direction de bissectrice.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 44,
                'label' => 'Apparent target-centered longitude of Sun (L_s)',
                'label_fr' => 'Longitude solaire apparente (L_s)',
                'description_fr' => 'Longitude solaire apparente centree sur la cible.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 45,
                'label' => 'Inertial frame apparent RA & DEC',
                'label_fr' => 'AD/DEC (repere inertiel)',
                'description_fr' => 'Coordonnees apparentes dans un repere inertiel.',
                'flags' => ['default' => true],
                'validated' => false,
            ],
            [
                'code' => 46,
                'label' => 'Rates: Inertial RA & DEC',
                'label_fr' => 'Vitesses AD/DEC (repere inertiel)',
                'description_fr' => 'Vitesses angulaires RA/DEC en repere inertiel.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 47,
                'label' => 'Sky motion: angular rate & angles',
                'label_fr' => 'Mouvement apparent du ciel',
                'description_fr' => 'Vitesse angulaire apparente et direction.',
                'flags' => ['default' => true],
                'validated' => false,
            ],
            [
                'code' => 48,
                'label' => 'Lunar sky brightness & target visual SNR',
                'label_fr' => 'Brillance ciel lunaire / SNR',
                'description_fr' => 'Brillance du ciel lunaire et SNR visuel.',
                'flags' => [],
                'validated' => false,
            ],
            [
                'code' => 49,
                'label' => 'DUT1 (UT1 - UTC)',
                'label_fr' => 'DUT1 (UT1-UTC)',
                'description_fr' => 'Ecart UT1 - UTC.',
                'flags' => [],
                'validated' => false,
                'ia' => 't',
            ],
        ];
    }

    /**
     * @return array<string, array{label:string,label_fr:string,description_fr:string,list:string}>
     */
    public function macros(): array
    {
        return [
            'A' => [
                'label' => 'All quantities',
                'label_fr' => 'Toutes les quantites',
                'description_fr' => 'Inclut tous les codes 1 a 49.',
                'list' => '1-49',
            ],
            'B' => [
                'label' => 'Bodycentric observer -> Any target',
                'label_fr' => 'Obs centre corps -> toute cible',
                'description_fr' => 'Preset pour observateur geocentrique, cible quelconque.',
                'list' => '1-3, 6, 9-33, 36-41, 43-46, 47, 49',
            ],
            'C' => [
                'label' => 'Body-center observer -> Small-body target',
                'label_fr' => 'Obs centre corps -> petite cible',
                'description_fr' => 'Preset geocentrique pour petits corps.',
                'list' => '1-3, 9-11, 13, 18-29, 33, 36-41, 43-46, 47, 49',
            ],
            'D' => [
                'label' => 'Topocentric observer -> Small-body target',
                'label_fr' => 'Obs topocentrique -> petite cible',
                'description_fr' => 'Preset topocentrique pour petits corps.',
                'list' => '1-5, 8-10, 11, 13, 18-29, 33-34, 36-49',
            ],
            'E' => [
                'label' => 'Bodycentric observer -> Spacecraft target',
                'label_fr' => 'Obs centre corps -> vaisseau',
                'description_fr' => 'Preset geocentrique pour vaisseaux.',
                'list' => '1-3, 8, 10, 18-25, 29, 41, 43-47, 49',
            ],
            'F' => [
                'label' => 'Topocentric observer -> Spacecraft target',
                'label_fr' => 'Obs topocentrique -> vaisseau',
                'description_fr' => 'Preset topocentrique pour vaisseaux.',
                'list' => '1-5, 8, 10, 18-25, 29, 41-49',
            ],
        ];
    }
}
