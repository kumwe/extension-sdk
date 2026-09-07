#!/usr/bin/env bash
set -euo pipefail
unset GITHUB_ENV

sdk_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
fixture_root=$(mktemp -d)
trap 'rm -rf "$fixture_root"' EXIT
export SDK_TEST_REAL_GIT
SDK_TEST_REAL_GIT=$(command -v git)
mkdir -p "$fixture_root/bin"
cat > "$fixture_root/bin/git" <<'GIT'
#!/usr/bin/env bash
set -euo pipefail
if [[ ${1:-} == ls-remote ]]; then
  printf 'lookup\n' >> "$SDK_TEST_CASE/remote.log"
  [[ ! -f "$SDK_TEST_CASE/deleted" ]] || exit 2
  printf '%s\t%s\n' "$("$SDK_TEST_REAL_GIT" -C "$SDK_TEST_CASE/dependencies/fixture" rev-parse HEAD)" "$SDK_TEST_REF"
  exit 0
fi
exec "$SDK_TEST_REAL_GIT" "$@"
GIT
cat > "$fixture_root/bin/composer" <<'COMPOSER'
#!/usr/bin/env bash
set -euo pipefail
[[ ! -f "$SDK_TEST_CASE/install-failure" ]] || exit 9
lock=${COMPOSER%.json}.lock
if [[ " $* " == *' --no-dev '* && -f "$SDK_TEST_CASE/candidate-dependency-evidence.json" ]]; then
  cmp "$SDK_TEST_CASE/candidate-composer.lock" "$lock"
fi
[[ -f "$lock" ]] || printf '{"packages":[],"packages-dev":[]}\n' > "$lock"
COMPOSER
chmod +x "$fixture_root/bin/git" "$fixture_root/bin/composer"
export PATH="$fixture_root/bin:$PATH"
export GITHUB_RUN_ID=123 GITHUB_RUN_ATTEMPT=1 GITHUB_JOB=sdk
export SDK_TEST_CASE SDK_TEST_REF

new_fixture() {
  SDK_TEST_CASE="$fixture_root/$1"
  local version=${2:-dev-source}
  SDK_TEST_REF=refs/heads/source
  [[ $version == dev-* ]] || SDK_TEST_REF="refs/tags/v$version"
  mkdir -p "$SDK_TEST_CASE/package/tools" "$SDK_TEST_CASE/package/resources" "$SDK_TEST_CASE/dependencies/fixture"
  cp "$sdk_root/tools/install-source-candidate.php" "$SDK_TEST_CASE/package/tools/"
  printf '{"name":"kumwe/fixture","autoload":{"psr-4":{"Kumwe\\\\Fixture\\\\":"src/"}}}\n' \
    > "$SDK_TEST_CASE/dependencies/fixture/composer.json"
  printf 'reviewed runtime\n' > "$SDK_TEST_CASE/dependencies/fixture/runtime.txt"
  "$SDK_TEST_REAL_GIT" -C "$SDK_TEST_CASE/dependencies/fixture" init -q
  "$SDK_TEST_REAL_GIT" -C "$SDK_TEST_CASE/dependencies/fixture" add .
  "$SDK_TEST_REAL_GIT" -C "$SDK_TEST_CASE/dependencies/fixture" -c user.name=Fixture -c user.email=fixture@example.invalid commit -qm initial
  local ref
  ref=$("$SDK_TEST_REAL_GIT" -C "$SDK_TEST_CASE/dependencies/fixture" rev-parse HEAD)
  printf '{"name":"kumwe/sdk-fixture","require":{"kumwe/fixture":"%s"}}\n' "$version" > "$SDK_TEST_CASE/package/composer.json"
  printf '{"kumwe/fixture":{"version":"%s","ref":"%s"}}\n' "$version" "$ref" \
    > "$SDK_TEST_CASE/package/resources/source-ci-dependencies.json"
  "$SDK_TEST_REAL_GIT" -C "$SDK_TEST_CASE/package" init -q
  "$SDK_TEST_REAL_GIT" -C "$SDK_TEST_CASE/package" add .
  "$SDK_TEST_REAL_GIT" -C "$SDK_TEST_CASE/package" -c user.name=Fixture -c user.email=fixture@example.invalid commit -qm initial
}

