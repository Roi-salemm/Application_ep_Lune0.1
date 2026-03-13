<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;





final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('admin_horizon_data');
    }
}


// (Test : voir le message ) 





// On va continuer sur les modifs structurelles, mais cette fois-ci, avant d'envisager la modif, tu vas rechercher dans l'intégralité du code les occurrences de ce que je vais énoncer. Actuellement, on a défini « fast key » comme zéro étant la nouvelle lune, ce qui est très déstabilisant pour un humain, puisque d'ordinaire, on fait en fait huit phases et donc il vaut mieux commencer à la nouvelle lune à 1. Donc 1 serait nouvelle lune et 8 serait la phase juste avant la nouvelle lune. Est-ce que tu penses qu'on peut modifier dans l'intégralité du code et dans la logique ce truc-là et comment on peut patcher éventuellement les données qui sont déjà en base de données et qui sont enregistrées comme ça ? J'ai un peu peur parce que des fois, il y a des JSON, des trucs comme ça. Donc en fait, est-ce qu'il faut que je recommence toute la base ou est-ce qu'on peut arranger les choses maintenant ou pas ?









// Merci de rédiger les 40 textes via "lecture symbolique 0.3"


// 1) Nouvelle Lune
// Variante 1

// La lumière disparaît presque entièrement du ciel nocturne.
// Le cycle revient à un temps de retrait où rien ne s’annonce encore au-dehors.
// Le moment évoque ces seuils silencieux où ce qui compte se rassemble hors du regard.
// Toute naissance s’appuie d’abord sur une part d’invisible.

// Variante 2

// Le disque s’efface presque tout entier dans l’obscurité du ciel.
// Le cycle rejoint une phase de retrait où l’élan cesse d’être visible sans pour autant s’interrompre.
// Le moment évoque ces préparations muettes dont rien n’apparaît encore, bien qu’elles soient déjà à l’œuvre.
// Ce qui commence vraiment ne se montre pas toujours d’abord.

// Variante 3

// La Lune devient presque indiscernable dans la profondeur nocturne.
// Le cycle revient à un point de suspension où le mouvement se poursuit sans se livrer au visible.
// Le moment évoque ces passages discrets où quelque chose se resserre avant de reparaître autrement.
// Il existe des commencements que seul le retrait rend possibles.

// Variante 4

// La clarté lunaire s’est presque entièrement retirée du ciel.
// Le cycle entre dans une phase de concentration où le visible cède la place à une préparation plus secrète.
// Le moment évoque ces temps d’attente dense où rien ne paraît mûrir, alors que tout se réunit.
// L’invisible n’est pas le vide, mais un autre mode de présence.

// Variante 5

// La Lune s’efface presque complètement dans la nuit.
// Le cycle rejoint un temps de retrait où ce qui vient ne laisse encore paraître aucun signe net.
// Le moment évoque ces silences pleins où quelque chose se rassemble avant de reprendre un cours visible.
// Toute origine traverse d’abord une obscurité féconde.

// 2) Premier Croissant
// Variante 1

// Une mince lueur reparaît au bord du disque obscur.
// Le cycle entre dans une phase d’émergence où ce qui s’était retiré recommence à se distinguer.
// Le moment évoque ces commencements discrets qui tiennent peu de place, mais déplacent déjà l’ensemble.
// Une faible apparition peut suffire à changer une direction.

// Variante 2

// Un fin croissant de lumière entaille de nouveau l’obscurité lunaire.
// Le cycle ouvre un temps d’amorce où l’élan reprend sans encore occuper tout l’espace.
// Le moment évoque ces premiers signes modestes qui n’imposent rien, mais orientent déjà le mouvement.
// Ce qui naît humblement peut porter une suite entière.

// Variante 3

// La lumière revient en filet sur le bord du disque.
// Le cycle amorce une reprise où l’invisible commence à céder un peu de terrain au visible.
// Le moment évoque ces débuts à peine tracés dont la portée dépasse largement leur apparence fragile.
// Les commencements les plus discrets déplacent parfois le plus profondément.

