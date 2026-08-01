# Runbook — bascule préproduction → production

**Objet :** faire passer le site de `https://wp.judo-lesmartresdeveyre.com/` à
`https://judo-lesmartresdeveyre.com/`.

**Contexte :** même hébergement, même dossier, même base de données. Seul le domaine
change. Aucune copie de fichiers ni de base n'est nécessaire — c'est uniquement une
réécriture d'URLs en base.

**Accès disponibles :** FTP + phpMyAdmin (pas de SSH ni WP-CLI).

**Durée estimée :** 45 min, dont ~15 min d'indisponibilité potentielle du back-office.

---

## Pourquoi une réécriture en base est nécessaire

WordPress stocke des URLs absolues à plusieurs endroits :

- les options `siteurl` et `home` (table `wp_options`) ;
- les `src` des images et les `href` des liens dans le contenu des articles et des
  pages (table `wp_posts`) ;
- les attributs JSON des blocs Gutenberg (`<!-- wp:image {"url":"https://…"} -->`),
  souvent **sérialisés et échappés** ;
- les réglages de certains blocs et widgets (table `wp_options`, `wp_postmeta`).

Un simple `UPDATE ... REPLACE()` en SQL casserait les données sérialisées : la longueur
déclarée de chaque chaîne (`s:42:"https://…"`) ne correspondrait plus au contenu, et
PHP refuserait de les désérialiser. D'où le recours à un outil qui recalcule ces
longueurs.

**Vérifié le 2026-08-01 :** aucune URL n'est codée en dur dans `jcmv-theme` ni dans
`wp-jcmv`. Il n'y a donc rien à modifier côté code.

---

## Outil retenu : Better Search Replace

Plugin gratuit, gère la désérialisation, propose un mode simulation.

Alternatives écartées :

- **Duplicator** (déjà installé) — conçu pour déplacer fichiers + base vers un autre
  hébergement. Hors sujet ici, et bien plus risqué.
- **SQL direct via phpMyAdmin** — casse les données sérialisées (voir ci-dessus).
- **WP-CLI `search-replace`** — la meilleure méthode, mais indisponible sans SSH.

---

## Procédure

### 1. Sauvegarde

- [ ] Export SQL complet de la base via phpMyAdmin (onglet *Exporter*, méthode
      *Personnalisée*, compression gzip).
- [ ] Copie FTP de `wp-content/uploads` et de `wp-config.php`.
- [ ] Vérifier que le fichier `.sql` téléchargé n'est pas vide et se termine bien.

> Le search-replace est **irréversible**. Sans cette sauvegarde, aucun retour arrière
> n'est possible.

### 2. Configuration du domaine chez l'hébergeur

- [ ] Faire pointer `judo-lesmartresdeveyre.com` (et `www.`) sur le **même document
      root** que le sous-domaine `wp.`.
- [ ] Générer le certificat SSL Let's Encrypt pour le nouveau domaine, `www.` inclus.
- [ ] Vérifier que `https://judo-lesmartresdeveyre.com/` répond sans avertissement de
      certificat.

À ce stade la page redirige encore vers `wp.judo-…` : c'est le comportement attendu,
`siteurl` n'a pas encore changé.

### 3. Débloquer l'accès à l'administration sur le nouveau domaine

Ajouter dans `wp-config.php`, **avant** la ligne `/* That's all, stop editing! */` :

```php
define( 'WP_HOME',    'https://judo-lesmartresdeveyre.com' );
define( 'WP_SITEURL', 'https://judo-lesmartresdeveyre.com' );
```

- [ ] Se connecter à `https://judo-lesmartresdeveyre.com/wp-admin/`.

Ces constantes court-circuitent les options en base : elles permettent d'atteindre le
back-office sur le nouveau domaine avant même d'avoir touché aux données.

### 4. Réécriture des URLs

- [ ] Installer et activer **Better Search Replace** (Extensions > Ajouter).
- [ ] Onglet *Search/Replace*, renseigner :

| Champ | Valeur |
| --- | --- |
| Rechercher | `wp.judo-lesmartresdeveyre.com` |
| Remplacer par | `judo-lesmartresdeveyre.com` |
| Tables | **toutes** (Ctrl+A dans la liste) |
| Remplacer les GUID | **décoché** |
| Simulation (dry run) | **coché** pour le premier passage |

