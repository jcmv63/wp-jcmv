# ADR-005 : Boutique — catalogue vitrine des produits floqués dans `wp-jcmv`

**Statut :** Accepté — implémenté sur la branche `boutique` (plugin 0.4.0)
**Date :** 2026-08-07
**Décideurs :** Alban (développeur), bureau du club (à valider)
**Périmètre :** module « boutique » du plugin `wp-jcmv` (branche `boutique`). Le paiement
en ligne, la gestion de stock et l'expédition ne sont **pas** couverts par cet ADR — ils
font l'objet d'une décision ultérieure (voir « Ce qui n'est pas décidé ici »).

## Contexte

Le bureau souhaite exposer sur le site les articles de sport floqués au logo du club
(textile, judogis, accessoires). Le cadrage recueilli :

- **Ce n'est pas l'activité principale du site.** Le module ne doit ni dominer la
  navigation, ni peser sur les performances des pages existantes.
- **Pas de gestion d'expédition.** Les produits sont retirés au dojo.
- **Paiement en ligne éventuellement plus tard**, sans engagement de calendrier.
- L'association **vend déjà du textile** (l'activité existe, le cadre associatif et
  fiscal est en place) et **dispose d'un compte HelloAsso** — écarté du périmètre pour
  l'instant à la demande du développeur.

### Forces en présence

