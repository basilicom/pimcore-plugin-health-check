#!/bin/sh
# This source file is available under the terms of the MIT License.
# Full copyright and license information is available in
# LICENSE.txt which is distributed with this source code.
#
# @copyright Copyright (c) Basilicom GmbH (https://basilicom.de)
# @license   MIT

# Local demo: a throwaway Pimcore app with this working tree as a composer path dependency.
# Provisioned once into the /app volume, then served with `php -S`. Each step leaves a stamp in
# /app/.demo/, so a restart resumes instead of re-downloading Pimcore. `make destroy` drops it.
#
# Pimcore 11 (the default) installs without a licence. 12.3 and 2026 install a LICENSED Pimcore
# and need PIMCORE_ENCRYPTION_SECRET, PIMCORE_INSTANCE_IDENTIFIER and PIMCORE_PRODUCT_KEY in
# .env.local; nothing here bypasses the product-key check.
set -eu

APP=/app
STAMPS="$APP/.demo"
PORT="${DEMO_PORT_INTERNAL:-8081}"
SKELETON="${DEMO_SKELETON:-^11.0}"

# The skeleton is the project template, not the Pimcore version: ^11.0 resolves 11.5.x, ^2025.4
# resolves 12.3.x, ^2026 resolves 2026.x.
case "$SKELETON" in
	*11.*) GENERATION=11 ;;
	*12.*|*2025.*) GENERATION=12 ;;
	*) GENERATION=2026 ;;
esac

require_licence() {
	missing=''
	for name in PIMCORE_ENCRYPTION_SECRET PIMCORE_INSTANCE_IDENTIFIER PIMCORE_PRODUCT_KEY; do
		eval "value=\${$name:-}"
		[ -n "$value" ] || missing="$missing $name"
	done
	if [ -n "$missing" ]; then
		echo "ERROR: Pimcore $GENERATION is a licensed install and needs its product key." >&2
		echo "       missing:$missing" >&2
		echo "       Put all three in .env.local, or use the default Pimcore 11 demo (make start)." >&2
		exit 1
	fi
}

done_with() { [ -f "$STAMPS/$1" ]; }
stamp() { mkdir -p "$STAMPS"; touch "$STAMPS/$1"; }

[ "$GENERATION" = 11 ] || require_licence

# A demo keeps its generation: switching resumes nothing and would serve the old app silently.
if [ -f "$STAMPS/skeleton.id" ] && [ "$(cat "$STAMPS/skeleton.id")" != "$SKELETON" ]; then
	echo "== this demo holds $(cat "$STAMPS/skeleton.id"), not $SKELETON - run make destroy first ==" >&2
	exit 1
fi

# Pimcore 11 cannot be installed under composer's advisory policy: the last public 11.x
# (pimcore 11.5.14.1, admin-ui-classic 1.7.16) carries advisories fixed only in Enterprise or 12.3+
# releases. Excepted by id, for blocking only, in this container's GLOBAL composer config: the
# bundle's composer.json is untouched, `composer audit` still reports them, and any NEW advisory
# still blocks the install - which is the signal to review this list. This is a localhost demo of a
# known-vulnerable Pimcore; never expose its port.
PIMCORE11_ADVISORIES='
PKSA-v29g-sqpm-mznn PKSA-pmf2-z78s-582m PKSA-vgg9-cbdg-s4xt PKSA-kkjc-bw16-f3kq PKSA-fpxc-s2d8-24zv
PKSA-6dhb-gq75-qpgr PKSA-vp19-ydt7-tws9 PKSA-vd5r-2gyh-m6cc PKSA-v5bg-33q7-q8zj PKSA-y4yc-6g1b-qfqz
PKSA-882j-k212-wjbf PKSA-kp19-xmdp-rvyj PKSA-8x4n-f9v2-4s1d'

