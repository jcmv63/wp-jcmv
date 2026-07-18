# ADR-001 : Répartition du stockage des données du plugin `wp-jcmv`

**Statut :** Accepté
**Date :** 2026-07-18
**Décideurs :** Alban (développeur)
**Périmètre :** architecture du plugin `wp-jcmv` (module « gestion du club »). Le choix de WordPress lui-même et le choix d'hébergement ne sont pas couverts par cet ADR.

## Contexte

Le site du Judo Club des Martres de Veyre (JCMV) est construit sur WordPress. Au-delà du
blog (articles natifs), de l'agenda (plugin The Events Calendar) et des galeries, le club a
besoin d'un module de gestion : cours proposés (judo, cross-training, self-défense),
créneaux horaires, tarifs, lieux (dojos), catégories d'âge FFJDA, le tout **versionné par
saison sportive** (septembre → août).

Une POC préalable (Next.js + Neon/Postgres) a validé un modèle relationnel dont les
enseignements structurent cet ADR :

- Le **cours** est le pivot : rattaché à une discipline, lié à 0..n catégories d'âge.
- Horaires (`schedules`) et tarifs (`pricing`) sont versionnés par **saison**, avec un
  cycle de vie `draft → active → archived`, une seule saison `active`, et une duplication
  automatique lors de la préparation de la saison suivante.
- Les montants licence FFJDA et adhésion club sont portés par la saison.
- Les années de naissance affichées se **calculent** depuis `start_year` de la saison et
  les bornes d'âge des catégories — jamais stockées, jamais dérivées de la date du jour.

### Forces en présence