// Variante 4

// Une fine clarté se détache de nouveau dans la nuit lunaire.
// Le cycle retrouve une dynamique d’apparition où quelque chose recommence à prendre contour dans le visible.
// Le moment évoque ces émergences modestes qui ne s’imposent pas encore, mais introduisent un nouvel axe.
// Il suffit parfois d’un signe mince pour rouvrir le mouvement.

// Variante 5

// Le disque obscur laisse paraître une première lisière de lumière.
// Le cycle entre dans une phase d’ouverture où ce qui s’était retiré revient sous un trait encore léger.
// Le moment évoque ces reprises discrètes qui ne remplissent pas encore l’espace, mais en changent l’orientation.
// Une naissance ne gagne pas tout d’un coup, elle s’annonce.

// 3) Premier Quartier
// Variante 1

// L’ombre et la lumière se partagent nettement le disque lunaire.
// Le cycle traverse une phase de tension où l’élan engagé rencontre sa première résistance décisive.
// Le moment évoque ces passages où avancer suppose d’accepter la friction plutôt que de l’éviter.
// Toute poussée durable apprend d’abord à composer avec une limite.

// Variante 2

// Le disque se divise clairement entre clarté et obscurité.
// Le cycle entre dans un temps de confrontation constructive où ce qui a commencé doit désormais tenir sa direction.
// Le moment évoque ces seuils où l’élan ne progresse qu’en rencontrant ce qui lui résiste.
// La solidité naît souvent d’une tension bien traversée.

// Variante 3

// La moitié lumineuse répond avec netteté à la moitié sombre.
// Le cycle atteint un point de mise à l’épreuve où l’amorce doit se confirmer dans l’effort.
// Le moment évoque ces passages où le mouvement cesse d’être simple et demande un engagement plus net.
// Ce qui veut durer traverse tôt ou tard une zone de résistance.

// Variante 4

// Le partage entre ombre et lumière apparaît avec une grande clarté.
// Le cycle rejoint une phase de tension active où l’élan rencontre une contrainte qui le précise.
// Le moment évoque ces moments où l’avancée ne s’approfondit qu’en se heurtant à ce qui l’arrête.
// La contrainte n’abolit pas toujours le mouvement, elle peut aussi l’affermir.

// Variante 5

// La Lune montre un équilibre net entre clarté et obscurité.
// Le cycle traverse un passage exigeant où l’élan initial doit franchir une première ligne de résistance.
// Le moment évoque ces seuils où progresser demande moins d’élan brut que d’ajustement juste.
// Toute croissance gagne en force lorsqu’elle rencontre de quoi se mesurer.

// 4) Gibbeuse Croissante
// Variante 1

// La lumière gagne désormais la plus grande part du disque lunaire.
// Le cycle poursuit son expansion et conduit l’élan engagé vers une maturité plus dense.
// Le moment évoque ces montées patientes où ce qui s’annonçait devient plus consistant et plus lisible.
// Ce qui croît vraiment s’épaissit avant d’atteindre son plein.

// Variante 2

// Le disque lumineux s’étend largement sans encore atteindre la totalité.
// Le cycle avance dans une phase d’amplification où ce qui a pris naissance se consolide peu à peu.
// Le moment évoque ces progressions continues qui ajoutent de la tenue à ce qui restait encore fragile.
// La maturation donne du poids à ce qui n’était qu’élan.

// Variante 3

// L’ombre recule et la clarté occupe presque tout le disque.
// Le cycle entre dans un temps de consolidation où ce qui a commencé prend davantage d’ampleur.
// Le moment évoque ces montées régulières où l’ébauche cesse d’être précaire pour devenir plus stable.
// Toute plénitude se prépare par un patient épaississement.

// Variante 4

// La Lune se remplit largement de lumière sans être encore entière.
// Le cycle poursuit une montée où l’élan se densifie et s’approche d’un accomplissement plus complet.
// Le moment évoque ces croissances soutenues où ce qui tenait encore à peu devient plus assuré.
// Avant l’éclat total, il y a le travail discret de la maturation.