- [ ] Lancer la simulation, noter le nombre d'occurrences trouvées et les tables
      concernées.
- [ ] Décocher la simulation, relancer.

**Pourquoi le hostname nu plutôt que l'URL complète :** rechercher
`wp.judo-lesmartresdeveyre.com` sans le protocole attrape aussi les occurrences en
`http://`, les URLs protocol-relative (`//wp.judo-…`) et les formes échappées dans le
JSON des blocs (`https:\/\/wp.judo-…`). Une seule passe suffit.

**Pourquoi ne pas toucher aux GUID :** le champ `guid` est un identifiant unique
historique, pas une URL d'affichage. WordPress ne s'en sert jamais pour construire le
`src` d'une image — celui-ci est reconstruit à partir de `siteurl` et du chemin relatif
stocké dans `_wp_attached_file`. Le modifier n'apporte rien et perturbe les lecteurs de
flux RSS.

### 5. Retirer les constantes temporaires

- [ ] Supprimer les deux `define()` ajoutés à l'étape 3.

La base contient désormais les bonnes valeurs. Les conserver figerait l'URL et
casserait l'environnement Docker local, qui tourne sur `localhost`.

### 6. Redirection 301 de l'ancien sous-domaine

Les deux domaines partagent le même `.htaccess` : la redirection doit donc être
conditionnée sur le `Host`. À placer **avant** le bloc `# BEGIN WordPress` :

```apache
RewriteEngine On
RewriteCond %{HTTP_HOST} ^wp\.judo-lesmartresdeveyre\.com$ [NC]
RewriteRule ^(.*)$ https://judo-lesmartresdeveyre.com/$1 [L,R=301]
```

- [ ] Tester : `https://wp.judo-lesmartresdeveyre.com/` doit renvoyer un 301 vers la
      racine du nouveau domaine, et une URL profonde doit conserver son chemin.

### 7. Vérifications post-bascule

- [ ] **Réglages > Permaliens** — cliquer *Enregistrer* sans rien changer (régénère les
      règles de réécriture).
- [ ] **Réglages > Général** — `siteurl` et `home` affichent bien le nouveau domaine.
- [ ] **Réglages > Lecture** — décocher « demander aux moteurs de recherche de ne pas
      indexer ce site » s'il avait été activé sur la préprod.
- [ ] **Réglages > Médias** — aucun chemin absolu ne doit apparaître.
- [ ] **phpMyAdmin** — vérifier que `upload_path` et `upload_url_path` dans `wp_options`
      sont vides :
      ```sql
      SELECT option_name, option_value FROM wp_options
      WHERE option_name IN ('upload_path','upload_url_path','siteurl','home');
      ```
- [ ] Ouvrir 3 ou 4 articles et vérifier l'affichage des **images mises en avant** et
      des images dans le contenu.
- [ ] Vérifier les **logos partenaires** (taille `jcmv-logo`, bloc `logo-showcase`).
- [ ] Ouvrir la console du navigateur sur la page d'accueil : aucune requête vers
      `wp.judo-…`, aucun avertissement de contenu mixte.
- [ ] Tester le formulaire de contact et la navigation du header/footer.

### 8. Nettoyage

- [ ] **Désinstaller Duplicator.** Le plugin laisse des archives et un `installer.php`
      accessibles publiquement à la racine ; c'est un vecteur d'intrusion documenté sur
      les sites WordPress. Supprimer aussi `wp-content/backups-dup-lite/` et tout
      fichier `installer*.php` restant à la racine.
- [ ] Désactiver Better Search Replace une fois la bascule validée (il peut rester
      installé pour un usage ultérieur).
- [ ] Soumettre le sitemap `https://judo-lesmartresdeveyre.com/wp-sitemap.xml` dans la
      Search Console.

---

## Retour arrière

Si le site est cassé après l'étape 4 :

1. Réimporter le dump SQL de l'étape 1 via phpMyAdmin (après avoir vidé les tables).
2. Retirer les `define()` de `wp-config.php`.
3. Retirer la règle de redirection du `.htaccess`.

Le sous-domaine `wp.` redevient opérationnel, aucun fichier n'ayant été déplacé.

---

## Journal

| Date | Étape | Résultat |
| --- | --- | --- |
| | | |
