# ADR-003 : Thème — enfant de Twenty Twenty-Five plutôt que thème sur mesure

**Statut :** Accepté
**Date :** 2026-07-19
**Décideurs :** Alban (développeur)
**Périmètre :** le thème du site (`wp-content/themes/jcmv-theme`). Le plugin `wp-jcmv`
(ADR-001/002) n'est pas couvert, mais la frontière thème/plugin est actée ici.

## Contexte

La charte graphique JCMV (v1.2, `docs/charte_jcmv_5.html`) définit une identité
exigeante : palette rouge/anthracite, couple Oswald/Archivo auto-hébergé (RGPD),
composants web (navigation, cartes, accordéons, alertes) et composants club (carte de
créneau, cartes dojo). Un `theme.json` de référence avait été rédigé en amont
(`docs/theme.json`) avec des slugs sémantiques (`rouge`, `anthracite`…).

### Forces en présence

1. **Maintenabilité par le bureau (exigence n° 1, héritée de l'ADR-001).** Le site doit
   rester administrable par des bénévoles non techniques : édition dans Gutenberg,
   mises à jour depuis l'admin, pas de dépendance à un développeur pour changer un texte.
2. **Sécurité et pérennité.** Un thème par défaut WordPress est maintenu par la core
   team (correctifs suivis, compatibilité garantie avec les futures versions). Un thème
   sur mesure n'est maintenu que par son auteur.
3. **Pas d'usine à gaz** (règle ADR-002) : le minimum de code à écrire et à maintenir.
4. **Fidélité à la charte** : header/footer spécifiques (logo SVG, CTA permanent,
   soulignement rouge actif), qui dépassent ce qu'un theme.json seul sait exprimer.

## Décision

**Thème enfant de Twenty Twenty-Five** (`Template: twentytwentyfive`), nommé
`jcmv-theme`, avec la répartition suivante :

- **theme.json v3** : tokens de la charte (palette, typo, espacements, ombre carte) et
  styles globaux (éléments, boutons, variation outline).
- **Compatibilité de slugs avec TT25** : les templates et patterns du parent référencent
  `base`, `contrast`, `accent-1`…`accent-6`, `small`…`xx-large`, spacing `20`…`80`. Le
  thème enfant **réutilise ces slugs** en les remappant sur les couleurs/valeurs JCMV
  (ex. `accent-1` = rouge #EE3435), complétés par des slugs propres pour les couleurs
  hors gabarit (`gris-texte`, `succes`…). Des **alias sémantiques** sont exposés en
  `settings.custom` (`--wp--custom--color--rouge`…) pour le code qui veut des noms
  parlants — dont le futur plugin.
- **Header et footer** : `parts/*.html` délèguent à des patterns PHP (`patterns/*.php`,
  `Inserter: no`) — même technique que TT25 — pour pouvoir servir le logo SVG via
  `get_theme_file_uri()` sans passer par la médiathèque (upload SVG bloqué par défaut).
- **Templates surchargés** : `front-page.html` (accueil sans titre rendu, contenu libre
  dans l'éditeur de page), `page.html` / `single.html` (bandeau titre anthracite),
  `home.html` (actualités en cartes charte), `404.html`. Le reste (archive, search,
  index) est hérité de TT25.
- **Patterns éditoriaux** insérables (catégorie « JCMV ») : hero, chiffres clés,
  témoignage, FAQ accordéon, cartes dojo, bandeau d'inscription.
- **`assets/css/components.css`** (front + éditeur) : ce que theme.json ne sait pas
  dire — focus visible RGAA, navigation, accordéon, composants `jcmv-*`.
- **Frontière thème/plugin** : le thème porte les *tokens et l'apparence* ; le plugin
  `wp-jcmv` rendra ses blocs dynamiques (créneaux, tarifs) en consommant les variables
  CSS du thème, sans dépendance inverse.

## Options considérées

### Option A : thème du commerce (Astra, GeneratePress…) + customizer

**Pour :** zéro développement de templates ; écosystème documenté.
**Contre :** fidélité charte limitée sans version pro payante ; lock-in ; options
éparpillées difficiles à documenter pour le bureau ; dépendance à un éditeur tiers.

### Option B : thème bloc complet sur mesure

**Pour :** contrôle total, pas de neutralisation du parent, arborescence minimale.
**Contre :** réécrire templates, patterns cachés et réglages qu'offre TT25 ;
maintenance de compatibilité à chaque version WordPress à notre charge ; contraire à
la règle « pas d'usine à gaz » pour un bénéfice marginal.

### Option C : thème classique (PHP) sur mesure

**Pour :** liberté totale de markup, compétences PHP du développeur.
**Contre :** prive le bureau de l'édition de site (FSE) et des patterns ; à
contre-courant de la direction du core ; volume de code le plus élevé des quatre.

### Option D : thème enfant de Twenty Twenty-Five (retenue)

**Pour :** templates, accessibilité et responsive hérités et maintenus par la core
team ; surcharge uniquement là où la charte l'exige ; migration vers l'option B peu
coûteuse si l'enfant devient trop contraint (theme.json, patterns et CSS réutilisables
tels quels).
**Contre :** slugs de presets imposés par le parent (d'où le remapping + alias) ;
styles TT25 à neutraliser ponctuellement ; deux thèmes à mettre à jour au lieu d'un.

## Analyse des compromis

| Dimension | A (commerce) | B (bloc sur mesure) | C (classique) | D (enfant TT25) |
|---|---|---|---|---|
| Fidélité charte | Moyenne | Forte | Forte | Forte |
| Code à maintenir | Faible | Élevé | Très élevé | Faible |
| Autonomie bureau | Moyenne | Forte | Faible | Forte |
| Pérennité / sécurité | Moyenne (tiers) | À notre charge | À notre charge | Core team |

Le coût principal de l'option D — les slugs non sémantiques — est amorti par les alias
`--wp--custom--color--*` et par la table de correspondance documentée dans
`GUIDE-INTEGRATION-WORDPRESS.md`.

## Conséquences

- Les mises à jour de TT25 et de WordPress doivent être appliquées régulièrement ;
  le rendu est à re-vérifier après chaque mise à jour majeure du parent.
- Tout composant réutilisable doit citer les tokens (`--wp--preset--*`,
  `--wp--custom--*`), jamais de couleur en dur — condition pour que le plugin et le
  thème évoluent indépendamment.
- Le mapping de slugs est un contrat : ne jamais renommer un slug existant (les
  contenus sauvegardés portent les classes `has-accent-1-*`…).
- En dev local, `WP_DEVELOPMENT_MODE=all` est requis pour que les caches de fichiers
  de thème (patterns, theme.json) soient désactivés.

## Actions

- [x] Scaffold `jcmv-theme` (style.css, theme.json v3, polices locales, favicons)
- [x] Header (2 variantes), footer, templates, patterns éditoriaux, styles de blocs
- [ ] Re-vérification visuelle à chaque mise à jour majeure de TT25/WordPress
- [ ] Réévaluer l'option B si une refonte majeure exige de sortir du gabarit TT25
