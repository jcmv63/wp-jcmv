# ADR-005 : Boutique — catalogue vitrine des produits floqués dans `wp-jcmv`

**Statut :** Accepté
**Date :** 2026-08-07
**Décideurs :** Alban (développeur), bureau du club (périmètre à valider)
**Dépend de :** ADR-001 (répartition du stockage), ADR-002 (architecture de
l'administration), ADR-003 (frontière thème/plugin)
**Périmètre :** module « boutique » du plugin `wp-jcmv` (branche `boutique`). Le paiement
en ligne, la prise de commande et la gestion de stock ne sont **pas** couverts — ils font
l'objet d'une décision ultérieure (voir « Ce qui n'est pas décidé ici »).

## Contexte

Le bureau souhaite exposer sur le site les articles de sport floqués au logo du club
(textile, judogis, accessoires). Le cadrage recueilli :

- **Ce n'est pas l'activité principale du site.** Le module ne doit ni dominer la
  navigation, ni peser sur les performances des pages existantes.
- **Pas de gestion d'expédition.** Les produits sont retirés au dojo.
- **Paiement en ligne éventuellement plus tard**, sans engagement de calendrier.
- L'association **vend déjà du textile** — l'activité existe, le cadre associatif et
  fiscal est en place — et **dispose d'un compte HelloAsso**, écarté du périmètre à ce
  stade.

### Forces en présence

1. **Administrable par le bureau** (exigence n° 1, héritée de l'ADR-001). Ajouter un
   produit, changer un prix ou masquer une référence épuisée doit se faire depuis
   l'admin, par un bénévole, sans développeur.
2. **Pas d'usine à gaz** (règle ADR-002). Une quinzaine de références, quelques mises à
   jour par saison : le volume ne justifie aucune infrastructure e-commerce.
3. **Hébergement mutualisé OVH.** Chaque extension lourde se paie en temps de réponse sur
   toutes les pages, y compris celles qui n'ont rien à voir avec la boutique.
4. **Réversibilité.** Le paiement et les commandes pouvant arriver plus tard, le modèle
   doit pouvoir les accueillir sans refonte ni migration de contenu.
5. **Hétérogénéité des tailles.** Le club vend des produits dont les systèmes de taille
   n'ont rien de commun : textile (`10 ans, 12 ans, …, S, M, L, XL`), judogis en
   centimètres (`110, 120, 130, …, 190`), chaussures (`32, 34, …, 40, 41, 42`). Aucun de
   ces systèmes ne se trie automatiquement, et le premier en mélange déjà deux.

## Décision

**Catalogue vitrine natif dans `wp-jcmv`.** Pas de WooCommerce, pas d'extension tierce,
pas de panier, pas de transaction. Le site présente les produits ; la vente se conclut
hors ligne, au dojo.

### Modèle de données

Application de la grille ADR-001, **sur deux niveaux seulement** : le module boutique
n'introduit aucune table custom.

| Niveau | Stockage | Données |
|---|---|---|
| Contenu administrable | CPT `jcmv_produit` + taxonomies `jcmv_famille` et `jcmv_systeme_taille` | produits, familles, systèmes de tailles |
| Structure | code du plugin | règles d'affichage et de tri |

**Deux axes indépendants**, et c'est la correction majeure apportée à une première
version de cet ADR :

- **La famille** (`jcmv_famille`) est un classement d'**affichage** : Textile, Judogis,
  Accessoires. Orientée visiteur, c'est elle que filtre le bloc.
- **Le système de tailles** (`jcmv_systeme_taille`) est orienté **saisie** : Taille
  internationale, Taille judogi, Pointures. Il ne paraît jamais sur le site.

Les avoir confondus en une seule notion — le « rayon » — était une erreur de conception.
Elle s'est révélée à l'usage, dès le premier produit saisi : impossible de nommer les
termes, puisqu'une famille Textile contient aussi bien des produits en tailles françaises
qu'en tailles internationales. Quand on n'arrive pas à nommer les instances d'un concept,
c'est le concept qui est faux.

Aucune des deux taxonomies n'est seedée : leurs termes sont des décisions du bureau,
prises sur le catalogue du fournisseur. Le critère d'ADR-001 est maintenu — on n'embarque
un référentiel dans le code que lorsqu'il existe en dehors du club, comme les catégories
FFJDA. Deviner des familles reviendrait à imposer des slugs immuables sur des valeurs
inventées.

#### CPT `jcmv_produit`

Libellé d'admin : **Produits**. Le terme « Articles » est écarté délibérément — c'est le
nom des posts natifs dans WordPress en français, deux entrées homonymes dans le menu
seraient une source d'erreur permanente pour le bureau.

| Réglage | Valeur | Motif |
|---|---|---|
| `public` | `false` | Le catalogue s'affiche par un bloc ; pas de page par produit à ce stade |
| `show_in_menu` | `'jcmv-club'` | Rattaché au menu JCMV, comme Cours / Lieux / Partenaires |
| `supports` | `title`, `editor`, `thumbnail`, `page-attributes` | `menu_order` pilote l'ordre de la grille |
| `has_archive`, `rewrite` | `false` | À rebasculer le jour d'une page produit publique |

**Trois règles produit, actées avec le bureau :**

1. **Un prix par produit.** Pas de tarif par taille.
2. **Un produit, une couleur.** Un t-shirt noir et un t-shirt blanc sont deux produits
   distincts. Le coloris ne fait pas varier le prix ; s'il le faisait, la séparation en
   deux produits le règle sans structure supplémentaire.
3. **Un produit, une famille et un système de tailles.** Les deux taxonomies sont
   présentées en boutons radio, comme la discipline des cours. Pour le système, deux
   valeurs rendraient l'arbitrage des cases indécidable ; pour la famille, un produit
   apparaîtrait dans deux grilles — ce qui relève de la mise en avant, un autre concept.
   Un choix « Aucune » figure en tête : un groupe de boutons radio ne se déselectionne
   pas, et un clic malencontreux serait sinon définitif.

**Piège du patron radio.** `wp_set_post_terms()` ne convertit `tax_input` en identifiants
de termes **que pour les taxonomies hiérarchiques** (`array_map( 'intval', … )`). Sur une
taxonomie plate, l'ID posté par un bouton radio arrive en chaîne, n'est trouvé ni par slug
ni par nom, et `wp_insert_term()` crée alors un terme **nommé « 19 »**. Les deux
taxonomies sont donc déclarées `hierarchical => true` sans hiérarchie réelle — comme
`jcmv_discipline` avant elles, dont le réglage avait été copié sans son motif.

#### Postmeta du produit

| Meta | Type | Rôle |
|---|---|---|
| `jcmv_produit_prix` | number | Prix unique, en euros |
| `jcmv_produit_couleur` | string | Coloris du produit, saisie libre |
| `jcmv_produit_dispo` | string (enum) | `disponible` / `sur-commande` / `epuise` |
| `jcmv_produit_tailles` | array\<string\> | Libellés de tailles, **dans l'ordre d'affichage** |
| `jcmv_produit_galerie` | array\<int\> | 0 à 3 IDs d'attachements, en plus de l'image mise en avant |

#### Term meta du système de tailles

| Meta | Type | Rôle |
|---|---|---|
| `jcmv_tailles` | array\<string\> | Tailles du système, ordonnées |

Éditée sur l'écran du terme (`{taxonomy}_edit_form_fields`), en champ texte séparé par
des virgules, normalisée à l'enregistrement (découpage, `trim`, dédoublonnage insensible
à la casse). C'est le niveau 2 d'ADR-002 : formulaire PHP classique pour du paramétrage
statique — même mécanique que les bornes d'âge de `jcmv_categorie_age`.

### Les tailles : liste au système, valeurs au produit

Le bureau choisit un système de tailles, ses tailles s'affichent en cases à cocher, il
coche celles réellement disponibles. **Ce qui part en base sur le produit est la liste des
libellés cochés, pas un pointeur vers le terme.**

Cette distinction porte l'essentiel de la décision :

- **Le système est une source de saisie, pas une référence.** Il évite de retaper huit
  tailles pour chaque judogi, sans introduire de niveau relationnel. Le produit reste
  plat : un tableau de chaînes.
- **Modifier un système n'altère jamais un produit existant.** Retirer `130` du système
  Judogi laisse intacts les produits qui l'avaient cochée, où elle s'affiche alors
  signalée « hors système ». Le jour où des commandes existeront, cette propriété devient
  indispensable : une commande passée ne doit pas changer rétroactivement parce que
  quelqu'un a corrigé un référentiel.
- **Un produit atypique n'oblige pas à inventer un système.** Un champ d'ajout libre à
  côté des cases suffit.
- **Changer de système conserve les tailles cochées** qui n'appartiennent pas au nouveau.
  Sans quoi un changement effacerait des saisies sans prévenir.
- **L'ordre est défini une fois, au niveau du système.** Aucun tri automatique ne classe
  `10 ans, 12 ans, S, M, L, XL` correctement : ni alphabétique, ni numérique. L'ordre
  n'existe que dans la tête de la personne qui saisit, et c'est le système qui le capture.
- **Un produit sans système n'affiche pas de tailles.** Gourdes, stickers, porte-clés :
  le bloc de cases ne s'affiche simplement pas.

Une taxonomie `jcmv_taille` a été écartée : elle réunirait quarante et quelques termes de
trois systèmes incompatibles dans une seule liste de cases, avec `12 ans`, `120` et `12`
à quelques lignes d'écart — et son ordre exigerait des plages de numérotation par
famille, convention implicite qui ne survivrait pas à la première insertion au milieu.

### Écran d'administration

**Éditeur de blocs désactivé**, conformément à la règle générale d'ADR-002 niveau 1 : un
produit est une fiche de données, pas une page. L'écran classique restitue la hiérarchie
réelle — titre, champs métier, description — là où Gutenberg propose un canevas de mise
en page à un objet qui n'en a pas et relègue le prix et les tailles sous un accordéon.

`title` et `editor` restent dans `supports` : ils fournissent nativement le champ Titre
et un éditeur riche TinyMCE pour la description, sans une ligne de code. Réimplémenter
ces deux champs dans une metabox a été écarté — le titre porte le permalien et
l'autosave, et retirer `editor` de `supports` fait sortir `post_content` du chemin de
sauvegarde standard d'`edit_post()`, avec des interactions fragiles autour des révisions.

Deux metaboxes : **Produit** (prix, couleur, disponibilité, tailles) et **Photos**
(galerie complémentaire). La première peut être remontée entre le titre et l'éditeur via
`edit_form_after_title` si l'ordre natif gêne à l'usage — à décider en le voyant.

### Bloc `jcmv/boutique`

Rendu serveur (`render.php`), sur le modèle du bloc `jcmv/partenaires` — ADR-002 : pas de
REST public, sortie cacheable.

| Attribut | Défaut | Rôle |
|---|---|---|
| `famille` | `''` | Slug de famille ; vide = toutes |
| `limite` | `0` | 0 = tous (plafonné à 100) |
| `colonnes` | `3` | Densité de la grille |
| `afficherDetails` | `true` | Bloc dépliable description / couleur / tailles |

Un produit sans image mise en avant est écarté du rendu, comme un partenaire sans logo :
la règle vit dans le repository, pas dans le gabarit.

**Les tailles s'affichent en liste, jamais en contrôle de formulaire.** Sans prise de
commande, une liste déroulante ne déclencherait rien : l'utilisateur l'actionne, rien ne
se passe, il en conclut que le site est cassé. Une liste se lit sans interaction et son
contenu est indexable, ce que celui d'un `<select>` n'est pas. La déroulante viendra avec
le formulaire de commande, alimentée par la même meta.

### Galerie et images

Image mise en avant + 3 photos complémentaires (face, dos, détail du flocage). Vignettes
sous la photo principale, permutation au clic par un `view.js` de quelques lignes — aucune
bibliothèque tierce. Les vignettes sont masquées tant que le script n'a pas posé sa
classe : sans JavaScript, la carte se réduit à sa photo principale plutôt qu'à une rangée
de boutons inertes.

Taille d'image `jcmv-produit` en **hard crop** 600 × 750 (4:5), contrairement à
`jcmv-logo` : une grille de produits n'est lisible que si toutes les vignettes ont le même
gabarit, et le bureau n'a pas à cadrer ses photos au pixel.

### Répartition des fichiers

```
src/Registration/PostTypes.php          → CPT jcmv_produit et ses metas
src/Registration/Taxonomies.php         → jcmv_famille + jcmv_systeme_taille (radio) + term meta
src/Registration/ImageSizes.php         → jcmv-produit (hard crop)
src/Domain/Sizes.php                    → normalisation, comparaison et tri des libellés de tailles
src/Domain/ProductRepository.php        → produits publiés avec image, données d'affichage
src/Admin/TermFields.php                → champ « Tailles » sur l'écran du système
src/Admin/ProduitMetabox.php            → prix, couleur, disponibilité, tailles, galerie
src/Front/Blocks.php                    → register_block_type( blocks/boutique )
src/Plugin.php                          → câblage ProduitMetabox (branche is_admin)
assets/js/produit-metabox.js            → cases de tailles + sélection de photos (wp.media)
assets/css/produit-metabox.css          → styles d'administration
blocks/boutique/                        → block.json, index.js, render.php, style.css, view.js
```

Aucune modification du thème : le bloc consomme les tokens `--wp--preset--*` /
`--wp--custom--*` (frontière thème/plugin, ADR-003).

## Options considérées

### Option A : WooCommerce, en mode catalogue

**Pour :** standard du marché ; la marche vers le paiement est déjà franchie ; gestion
native des variations, du stock et des taxes.
**Contre :** une soixantaine de tables et un volume de scripts considérable pour afficher
quinze produits sur un mutualisé ; surface de mise à jour et de sécurité qui retombe sur
le club ; l'admin Woo est un environnement à part entière à faire apprendre au bureau.
Disproportionné (ADR-002).

### Option B : extension de catalogue dédiée

Le dépôt officiel n'offre qu'un candidat crédible : **Ultimate Product Catalog**
(4 000+ installations, testé jusqu'à WP 7.0.3, maintenu). CPT, blocs, filtrage, limite de
100 produits en gratuit.

**Contre :** son changelog 5.3.0 prévient que des modifications de mise en page imposent
un test en préproduction avant montée de version — sur un site tenu par des bénévoles,
c'est un incident à réparer par quelqu'un ; l'aide en admin passe depuis 5.3.13 par une
**extension compagnon** supplémentaire ; les champs personnalisés sont en premium.

**Signalé pour mémoire :** `wp-catalogue`, encore recommandé par plusieurs comparatifs
francophones, est **fermé depuis mars 2024 pour faille de sécurité**, dernière mise à jour
il y a neuf ans. À refuser si le nom revient.

### Option C : outils génériques (champs personnalisés + CPT UI)

Construire le même modèle sans PHP, avec une extension de champs.

**Contre :** **Secure Custom Fields** — le fork d'ACF maintenu par WordPress.org depuis
octobre 2024 — ne reprend pas le champ *repeater* d'ACF Pro. **Pods** propose un réglage
« répétable » gratuit mais **limité à un champ isolé**. Dans le modèle finalement retenu,
plus aucun champ répétable n'est nécessaire : cette option redevient viable sur le papier,
mais impose alors une dépendance tierce et une seconde interface d'administration au
bureau, pour reproduire ce que six metas natives font déjà.

### Option D : plateforme externe seule (boutique HelloAsso, boutique fournisseur)

**Pour :** zéro code, zéro maintenance, paiement inclus.
**Contre :** le catalogue quitte le site — perte de SEO, de charte graphique et de
maîtrise du rendu ; dépendance à un tiers pour afficher ce que le club vend. **Écartée
comme solution unique, retenue comme complément possible** pour la seule brique paiement.

### Option E : catalogue natif dans `wp-jcmv` (retenue)

**Pour :** cohérent avec l'existant (CPT + bloc rendu serveur, ADR-001/002) ; poids nul
sur les pages qui ne portent pas le bloc ; aucune dépendance tierce ; le bureau reste dans
une admin qu'il connaît ; réversible vers A, B ou D.
**Contre :** tout est à écrire ; pas de stock temps réel ; les prix vivent sur le site et
doivent y être maintenus.

### Option F : pages rédigées à la main dans Gutenberg

**Pour :** disponible immédiatement, aucun développement.
**Contre :** aucune structure — pas de tri, pas de filtre par famille, pas de réemploi sur
la page d'accueil ; mise en page à refaire à chaque ajout ; dérive graphique garantie.

## Analyse des compromis

| Dimension | A (Woo) | B (extension) | C (génériques) | D (externe) | E (natif) |
|---|---|---|---|---|---|
| Code à écrire | Faible | Nul | Faible | Nul | Moyen |
| Poids sur le site | Élevé | Moyen | Faible | Nul | Faible |
| Dépendance tierce | Oui | Oui (+ compagnon) | Oui | Oui | **Non** |
| Risque de casse en mise à jour | Moyen | Avéré | Faible | — | Nul |
| Maîtrise du rendu / SEO | Forte | Moyenne | Forte | Nulle | Forte |
| Charge de maintenance | Élevée | Faible | Faible | Nulle | Faible |

Le facteur limitant reste celui d'ADR-002 : l'énergie de bénévole disponible dans cinq
ans. Sur ce critère, ce qui compte n'est pas le code écrit une fois mais le nombre de
dépendances dont il faudra suivre les mises à jour. Le modèle retenu n'en ajoute aucune.

## Le modèle à grille tarifaire, envisagé puis abandonné

Une première implémentation (commit `7cc4523`) portait une table
`wp_jcmv_produit_tarif` — un prix par taille et par produit — justifiée par le seul
judogi, dont le tarif varie réellement avec la taille.

Elle a été abandonnée avant livraison :

- **Coût structurel disproportionné.** Elle imposait une table custom, un repository, une
  metabox à lignes répétables et son JavaScript, un remplacement transactionnel, un
  témoin de soumission de formulaire, un transient d'erreur et sa notice, une purge des
  lignes orphelines à la suppression, et une migration de schéma. Environ 400 lignes sur
  1 100 — et les trois mécanismes les plus fragiles du module.
- **Pour un seul cas métier.** Il se contourne en éclatant le judogi en deux produits
  (« Judogi enfant », « Judogi adulte »), ce qui est aussi plus lisible pour l'acheteur.
- **Le produit cartésien taille × couleur, un temps envisagé, a été écarté en amont :**
  huit tailles × deux coloris font seize lignes à saisir par judogi. Un catalogue non tenu
  à jour est pire qu'une absence de catalogue, et le frottement de saisie est ce qui
  décide de sa tenue.

La réintroduire reste **purement additif** : une table, une migration, aucune donnée
existante touchée. L'inverse n'est pas vrai — du code écrit pour rien se maintient
indéfiniment. Le déclencheur probable n'est d'ailleurs pas le prix mais la
**disponibilité par taille** (« il n'y a plus de M »), qui deviendra nécessaire le jour
des commandes. La table reviendra alors avec `dispo` là où elle avait `prix`.

## Ce qui n'est pas décidé ici

- **La prise de commande.** C'est l'étape suivante pressentie. Les tailles sont stockées
  en valeurs normalisées précisément pour alimenter un jour un `<option>` et une ligne de
  commande. Mais le gros du travail sera ailleurs : stockage des commandes et de leurs
  lignes, statuts, notification au bureau, protection anti-spam d'un formulaire public, et
  surtout RGPD — nom, e-mail et téléphone d'un acheteur sont des données personnelles
  (durée de conservation, mention d'information, droit à l'effacement).
- **Le paiement en ligne.** Aucune meta `lien_achat` n'est créée tant que la décision
  n'est pas prise : une meta vide sur tous les produits est une dette, pas une
  préparation.
- **La gestion de stock.** `jcmv_produit_dispo` est déclaratif, au niveau du produit.
  Aucun décompte automatique, aucune disponibilité par taille.
- **Les CGV.** Sans transaction en ligne, elles ne sont pas exigibles. Elles le
  deviendront le jour du paiement.
- **La page produit publique.** Prévue structurellement, pas planifiée.
- **Les échanges de taille.** Question de fonctionnement, pas de code : les chaussures
  sont le seul produit du catalogue où la taille se trompe vraiment, et le retrait au dojo
  sans procédure d'échange écrite fait retomber le problème sur le bénévole qui tient le
  stand.

## Conséquences

- **Bascule vers des pages produit publiques** — la liste est courte et connue d'avance :
  `public => true`, `has_archive => true`, `rewrite` avec un slug (`/boutique/`), gabarits
  `single-jcmv_produit.html` et `archive-jcmv_produit.html` dans le thème enfant,
  incrément de `Plugin::REWRITE_VERSION`. Aucune donnée à migrer, et le choix de l'éditeur
  classique n'y change rien : `post_content` reste `post_content`.
- **Tant que le CPT est non public**, les produits ne sont pas indexés individuellement :
  c'est la page portant le bloc qui porte le référencement. À intégrer à `ANALYSE-SEO.md`
  et `META-DESCRIPTIONS.md`.
- **Les prix vivent sur le site.** Une hausse fournisseur non répercutée devient un
  affichage trompeur. La relecture des tarifs entre dans le rituel de début de saison.
- **`Schema::DB_VERSION` reste à `'2'`** bien que le schéma soit identique à la version 1.
  Une version de schéma ne recule jamais : la redescendre casserait les installations
  ayant déjà migré. La table `wp_jcmv_produit_tarif` créée par la version abandonnée est à
  supprimer manuellement sur les bases de développement concernées.
- **`jcmv-produit` étant en hard crop**, toute modification ultérieure du ratio impose une
  régénération des miniatures — même contrainte que `jcmv-logo`.
- **`DeletionGuard` n'est pas concerné** : aucun autre objet du modèle ne référence un
  produit, et le produit ne possède plus de lignes en table custom.
- **Convention ADR-001 maintenue** : dépublier = retirer du site sans perdre la fiche. Un
  produit saisonnier se dépublie, il ne se supprime pas.
- **Une seule famille par produit** : un produit ne peut pas apparaître dans deux
  grilles filtrées. Mettre un produit en avant hors de sa famille demandera un autre
  mécanisme, à concevoir le jour venu.
- **Les deux taxonomies sont rattachées au menu à la main** (`Admin\Menu`) : WordPress ne
  le fait que pour les CPT dont `show_in_menu` vaut `true`. Sans ce rattachement, leurs
  écrans existent mais restent inatteignables — l'oubli a été commis une fois.

## Actions

- [x] Retirer la table `wp_jcmv_produit_tarif`, `ProductPriceRepository` et la purge
      associée dans `DeletionGuard`
- [x] Metas produit : `prix`, `couleur`, `dispo`, `tailles`, `galerie`
- [x] Term meta `jcmv_tailles` + champ dans `Admin\TermFields`
- [x] Famille et système en boutons radio, avec choix « Aucune »
- [x] Metabox produit : cases de tailles issues du système + ajout libre, galerie
- [x] Réactiver l'écran classique sur `jcmv_produit` (filtre `use_block_editor_for_post_type`)
- [x] Bloc `jcmv/boutique` : tailles en liste, retrait de la grille tarifaire et de
      `do_blocks()` dans `render.php`
- [ ] Valider le périmètre avec le bureau (nombre de références, qui saisit, qui relit les
      prix, familles et systèmes de tailles réels)
- [ ] Recette fonctionnelle en local : un judogi, un t-shirt noir, un t-shirt blanc, un
      accessoire sans taille
- [ ] Page `/boutique` composée dans l'éditeur, entrée de navigation
- [ ] Passe accessibilité (contraste des pastilles de statut, navigation clavier des
      vignettes)
- [ ] Mise à jour de `ANALYSE-SEO.md`, `META-DESCRIPTIONS.md` et du guide bureau