install_fixture() {
  php "$SDK_TEST_CASE/package/tools/install-source-candidate.php" "$@" > "$SDK_TEST_CASE/output.log" 2>&1
}

reject_fixture() {
  local expected=$1
  shift
  if install_fixture "$@"; then
    printf 'Expected refusal: %s\n' "$expected" >&2
    exit 1
  fi
  grep -qE "$expected" "$SDK_TEST_CASE/output.log" || {
    cat "$SDK_TEST_CASE/output.log" >&2
    exit 1
  }
}

new_fixture deleted-after-install
install_fixture
touch "$SDK_TEST_CASE/deleted"
install_fixture --no-dev
[[ $(wc -l < "$SDK_TEST_CASE/remote.log") -eq 1 ]]

new_fixture unavailable-before-install
touch "$SDK_TEST_CASE/deleted"
reject_fixture 'Unavailable or unrelated source coordinate' --no-dev

new_fixture changed-graph
install_fixture
printf '\n' >> "$SDK_TEST_CASE/package/resources/source-ci-dependencies.json"
reject_fixture 'snapshot does not match' --no-dev

new_fixture changed-checkout
install_fixture
printf 'new revision\n' >> "$SDK_TEST_CASE/dependencies/fixture/runtime.txt"
"$SDK_TEST_REAL_GIT" -C "$SDK_TEST_CASE/dependencies/fixture" add .
"$SDK_TEST_REAL_GIT" -C "$SDK_TEST_CASE/dependencies/fixture" -c user.name=Fixture -c user.email=fixture@example.invalid commit -qm next
reject_fixture 'commit mismatch' --no-dev

new_fixture changed-tracked-content
install_fixture
printf 'dirty source\n' >> "$SDK_TEST_CASE/dependencies/fixture/runtime.txt"
reject_fixture 'checkout has changed' --no-dev

new_fixture untracked-content
install_fixture
printf '<?php\n' > "$SDK_TEST_CASE/dependencies/fixture/extra.php"
reject_fixture 'contains untracked files' --no-dev

new_fixture changed-manifest
install_fixture
printf '\n' >> "$SDK_TEST_CASE/package/composer.json"
reject_fixture 'snapshot does not match' --no-dev

new_fixture changed-lock
install_fixture
printf '\n' >> "$SDK_TEST_CASE/candidate-composer.lock"
reject_fixture 'Composer plan or lock has changed' --no-dev

new_fixture different-job
install_fixture
GITHUB_JOB=other reject_fixture 'snapshot does not match' --no-dev

new_fixture outside-actions
install_fixture
touch "$SDK_TEST_CASE/deleted"
GITHUB_RUN_ID= GITHUB_RUN_ATTEMPT= GITHUB_JOB= reject_fixture 'Unavailable or unrelated source coordinate' --no-dev

new_fixture stable-tag 0.1.0
install_fixture
touch "$SDK_TEST_CASE/deleted"
reject_fixture 'Unavailable or unrelated source coordinate' --no-dev
[[ $(wc -l < "$SDK_TEST_CASE/remote.log") -eq 2 ]]

new_fixture failed-first-install
touch "$SDK_TEST_CASE/install-failure"
if install_fixture; then exit 1; fi
[[ ! -e "$SDK_TEST_CASE/candidate-dependency-evidence.json" ]]
rm "$SDK_TEST_CASE/install-failure"
touch "$SDK_TEST_CASE/deleted"
reject_fixture 'Unavailable or unrelated source coordinate' --no-dev

printf 'Source installer regression suite passed: 12 cases.\n'
