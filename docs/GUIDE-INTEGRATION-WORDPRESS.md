# Guide d'intégration WordPress — JCMV

Ce guide accompagne `theme.json` : il explique comment le brancher sur un thème bloc (FSE) ou un thème classique, et comment auto-héberger les polices pour rester conforme RGPD.

## 1. Où placer `theme.json`

À la racine du thème (thème enfant ou thème sur mesure) :

```
wp-content/themes/jcmv/
├── theme.json          ← le fichier fourni
├── style.css            (en-tête de thème obligatoire)
├── assets/
│   └── fonts/
│       ├── oswald-400.woff2
│       ├── oswald-500.woff2
│       ├── oswald-600.woff2
│       ├── oswald-700.woff2
│       ├── archivo-400.woff2
│       ├── archivo-500.woff2
│       ├── archivo-600.woff2
│       └── archivo-700.woff2
└── functions.php
```

Le bloc `fontFace` dans `theme.json` nécessite **WordPress 6.5+**. En dessous, il faut enregistrer les polices via `wp_enqueue_style()` dans `functions.php` avec le même chemin `@font-face`.

## 2. Récupérer les polices en local (RGPD)

1. Aller sur [Google Fonts Helper](https://gwfh.mranftl.com/fonts) (ou télécharger directement depuis fonts.google.com).
2. Chercher **Oswald**, cocher les graisses 400 / 500 / 600 / 700, télécharger en **WOFF2 uniquement** (suffisant pour tous les navigateurs modernes).
3. Répéter pour **Archivo** (400 / 500 / 600 / 700).
4. Déposer les fichiers dans `assets/fonts/` avec exactement les noms utilisés dans `theme.json`.

Ne jamais laisser une balise `<link href="https://fonts.googleapis.com/...">` dans le thème final — c'est ce lien qui pose le problème RGPD, pas la police elle-même.

## 3. Vérifier que ça fonctionne

- Dans l'éditeur de blocs (Gutenberg), les styles globaux doivent proposer la palette JCMV (Rouge JCMV, Anthracite, Gris fond…) et les deux familles de police dans les panneaux de style.
- Ouvrir les outils de dev du navigateur → onglet Réseau → filtrer « font » → vérifier qu'aucune requête ne part vers `fonts.googleapis.com` ou `fonts.gstatic.com`.
- Vérifier le `font-display: swap` : le texte doit s'afficher immédiatement avec une police système, puis basculer sur Oswald/Archivo sans écran blanc.

## 4. Mapping des tokens de la charte → theme.json

| Token charte              | Valeur       | Slug dans theme.json          |
|----------------------------|-------------|-------------------------------|
| Rouge JCMV                 | `#EE3435`   | `rouge`                       |
| Rouge foncé                | `#C8282A`   | `rouge-fonce`                 |
| Rouge pâle                 | `#FCE9E9`   | `rouge-pale`                  |
| Anthracite                 | `#323642`   | `anthracite`                  |
| Anthracite foncé           | `#1F222B`   | `anthracite-fonce`            |
| Gris fond                  | `#F5F6F8`   | `gris-fond`                   |
| Gris bordure               | `#E3E5EA`   | `gris-bordure`                |
| Rayon S (boutons, champs)  | `6px`       | `settings.custom.radius.small`|
| Rayon M (cartes)           | `14px`      | `settings.custom.radius.medium`|
| Ombre carte                | voir charte | `settings.custom.shadow.card` |
| Espacement 4/8/16/24/32/48 | px          | `spacing.spacingSizes`        |

Ces valeurs sont directement réutilisables dans les blocs via les classes générées automatiquement par WordPress, par exemple `has-rouge-background-color`, `has-rouge-color`, ou en CSS via `var(--wp--preset--color--rouge)`.

## 5. Prochaine étape suggérée : patterns de blocs

Une fois le thème.json en place, la brique la plus rentable à créer ensuite est un **pattern de bloc réutilisable** pour la carte « créneau horaire » définie en section 09 de la charte (nom de la catégorie, badge d'âge, tableau d'horaires, tarif, bouton d'inscription) — c'est le composant qui manque le plus sur la page Horaires & Tarifs actuelle.

## 6. Auto-hébergement — alternative pour thème classique (non-FSE)

Si le thème n'utilise pas `theme.json` pour le rendu final (thème classique avec `functions.php`), ajouter :

```php
function jcmv_enqueue_fonts() {
    wp_register_style( 'jcmv-fonts', get_theme_file_uri( '/assets/fonts/fonts.css' ), array(), '1.0' );
    wp_enqueue_style( 'jcmv-fonts' );
}
add_action( 'wp_enqueue_scripts', 'jcmv_enqueue_fonts' );
```

Avec un fichier `assets/fonts/fonts.css` contenant les mêmes déclarations `@font-face` que celles décrites dans `theme.json` (voir section 11 de la charte pour la syntaxe complète).
