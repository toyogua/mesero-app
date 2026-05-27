#!/usr/bin/env bash
set -e -o pipefail

REMOTE_USER="dh_7ejcae"
REMOTE_HOST="iad1-shared-b7-44.dreamhost.com"
DOMAIN="mesero-app.tenantsrocksoftgt.com"
REMOTE_PATH="/home/dh_7ejcae/mesero-app.tenantsrocksoftgt.com"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../" && pwd)"

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'
CYAN='\033[0;36m'; NC='\033[0m'
info()    { echo -e "${CYAN}> $*${NC}"; }
success() { echo -e "${GREEN}✓ $*${NC}"; }
warn()    { echo -e "${YELLOW}⚠ $*${NC}"; }
error()   { echo -e "${RED}✗ $*${NC}" >&2; exit 1; }

SSH_OPTS="-o StrictHostKeyChecking=no -o ConnectTimeout=15"
if [[ -n "${DEPLOY_PASSWORD:-}" ]]; then
  command -v sshpass &>/dev/null || error "sshpass no instalado (brew install sshpass)"
  export SSHPASS="$DEPLOY_PASSWORD"
  SSH_CMD="sshpass -e ssh $SSH_OPTS -o PubkeyAuthentication=no"
  RSYNC_SSH="sshpass -e ssh $SSH_OPTS -o PubkeyAuthentication=no"
else
  SSH_CMD="ssh $SSH_OPTS"
  RSYNC_SSH="ssh $SSH_OPTS"
fi

BACKEND_BUILT=false

cleanup() {
  if [[ "$BACKEND_BUILT" == "true" ]]; then
    info "Restaurando dependencias locales..."
    cd "$PROJECT_DIR" && composer install 2>&1 | tail -1
    success "Entorno local restaurado"
  fi
}
trap cleanup EXIT

build_frontend() {
  info "Compilando assets (Vite)..."
  cd "$PROJECT_DIR"
  npm install --silent
  npm run build
  success "Frontend listo: $(find public/build -type f 2>/dev/null | wc -l | tr -d ' ') archivos"
}

build_backend() {
  info "Preparando Laravel (sin devDeps)..."
  cd "$PROJECT_DIR"
  composer install --no-dev --optimize-autoloader 2>&1 | tail -1
  BACKEND_BUILT=true
  success "Backend listo"
}

upload_files() {
  local mode="${1:-all}"
  local excludes=(
    --exclude='.env'
    --exclude='.env.*'
    --exclude='.DS_Store'
    --exclude='.git/'
    --exclude='node_modules/'
    --exclude='tests/'
    --exclude='scripts/'
    --exclude='docker/'
    --exclude='docker-compose.yml'
    --exclude='openspec/'
    --exclude='public/hot'
    --exclude='storage/logs/*'
    --exclude='storage/framework/cache/*'
    --exclude='storage/framework/sessions/*'
    --exclude='storage/framework/views/*'
  )

  if [[ "$mode" == "frontend" ]]; then
    info "Subiendo assets..."
    rsync -az --delete -e "$RSYNC_SSH" \
      "$PROJECT_DIR/public/build/" \
      "$REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH/public/build/"
  elif [[ "$mode" == "backend" ]]; then
    info "Subiendo backend..."
    rsync -az --delete --progress -e "$RSYNC_SSH" "${excludes[@]}" \
      --exclude='public/build/' \
      "$PROJECT_DIR/" "$REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH/"
  else
    info "Subiendo todo..."
    rsync -az --delete --progress -e "$RSYNC_SSH" "${excludes[@]}" \
      "$PROJECT_DIR/" "$REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH/"
  fi

  if [[ -f "$PROJECT_DIR/.env.production" ]]; then
    rsync -az -e "$RSYNC_SSH" \
      "$PROJECT_DIR/.env.production" \
      "$REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH/.env"
    success ".env.production subido como .env"
  else
    warn "No existe .env.production — el .env remoto no fue tocado"
  fi

  success "Upload OK"
}

post_deploy() {
  info "Post-deploy en servidor..."
  $SSH_CMD "$REMOTE_USER@$REMOTE_HOST" bash << REMOTE
    set -e
    cd $REMOTE_PATH

    # Root .htaccess: redirige todo a public/
    cat > .htaccess << 'HTACC'
RewriteEngine On

# Evita leak de /public/ en la URL
RewriteCond %{REQUEST_URI} ^/public/
RewriteRule ^public/(.*)$ /\$1 [R=301,L]

# Todo pasa por public/
RewriteRule ^(.*)$ public/\$1 [L,QSA]
HTACC

    # public/.htaccess: routing de Laravel
    cat > public/.htaccess << 'HTACC'
<IfModule mod_rewrite.c>
    Options -MultiViews -Indexes
    RewriteEngine On
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
HTACC

    # PHP 8.3 FastCGI para DreamHost
    cat > public/dispatch.fcgi << 'DISP'
#!/bin/bash
exec /usr/local/bin/php83.cgi
DISP
    chmod 755 public/dispatch.fcgi

    chmod -R 775 storage bootstrap/cache
    chmod -R 755 public

    # Generar APP_KEY si no existe
    if ! grep -q 'APP_KEY=base64:' .env 2>/dev/null; then
      php artisan key:generate --force
    fi

    php artisan migrate --force
    php artisan storage:link --force 2>/dev/null || true
    php artisan optimize:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    echo "OK"
REMOTE
  success "Post-deploy OK"
}

migrate() {
  info "Ejecutando migraciones..."
  $SSH_CMD "$REMOTE_USER@$REMOTE_HOST" \
    "cd $REMOTE_PATH && php artisan migrate --force && php artisan optimize"
  success "Migraciones OK"
}

verify() {
  info "Verificando respuesta HTTP..."
  local http
  http=$(curl -s -o /dev/null -w "%{http_code}" --max-time 15 "https://$DOMAIN/" 2>/dev/null || echo "000")
  [[ "$http" == "200" ]] && success "HTTP $http — sitio OK" || warn "HTTP $http — revisá el servidor"
}

echo ""
echo "mesero-app → DreamHost ($DOMAIN)"
echo ""

case "${1:-deploy}" in
  deploy|"")    build_frontend; build_backend; upload_files all; post_deploy; verify ;;
  --frontend)   build_frontend; upload_files frontend; verify ;;
  --backend)    build_backend; upload_files backend; post_deploy; verify ;;
  --skip-build) upload_files all; post_deploy; verify ;;
  --db)         migrate ;;
  *) echo "Uso: $0 [--frontend|--backend|--skip-build|--db]"; exit 1 ;;
esac
