#!/usr/bin/env bash
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

HOST="u284727446@62.72.2.157"
PORT="65002"
PHP84="/opt/alt/php84/usr/bin/php"

RESIDENCES="/home/u284727446/domains/theazariresidence.com/public_html"
HOTELS="/home/u284727446/domains/azarihotels.com/public_html"

echo
echo "======================================================"
echo " AZARI — CANONICAL HOSTINGER PRODUCTION DEPLOY"
echo "======================================================"

REMOTE="$(git remote get-url origin 2>/dev/null || true)"

if [[ "$REMOTE" != *"wbizmo/azari-residences"* ]]; then
    echo "ERROR: Wrong repository: $REMOTE"
    exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
    echo "ERROR: Working tree is not clean."
    git status --short
    exit 1
fi

echo
echo "[1/8] Sync canonical GitHub main"

git fetch origin
git checkout main
git pull --ff-only origin main

COMMIT="$(git rev-parse --short HEAD)"
FULL_COMMIT="$(git rev-parse HEAD)"

echo
echo "Deploying:"
git log -1 --oneline


echo
echo "[2/8] Verify critical routes locally"

php artisan optimize:clear

ROUTES="$(php artisan route:list)"

for NEEDLE in \
    "azaridevadmin/login" \
    "azari-admin/login" \
    "azari-admin/reports" \
    "azari-admin/inventory"
do
    echo "$ROUTES" | grep -F "$NEEDLE" >/dev/null || {
        echo "ERROR: Required route missing locally: $NEEDLE"
        exit 1
    }
done

echo "Critical routes: OK"


echo
echo "[3/8] Build clean production snapshot"

STAGE="$(mktemp -d)"
PACKAGE="/tmp/azari-production-${COMMIT}.tar.gz"
REMOTE_PACKAGE="/home/u284727446/azari-production-${COMMIT}.tar.gz"

cleanup() {
    rm -rf "$STAGE"
    rm -f "$PACKAGE"
}
trap cleanup EXIT

#
# Export the exact committed Git tree.
#
git archive --format=tar HEAD | tar -xf - -C "$STAGE"

#
# Production-owned state NEVER belongs in the release snapshot.
#
rm -rf "$STAGE/storage"
rm -rf "$STAGE/public/storage"

#
# Cached Laravel state must also never be shipped.
#
rm -rf "$STAGE/bootstrap/cache"
mkdir -p "$STAGE/bootstrap/cache"

#
# Remove things that are useful in source control but unnecessary
# on the production web host.
#
rm -rf "$STAGE/.github"
rm -rf "$STAGE/tests"

#
# Create a manifest of files managed by this release.
# Future deployments can remove files that disappeared from Git.
#
(
    cd "$STAGE"
    find . -type f -o -type l \
        | sed 's#^\./##' \
        | sort \
        > .azari-release-manifest
)

tar -czf "$PACKAGE" -C "$STAGE" .

echo
echo "Package:"
ls -lh "$PACKAGE"

echo
echo "Checking release safety..."

if tar -tzf "$PACKAGE" | grep -E \
    '(^|/)\.env$|^(\./)?storage/|^(\./)?public/storage(/|$)|^(\./)?bootstrap/cache/.+'
then
    echo
    echo "ERROR: Production-owned/runtime state entered the package."
    exit 1
fi

echo "Release package safety: OK"


echo
echo "[4/8] Upload canonical snapshot"

scp \
    -P "$PORT" \
    "$PACKAGE" \
    "$HOST:$REMOTE_PACKAGE"


echo
echo "[5/8] Synchronize BOTH Hostinger applications"

ssh -p "$PORT" "$HOST" 'bash -s' <<REMOTE
set -euo pipefail

PHP84="$PHP84"
PACKAGE="$REMOTE_PACKAGE"
COMMIT="$COMMIT"
FULL_COMMIT="$FULL_COMMIT"

