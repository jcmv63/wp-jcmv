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
| `jcmv_produit_galerie` | array\<int\> | 0 à 12 IDs d'attachements, **dans l'ordre d'affichage**, en plus de l'image mise en avant |

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

#### L'ordre des photos : des boutons, pas un glisser-déposer (2026-09-04)

L'ordre existait déjà — `jcmv_produit_galerie` est un tableau ordonné, hérité de l'ordre
des clics dans la médiathèque. Ce qui manquait, c'était le moyen de le **corriger** sans
tout resélectionner.

Le glisser-déposer a été écarté, et l'argument tranche presque seul : le RGAA impose une
alternative à un seul pointeur pour toute action au glissement. Une bibliothèque de tri
n'en dispense pas — les boutons seraient donc à écrire **en plus**, jamais à la place. La
question n'était pas « boutons ou glisser » mais « boutons seuls, ou boutons plus
glisser ».

Les autres options, pour mémoire : `jquery-ui-sortable` est déjà embarqué par WordPress
(aucune dépendance à installer) mais c'est la partie du cœur la plus susceptible d'être
retirée à terme, et son accessibilité au clavier est inexistante ; l'API glisser-déposer
native n'émet **aucun événement au tactile**, ce qui la disqualifie pour un bureau qui
saisit souvent sur portable ; un glissement aux événements de pointeur marcherait partout
mais coûte quatre-vingts lignes et ses cas tordus (seuil de déclenchement pour distinguer
un clic d'un glissement, défilement automatique en bord de liste).

Deux flèches par vignette coûtent quinze lignes, se comportent à l'identique à la souris,
au doigt et au clavier, et n'ajoutent aucune dépendance. Leur défaut est réel — amener la
douzième photo en tête demande onze clics — mais on réordonne une galerie **une fois, à la
création de la fiche**. Ajouter le glissement par-dessus reste purement additif : les deux
mécanismes ne font que permuter des `<li>` et resynchroniser le même champ caché.

Deux détails portent l'essentiel de l'ergonomie :

- **Le focus suit la photo déplacée, pas la position.** Sans ça, on perd sa place à chaque
  clic et trois déplacements deviennent trois clics plus trois tabulations. Quand la flèche
  empruntée vient d'être désactivée — la photo a atteint le bout —, le focus bascule sur
  l'autre plutôt que de retomber sur le document.
- **Le libellé de chaque flèche porte le rang de la photo** (« Déplacer la photo 3 vers la
  gauche »), réécrit à chaque mutation. Sans lui, les vingt-quatre boutons d'une galerie de
  douze photos porteraient deux libellés seulement, répétés à l'identique, et les vignettes
  étant en `alt=""`, rien ne distinguerait la troisième photo de la septième à la synthèse
  vocale. Il tient aussi lieu de compte rendu : le focus restant sur le bouton de la photo
  déplacée, c'est le changement de son propre nom qui annonce le nouveau rang — sans région
  `aria-live` à maintenir.
- **Rouvrir la médiathèque ne détruit plus l'ordre.** La liste n'est plus reconstruite de
  zéro : les photos déjà présentes gardent leur place, les disparues sont retirées, les
  nouvelles ajoutées à la suite. Sans cela, ajouter une seule photo effacerait l'ordre que
  le bureau vient de composer — le genre de perte qu'on ne remarque qu'après
  enregistrement.

#### La liste des produits signale les fiches sans photo

Un produit sans image mise en avant est écarté de la grille par le repository. La règle est
maintenue, mais elle était **silencieuse** : le bureau publiait une fiche, ne la voyait pas
apparaître, et rien ne lui disait pourquoi. Une colonne « Photo » sur l'écran de liste
montre la miniature, ou l'avertissement quand elle manque.

La liste plutôt qu'une notice sur l'écran d'édition : une notice ne se voit qu'après coup,
sur une fiche à la fois, là où la colonne montre tout le catalogue d'un coup d'œil et
répond à la question avant qu'elle ne soit posée. Le message dépend du statut — sur un
brouillon, « n'apparaît pas sur le site » serait faux, et l'avertissement deviendrait un
bruit qu'on apprend à ignorer, ce qui le rendrait inopérant là où il compte.

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

Image mise en avant + jusqu'à 12 photos complémentaires (face, dos, détail du flocage,
autres coloris). Vignettes sous la photo principale, permutation au clic par un `view.js`
de quelques lignes — aucune bibliothèque tierce. Les vignettes sont masquées tant que le
script n'a pas posé sa classe : sans JavaScript, la carte se réduit à sa photo principale
plutôt qu'à une rangée de boutons inertes.