// Variante 5

// Le disque apparaît presque plein, déjà largement gagné par la clarté.
// Le cycle traverse une phase d’extension où ce qui était engagé s’affermit et prend plus de portée.
// Le moment évoque ces développements continus qui donnent de la consistance à ce qui se cherchait encore.
// Ce qui mûrit ne se contente pas d’avancer, il se densifie.

// 5) Pleine Lune
// Variante 1

// Le disque lunaire apparaît dans sa pleine visibilité.
// Le cycle atteint son point d’accomplissement visible et porte à son maximum ce qu’il faisait croître.
// Le moment évoque ces passages où les contrastes deviennent trop nets pour rester mêlés ou confondus.
// Ce qui parvient à son plein révèle aussi sa limite.

// Variante 2

// La lumière occupe toute la surface visible du disque.
// Le cycle rejoint une phase de culmination où ce qui se préparait devient entièrement manifeste.
// Le moment évoque ces instants où rien ne peut plus rester à demi dit ni à demi perçu.
// Toute clarté totale expose en même temps ce qu’elle éclaire.

// Variante 3

// La Lune se montre entière dans la nuit.
// Le cycle atteint un sommet de visibilité où ce qui avançait par degrés se livre sans réserve.
// Le moment évoque ces moments où les écarts, les reliefs et les tensions cessent de pouvoir se dissimuler.
// La pleine lumière ne cache rien, elle distingue.

// Variante 4

// Le disque apparaît complet, offert sans retrait au regard.
// Le cycle traverse son point le plus manifeste et rend pleinement visibles les effets de sa progression.
// Le moment évoque ces culminations où ce qui était en chemin se donne tout entier à voir.
// Lorsqu’une chose arrive à son plein, elle montre aussi son bord.

// Variante 5

// La totalité du disque se détache avec netteté dans le ciel nocturne.
// Le cycle parvient à sa plus grande exposition et rassemble dans le visible tout ce qu’il portait.
// Le moment évoque ces seuils où les différences deviennent si claires qu’aucun mélange ne tient encore.
// L’évidence éclaire, mais elle sépare aussi.

// 6) Gibbeuse Décroissante
// Variante 1

// La lumière reste large sur le disque, mais son retrait a commencé.
// Le cycle quitte l’expansion et fait passer ce qui s’est montré vers un temps d’intégration.
// Le moment évoque ces périodes où ce qui a été clairement exposé commence à se déposer autrement.
// Après l’éclat vient souvent le travail plus discret de l’assimilation.

// Variante 2

// Le disque demeure très éclairé, bien que la diminution soit engagée.
// Le cycle entre dans une phase de redistribution où ce qui a culminé cesse de croître pour se réorganiser.
// Le moment évoque ces temps où l’évidence ne cherche plus à s’étendre, mais à être reprise en profondeur.
// Ce qui a beaucoup montré doit ensuite se déposer.

// Variante 3

// La clarté domine encore largement, mais elle n’avance plus.
// Le cycle amorce un recul fécond où ce qui s’est pleinement donné commence à être intégré.
// Le moment évoque ces passages où l’essentiel ne tient plus dans l’essor, mais dans la décantation.
// Toute culmination appelle un temps de reprise intérieure.

// Variante 4

// La Lune reste presque entière, tout en amorçant sa diminution.
// Le cycle passe d’un temps d’expansion à un temps d’assimilation où ce qui a paru doit être redistribué.
// Le moment évoque ces périodes où l’exposé cesse de s’étendre pour devenir plus habitable.
// Ce qui s’est montré demande ensuite à être intégré.

// Variante 5

// Le disque conserve une large clarté, déjà gagnée pourtant par le retrait.
// Le cycle poursuit sa course dans un mouvement de retour où le visible commence à se déposer.
// Le moment évoque ces temps où l’élan laisse place à une reprise plus lente de ce qui a été vécu.
// Après l’expression, vient souvent le temps de la décantation.

