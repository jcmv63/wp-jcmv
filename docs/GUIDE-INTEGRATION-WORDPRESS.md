# Guide d'intégration WordPress — thème `jcmv-theme`

Documentation du thème enfant en place dans `wp-content/themes/jcmv-theme`
(enfant de Twenty Twenty-Five — choix acté dans l'ADR-003). Ce guide remplace la
version initiale qui accompagnait le `theme.json` de référence : l'implémentation
réelle a fait évoluer certains choix, notamment les slugs.

## 1. Arborescence du thème

```
wp-content/themes/jcmv-theme/
├── style.css                  # en-tête du thème (Template: twentytwentyfive)
├── theme.json                 # tokens + styles globaux (schéma v3)
├── functions.php              # enqueue CSS, favicons, styles de blocs, catégorie patterns
├── screenshot.png             # vignette écran Apparence
├── assets/
│   ├── css/components.css     # composants hors theme.json (front + éditeur)
│   ├── fonts/*.woff2          # Oswald + Archivo 400/500/600/700, auto-hébergées
│   ├── img/logo.svg           # logo couleur (+ logo-blanc.svg)
│   └── favicon/               # set complet + site.webmanifest
├── parts/                     # header.html, footer.html (délèguent aux patterns PHP)
├── patterns/                  # header, header-blanc, footer + patterns éditoriaux
└── templates/                 # front-page, page, single, home, 404
```

## 2. Tokens : mapping charte → theme.json

**Important :** le thème réutilise les slugs de Twenty Twenty-Five (ses templates les
référencent), remappés sur les couleurs de la charte. Ne jamais renommer un slug.

| Charte | Valeur | Slug WP | Variable CSS | Alias sémantique |
|---|---|---|---|---|
| Blanc | `#FFFFFF` | `base` | `--wp--preset--color--base` | `--wp--custom--color--blanc` |
| Anthracite | `#323642` | `contrast` | `--wp--preset--color--contrast` | `--wp--custom--color--anthracite` |
| Rouge JCMV | `#EE3435` | `accent-1` | `--wp--preset--color--accent-1` | `--wp--custom--color--rouge` |
| Rouge foncé | `#C8282A` | `accent-2` | `--wp--preset--color--accent-2` | `--wp--custom--color--rougeFonce` |
| Rouge pâle | `#FCE9E9` | `accent-3` | `--wp--preset--color--accent-3` | `--wp--custom--color--rougePale` |
| Anthracite foncé | `#1F222B` | `accent-4` | `--wp--preset--color--accent-4` | `--wp--custom--color--anthraciteFonce` |
| Gris fond | `#F5F6F8` | `accent-5` | `--wp--preset--color--accent-5` | `--wp--custom--color--grisFond` |
| Gris bordure | `#E3E5EA` | `accent-6` | `--wp--preset--color--accent-6` | `--wp--custom--color--grisBordure` |
| Anthracite clair | `#4B5160` | `anthracite-clair` | `--wp--preset--color--anthracite-clair` | — |
| Gris texte | `#6B7280` | `gris-texte` | `--wp--preset--color--gris-texte` | — |
| Succès / Attention / Info | `#2E9E5B` / `#E0982B` / `#3B7DD8` | `succes` / `attention` / `info` | idem | — |

Les classes générées suivent le slug : `has-accent-1-background-color`, etc.

Autres presets :

| Token | Slug / clé | Valeur |
|---|---|---|
| Tailles de texte | `small` / `medium` / `large` / `x-large` / `xx-large` / `huge` | 13 / 16 / 18 / 22 / 34 px / clamp H1 |
| Espacements | `20` → `80` | 4 / 8 / 16 / 24 / 32 / 48 / 64 px |
| Rayons | `--wp--custom--radius--{small,medium,round}` | 6 / 14 / 999 px |
| Ombre carte | `--wp--preset--shadow--card` | ombre de la charte |
| Polices | `--wp--preset--font-family--{display,corps}` | Oswald / Archivo |

## 3. Polices (RGPD)

Les WOFF2 sont dans `assets/fonts/` et déclarées via `fontFace` dans theme.json —
aucune requête vers `fonts.googleapis.com` (vérifiable : devtools → Réseau → « font »).
Sous-ensemble latin (accents français et « œ » couverts). Pour changer une graisse :
déposer le fichier `oswald-XXX.woff2` / `archivo-XXX.woff2` et compléter `fontFace`.

## 4. Header et footer : mécanique parts → patterns PHP

`parts/header.html` contient une seule ligne : `<!-- wp:pattern {"slug":"jcmv/header-blanc"} /-->`.
Le pattern PHP correspondant (`patterns/header-blanc.php`, `Inserter: no`) peut exécuter
du PHP — c'est ce qui permet de servir le logo SVG par `get_theme_file_uri()` sans
médiathèque. Même mécanique que TT25.

Deux variantes de header (charte §05) :

- `jcmv/header` — bandeau anthracite foncé, logo blanc (pages sans hero sombre) ;
- `jcmv/header-blanc` — fond blanc, logo couleur (**actif** ; requis quand un bandeau
  anthracite suit immédiatement : bandeau titre, hero).

Pour basculer : changer le slug dans `parts/header.html`.

Le footer (`jcmv/footer`) porte le lien permanent « Gérer mes cookies » (obligation
charte §11) — à brancher sur la solution de consentement lorsqu'elle sera choisie.

## 5. Templates

| Template | Rôle |
|---|---|
| `front-page.html` | Accueil : header + `post-content` (titre jamais rendu). Le contenu s'édite comme n'importe quelle page, avec les patterns |
| `page.html` / `single.html` | Bandeau titre anthracite foncé sous le header, contenu sur blanc |
| `home.html` | Page Actualités : grille de cartes (image 3/2, date, titre, extrait) + pagination charte |
| `404.html` | Bandeau anthracite, ton éditorial club, CTA + recherche |
| hérités de TT25 | archive, search, index… |

Pour une page ponctuelle sans bandeau titre : réglages de la page → Modèle →
« Page sans titre » (fourni par TT25).

## 6. Patterns éditoriaux (catégorie « JCMV » dans l'inséreur)

`jcmv/hero`, `jcmv/chiffres-cles`, `jcmv/temoignage`, `jcmv/faq` (bloc Details natif,
accessible sans JS), `jcmv/dojos`, `jcmv/bandeau-inscription`. Un pattern inséré devient
une copie indépendante : modifier le fichier ne change pas les contenus déjà en place.

## 7. Styles de blocs et composants CSS

- **Bouton** : primaire rouge (styles globaux) ; variation **outline** déclarée dans
  theme.json (ghost rouge ; bordure `currentColor`, donc blanc si `textColor: base`).
- **Paragraphe** : 4 styles d'alerte (succès / erreur / information / attention) au
  panneau Styles de l'éditeur — toujours accompagnés d'un texte explicite (RGAA).
- **Tableau** : stylé d'office (thead anthracite Oswald, zébrures, survol rouge pâle).
- Classes utilitaires dans `components.css` : `jcmv-card`, `jcmv-badge(-…)`,
  `jcmv-kicker`, `jcmv-on-dark-muted`, `jcmv-stat…`, `jcmv-quote…`, `jcmv-accordion`,
  `jcmv-location…`. Le fichier est chargé sur le front **et** dans l'éditeur
  (`add_editor_style`).

## 8. Consommer les tokens depuis le plugin `wp-jcmv`

Le CSS des blocs dynamiques du plugin doit citer les variables du thème, jamais de
valeur en dur :

```css
.jcmv-schedule-card {
    border-left: 5px solid var(--wp--custom--color--rouge);
    border-radius: var(--wp--custom--radius--medium);
    box-shadow: var(--wp--preset--shadow--card);
}
```

Les alias `--wp--custom--color--*` sont préférés aux slugs `accent-*` dans le code du
plugin (lisibilité) ; les deux résolvent vers les mêmes valeurs.

## 9. Développement local

- `WP_DEVELOPMENT_MODE=all` est défini dans le docker-compose : sans lui, WordPress
  met en cache la liste des fichiers `patterns/` et le theme.json (symptôme : un
  nouveau pattern « n'existe pas »). Ne jamais l'activer en production.
- wp-cli tourne dans un conteneur séparé : il ne peut pas régénérer le `.htaccess`
  (voir `scripts/setup-structure.sh` qui l'écrit via le conteneur Apache).
- Structure du site (pages, menu, réglages de lecture) : `./scripts/setup-structure.sh`
  (idempotent).

## 10. Checklist avant mise en production

- [ ] Aucune requête vers `fonts.googleapis.com` / `fonts.gstatic.com`
- [ ] `WP_DEVELOPMENT_MODE` absent / vide en production
- [ ] Contrastes vérifiés après tout ajout de couleur (règles charte §03)
- [ ] Pages légales publiées + consentement cookies branché sur « Gérer mes cookies »
- [ ] Icône de site **non** définie dans les réglages WP (sinon elle prime sur le set
      du thème — comportement voulu, mais à décider une fois)