#### Format d'import demandé au bureau : 4:5, 1200 × 1500

La consigne est affichée dans la boîte Photos, là où le bureau téléverse — pas dans un
guide qu'il n'ouvrira pas.

Le ratio compte plus que la taille, et pour une raison qui n'est pas intuitive.
`wp_calculate_image_srcset()` n'inclut dans le `srcset` **que les tailles dont le ratio
correspond** à celle demandée. Avec un original en 4:5, les tailles bornées du cœur
conservent ce ratio et entrent toutes dans le jeu — `300×375`, `600×750`, `768×960`,
`800×1000`, la pleine taille — et le navigateur choisit selon la carte et la densité de
l'écran. Avec un original en 4:3 ou en carré, `medium`, `medium_large` et `large` gardent
le ratio d'origine et sont **toutes écartées** : il ne reste que le recadrage
`jcmv-produit`, étiré sur un écran à haute densité. Cadrer en 4:5 ne fait donc pas
qu'éviter la coupe, cela débloque cinq déclinaisons au lieu d'une.

1200 px de large couvre le pire cas de la mise en page — une carte d'environ 550 px en une
colonne, sur un écran à densité 2 — et reste sous le seuil de 2560 px au-delà duquel
WordPress redimensionne l'original à l'import.

Reste possible, si la discipline de saisie ne suit pas : enregistrer une seconde taille
`jcmv-produit-2x` en 1200 × 1500, également en *hard crop*. Les deux recadrages étant en
4:5, ils se retrouveraient tous les deux dans le `srcset` quel que soit le format de
l'original. Le prix serait un fichier dérivé de plus par photo et une régénération des
miniatures — non retenu tant que la consigne affichée suffit.

#### Le plafond, et pourquoi il a changé de rôle (2026-09-04)

Il valait 3, et ce n'était pas une limite de stockage : c'était la largeur d'une carte.
Douze miniatures de 44 px ne tenaient pas dans une rangée qui ne défilait pas. Depuis que
la bande défile, ce n'est plus elle qu'il faut protéger — le plafond ne sert plus qu'à
empêcher l'absurde, une sélection ratée dans la médiathèque partant à deux cents images.
D'où 12, qui couvre cinq coloris en face et en dos avec de la marge.

#### La bande de vignettes défile

`overflow-x: auto` sur la liste, rien de plus : pas de bibliothèque, pas de JavaScript
supplémentaire, aucun changement de balisage. `view.js` continue de faire ce qu'il faisait,
c'est-à-dire déplacer une classe.

