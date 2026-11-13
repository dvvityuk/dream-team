#!/bin/bash
# Раз в сутки отправляет проект на GitHub, если контейнер qdigest запущен.
# Логин и пароль читаются из data/github-push.env и в репозиторий не попадают.
set -euo pipefail

export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
export GIT_TERMINAL_PROMPT=0

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
REMOTE="https://github.com/dvvityuk/dream-team.git"
mkdir -p "$ROOT/data/logs"
exec >>"$ROOT/data/logs/github-push.log" 2>&1
echo "=== $(date -Is) ==="

exec 9>>"$ROOT/data/logs/github-push.lock"
if ! flock -n 9; then
  echo "Уже выполняется другая отправка."
  exit 0
fi

running="$(docker inspect -f '{{.State.Running}}' qdigest 2>/dev/null || true)"
if [[ "$running" != "true" ]]; then
  echo "Контейнер qdigest не запущен. Отправка пропущена."
  exit 0
fi

cred="$ROOT/data/github-push.env"
if [[ ! -f "$cred" ]]; then
  echo "Нет файла data/github-push.env" >&2
  exit 1
fi
GITHUB_USER="$(sed -n 's/^GITHUB_USER=//p' "$cred" | head -n 1 | tr -d '\r')"
GITHUB_TOKEN="$(sed -n 's/^GITHUB_TOKEN=//p' "$cred" | head -n 1 | tr -d '\r')"
if [[ -z "$GITHUB_USER" || -z "$GITHUB_TOKEN" ]]; then
  echo "В data/github-push.env нужны GITHUB_USER и GITHUB_TOKEN." >&2
  exit 1
fi
export GITHUB_USER GITHUB_TOKEN

askpass="$(mktemp)"
chmod 700 "$askpass"
cat >"$askpass" <<'EOF'
#!/bin/sh
case "$1" in
  *sername*) printf '%s\n' "$GITHUB_USER" ;;
  *) printf '%s\n' "$GITHUB_TOKEN" ;;
esac
EOF
cleanup() {
  rm -f "$askpass"
}
trap cleanup EXIT
export GIT_ASKPASS="$askpass"

name="$(git config user.name || true)"
email="$(git config user.email || true)"
if [[ -z "$name" ]]; then
  name="$GITHUB_USER"
fi
if [[ -z "$email" ]]; then
  email="${GITHUB_USER}@users.noreply.github.com"
fi

git add -A
if ! git diff --cached --quiet; then
  git -c user.name="$name" -c user.email="$email" commit -m "Ежедневная копия $(date +%F)"
  echo "Создан коммит."
else
  echo "Новых изменений нет."
fi

if ! git pull --rebase "$REMOTE" main; then
  git rebase --abort >/dev/null 2>&1 || true
  echo "Не удалось подтянуть изменения с GitHub." >&2
  exit 1
fi
git push "$REMOTE" HEAD:main
echo "Проект отправлен на GitHub."