deploy_app() {
    NAME="\$1"
    APP="\$2"

    echo
    echo "======================================================"
    echo " DEPLOYING: \$NAME"
    echo " \$APP"
    echo "======================================================"

    if [ ! -f "\$APP/artisan" ]; then
        echo "ERROR: Laravel installation not found:"
        echo "  \$APP"
        exit 1
    fi

    cd "\$APP"

    STAMP="\$(date +%Y%m%d-%H%M%S)"
    BACKUP="/home/u284727446/\${NAME}-predeploy-\${STAMP}.tar.gz"
    NEW_MANIFEST="/tmp/\${NAME}-new-manifest-\$$"

    echo
    echo "[A] Read new release manifest"

    tar -xOf "\$PACKAGE" ./.azari-release-manifest > "\$NEW_MANIFEST" 2>/dev/null \
        || tar -xOf "\$PACKAGE" .azari-release-manifest > "\$NEW_MANIFEST"

    test -s "\$NEW_MANIFEST"

    echo "Managed release files: \$(wc -l < "\$NEW_MANIFEST")"


    echo
    echo "[B] Back up current application code"

    tar -czf "\$BACKUP" \
        --exclude='.env' \
        --exclude='storage' \
        --exclude='public/storage' \
        --exclude='vendor' \
        --exclude='node_modules' \
        . 2>/dev/null || true

    echo "Backup:"
    echo "  \$BACKUP"


    echo
    echo "[C] Remove files deleted from Git since prior canonical release"

    OLD_MANIFEST="storage/app/azari-deployed-manifest.txt"

    if [ -f "\$OLD_MANIFEST" ]; then
        while IFS= read -r OLD_FILE
        do
            [ -n "\$OLD_FILE" ] || continue

            case "\$OLD_FILE" in
                .env|storage/*|public/storage|public/storage/*)
                    continue
                    ;;
            esac

            if ! grep -Fxq "\$OLD_FILE" "\$NEW_MANIFEST"; then
                if [ -f "\$OLD_FILE" ] || [ -L "\$OLD_FILE" ]; then
                    rm -f "\$OLD_FILE"
                    echo "Removed obsolete managed file: \$OLD_FILE"
                fi
            fi
        done < "\$OLD_MANIFEST"
    else
        echo "First canonical deployment: no previous manifest yet."
    fi


    echo
    echo "[D] Extract complete canonical application snapshot"

    tar -xzf "\$PACKAGE" -C "\$APP"

    rm -f .azari-release-manifest


    echo
    echo "[E] Ensure Laravel runtime directories exist"

    mkdir -p \
        storage/app \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache

    chmod -R u+rwX storage bootstrap/cache || true


    echo
    echo "[F] Install production dependencies"

    composer install \
        --no-dev \
        --prefer-dist \
        --optimize-autoloader \
        --no-interaction


    echo
    echo "[G] Rebuild Laravel caches"

    "\$PHP84" artisan optimize:clear
    "\$PHP84" artisan config:cache
    "\$PHP84" artisan route:cache
    "\$PHP84" artisan view:cache
    "\$PHP84" artisan queue:restart || true


    echo
    echo "[H] Verify production route table"

    PROD_ROUTES="\$("\$PHP84" artisan route:list)"

    for NEEDLE in \
        "azaridevadmin/login" \
        "azari-admin/login" \
        "azari-admin/reports" \
        "azari-admin/inventory"
    do
        echo "\$PROD_ROUTES" | grep -F "\$NEEDLE" >/dev/null || {
            echo "ERROR: Required production route missing: \$NEEDLE"
            exit 1
        }
    done

    echo "Critical production routes: OK"


    echo
    echo "[I] Save canonical deployment manifest/version"

    cp "\$NEW_MANIFEST" \
        storage/app/azari-deployed-manifest.txt

    cat > storage/app/azari-deployed-version.txt <<EOF
application=\$NAME
commit=\$FULL_COMMIT
short_commit=\$COMMIT
deployed_at=\$(date -u '+%Y-%m-%dT%H:%M:%SZ')
source=wbizmo/azari-residences
deployment=canonical-snapshot
EOF

    rm -f "\$NEW_MANIFEST"

    echo
    echo "✓ \$NAME synchronized to \$COMMIT"
}

deploy_app \
    "azari-residences" \
    "$RESIDENCES"

deploy_app \
    "azari-hotels" \
    "$HOTELS"

rm -f "\$PACKAGE"

echo
echo "======================================================"
echo " BOTH PRODUCTION APPS SYNCHRONIZED"
echo "======================================================"
REMOTE


echo
echo "[6/8] Public production smoke tests"

test_endpoint() {
    LABEL="$1"
    URL="$2"
    EXPECT="$3"

    CODE="$(curl -sS -o /dev/null -w '%{http_code}' "$URL")"

    printf '%-34s HTTP %s\n' "$LABEL" "$CODE"

    if [[ "$CODE" != "$EXPECT" ]]; then
        echo "ERROR: $URL expected HTTP $EXPECT but received $CODE"
        exit 1
    fi
}

test_endpoint \
    "Residences direct admin login" \
    "https://theazariresidence.com/azaridevadmin/login" \
    "200"

test_endpoint \
    "Hotels direct admin login" \
    "https://azarihotels.com/azaridevadmin/login" \
    "200"


echo
echo "[7/8] Verify compatibility redirects"

for URL in \
    "https://theazariresidence.com/azari-admin/login" \
    "https://azarihotels.com/azari-admin/login"
do
    curl -sS -o /dev/null \
        -w 'HTTP %{http_code} -> %{redirect_url}\n' \
        "$URL"
done


echo
echo "[8/8] COMPLETE"

echo
echo "Canonical production commit:"
echo "  $FULL_COMMIT"

echo
echo "Permanent deployment guarantees:"
echo "  ✓ GitHub/Replit main is source of truth"
echo "  ✓ complete committed application snapshot deployed"
echo "  ✓ .env never packaged or overwritten"
echo "  ✓ production storage never packaged or overwritten"
echo "  ✓ public/storage never overwritten"
echo "  ✓ database untouched"
echo "  ✓ Laravel caches always rebuilt"
echo "  ✓ critical admin routes checked before and after deployment"
echo "  ✓ deployed commit recorded on each server"
echo "  ✓ managed-file manifest enables future deletion of obsolete code"
