# ADR-004 : Flux ICS d'abonnement au calendrier — endpoint dédié plutôt que taxonomie TEC

**Statut :** Accepté
**Date :** 2026-08-06
**Décideurs :** Alban (développeur)
**Périmètre :** le plugin `wp-jcmv` (routage, génération ICS, bloc d'abonnement) et le
thème `jcmv-theme` (placement du bloc dans `templates/archive-events.html`). Les créneaux
hebdomadaires du CPT `cours` sont explicitement hors périmètre — voir « Périmètre exclu ».

## Contexte

Les familles doivent pouvoir **s'abonner** au calendrier du club depuis leur téléphone —
globalement, ou filtré sur la ou les catégories d'âge qui les concernent — au lieu d'avoir
à consulter le site. Une famille ayant deux enfants dans des catégories différentes doit
pouvoir obtenir un flux unique couvrant les deux.

S'abonner n'est pas exporter : il s'agit d'une URL que le client calendrier interroge
périodiquement, pas d'un fichier `.ics` téléchargé une fois et figé.

### État technique constaté

- **The Events Calendar 6.17.1, version gratuite.** Pas de Pro, donc **pas d'événements
  récurrents**.
- Le slug des événements a été francisé : les vues vivent sous `/evenements/`.
- La taxonomie `jcmv_categorie_age` est déjà rattachée au CPT `tribe_events`
  (`src/Registration/Taxonomies.php:85`). Neuf termes en production : `baby-judo`,
  `benjamin`, `cadet`, `eveil-judo`, `junior`, `mini-poussin`, `minime`, `poussin`,
  `senior`. Les événements sont donc **déjà correctement catégorisés** en back-office.
- Mais cette taxonomie est déclarée `public => false` et `rewrite => false`. Son
  `show_ui => true` explique la métabox visible à la saisie d'un événement, ce qui est
  trompeur : **aucune URL publique** ne permet de filtrer les événements par catégorie
  d'âge. Le rattachement des données est fait ; c'est le routage qui manque.
- TEC produit un `.ics` en ajoutant `?ical=1` à une URL de vue calendrier. Par catégorie,
  cela ne fonctionne que pour **sa** taxonomie `tribe_events_cat` — qui est publique et
  routée, mais **inutilisée** dans ce projet (la métabox « Catégories d'Évènement » est
  vide).
- Le flux TEC est plafonné à **30 événements** (`src/Tribe/iCal.php:19`,
  `feed_default_export_count`), ajustable via le filtre `tribe_ical_feed_posts_per_page`.
- `generate_ical_feed( $ids, false )` **retourne la chaîne** sans envoyer d'en-têtes ni
  appeler `tribe_exit()` (`src/Tribe/iCal.php:355-386`). La génération VEVENT/VTIMEZONE de
  TEC est donc réutilisable telle quelle, en gardant la main sur la sélection et les
  en-têtes.

### Forces en présence

1. **Référentiel unique (exigence héritée de l'ADR-001).** `jcmv_categorie_age` est le
   référentiel FFJDA du club : slugs immuables, bornes d'âge en term meta. Le bureau
   l'enrichit déjà de lui-même — Baby Judo, Éveil Judo, Junior et Sénior/Vétéran ont été
   ajoutés après le seed initial, qui n'en comptait que cinq.
2. **Maintenabilité par le bureau.** Aucune double saisie, aucun risque de divergence
   silencieuse entre deux listes.
3. **Pas d'usine à gaz** (règle ADR-002) : le minimum de code à écrire et à maintenir.
4. **URLs communicables.** Un lien d'abonnement se dicte au téléphone, s'imprime sur un
   flyer, se colle dans une newsletter. Sa forme compte.
5. **Ne pas réécrire un générateur ICS.** VTIMEZONE, règles de passage à l'heure d'été,
   échappement RFC 5545, pliage des lignes à 75 octets : un terrain à pièges où TEC a
   déjà payé le prix de l'apprentissage.

## Décision

**Un endpoint ICS propre au plugin**, alimenté par `jcmv_categorie_age`, déléguant la
génération à TEC.

### Schéma d'URL

```
/agenda/tous.ics                          tous les événements
/agenda/poussin.ics                       une catégorie
/agenda/poussin+benjamin.ics              plusieurs catégories
```

- **Séparateur `+`.** Le tiret est exclu : `mini-poussin` en contient déjà un. La virgule
  est acceptée en alias. Dans un segment de chemin, le `+` est littéral — l'ambiguïté avec
  l'espace n'existe que dans une chaîne de requête.
- **`tous` est un slug réservé** : il ne pourra pas désigner une catégorie d'âge.
- **Slug inconnu → 404**, jamais un flux vide. Un calendrier vide sans erreur est un
  symptôme muet, le pire des modes de défaillance pour une fonctionnalité qu'on ne
  consulte pas activement.
- **Base `/agenda/`** : courte, dictable, immédiatement compréhensible par un parent — et
  elle fait écho à « Google Agenda », que les familles ont sous les yeux au moment de
  coller le lien. Surtout, elle est indépendante du slug TEC, qui a déjà changé une fois
  (`events` → `evenements`) et dont un nouveau changement casserait tous les abonnements
  en cours si les flux en dépendaient.
- **`/webcal/` a été écarté** : le lien d'abonnement s'écrirait
  `webcal://jcmv.fr/webcal/poussin.ics`, où le même mot désigne à la fois le protocole et
  un segment de chemin servi en `https`. Bégaiement disgracieux et trompeur, pour un mot
  qui ne dit rien à un parent.
- La règle est enregistrée en priorité `top`, donc évaluée avant la résolution des pages.
  Une page de slug `agenda` n'entrerait donc pas en conflit avec `/agenda/poussin.ics`,
  qui est une URL distincte ; seule une page enfant `/agenda/quelque-chose/` finirait
  masquée. Vérifier que le slug est libre relève de l'hygiène, pas du prérequis bloquant.

### Sélection des événements

- CPT `tribe_events` uniquement.
- `tax_query` sur `jcmv_categorie_age`, en relation `OR` entre les slugs demandés et un
  `NOT EXISTS` : **un événement sans catégorie d'âge apparaît dans tous les flux**,
  l'absence de catégorie valant « tous publics ». C'est le cas de l'assemblée générale, du
  gala ou d'un tournoi ouvert. Le bureau n'a aucune saisie supplémentaire à faire ; en
  contrepartie, un oubli de cochage rend l'événement visible partout — inconvénient jugé
  moindre que le risque inverse, où les familles abonnées à une seule catégorie
  rateraient les temps forts du club.
- **Horizon : à venir sans limite, plus six mois de passé.** Fenêtre glissante,
  indépendante du découpage en saisons — elle ne se vide donc pas à cheval sur deux
  saisons si les événements de la suivante ne sont pas encore saisis. Les familles gardent
  un historique récent.
- `posts_per_page = -1` : le plafond de 30 de TEC est contourné par construction, puisque
  la requête est la nôtre.

### Génération et livraison

- Délégation à `tribe( 'tec.iCal' )->generate_ical_feed( $ids, false )`. Aucun générateur
  ICS maison.
- En-têtes maîtrisés par le plugin : `Content-Type: text/calendar; charset=UTF-8`,
  `Content-Disposition`, `Cache-Control` avec `max-age` d'une heure, `Last-Modified` et
  `ETag` calés sur le `post_modified` le plus récent du jeu, `X-Robots-Tag: noindex`.
- `X-WR-CALNAME` explicite via le filtre `tribe_ical_feed_calname` — « JCMV — Poussin,
  Benjamin », « JCMV — Tous les événements ».
- **Dégradation propre** : 404 si TEC est absent ou désactivé, sur le modèle du
  `post_type_exists()` déjà pratiqué dans `Taxonomies.php:84`.

### Interface : bloc `jcmv/abonnement-calendrier`

- Bloc dynamique du plugin, catégorie « JCMV », **sans build** — même convention que les
  blocs existants : globales `window.wp.*` et `index.asset.php` écrit à la main
  (cf. `blocks/partenaires/`). L'interactivité étant purement front, l'`index.js`
  d'éditeur se limite à un placeholder.
- **Placé dans `templates/archive-events.html`, en dehors du conteneur
  `tec/archive-events`** : la navigation entre mois de Views V2 se fait en AJAX et
  remplace ce conteneur.
- **Pas sur la fiche d'un événement seul** : TEC y pose déjà ses propres boutons
  « Ajouter au calendrier ». Deux dispositifs concurrents sur la même page brouilleraient
  la distinction entre abonnement et ajout unitaire.
- **Les neuf catégories sont affichées, y compris celles sans événement à venir.** La
  liste reste stable et le référentiel lisible ; un abonnement pris en début de saison se
  remplira à mesure des saisies. Masquer les catégories vides aurait fait disparaître des
  entrées d'une visite à l'autre.
- **Toutes les cases cochées par défaut**, ce qui correspond au flux global. La personne
  qui ne comprend pas l'interface et clique directement obtient tous les événements — le
  résultat le plus utile. Partir de zéro case cochée rendrait le bouton inerte au
  chargement.
- **Deux sorties recalculées en direct** : un bouton **S'abonner** en `webcal://`, qui
  ouvre l'application calendrier sur iOS et Outlook, et l'URL `https://` avec un bouton
  copier, que Google Agenda réclame dans son champ « À partir de l'URL ». C'est la raison
  principale de préférer un bloc à une liste de liens statiques.
- **Fonctionne sans JavaScript** : le `render.php` émet le lien du flux global côté
  serveur, le script ne fait que l'affiner.
- **RGAA** : `fieldset` et `legend` pour le groupe de cases, zone `aria-live` sur l'URL
  générée pour que sa mise à jour soit annoncée.
- Le bloc porte une **mention explicite du délai de rafraîchissement** (voir Conséquences).

### Périmètre exclu

- **Les créneaux hebdomadaires** (CPT `cours`) ne sont pas dans les flux. TEC gratuit
  n'ayant pas la récurrence, ils devraient être générés en `RRULE` depuis les créneaux et
  bornés par les dates de saison, avec les vacances scolaires et les changements en cours
  d'année à gérer. C'est un second moteur de génération, à traiter dans un ADR distinct
  s'il est décidé.
- **Les annulations** se font par suppression de l'événement, qui disparaît alors du flux
  au rafraîchissement suivant côté client. Pas de `STATUS:CANCELLED`, qui supposerait un
  champ à cocher et une convention de saisie supplémentaires.

## Options considérées

### Option A : basculer les événements sur `tribe_events_cat`

**Pour :** zéro ligne de code. Flux global, flux par catégorie, boutons « Ajouter au
calendrier » et liens `webcal://` fonctionnent immédiatement.
**Contre :** crée un **second référentiel d'âges** à saisir et à tenir synchronisé à la
main. Les neuf termes existants seraient à recopier, puis à maintenir en double à chaque
ajout — alors qu'on a la preuve que le bureau en ajoute. Deux listes qui divergent, et
personne ne s'en aperçoit avant qu'un flux revienne vide. Contraire à l'ADR-001, qui a
posé `jcmv_categorie_age` comme référentiel unique.

### Option B : rendre `jcmv_categorie_age` publique et compter sur les archives

Passer la taxonomie en `public => true` et `rewrite => true` pour obtenir des archives
`/categorie-age/poussin/`, puis y accoler `?ical=1`.

**Cela produirait effectivement un flux**, mais par effet de bord : `do_ical_template()`
est branché sur `template_redirect` de façon globale et ne teste que la présence de
`?ical` dans la requête (`iCal.php:57` et `235-244`), sans vérifier qu'on se trouve sur
une vue TEC.

**Deux obstacles sont structurels, et à eux seuls disqualifient l'option :**

1. **`/categorie-age/poussin+benjamin/` ferait l'inverse du besoin.** Dans les query vars
   de taxonomie natives de WordPress, le `+` signifie **ET**. On obtiendrait les
   événements portant *à la fois* les deux catégories — un ensemble presque toujours
   vide, alors que la famille à deux enfants attend leur réunion.
2. **« Sans catégorie = tous publics » devient inexprimable.** Une archive de terme ne
   peut pas, par construction, inclure les événements qui n'ont aucun terme. L'assemblée
   générale et le gala disparaîtraient de tous les flux par catégorie.

**S'y ajoutent des défauts de second rang :** `$args['eventDisplay']` n'existe pas hors
vue TEC (`iCal.php:432`), d'où un *Undefined array key* dans `debug.log` à chaque appel
sur PHP 8.3 ; le plafond de 30 continue de s'appliquer, `$wp_query` ayant un
`posts_per_page` positif ; la fenêtre temporelle échappe à tout contrôle.

**Enfin, le coût collatéral n'est pas nul.** Rendre la taxonomie publique crée neuf URL
indexables. `jcmv_cours` étant `public => false`, ses contenus n'y fuiteraient pas — WordPress
construit le `post_type` d'une archive via `get_post_types(['exclude_from_search' => false])` —
mais `jcmv-theme` n'ayant ni `taxonomy-jcmv_categorie_age.html` ni `archive.html`, le
rendu retomberait sur celui de TT25 : des événements en cartes d'articles génériques, sans
date, sans lieu, sans tri chronologique ni filtrage des événements passés. Il faudrait
donc écrire un template et poser un `noindex` pour publier des pages dont le besoin n'a
jamais été exprimé.

Rendre `jcmv_categorie_age` publique reste envisageable **pour elle-même**, si un besoin
de navigation « voir les événements Poussin » émerge un jour. C'est alors un arbitrage
distinct — template dédié, `noindex`, et décision assumée de rendre public un référentiel
que l'ADR-001 avait conçu comme interne — et il est orthogonal aux flux.

### Option C : endpoint ICS propre au plugin (retenue)

**Pour :** référentiel unique préservé, aucune saisie nouvelle pour le bureau, URLs
stables et lisibles, maîtrise complète de l'horizon temporel et du volume, multi-catégories
possible — impossible en A. Découplé du slug TEC.
**Contre :** environ cent lignes à écrire et à maintenir, plus un bloc. Introduit une
règle de réécriture, donc une contrainte de flush.

### Option D : générateur ICS entièrement maison

**Pour :** indépendance totale vis-à-vis de TEC.
**Contre :** réécrire VTIMEZONE, la gestion des fuseaux et l'échappement RFC 5545 pour
un bénéfice nul, alors que `generate_ical_feed()` accepte une liste de posts et sait
rendre sans en-têtes. Écartée sans hésitation.

## Analyse des compromis

L'option A est objectivement la moins chère à écrire, et c'est le seul argument en sa
faveur. Le raisonnement qui la disqualifie n'est pas technique mais organisationnel : le
coût réel d'une fonctionnalité de club bénévole se paie en saisie et en oublis, pas en
lignes de code. Un dispositif qui exige de cocher la même information à deux endroits
finira désynchronisé, et le symptôme — un flux d'abonnement incomplet — est invisible
depuis l'administration.

L'option C déplace ce coût vers du code écrit une fois, testé une fois, et qui ne demande
ensuite aucun geste au bureau : les catégories d'âge se saisissent comme aujourd'hui, et
les flux suivent. Elle accepte en échange une dépendance à `generate_ical_feed()`,
concentrée en un seul point d'appel et donc facile à réauditer.

## Conséquences

### Positives

- Un seul référentiel de catégories d'âge, conforme à l'ADR-001.
- Aucun changement dans les habitudes de saisie du bureau.
- Flux multi-catégories, qui répond au cas réel de la famille à plusieurs enfants.
- URLs découplées du slug TEC : un futur changement de `evenements` ne cassera aucun
  abonnement.
- Le bloc étant dynamique, l'ajout d'une catégorie d'âge apparaît automatiquement dans
  l'interface d'abonnement. Aucun lien en dur à maintenir.

### Négatives et risques

- **Dépendance à `generate_ical_feed()`**, méthode publique mais non contractuelle de TEC.
  À revérifier à chaque montée de version majeure. Le risque est borné : un seul point
  d'appel dans tout le plugin.
- **Le rafraîchissement côté client est lent et opaque.** Google Agenda relit les flux
  externes selon sa propre logique, souvent avec douze à vingt-quatre heures de retard ;
  Apple est configurable, Outlook tourne autour de trois heures. **Le flux ICS est un
  canal de fond, jamais le canal d'une information urgente.** Un changement d'horaire à
  J-2 doit passer par le mail et le site. Cette réserve doit être écrite dans le bloc, pas
  seulement dans cet ADR.
- **Règles de réécriture à purger.** Un flush au `register_activation_hook` existant
  (`wp-jcmv.php:47`) ne suffit pas : il ne se déclenche pas lors d'une mise à jour via
  l'updater. Un second déclenchement sur comparaison de version stockée en option est
  nécessaire, sans quoi les flux répondraient 404 après la montée de version jusqu'à un
  passage manuel par *Réglages → Permaliens*.
- **Les slugs de termes deviennent une surface publique.** Renommer un terme ne change pas
  son slug — sans danger. Mais le supprimer puis le recréer en génère un nouveau, et casse
  silencieusement les abonnements pris. À signaler au bureau dans la documentation
  d'exploitation.
- La base `/agenda/` déconseille de créer une arborescence de pages sous ce slug, dont les
  enfants seraient masqués par la règle de réécriture.

## Actions

- [ ] Vérifier que `/agenda` est libre en production (hygiène, non bloquant : voir la
      note sur la priorité `top`).
- [ ] Implémenter le routage, la sélection et la livraison dans `wp-jcmv`.
- [ ] Implémenter le bloc `jcmv/abonnement-calendrier` et l'enregistrer dans
      `src/Front/Blocks.php`.
- [ ] Insérer le bloc dans `templates/archive-events.html`, hors du conteneur
      `tec/archive-events`.
- [ ] Ajouter le flush des règles de réécriture sur comparaison de version.
- [ ] Passer `Version:` et `JCMV_VERSION` en 0.3.0 (`wp-jcmv.php:5` et `:26`) — le
      workflow `release-plugin.yml` vérifie les deux.
- [ ] Valider un flux produit avec un validateur ICS, puis un abonnement réel sur iOS,
      Google Agenda et Outlook.
- [ ] Documenter pour le bureau : délai de rafraîchissement, et interdiction de supprimer
      puis recréer un terme de catégorie d'âge.
