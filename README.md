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
  --title="Judo Club des Martres-de-Veyre" \
  --admin_user=admin \
  --admin_password=admin_local \
  --admin_email=equaproduction@gmail.com \
  --locale=fr_FR
docker compose run --rm wpcli language core install fr_FR --activate
```

## Debug

`WP_DEBUG` est actif ; les erreurs sont écrites dans `wp-content/debug.log` (non affichées à l'écran).

## Notes pour la migration OVH

- Image PHP 8.3 pour coller aux hébergements mutualisés OVH récents (version recommandée par WordPress 7.0).
- MariaDB 10.11, compatible avec les BDD OVH.
- Pour migrer : export BDD (phpMyAdmin ou `wpcli db export`), copie de `wp-content/`, puis recherche/remplacement des URLs (`wpcli search-replace http://localhost:8080 https://votre-domaine.fr`).
