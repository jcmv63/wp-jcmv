# ADR-002 : Architecture de l'administration — page « Saisons » en REST + JS, natif partout ailleurs

**Statut :** Accepté
**Date :** 2026-07-18
**Décideurs :** Alban (développeur)
**Dépend de :** ADR-001 (répartition du stockage)

## Contexte

L'ADR-001 place créneaux et tarifs dans des tables custom versionnées par saison. Ces
données n'ont donc pas d'écran d'administration natif : une page d'admin dédiée est à
construire. Elle porte l'essentiel du travail annuel du bureau : sélecteur de saison,
workflow « préparer la saison suivante » (duplication) / « activer », édition des frais
licence/adhésion, et la grille créneaux + tarifs par cours.

Forces en présence :

- **Usage saisonnier concentré** : édition intensive quelques jours par an (préparation de
  saison), consultations et retouches ponctuelles le reste du temps.
- **Utilisateurs non techniques** : l'ergonomie de cette page conditionne l'autonomie du
  bureau (exigence n° 1 de l'ADR-001). La POC Next.js a établi une référence d'UX :
  édition en grille, ajout/suppression de lignes, feedback immédiat.
- **Développeur sénior PHP et JavaScript**, peu à l'aise en TypeScript ; « pas peur de
  faire du code », mais règle explicite : **pas d'usine à gaz**.
- **Déploiement** : hébergement mutualisé ; une CI GitHub est disponible et souhaitée.
- **Phase 2 pressentie** (portail membres) qui consommera une API.

## Décision

Administration à **trois niveaux, du plus natif au plus riche** — avec pour garde-fou :
*si un besoin d'admin peut être servi par un écran natif ou un formulaire PHP, il n'entre
pas dans l'app JS ; l'app ne grandit que si le besoin est intrinsèquement interactif.*

1. **Écrans natifs tels quels** : cours, lieux, termes de taxonomies, événements (TEC),
   articles, médias. Aucune UI custom (au plus une metabox si un champ apparaît).
2. **Formulaires PHP classiques pour le paramétrage** : champs `age_min`/`age_max` sur
   l'écran d'édition des termes (hooks `{taxonomy}_edit_form_fields`), éventuelle page de
   réglages via la Settings API. Écrans statiques par nature — du React ici serait de la
   sur-ingénierie.
3. **Une seule application REST + JS : la page « Saisons »** — sélecteur de saison,
   workflow préparer/dupliquer/activer, frais et mentions licence/adhésion, grille
   créneaux/tarifs éditable.

### Choix techniques de l'app

- **Stack** : `@wordpress/element` (le React embarqué de WordPress), `@wordpress/components`
  et `api-fetch`. Pas de framework front supplémentaire à shipper ; cohérence visuelle
  avec l'admin ; JSX sans TypeScript. Build par `@wordpress/scripts`.
- **API REST minimale et fermée** : namespace `jcmv/v1`, uniquement ce que l'app
  consomme — `seasons` (CRUD + actions `prepare`, `activate`), `schedules` et `pricing`
  (sauvegarde par lot cours × saison, pour coller à l'édition en grille). Les selects
  cours/lieux sont peuplés via le REST natif des CPT (`show_in_rest`).
- **Sécurité** : `permission_callback` sur une capability custom `manage_jcmv_club`
  accordée au rôle du bureau ; nonce REST géré par `api-fetch` ; aucun endpoint public.
- **Affichage public sans REST** : les templates du thème appellent directement les
  repositories PHP (saison active → cours par discipline → créneaux, tarifs). Rendu
  serveur, cacheable ; le REST ne sert que l'admin.

### Build et livraison

- GitHub Action sur tag : `npm ci && npm run build`, zip du plugin (assets compilés, sans
  sources JS ni `node_modules`), attaché à une release GitHub.
- Mise à jour depuis l'admin WordPress via la bibliothèque **Plugin Update Checker**
  (YahnisElsts, open source) pointée sur les releases : le bureau voit « Mise à jour
  disponible » comme pour n'importe quel plugin. Ce pipeline sert aussi la gouvernance :
  processus de release documenté, reprenable par un successeur.
- Le dossier `admin-ui/` (sources JS) est dans git ; les bundles ne sont pas commités.

### Structure du plugin

```
wp-jcmv/
├── src/
│   ├── Domain/        # repositories, entités, seed des référentiels
│   ├── Registration/  # CPT, taxonomies, capabilities
│   ├── Rest/          # controllers jcmv/v1
│   └── Admin/         # bootstrap page Saisons, champs de termes, settings
├── admin-ui/          # app JS (buildée en CI, non commitée en bundle)
└── templates/         # helpers d'affichage pour le thème
```

## Options considérées

### Option A : formulaires PHP purs (admin-post.php + PRG)

| Dimension | Évaluation |
|---|---|
| Complexité | Faible |
| Coût de possession | Minimal (zéro build, zéro dépendance) |
| UX | Rigide (rechargement par action) |
| Pérennité | Excellente |

**Pour :** zéro build ; sécurité par défaut (cookies + nonces) ; pattern le plus documenté
de l'écosystème (fiable aussi pour l'assistance par LLM) ; débogage trivial.
**Contre :** la grille créneaux/tarifs et le workflow saison sont intrinsèquement
interactifs — en PHP pur, cela devient une constellation de petits formulaires avec
rechargements, pénible sur mobile ; repeupler les formulaires après erreur est verbeux.

### Option B : formulaires PHP + enhancement léger (Alpine.js, sans build)

**Pour :** garde le zéro-build en corrigeant 90 % de la rigidité (ajout/suppression de
lignes côté client).
**Contre :** l'état client (lignes ajoutées, non sauvegardées) finit par être re-sérialisé
dans des formulaires PHP — à la complexité de la grille de la POC, on réimplémente un
mini-framework à la main. Position médiane instable pour ce cas précis.

### Option C : REST + JS généralisé (toute l'admin du module en SPA)

**Pour :** UX uniforme et riche partout.
**Contre :** viole le garde-fou anti-usine à gaz : cours, lieux et termes ont déjà des
écrans natifs corrects ; les réécrire est du coût de possession sans bénéfice. Plus de
surface de sécurité et de code à maintenir seul.

### Option D : REST + JS confiné à la page « Saisons » (retenue)

| Dimension | Évaluation |
|---|---|
| Complexité | Moyenne, concentrée sur un seul écran |
| Coût de possession | Un build + une app, périmètre fermé |
| UX | Au niveau de la référence POC là où ça compte |
| Pérennité | Bonne (stack maintenu par WordPress core) |

**Pour :** l'interactivité est investie uniquement là où elle paie ; endpoints réutilisables
en phase 2 ; stack aligné sur Gutenberg (maintenu avec le core, pas de framework tiers).
**Contre :** deux contrats à maintenir synchrones (endpoints ↔ client) ; churn de
l'écosystème `@wordpress/scripts`/`@wordpress/components` ; un pipeline de build à
posséder — largement neutralisé par la CI et le mécanisme de release, mais une pièce
d'infrastructure de plus qui peut casser (montée de version Node, par exemple).

## Analyse des compromis

Le facteur limitant du projet n'est pas la capacité à construire mais **l'énergie de
bénévole disponible pour maintenir dans cinq ans**. L'option D est celle qui minimise le
code total à UX égale : l'app JS couvre le seul écran où l'interactivité est indispensable,
le natif couvre le reste gratuitement. L'argument « le build complique le déploiement »
initialement retenu contre REST+JS a été requalifié : avec la GitHub Action et Plugin
Update Checker, le build est un coût de montage initial, pas un coût récurrent — et le
pipeline de release bénéficierait de toute façon à un plugin full-PHP. Les cons résiduels
(double contrat, churn JS) sont jugés acceptables au regard de l'exigence d'autonomie du
bureau (ADR-001, force n° 1) que sert directement la qualité de cette page.

## Conséquences

**Devient plus simple :**

- Le workflow annuel du bureau (préparer, ajuster, activer une saison) tient dans un
  écran unique, ergonomique, utilisable sur mobile.
- La phase 2 (portail membres) trouvera un namespace REST, des capabilities et des
  repositories déjà en place.
- Les mises à jour du plugin se font en un clic depuis l'admin.

**Devient plus contraignant :**

- Un build et une CI à maintenir (Node, `@wordpress/scripts`) ; pannes rares mais
  possibles à documenter.
- Les endpoints REST doivent être testés (permissions, validation) — surface absente
  d'une solution full-PHP.
- Un successeur devra connaître PHP **et** le stack JS de WordPress pour faire évoluer la
  page Saisons (mais peut maintenir tout le reste en PHP seul).

**À revisiter :**

- Si `@wordpress/components` évolue défavorablement, l'app peut migrer vers du Preact/
  React vanilla sans toucher aux endpoints.
- L'ouverture d'endpoints publics (lecture seule) si un front headless ou une app mobile
  apparaissait — non prévu à ce jour.

## Actions

1. [ ] Endpoints `jcmv/v1` : `seasons` (+ actions `prepare`/`activate`), `schedules`,
   `pricing` (batch), avec `permission_callback` sur `manage_jcmv_club`.
2. [ ] Capability `manage_jcmv_club` + attribution au rôle du bureau.
3. [ ] App « Saisons » (`admin-ui/`) : sélecteur de saison, workflow, frais, grille
   créneaux/tarifs — `@wordpress/element` + `@wordpress/components`.
4. [ ] Champs de paramétrage en formulaires PHP (bornes d'âge des termes).
5. [ ] GitHub Action de release (build + zip) et intégration Plugin Update Checker.
6. [ ] Tests des endpoints (permissions, validation, transactions saison).
7. [ ] Documentation « reprise du projet » (README : architecture, pipeline, comment
   livrer une version sans le développeur d'origine).