if [ "$GENERATION" = 11 ]; then
	# Newer composer calls it policy.advisories, the 2.9 in the stock image still audit.ignore.
	policy='' legacy=''
	for id in $PIMCORE11_ADVISORIES; do
		policy="$policy${policy:+,}\"$id\":{\"reason\":\"no public 11.x fix\",\"on-block\":true,\"on-audit\":false}"
		legacy="$legacy${legacy:+,}\"$id\":{\"apply\":\"block\",\"reason\":\"no public 11.x fix\"}"
	done
	composer config -g --json policy.advisories.ignore-id "{$policy}" 2>/dev/null \
		|| composer config -g --json audit.ignore "{$legacy}"
	echo "NOTE: Pimcore 11 demo - $(echo $PIMCORE11_ADVISORIES | wc -w | tr -d ' ') advisories excepted from blocking (composer audit still reports them)" >&2
fi

if ! done_with skeleton; then
	echo "== [1/4] fetching pimcore/skeleton ($SKELETON) =="
	# create-project refuses a non-empty target and counts dotfiles, so /app is wiped whole.
	find "$APP" -mindepth 1 -maxdepth 1 -exec rm -rf {} +
	composer create-project --no-install --no-scripts --no-interaction --no-progress \
		"pimcore/skeleton:$SKELETON" "$APP"
	mkdir -p "$STAMPS"
	printf '%s' "$SKELETON" > "$STAMPS/skeleton.id"
	cd "$APP"
	composer config allow-plugins true
	composer config optimize-autoloader false
	# The working tree, mounted at /bundle, symlinked into vendor/: PHP changes are live.
	composer config repositories.bundle '{"type":"path","url":"/bundle","options":{"symlink":true}}'
	stamp skeleton
fi

cd "$APP"

if ! done_with composer; then
	echo "== [2/4] installing the app + the bundle as a path dependency =="
	# pimcore-bundle-manager registers bundles in config/bundles.php on Pimcore 11+, where
	# pimcore:bundle:enable no longer exists. Demo app only, never a dependency of the bundle.
	composer require --no-scripts --no-interaction --no-progress \
		'basilicom/pimcore-plugin-health-check:*@dev' \
		blackbit_digital_commerce/pimcore-bundle-manager
	stamp composer
fi

if ! done_with install; then
	echo "== [3/4] installing Pimcore $GENERATION =="
	case "$GENERATION" in
	11|12)
		# 11.5 and 12.3 take the coordinates as arguments; none of their options reads the env.
		set -- \
			--admin-username="${PIMCORE_ADMIN_USER:-admin}" \
			--admin-password="${PIMCORE_ADMIN_PASSWORD:-admin}" \
			--mysql-host-socket=demo-db \
			--mysql-port=3306 \
			--mysql-username=pimcore \
			--mysql-password=pimcore \
			--mysql-database=pimcore
		if [ "$GENERATION" = 12 ]; then
			set -- "$@" \
				--encryption-secret="$PIMCORE_ENCRYPTION_SECRET" \
				--instance-identifier="$PIMCORE_INSTANCE_IDENTIFIER" \
				--product-key="$PIMCORE_PRODUCT_KEY"
		fi
		vendor/bin/pimcore-install --no-interaction "$@"
		;;
	*)
		vendor/bin/pimcore-install --no-interaction --skip-validation=mercure
		;;
	esac
	stamp install
fi

if ! done_with bundle; then
	echo "== [4/4] registering and configuring the bundle =="
	# After the install: the installer rewrites config/bundles.php, dropping anything added earlier.
	vendor/bin/pimcore-bundle-enable PimcorePluginHealthCheckBundle
	# The key comes from the environment, so the demo never writes a literal secret into the app.
	printf 'pimcore_plugin_health_check:\n    dashboard:\n        api_key: "%%env(HEALTH_CHECK_API_KEY)%%"\n' \
		> config/packages/health_check.yaml
	printf 'web_profiler:\n    toolbar: false\n' > config/packages/dev/demo.yaml
	bin/console cache:clear --no-warmup
	stamp bundle
	echo "== provisioned =="
fi

# The dashboard only renders a stored run; refresh it on every start so the page is never empty.
echo "== running the audit =="
bin/console basilicom:health-check:audit || echo "NOTE: the audit reported failures - that is what the dashboard is for"

echo "== serving http://0.0.0.0:$PORT =="
exec php -d memory_limit=1G -S "0.0.0.0:$PORT" -t "$APP/public" /bundle/.docker/demo/router.php
