#!/usr/bin/env bash

set -euo pipefail

repository="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
workspace="$(mktemp -d)"
trap 'rm -rf -- "$workspace"' EXIT
if [[ -n "${COMPOSER_BINARY:-}" ]]; then
    composer_command=(php "$COMPOSER_BINARY")
else
    composer_command=(composer)
fi

archive_directory="$workspace/archive"
extraction_directory="$workspace/extracted"
mkdir -p -- "$archive_directory" "$extraction_directory"

"${composer_command[@]}" --working-dir="$repository" archive --quiet \
    --format=zip \
    --dir="$archive_directory" \
    --file=kumwe-extension-sdk

mapfile -d '' -t archives < <(
    find "$archive_directory" -maxdepth 1 -type f -name 'kumwe-extension-sdk*.zip' -print0
)
if [[ "${#archives[@]}" -ne 1 ]]; then
    echo "Expected exactly one Composer archive; found ${#archives[@]}." >&2
    exit 1
fi

mapfile -t archive_entries < <(unzip -Z1 -- "${archives[0]}")
archive_directories=()
archive_files=()
for archive_entry in "${archive_entries[@]}"; do
    if [[ "$archive_entry" = /* || "$archive_entry" =~ (^|/)\.\.(/|$) ]]; then
        echo "Composer archive contains an unsafe path: $archive_entry" >&2
        exit 1
    fi
    if [[ "$archive_entry" == */ ]]; then
        archive_directories+=("$archive_entry")
    else
        archive_files+=("$archive_entry")
    fi
done

if [[ "${#archive_directories[@]}" -ne 0 ]]; then
    echo "Composer archive contains unexpected explicit directory entries: ${archive_directories[*]}" >&2
    exit 1
fi
if [[ "${#archive_files[@]}" -ne 238 ]]; then
    echo "Expected 238 archive file entries; found ${#archive_files[@]}." >&2
    exit 1
fi
duplicate_entry="$(printf '%s\n' "${archive_files[@]}" | LC_ALL=C sort | uniq -d | sed -n '1p')"
if [[ -n "$duplicate_entry" ]]; then
    echo "Composer archive contains a duplicate file entry: $duplicate_entry" >&2
    exit 1
fi

unzip -q -- "${archives[0]}" -d "$extraction_directory"

package="$extraction_directory"
if [[ ! -d "$package/src" ]]; then
    mapfile -d '' -t package_directories < <(
        find "$extraction_directory" -mindepth 1 -maxdepth 1 -type d -print0
    )
    if [[ "${#package_directories[@]}" -ne 1 ]]; then
        echo "Expected one extracted package root; found ${#package_directories[@]}." >&2
        exit 1
    fi
    package="${package_directories[0]}"
fi

mapfile -t root_files < <(
    find "$package" -mindepth 1 -maxdepth 1 -type f -printf '%f\n' | LC_ALL=C sort
)
expected_root_files=(CHANGELOG.md LICENSE README.md composer.json)
if [[ "${root_files[*]}" != "${expected_root_files[*]}" ]]; then
    echo "Unexpected archive root files: ${root_files[*]:-(none)}" >&2
    exit 1
fi

mapfile -t root_directories < <(
    find "$package" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | LC_ALL=C sort
)
expected_root_directories=(resources src)
if [[ "${root_directories[*]}" != "${expected_root_directories[*]}" ]]; then
    echo "Unexpected archive root directories: ${root_directories[*]:-(none)}" >&2
    exit 1
fi

if find "$package" -type l -print -quit | grep -q .; then
    echo 'Consumer archive must not contain symbolic links.' >&2
    exit 1
fi

count_files() {
    find "$1" -type f -printf '.' | wc -c | tr -d '[:space:]'
}

root_file_count="${#root_files[@]}"
source_file_count="$(count_files "$package/src")"
resource_file_count="$(count_files "$package/resources")"
archive_file_count="$(count_files "$package")"

if [[ "$root_file_count" -ne 4 \
    || "$source_file_count" -ne 172 \
    || "$resource_file_count" -ne 62 \
    || "$archive_file_count" -ne 238 ]]; then
    printf '%s\n' \
        'Composer archive file counts differ from the reviewed release surface.' \
        "root=$root_file_count (expected 4)" \
        "src=$source_file_count (expected 172)" \
        "resources=$resource_file_count (expected 62)" \
        "total=$archive_file_count (expected 238)" >&2
    exit 1
fi

for root_file in "${expected_root_files[@]}"; do
    if ! cmp -s -- "$repository/$root_file" "$package/$root_file"; then
        echo "Composer archive root file differs from the reviewed source: $root_file" >&2
        exit 1
    fi
done

for consumer_directory in src resources; do
    if ! diff -qr -- "$repository/$consumer_directory" "$package/$consumer_directory"; then
        echo "Composer archive directory differs from the reviewed source: $consumer_directory" >&2
        exit 1
    fi
done

for forbidden in \
    .cache .git .github .idea .phpunit.cache .vscode \
    artifacts build cache caches coverage dist generated node_modules temp tmp var vendor; do
    if find "$package" -mindepth 1 -type d -name "$forbidden" -print -quit | grep -q .; then
        echo "Consumer archive contains forbidden directory: $forbidden" >&2
        exit 1
    fi
done

for forbidden in \
    .DS_Store .cache .coverage .env .php-cs-fixer.cache .phpcs-cache \
    .phpstan.cache .phpunit.cache .phpunit.result.cache clover.xml coverage.xml \
    composer.lock package-lock.json pnpm-lock.yaml yarn.lock; do
    if find "$package" -mindepth 1 -type f -name "$forbidden" -print -quit | grep -q .; then
        echo "Consumer archive contains forbidden file: $forbidden" >&2
        exit 1
    fi
done

if find "$package" -mindepth 1 -type f \
    \( -name '.env.*' -o -name '*.cache' -o -name '*.lcov' -o -name '*.log' -o -name '*.pyc' \
        -o -name '*.swp' -o -name '*.tmp' -o -name '*~' \) \
    -print -quit | grep -q .; then
    echo 'Consumer archive contains cache, log, temporary, or editor state.' >&2
    exit 1
fi

echo 'Composer archive surface verified: 4 root + 172 src + 62 resources = 238 files.'

"${composer_command[@]}" --working-dir="$package" install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader
"${composer_command[@]}" --working-dir="$package" smoke

echo 'Composer archive no-dev installation and smoke test passed.'