1. **Administrable par le bureau (exigence).** Le site doit rester entièrement gérable par
   des bénévoles non techniques, y compris si le développeur n'est plus joignable dans
   5 ans (changement d'adresse d'un dojo, nouvelle discipline, redécoupage FFJDA des
   catégories d'âge). Cette exigence prime sur le confort de versionnement git du
   développeur.
2. **Intégration calendrier.** Les événements (The Events Calendar) doivent pouvoir être
   liés aux catégories d'âge (« compétition cadets »), avec des flux ICS filtrables par
   catégorie auxquels les membres s'abonnent.
3. **Contraintes techniques.** MySQL/MariaDB sur hébergement mutualisé ; dépendances
   gratuites, open source et maintenues uniquement (pas d'ACF Pro) ; développeur sénior
   PHP/JS, à l'aise avec le SQL, peu attiré par les conventions du core WordPress.
4. **Échelle.** Quelques dizaines de cours/créneaux/tarifs, deux dojos, neuf catégories.
   La performance n'est pas un critère discriminant ; l'intégrité et la maintenabilité le
   sont.

## Décision

Stockage **hybride à trois niveaux**, selon la nature de la donnée :

| Niveau | Stockage | Données |
|---|---|---|
| Contenu administrable | CPT + taxonomies (natif WP) | cours, lieux, disciplines, catégories d'âge |
| Relationnel saisonnier | Tables custom (`$wpdb`) | saisons, créneaux, tarifs |
| Structure | Code du plugin | schémas, seed des référentiels, règles métier, workflows |

### Correspondance exacte POC → WordPress

| POC (Neon/Postgres) | Destination WordPress | Notes |
|---|---|---|
| `activities` | Taxonomie `jcmv_discipline` sur le CPT cours | Seedée à l'activation (judo, cross-training, self-défense) ; le bureau peut créer un terme (ex. taïso) sans développeur |
| `age_categories` | Taxonomie `jcmv_categorie_age` + term meta `age_min` / `age_max` | Seedée depuis le référentiel FFJDA embarqué dans le code ; bornes d'âge éditables ensuite dans l'admin (champs ajoutés à l'écran du terme). Slugs immuables |
| `course_age_categories` | `wp_term_relationships` | Liaison n-n native des taxonomies |
| `courses` | CPT `jcmv_cours` | `name` → `post_title` ; `sort_order` → `menu_order` ; statut publish/draft = visible/masqué |
| `locations` | CPT `jcmv_lieu` | `title` → `post_title` ; nom et adresse en postmeta ; **dépublier = désactiver** (jamais de suppression d'un lieu référencé — un hook refuse la suppression si des créneaux l'utilisent, y compris en saison archivée) |
| `seasons` | Table `wp_jcmv_season` | `start_year` unique, `status`, `licence_amount`, `adhesion_amount` + colonnes `licence_note` / `adhesion_note` (mentions « Paiement séparé », « après déduction fiscale » — en base et non en dur, contrairement à la POC) |
| `schedules` | Table `wp_jcmv_schedule` | Identique à la POC ; `course_id` et `location_id` = IDs `wp_posts` (intégrité applicative) |
| `pricing` | Table `wp_jcmv_pricing` | Identique à la POC (label, amount, period, note, sort_order) |
| `event_age_categories` | `wp_term_relationships` | La taxonomie `jcmv_categorie_age` est aussi enregistrée sur le CPT de The Events Calendar |

Particularités MySQL/WordPress :

- L'index unique partiel Postgres (« une seule saison active ») n'existe pas en MySQL :
  la contrainte est garantie par transaction applicative dans `SeasonRepository`
  (`activateSeason` archive l'ancienne et active la nouvelle atomiquement).
- Les tables custom sont créées via `dbDelta()` avec une option `jcmv_db_version`
  comparée au chargement pour rejouer les migrations de schéma.
- Aucune clé étrangère SQL vers `wp_posts` (convention WordPress) : intégrité applicative
  dans les repositories, `$wpdb->prepare()` systématique.

## Options considérées

### Option A : tout en CPT + postmeta

| Dimension | Évaluation |
|---|---|
| Complexité | Moyenne (modélisation contorsionnée) |
| Coût | Nul |
| Intégrité des données | Faible (EAV, pas de types ni contraintes) |
| Familiarité | Forte (pattern WP archi-documenté) |

**Pour :** 100 % natif — écrans d'admin, REST, révisions, permissions gratuits partout.
**Contre :** le versionnement par saison (duplication schedules/pricing, unicité de la
saison active) n'a aucun équivalent natif ; requêtes multi-critères sur postmeta laides
(un JOIN par champ) ; tout est `longtext` non typé ; nécessiterait des repeaters (ACF Pro,
payant — exclu).

### Option B : tout en tables custom

| Dimension | Évaluation |
|---|---|
| Complexité | Élevée (tout l'admin à construire) |
| Coût | Nul en licence, élevé en développement |
| Intégrité des données | Forte (SQL typé, transactions InnoDB) |
| Familiarité | Forte pour le développeur, nulle pour l'écosystème WP |

**Pour :** transposition directe du schéma de la POC ; SQL propre.
**Contre :** les données deviennent invisibles pour tout l'écosystème WordPress — pas
d'écrans d'admin, pas de REST, pas de liaison possible avec The Events Calendar (qui exige
une taxonomie), pas d'exposition MCP. WordPress se réduit à un système de login : autant
garder la POC Next+Neon.

### Option C : hybride avec référentiels en code (tableaux PHP)

Variante de l'option retenue où disciplines, catégories d'âge et lieux vivraient en
tableaux PHP versionnés dans git (clé `enabled` pour activer/désactiver), modifiables par
commit.

**Pour :** référentiels diffables, revus en PR, identiques entre environnements ; pas
d'« état en base » hors git.
**Contre :** **viole l'exigence n° 1**. Un changement d'adresse de dojo ou un redécoupage
FFJDA exigerait un développeur. Écartée pour cette raison, après avoir été sérieusement
envisagée : le compromis retenu (seed en code + propriété admin ensuite) en conserve le
principal bénéfice — un référentiel initial reproductible — sans le coût de gouvernance.

### Option D : hybride CPT/taxonomies + tables custom (retenue)

| Dimension | Évaluation |
|---|---|
| Complexité | Moyenne (deux mécanismes de persistance à connaître) |
| Coût | Nul |
| Intégrité des données | Forte là où ça compte (tables), suffisante ailleurs |
| Familiarité | Les deux patterns sont standards (WooCommerce fait de même) |

**Pour :** chaque donnée est dans le mécanisme qui la sert le mieux ; écrans natifs pour le
contenu ; intégration The Events Calendar gratuite via la taxonomie partagée ; SQL propre
pour le versionnement saisonnier ; aucune dépendance payante.
**Contre :** frontière à documenter et à respecter ; intégrité applicative entre les deux
mondes (IDs `wp_posts` référencés depuis les tables).

## Analyse des compromis

Le critère de découpage qui départage les options : **ce qui est du contenu à identité
éditoriale** (un cours, un lieu — édité à l'unité, listable, connectable) **va en CPT/
taxonomie ; ce qui est de la donnée relationnelle en grille** (des lignes créneaux/tarifs
sans identité propre, versionnées par saison) **va en tables custom**. L'option A force le
relationnel dans un moule éditorial ; l'option B force le contenu hors de l'écosystème ;
l'option C optimise pour le développeur au détriment du bureau. À l'échelle du club,
l'argument performance (EAV vs SQL) est négligeable — ce sont l'intégrité, l'ergonomie
d'administration et la gouvernance à long terme qui tranchent.

## Conséquences

**Devient plus simple :**

- Cours et lieux gérés dans des écrans WordPress standards, sans code d'UI.
- Événements taguables par catégorie d'âge dans l'admin TEC ; flux ICS filtrables.
- Le bureau est autonome sur la totalité des données, référentiels compris.
- Le schéma de 8 tables de la POC se réduit à 3 tables custom.
- Cours et lieux exposés en REST (`show_in_rest`) et via MCP sans travail supplémentaire.

**Devient plus contraignant :**

- Intégrité référentielle applicative entre tables custom et `wp_posts` : à couvrir par
  les repositories et des hooks (suppression de lieu/cours refusée si référencé).
- Migrations de schéma à gérer manuellement (`dbDelta` + `jcmv_db_version`).
- Les référentiels seedés ne sont plus versionnés dans git après l'activation : la
  routine de seed doit être **idempotente** (créer les termes manquants, ne jamais
  écraser les modifications de l'admin, ne jamais supprimer un terme utilisé).
- Deux patterns de persistance à documenter pour un éventuel successeur.

**À revisiter :**

- **Phase 2 (judokas et portail membres)** : le CPT privé `jcmv_judoka` + comptes
  utilisateurs pressenti fera l'objet d'un ADR dédié (enjeux RGPD spécifiques — données
  de mineurs).
- Si le club dépasse un jour l'échelle actuelle (multi-clubs, centaines de cours), la
  frontière CPT/tables pourra être déplacée sans casser le modèle.

## Actions

1. [ ] Scaffolding du plugin `wp-jcmv` (structure `src/Domain`, `src/Registration`,
   `src/Rest`, `src/Admin` — voir ADR-002).
2. [ ] Enregistrement des CPT `jcmv_cours`, `jcmv_lieu` et des taxonomies
   `jcmv_discipline`, `jcmv_categorie_age` (attachée aussi au CPT de TEC).
3. [ ] Création des tables via `dbDelta()` + mécanisme `jcmv_db_version`.
4. [ ] Seed idempotent des référentiels (disciplines, catégories FFJDA avec bornes d'âge).
5. [ ] Champs `age_min`/`age_max` sur l'écran d'édition des termes `jcmv_categorie_age`.
6. [ ] Repositories (`SeasonRepository`, `ScheduleRepository`, `PricingRepository`) avec
   transactions pour le cycle de vie des saisons.
7. [ ] Hooks d'intégrité (refus de suppression d'un lieu/cours référencé).