// 7) Dernier Quartier
// Variante 1

// L’ombre et la lumière se partagent de nouveau nettement le disque.
// Le cycle entre dans une phase de tri où ce qui demeure se distingue de ce qui perd sa nécessité.
// Le moment évoque ces passages où l’ensemble s’allège en laissant apparaître des fonctions plus nettes.
// Ce qui se défait n’est pas toujours perdu, c’est parfois clarifié.

// Variante 2

// Le disque retrouve un partage net entre clarté et obscurité.
// Le cycle rejoint un temps de simplification où ce qui reste doit être réordonné avec plus de justesse.
// Le moment évoque ces phases où certaines présences se retirent pour que d’autres deviennent plus lisibles.
// La clarification passe souvent par une réduction.

// Variante 3

// La moitié lumineuse fait à nouveau face à la moitié sombre.
// Le cycle traverse un moment de réorganisation où l’essentiel se sépare peu à peu du reste.
// Le moment évoque ces passages où le mouvement ne gagne plus en ajoutant, mais en discernant mieux.
// Alléger peut être une manière d’approcher plus juste.

// Variante 4

// Le disque revient à un équilibre net entre lumière et ombre.
// Le cycle entre dans une phase de sélection où ce qui a compté doit maintenant être remis à sa juste place.
// Le moment évoque ces moments où l’ensemble se recompose en perdant ce qui n’a plus de fonction claire.
// Toute simplification juste augmente la lisibilité du réel.

// Variante 5

// La clarté et l’obscurité se répondent à parts égales sur le disque.
// Le cycle rejoint un temps de discernement où l’élan passé laisse place à une redistribution plus sobre.
// Le moment évoque ces périodes où certaines choses s’effacent afin que le nécessaire apparaisse mieux.
// Le tri n’appauvrit pas toujours, il révèle aussi.

// 8) Dernier Croissant
// Variante 1

// La lumière devient fine et s’approche de sa disparition.
// Le cycle touche à son terme et conduit ce qui demeure vers un effacement progressif.
// Le moment évoque ces fins calmes où l’activité se retire sans rupture, comme rendue à plus vaste qu’elle.
// Toute fin prépare en silence ce qu’un autre cycle reprendra.

// Variante 2

// Un mince reste de clarté subsiste encore sur le disque.
// Le cycle avance vers son point de retrait le plus poussé et raréfie peu à peu ce qui restait visible.
// Le moment évoque ces dénouements lents où quelque chose se retire sans drame, simplement par épuisement du cours.
// Ce qui s’efface ne disparaît pas toujours sans retour.

// Variante 3

// La Lune ne garde plus qu’un filet de lumière avant l’effacement.
// Le cycle approche d’un terme où le visible rend progressivement la place à ce qui ne se montre plus.
// Le moment évoque ces fins déliées où rien ne casse, mais où tout se retire par degrés.
// Toute disparition prépare aussi un recommencement possible.

// Variante 4

// La clarté lunaire n’occupe plus qu’une très mince part du disque.
// Le cycle rejoint une phase d’effacement où ce qui persistait se relâche et retourne vers le retrait.
// Le moment évoque ces achevements paisibles où le mouvement s’apaise jusqu’à presque se confondre avec le silence.
// La fin n’interrompt pas toujours, elle rend parfois à l’origine.

// Variante 5

// Le disque conserve à peine une lisière de lumière avant la nuit complète.
// Le cycle entre dans son dernier retrait et reconduit peu à peu le visible vers l’inaperçu.
// Le moment évoque ces fins sobres où ce qui demeurait encore se défait sans violence ni éclat.
// Ce qui revient à l’ombre n’est pas aboli, mais repris autrement.













// {
//     "color": "#2D66A0",
//     "label": "L’ombre et la lumière se partagent de nouveau nettement le disque.\nLe cycle entre dans une phase de tri où ce qui demeure se distingue de ce qui perd sa nécessité.\nLe moment évoque ces passages où l’ensemble s’allège en laissant apparaître des fonctions plus nettes.\nCe qui se défait n’est pas toujours perdu, c’est parfois clarifié.",
//     "variant_id": "19",
//     "symbolic_weather": {
//         "is_used": true,
//         "phase_key": 6,
//         "variant_id": "19",
//         "variant_no": 1,
//         "is_validated": true
//     }
// }