1. **Administrable par le bureau** (exigence n° 1, héritée de l'ADR-001). Ajouter un
   produit, changer un prix ou masquer une référence épuisée doit se faire depuis
   l'admin, par un bénévole, sans développeur.
2. **Pas d'usine à gaz** (règle ADR-002). Une quinzaine de références, quelques mises à
   jour par saison : le volume ne justifie aucune infrastructure e-commerce.
3. **Hébergement mutualisé OVH.** Chaque plugin lourd se paie en temps de réponse sur
   toutes les pages, y compris celles qui n'ont rien à voir avec la boutique.
4. **Réversibilité.** Le paiement en ligne pouvant arriver plus tard, le modèle de
   données doit pouvoir accueillir un lien de paiement ou une page produit publique sans
   refonte ni migration de contenu.
5. **Cas métier discriminant : le judogi.** Contrairement au textile courant, son prix
   varie selon la taille. Le modèle doit porter une grille tarifaire, pas un prix unique.

## Décision

**Catalogue vitrine natif dans `wp-jcmv`.** Pas de WooCommerce, pas de panier, pas de
transaction. Le site présente les produits ; la vente se conclut hors ligne, au dojo.

### Modèle de données (application de la grille ADR-001)

| Niveau | Stockage | Données |
|---|---|---|
| Contenu administrable | CPT `jcmv_produit` + taxonomie `jcmv_categorie_produit` | produits, catégories |
| Relationnel | table `wp_jcmv_produit_tarif` | grille de prix par taille |
| Structure | code du plugin | seed des catégories, règles d'affichage et de tri |

#### CPT `jcmv_produit`

Libellé d'admin : **Produits**. Le terme « Articles » est écarté délibérément — c'est le
nom des posts natifs dans WordPress en français, deux entrées homonymes dans le menu
seraient une source d'erreur permanente pour le bureau.

| Réglage | Valeur | Motif |
|---|---|---|
| `public` | `false` | Le catalogue s'affiche par un bloc ; pas de page par produit à ce stade |
| `show_in_menu` | `'jcmv-club'` | Rattaché au menu JCMV, comme Cours / Lieux / Partenaires |
| `show_in_rest` | `true` | Éditeur de blocs actif pour la description, cohérence avec l'existant |
| `supports` | `title`, `editor`, `thumbnail`, `page-attributes` | `menu_order` pilote l'ordre de la grille |
| `has_archive`, `rewrite` | `false` | À rebasculer le jour d'une page produit publique |

Contrairement à `jcmv_cours` / `jcmv_lieu` / `jcmv_partenaire`, **l'éditeur de blocs
n'est pas désactivé** : la description d'un produit est du contenu rédigé (matière,
grammage, conseils de taille) qui deviendra le corps de la page produit publique le jour
venu. Le CPT est donc **retiré du filtre `use_block_editor_for_post_type`**.

#### Postmeta

| Meta | Type | Rôle |
|---|---|---|
| `jcmv_produit_prix` | number | Prix unique. Ignoré si une grille tarifaire existe |
| `jcmv_produit_coloris` | string | Coloris disponibles, saisie libre (« blanc, bleu ») |
| `jcmv_produit_dispo` | string (enum) | `disponible` / `sur-commande` / `epuise` |
| `jcmv_produit_galerie` | array\<int\> | 0 à 3 IDs d'attachements, en complément de l'image mise en avant |

Toutes en `show_in_rest`, avec `sanitize_callback` et `auth_callback` sur
`current_user_can( 'edit_posts' )` — convention `PostTypes::register()` existante.

#### Table `wp_jcmv_produit_tarif`

```
id           BIGINT UNSIGNED AUTO_INCREMENT
produit_id   BIGINT UNSIGNED   -- ID wp_posts, intégrité applicative (convention ADR-001)
taille       VARCHAR(32)       -- « 120 cm », « M », « 4 » (judo) — libellé libre
prix         DECIMAL(8,2)
sort_order   SMALLINT
```

**La grille est facultative, produit par produit.** Sans ligne, le produit affiche son
prix unique (`jcmv_produit_prix`). Avec des lignes, il affiche « à partir de {min} € » en
vignette et le détail de la grille en fiche. Un t-shirt reste donc à un seul champ ; seul
le judogi ouvre une grille.

Création par `dbDelta()` et incrément de `jcmv_db_version`, comme `wp_jcmv_pricing` — dont
cette table reprend exactement la forme (lignes ordonnées avec montant, rattachées à un
parent).

### Le produit cartésien taille × couleur est refusé

Le besoin exprimé était « taille × couleur × prix ». Il est réduit à une seule dimension,
la taille, pour trois raisons :

1. **Le coloris ne fait pratiquement jamais varier le prix.** Un judogi blanc et un judogi
   bleu de même taille sont au même tarif chez la quasi-totalité des fournisseurs.
2. **Le volume de saisie explose.** 8 tailles × 2 coloris = 16 lignes à renseigner par
   judogi, contre 8. Pour un bénévole qui saisit une fois par an, c'est le genre de
   frottement qui fait abandonner l'outil — et un catalogue non tenu à jour est pire
   qu'une absence de catalogue.
3. **L'échappatoire existe et est plus lisible.** Si un coloris a réellement son propre
   tarif, il devient un produit distinct (« Judogi blanc », « Judogi bleu »), chacun avec
   sa grille. Le bureau raisonne en produits, pas en matrices.

Ce choix est réversible : ajouter une colonne `coloris` à `wp_jcmv_produit_tarif` est une
migration additive qui ne casse aucune donnée existante.

### Bloc `jcmv/boutique`

Rendu serveur (`render.php`), sur le modèle du bloc `jcmv/partenaires` — ADR-002 : pas de
REST public, sortie cacheable.

| Attribut | Défaut | Rôle |
|---|---|---|
| `categorie` | `''` | Slug de `jcmv_categorie_produit` ; vide = toutes |
| `limite` | `0` | 0 = tous (plafonné à 100) |
| `colonnes` | `3` | Densité de la grille |

Un produit sans image mise en avant est écarté du rendu, comme un partenaire sans logo :
la règle vit dans le repository, pas dans le gabarit. Message d'aide affiché aux seuls
utilisateurs `edit_posts` quand la grille est vide.

### Galerie

Image mise en avant + 3 photos complémentaires (face, dos, détail du flocage). Vignettes
sous la photo principale, permutation au clic par un `view.js` de quelques lignes — aucune
bibliothèque tierce, précédent : `blocks/abonnement-calendrier/view.js`.

Nouvelle taille d'image `jcmv-produit` en **hard crop** (contrairement à `jcmv-logo`) : une
grille de produits n'est lisible que si toutes les vignettes ont le même gabarit. Ratio 4:5,
600 × 750, cadrage centré.

### Répartition des fichiers

```
src/Registration/PostTypes.php          → + jcmv_produit et ses metas
src/Registration/Taxonomies.php         → + jcmv_categorie_produit
src/Registration/ImageSizes.php         → + jcmv-produit (hard crop)
src/Domain/Schema.php                   → + wp_jcmv_produit_tarif, DB_VERSION '1' → '2'
src/Domain/Seed.php                     → + catégories (Textile, Judogis, Équipement, Accessoires)
src/Domain/ProductRepository.php        → lecture des produits + résolution du prix affiché
src/Domain/ProductPriceRepository.php   → grille tarifaire (lecture par lot, remplacement atomique)
src/Admin/ProduitMetabox.php            → prix, coloris, disponibilité, galerie, grille tarifaire
src/Registration/DeletionGuard.php      → + purge des tarifs sur before_delete_post
src/Front/Blocks.php                    → + register_block_type( blocks/boutique )
src/Plugin.php                          → + câblage ProduitMetabox (branche is_admin)
assets/js/produit-metabox.js            → lignes répétables + sélection de photos (wp.media)
assets/css/produit-metabox.css          → styles d'administration
blocks/boutique/                        → block.json, index.js, render.php, style.css, view.js
```

Le nettoyage des tarifs orphelins vit dans `DeletionGuard` et non dans la
metabox : une suppression peut venir de WP-CLI ou de l'API REST, où le module
d'administration n'est pas chargé.

Aucune modification du thème : le bloc consomme les tokens `--wp--preset--*` /
`--wp--custom--*` (frontière thème/plugin, ADR-003).

## Options considérées

### Option A : WooCommerce en mode catalogue

**Pour :** standard du marché ; la marche vers le paiement en ligne est déjà franchie ;
gestion native des variations, du stock et des taxes.
**Contre :** une soixantaine de tables et un volume de scripts considérable pour afficher
quinze produits sur un mutualisé ; surface de mise à jour et de sécurité qui retombe sur
le club ; compatibilité à surveiller avec le thème bloc ; l'admin Woo est un
environnement à part entière à faire apprendre au bureau. Disproportionné (ADR-002).

### Option B : plateforme externe seule (boutique HelloAsso, boutique fournisseur)

**Pour :** zéro code, zéro maintenance, paiement inclus.
**Contre :** le catalogue quitte le site — perte de SEO, de charte graphique et de
maîtrise du rendu ; dépendance à la disponibilité d'un tiers pour afficher ce que le club
vend. **Écartée comme solution unique, retenue comme complément possible** pour la seule
brique paiement (voir « Ce qui n'est pas décidé ici »).

### Option C : catalogue natif dans `wp-jcmv` (retenue)

**Pour :** cohérent avec l'existant (CPT + bloc rendu serveur, ADR-001/002) ; poids nul
sur les pages qui ne portent pas le bloc ; aucune dépendance tierce ; le bureau reste dans
une admin qu'il connaît déjà ; réversible vers A ou B.
**Contre :** tout est à écrire, y compris l'UI de saisie de la grille tarifaire ; pas de
stock temps réel ; les prix vivent sur le site et doivent y être maintenus.

### Option D : pages rédigées à la main dans Gutenberg

**Pour :** disponible immédiatement, aucun développement.
**Contre :** aucune structure — pas de tri, pas de filtre par catégorie, pas de réemploi
sur la page d'accueil ; mise en page à refaire à chaque ajout ; dérive graphique garantie
au fil des contributeurs.

## Analyse des compromis

| Dimension | A (Woo) | B (externe) | C (natif) | D (manuel) |
|---|---|---|---|---|
| Code à écrire | Faible | Nul | Moyen | Nul |
| Poids sur le site | Élevé | Nul | Faible | Nul |
| Autonomie du bureau | Moyenne | Forte | Forte | Forte |
| Maîtrise du rendu / SEO | Forte | Nulle | Forte | Moyenne |
| Chemin vers le paiement | Immédiat | Immédiat | À construire | Aucun |
| Charge de maintenance | Élevée | Nulle | Faible | Nulle |

Le coût principal de l'option C — l'UI de saisie de la grille tarifaire — est circonscrit :
une metabox à lignes répétables, sur un seul CPT, sans dépendance à l'app React des
Saisons.

## Ce qui n'est pas décidé ici

- **Le paiement en ligne.** Le champ `jcmv_produit_lien_achat` n'est volontairement pas
  créé tant que la décision n'est pas prise : une meta vide sur tous les produits est une
  dette, pas une préparation. L'ajouter le jour venu coûte quelques lignes.
- **La gestion de stock.** `jcmv_produit_dispo` est déclaratif : le bureau bascule
  manuellement un produit en « épuisé ». Aucun décompte automatique.
- **Les CGV.** Sans transaction en ligne, elles ne sont pas exigibles. Elles le
  deviendront le jour du paiement.
- **La page produit publique.** Prévue structurellement, pas planifiée.

## Conséquences

- **Bascule vers des pages produit publiques** — la liste est courte et connue d'avance :
  `public => true`, `has_archive => true`, `rewrite` avec un slug (`/boutique/`), gabarits
  `single-jcmv_produit.html` et `archive-jcmv_produit.html` dans le thème enfant,
  incrément de `Plugin::REWRITE_VERSION`. Aucune donnée à migrer.
- **Tant que le CPT est non public**, les produits ne sont pas indexés individuellement :
  c'est la page portant le bloc qui porte le référencement. À intégrer à `ANALYSE-SEO.md`
  et `META-DESCRIPTIONS.md`.
- **Les prix vivent sur le site.** Une hausse fournisseur non répercutée devient un
  affichage trompeur. Le runbook de saison doit inclure une relecture des tarifs.
- **`jcmv-produit` étant en hard crop**, toute modification ultérieure du ratio impose une
  régénération des miniatures — même contrainte que `jcmv-logo`.
- **`DeletionGuard` n'est pas concerné** : aucun autre objet du modèle ne référence un
  produit. La suppression d'un produit doit en revanche supprimer ses lignes tarifaires
  (nettoyage sur `before_delete_post`).
- **Convention ADR-001 maintenue** : dépublier = retirer du site sans perdre la fiche. Un
  produit saisonnier se dépublie, il ne se supprime pas.
- Incrément de `jcmv_db_version` pour `wp_jcmv_produit_tarif` ; la migration est additive,
  aucune donnée existante n'est touchée.

## Actions

- [x] CPT `jcmv_produit`, taxonomie `jcmv_categorie_produit`, metas, seed des rayons
- [x] Table `wp_jcmv_produit_tarif` + migration `Schema` (`DB_VERSION` 1 → 2)
- [x] `ProductRepository` / `ProductPriceRepository`
- [x] `ProduitMetabox` : prix, coloris, disponibilité, galerie, grille tarifaire répétable
- [x] `ImageSizes` : `jcmv-produit` 600 × 750 hard crop
- [x] Bloc `jcmv/boutique` (rendu serveur, colonnes réglables, filtre par rayon)
- [x] `view.js` de permutation de galerie + styles alignés sur la charte
- [x] Nettoyage des lignes tarifaires sur `before_delete_post`
- [ ] Valider le périmètre avec le bureau (nombre de références, qui saisit, qui relit les prix)
- [ ] Recette fonctionnelle en local : saisie d'un judogi à grille, d'un t-shirt à prix unique
- [ ] Page `/boutique` composée dans l'éditeur, entrée de navigation
- [ ] Passe accessibilité (contraste des pastilles de statut, navigation clavier des vignettes)
- [ ] Mise à jour de `ANALYSE-SEO.md`, `META-DESCRIPTIONS.md` et du guide bureau
- [ ] Vérifier le rendu après régénération des miniatures (`jcmv-produit` est en hard crop)
