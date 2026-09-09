#!/usr/bin/env bash
set -euo pipefail
sdk_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
php "$sdk_root/tools/verify-dependency-selection.php"
fixture="$(mktemp -d)"
trap 'rm -rf "$fixture"' EXIT
mkdir -p "$fixture/tools" "$fixture/resources" "$fixture/.github/workflows"
cp "$sdk_root/tools/verify-dependency-selection.php" "$fixture/tools/"
reset_fixture() {
    cp "$sdk_root/composer.json" "$fixture/"
    cp "$sdk_root/resources/source-ci-dependencies.json" "$sdk_root/resources/source-candidate-dependencies.json" "$fixture/resources/"
    cp "$sdk_root/.github/workflows/ci.yml" "$sdk_root/.github/workflows/source-candidate.yml" "$fixture/.github/workflows/"
}
refused() {
    if php "$fixture/tools/verify-dependency-selection.php" > "$fixture/result.log" 2>&1; then
        echo "Invalid dependency selection was accepted: $1" >&2
        exit 1
    fi
}
reset_fixture
php -r '$p=$argv[1];$d=json_decode(file_get_contents($p),true);$d["require"]["kumwe/producer"]="^0.2";file_put_contents($p,json_encode($d));' "$fixture/composer.json"
refused 'floating runtime version'
reset_fixture
php -r '$p=$argv[1];$d=json_decode(file_get_contents($p),true);$d["minimum-stability"]="dev";file_put_contents($p,json_encode($d));' "$fixture/composer.json"
refused 'development stability'
reset_fixture
php -r '$p=$argv[1];$d=json_decode(file_get_contents($p),true);$d["kumwe/producer"]["version"]="dev-main";file_put_contents($p,json_encode($d));' "$fixture/resources/source-ci-dependencies.json"
refused 'development source coordinate'
reset_fixture
php -r '$p=$argv[1];$d=json_decode(file_get_contents($p),true);$d[]=$d[0];file_put_contents($p,json_encode($d));' "$fixture/resources/source-candidate-dependencies.json"
refused 'duplicate evidence'
reset_fixture
php -r '$p=$argv[1];$d=json_decode(file_get_contents($p),true);$d[0]["requested_ref"]=str_repeat("0",40);file_put_contents($p,json_encode($d));' "$fixture/resources/source-candidate-dependencies.json"
refused 'stale evidence commit'
reset_fixture
php -r '$p=$argv[1];$d=file_get_contents($p);$d=preg_replace_callback("~(repository: kumwe/producer\\R\\s+ref: )[0-9a-f]{40}~", static fn($m) => $m[1] . str_repeat("0",40), $d);file_put_contents($p,$d);' "$fixture/.github/workflows/source-candidate.yml"
refused 'stale secondary workflow checkout'
echo 'Dependency selection regression checks passed: 6 invalid graphs refused.'