// {
//     "color": "#2D66A0",
//     "label": "L’ombre et la lumière se partagent de nouveau nettement le disque.\nLe cycle entre dans une phase de tri où ce qui demeure se distingue de ce qui perd sa nécessité.\nLe moment évoque ces passages où l’ensemble s’allège en laissant apparaître des fonctions plus nettes.\nCe qui se défait n’est pas toujours perdu, c’est parfois clarifié.",
//     "variant_id": "19",
//     "symbolic_weather": {
//         "is_used": true,
//         "phase_key": 6,
//         "variant_id": "19",
//         "variant_no": 1,
//         "is_validated": true
//     }
// }

// {
//     "icon": "",
//     "tone": "",
//     "color": "#2D66A0",
//     "label": "Un mince reste de clarté subsiste encore sur le disque.\nLe cycle avance vers son point de retrait le plus poussé et raréfie peu à peu ce qui restait visible.\nLe moment évoque ces dénouements lents où quelque chose se retire sans drame, simplement par épuisement du cours.\nCe qui s’efface ne disparaît pas toujours sans retour.",
//     "media": [],
//     "title": "",
//     "status": "validated",
//     "subtitle": "",
//     "phase_key": null,
//     "variant_id": "23",
//     "variant_no": null,
//     "content_card": [
//         {
//             "text": "",
//             "media": [],
//             "title": "",
//             "baseline": "",
//             "citation": "",
//             "commentaire": ""
//         }
//     ],
//     "schema_version": "1.0",
//     "editorial_notes": "",
//     "symbolic_weather": {
//         "phase_key": 7,
//         "variant_id": "23",
//         "variant_no": 2
//     }
// }


// {
//     "icon": "",
//     "tone": "",
//     "color": "#2D66A0",
//     "label": "Un mince reste de clarté subsiste encore sur le disque.\nLe cycle avance vers son point de retrait le plus poussé et raréfie peu à peu ce qui restait visible.\nLe moment évoque ces dénouements lents où quelque chose se retire sans drame, simplement par épuisement du cours.\nCe qui s’efface ne disparaît pas toujours sans retour.",
//     "media": [],
//     "title": "",
//     "status": "validated",
//     "subtitle": "",
//     "phase_key": null,
//     "variant_id": "23",
//     "variant_no": null,
//     "content_card": [
//         {
//             "text": "",
//             "media": [],
//             "title": "",
//             "baseline": "",
//             "citation": "",
//             "commentaire": ""
//         }
//     ],
//     "schema_version": "1.0",
//     "editorial_notes": "",
//     "symbolic_weather": {
//         "phase_key": 7,
//         "variant_id": "23",
//         "variant_no": 2
//     }
// }


// {
//     "icon": "",
//     "tone": "",
//     "color": "#2D66A0",
//     "label": "La lumière devient fine et s’approche de sa disparition.\nLe cycle touche à son terme et conduit ce qui demeure vers un effacement progressif.\nLe moment évoque ces fins calmes où l’activité se retire sans rupture, comme rendue à plus vaste qu’elle.\nToute fin prépare en silence ce qu’un autre cycle reprendra.",
//     "media": [],
//     "title": "",
//     "status": "validated",
//     "subtitle": "",
//     "phase_key": null,
//     "variant_id": "22",
//     "variant_no": null,
//     "content_card": [
//         {
//             "text": "",
//             "media": [],
//             "title": "",
//             "baseline": "",
//             "citation": "",
//             "commentaire": ""
//         }
//     ],
//     "schema_version": "1.0",
//     "editorial_notes": "",
//     "symbolic_weather": {
//         "phase_key": 7,
//         "variant_id": "22",
//         "variant_no": 1
//     }
// }