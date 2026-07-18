#!/usr/bin/env bash
#
# Structure du site JCMV : pages squelettes, réglages de lecture,
# permaliens et menu de navigation principal.
# Idempotent : peut être relancé sans créer de doublons.
#
set -euo pipefail
cd "$(dirname "$0")/.."

wp() { docker compose run --rm -T wpcli "$@"; }

echo "== Permaliens propres (/%postname%/)"
wp rewrite structure '/%postname%/'
# wp-cli (conteneur séparé) ne peut pas régénérer le .htaccess : on l'écrit
# directement dans le conteneur Apache.
docker compose exec -T wordpress sh -c 'cat > /var/www/html/.htaccess << "EOF"
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
EOF
chown www-data:www-data /var/www/html/.htaccess'
echo "   .htaccess écrit"

# Crée la page si son slug n'existe pas, renvoie son ID dans tous les cas.
get_or_create_page() {
	local title="$1" slug="$2" id
	id=$(wp post list --post_type=page --name="$slug" --field=ID | tr -d '[:space:]')
	if [ -z "$id" ]; then
		id=$(wp post create --post_type=page --post_status=publish \
			--post_title="$title" --post_name="$slug" --porcelain | tr -d '[:space:]')
		echo "   page créée : $title (#$id)" >&2
	else
		echo "   page existante : $title (#$id)" >&2
	fi
	echo "$id"
}

echo "== Pages"
ACCUEIL=$(get_or_create_page "Accueil" "accueil")
CLUB=$(get_or_create_page "Le club" "le-club")
PRATIQUER=$(get_or_create_page "Pratiquer" "pratiquer")
HORAIRES=$(get_or_create_page "Horaires et tarifs" "horaires-tarifs")
ACTUS=$(get_or_create_page "Actualités" "actualites")
CONTACT=$(get_or_create_page "Contact" "contact")

echo "== Réglages de lecture (accueil statique + page des articles)"
wp option update show_on_front page
wp option update page_on_front "$ACCUEIL"
wp option update page_for_posts "$ACTUS"

echo "== Menu principal (wp_navigation)"
BASE=$(wp option get siteurl | tr -d '[:space:]')
NAV_ID=$(wp post list --post_type=wp_navigation --field=ID | head -n1 | tr -d '[:space:]')

NAV_CONTENT=$(cat <<EOF
<!-- wp:navigation-link {"label":"Le club","type":"page","id":$CLUB,"url":"$BASE/le-club/","kind":"post-type"} /-->
<!-- wp:navigation-link {"label":"Pratiquer","type":"page","id":$PRATIQUER,"url":"$BASE/pratiquer/","kind":"post-type"} /-->
<!-- wp:navigation-link {"label":"Horaires et tarifs","type":"page","id":$HORAIRES,"url":"$BASE/horaires-tarifs/","kind":"post-type"} /-->
<!-- wp:navigation-link {"label":"Actualités","type":"page","id":$ACTUS,"url":"$BASE/actualites/","kind":"post-type"} /-->
<!-- wp:navigation-link {"label":"Contact","type":"page","id":$CONTACT,"url":"$BASE/contact/","kind":"post-type"} /-->
EOF
)

if [ -z "$NAV_ID" ]; then
	NAV_ID=$(wp post create --post_type=wp_navigation --post_status=publish \
		--post_title="Menu principal" --post_content="$NAV_CONTENT" --porcelain | tr -d '[:space:]')
	echo "   menu créé (#$NAV_ID)"
else
	wp post update "$NAV_ID" --post_content="$NAV_CONTENT" >/dev/null
	echo "   menu existant mis à jour (#$NAV_ID)"
fi

echo "== Terminé. Pages : accueil=$ACCUEIL club=$CLUB pratiquer=$PRATIQUER horaires=$HORAIRES actus=$ACTUS contact=$CONTACT"