**La largeur de la bande est bornée par un calcul, pas choisie à l'œil** :
`4,5 × pas + 1,5 × gouttière`, où le pas est la largeur réellement occupée par une vignette
(44 px d'image, plus 2 px de cadre de chaque côté) augmentée de la gouttière. La cinquième
vignette montre donc exactement **la moitié d'elle-même**, toujours, quelle que soit la
largeur de la carte — vérifié en navigateur : 24 px sur 48.

C'est le cœur de la décision d'affordance. Un objet tronqué se lit comme « ça continue »
sans avoir à être appris ; un bord net se lit comme « c'est fini ». Aucun dégradé, aucune
flèche ne dit cela aussi bien — et un dégradé se lit au moins autant comme une décoration
que comme une promesse. Les flèches `::scroll-button()` ont été écartées pour deux
raisons : elles n'existent que dans les moteurs Chromium, et elles occupent de la place à
l'état désactivé, c'est-à-dire précisément quand il n'y a rien à faire défiler.

La gouttière est en dur (8 px) et non en token d'espacement, contrairement au reste du
bloc : les presets du thème peuvent être fluides (`clamp`, `vw`), et un pas variable
rendrait la coupe aléatoire. C'est une valeur de calcul, pas une valeur de charte.

**Le centrage se fait par `width: fit-content` et des marges automatiques, pas par
`justify-content`.** Sur un conteneur qui déborde, un centrage ordinaire répartit le
dépassement des deux côtés et la première vignette devient inatteignable — le défilement ne
remonte pas avant l'origine.

`justify-content: safe center` corrige précisément ce cas, et c'est ce qui avait été écrit
d'abord, avec la déclaration ordinaire en repli. Mauvaise idée : un moteur qui ignore le
mot-clé jette la seconde déclaration et applique la première, c'est-à-dire la panne
elle-même. **Un repli qui reproduit le bug n'est pas un repli.** `fit-content` donne le même
rendu sans dépendre d'aucun mot-clé — sous le seuil la bande se réduit à son contenu et les
marges la centrent, au-delà `max-width` la plafonne et le contenu part de l'origine.

**Le défilement sur poste fixe** se fait au pavé tactile à deux doigts, à la molette
(les moteurs la traduisent en défilement horizontal quand l'élément ne déborde que dans ce
sens) et, surtout, **au clic sur la vignette coupée** : ce qui signale qu'il y a autre
chose est aussi ce sur quoi on clique pour y aller.

Ce dernier point demande six lignes dans `view.js`, contrairement à ce qui avait été écrit
ici d'abord. Un navigateur amène bien dans le champ visible l'élément qui reçoit le focus,
mais **seulement pour la navigation séquentielle au clavier** — jamais pour un focus
provoqué par un clic, puisque le pointeur est déjà sur l'élément et qu'il n'y a rien à
révéler de son point de vue. À l'essai, la photo changeait donc, et la bande ne bougeait
pas : la vignette qu'on venait de choisir restait à moitié cachée. Le clavier, lui,
fonctionne bien tout seul.

Le défilement est écrit à la main plutôt que délégué à `scrollIntoView()` — celui-ci
remonte toute la chaîne des conteneurs et ferait sauter la page quand la carte est à cheval
sur le bas de la fenêtre — et il **laisse dépasser une demi-vignette du côté où l'on va**.
Un défilement minimal collerait la vignette choisie contre le bord et ferait disparaître le
signal de continuation au premier clic, précisément quand on vient de prouver qu'il y a une
suite. La bande avance ainsi d'exactement un pas par clic, et l'amorce de la suivante
mesure toujours la même moitié qu'au repos — vérifié en navigateur : 24 px sur 48, dans
tous les états.

**La barre de défilement est masquée**, et c'est un renoncement à ce qui était prévu. Elle
aurait été le seul repère à dire *combien* il reste. Mais là où elle n'est pas en
surimpression — Windows, la plupart des Linux — elle occupe une dizaine de pixels dans
l'axe de bloc, et **seulement sur les cartes qui débordent** : elle rallongeait donc les
cartes à cinq photos et plus, décalant leurs titres. C'est exactement le défaut que la
bande toujours rendue existe pour supprimer, simplement déplacé du seuil « une photo /
plusieurs » au seuil « quatre / cinq ».

`scrollbar-gutter: stable` réserverait la place en permanence, mais son application à une
barre horizontale est bien moins établie que pour la verticale. La vignette coupée reste
donc le signal, seule — ce qui était de toute façon la décision, la barre n'ayant jamais été
qu'un renfort absent sur la moitié des plateformes.

#### La bande est affichée même pour une seule photo

Le cadre étant en ratio fixe, toutes les photos font la même hauteur. Ce qui décalait les
titres d'une carte à l'autre, c'était la bande : présente ici, absente là. En la rendant
toujours, les titres et les prix — les deux repères que l'œil balaie en diagonale sur une
grille — se retrouvent à la même altitude partout, **sans déplacer les vignettes loin de
la photo qu'elles commandent**.

L'alternative examinée était de descendre la bande sous le titre et le prix. Elle a été
écartée pour une raison qui ne se voit pas aujourd'hui : la carte va grandir. La page
produit publique, puis le formulaire de commande avec sa liste de tailles et son bouton,
s'empileront sous le prix, et les vignettes s'éloigneraient un peu plus de leur photo à
chaque ajout. Le bloc média doit rester une unité — la photo et ses commandes —, le reste
s'accumulant en dessous.

Le prix payé est une miniature redondante sur les produits à une seule photo, c'est-à-dire
sur la majorité d'un catalogue de club. Il est ramené au minimum : **dans ce cas la
vignette n'est pas un bouton** mais un `<span>`, et la liste entière porte `aria-hidden`.
Plus d'arrêt de tabulation inutile, plus de « Photo 1 sur 1, bouton, enfoncé » répété sur
chaque carte d'une grille — c'était là le vrai coût, et non les 60 px de hauteur. Elle
garde le cadre actif : sans lui, elle se lirait « non sélectionnée » à côté de voisines
qui, elles, le sont.

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
src/Admin/ProduitMetabox.php            → prix, couleur, disponibilité, tailles, galerie ordonnée
src/Admin/ProduitListe.php              → colonne « Photo » de l'écran de liste
src/Front/Blocks.php                    → register_block_type( blocks/boutique )
src/Plugin.php                          → câblage ProduitMetabox (branche is_admin)
assets/js/produit-metabox.js            → cases de tailles + sélection et ordre des photos (wp.media)
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
- **Le pas des vignettes est figé en CSS** (44 px d'image, 2 px de cadre, 8 px de
  gouttière). Changer l'une de ces trois valeurs sans recalculer la largeur maximale de la
  bande fait disparaître la vignette coupée, et avec elle le seul signal qui invite au
  défilement. Les trois vivent en propriétés personnalisées au même endroit, précisément
  pour que la formule reste vraie.
- **Rien ne doit reprendre de la hauteur dans la bande.** Sa hauteur est constante d'une
  carte à l'autre, et c'est ce qui aligne les titres de la grille. Une barre de défilement
  classique, un contour, une bordure ajoutés à la bande sur les seules cartes qui débordent
  ramèneraient le décalage — c'est le piège dans lequel la première version est tombée.
- **Douze photos par produit sur cent produits**, c'est jusqu'à treize images par carte
  dans le DOM. Elles sont différées et le repository amorce leur cache en une requête, mais
  le poids de page d'une grille pleine n'a pas été mesuré. À vérifier avant d'ouvrir la
  boutique au bureau.
- **Les arrêts de tabulation se multiplient.** Un produit à douze photos, ce sont douze
  boutons ; sur une grille de vingt produits, deux cent quarante arrêts. La vignette
  solitaire des produits à une seule photo n'en ajoute aucun (c'est un `<span>`), mais le
  fond du problème demeure. Le remède standard — un seul arrêt par bande, les flèches du
  clavier pour circuler à l'intérieur — demande du JavaScript et mérite une passe
  d'accessibilité pour lui-même.
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
- [x] Plafond de galerie porté à 12, bande de vignettes en défilement horizontal avec
      vignette coupée garantie
- [x] Bande affichée même pour une seule photo, vignette non interactive dans ce cas
- [x] Ordre des photos par flèches dans la metabox, focus qui suit la photo déplacée,
      ordre préservé à la réouverture de la médiathèque
- [x] Colonne « Photo » sur l'écran de liste des produits
- [ ] Recette fonctionnelle en local : un judogi, un t-shirt noir, un t-shirt blanc, un
      accessoire sans taille
- [x] Défilement de la bande au clic sur la vignette coupée (le focus au clic ne suffit
      pas, contrairement à ce qui avait été supposé)
- [ ] Vérifier le défilement de la bande à la molette sur Firefox et Safari — comportement
      de fait, pas de spécification
- [ ] Mesurer le poids d'une grille de vingt produits à douze photos
- [ ] Page `/boutique` composée dans l'éditeur, entrée de navigation
- [ ] Passe accessibilité (contraste des pastilles de statut, navigation clavier des
      vignettes)
- [ ] Mise à jour de `ANALYSE-SEO.md`, `META-DESCRIPTIONS.md` et du guide bureau
