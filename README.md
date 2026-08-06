# JCMV — Site WordPress (dev local)

Environnement de développement local pour le site du Judo Club des Martres-de-Veyre, en vue du développement d'un plugin et de la personnalisation d'un thème. Hébergement cible : OVH (mutualisé, PHP 8.3, MySQL/MariaDB).

## Prérequis

Docker Desktop (ou Docker Engine + Compose v2).

## Démarrage

```bash
docker compose up -d
```

Services :

- WordPress : http://localhost:8080 (installation au premier lancement)
- phpMyAdmin : http://localhost:8081 (user `jcmv` / mdp `jcmv_local`, ou `root` / `root_local`)

Arrêt : `docker compose down` (les données sont conservées). Remise à zéro complète : `docker compose down -v`.

## Structure

```
docker-compose.yml
.env                  # variables locales (ports, identifiants BDD)
config/uploads.ini    # config PHP (taille uploads, mémoire)
wp-content/           # monté dans le conteneur — c'est ici qu'on développe
  plugins/jcmv-*      # futurs plugins du club (versionnés)
  themes/jcmv-*       # futur thème enfant (versionné)
```

Le cœur de WordPress vit dans un volume Docker (`wp_core`) : seul `wp-content/` est exposé sur l'hôte. Le `.gitignore` ne versionne que les plugins/thèmes préfixés `jcmv-`.

## WP-CLI

Un service `wpcli` (profil `cli`) est disponible :

```bash
docker compose run --rm wpcli plugin list
docker compose run --rm wpcli theme install twentytwentyfive --activate
docker compose run --rm wpcli core update
```

Installation initiale scriptée (au lieu de l'assistant web). **Important : lancer d'abord `docker compose up -d`** et attendre que http://localhost:8080 réponde — c'est le conteneur WordPress qui copie les fichiers du core au premier démarrage :

```bash
docker compose run --rm wpcli core install \
  --url=http://localhost:8080 \
  --title="Judo Club les Martres-de-Veyre" \
  --admin_user=admin \
  --admin_password=admin_local \
  --admin_email=equaproduction@gmail.com \
  --locale=fr_FR
docker compose run --rm wpcli language core install fr_FR --activate
```

## Debug

`WP_DEBUG` est actif ; les erreurs sont écrites dans `wp-content/debug.log` (non affichées à l'écran).

## Notes 

### Forcer l'apparition d'une mise à jour

Exécuter les requêtes dans l'ordre indiqué

#### Plugin
```
DELETE FROM wp_options WHERE option_name LIKE '%jcmv_plugin_update_manifest%';
DELETE FROM wp_options WHERE option_name = '_site_transient_update_plugins';
```

#### Thème
```
DELETE FROM wp_options WHERE option_name LIKE '%jcmv_theme_update_manifest%';
DELETE FROM wp_options WHERE option_name = '_site_transient_update_themes';
```

## Outillage

- [squoosh](https://squoosh.app/) : outil en ligne pour optimiser les images
- [Google Lighthouse / PageSpeed Insights](https://pagespeed.web.dev/) : analyse de la performance
