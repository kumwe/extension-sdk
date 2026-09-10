#!/usr/bin/env bash
set -euo pipefail
kind="${1:?native artifact kind}"
source_root="${2:?unchanged extracted source root}"
evidence="${3:?external evidence directory}"
export KUMWE_NATIVE_EVIDENCE="$evidence"
python3 - <<'PY'
import json, os, pathlib, socket
parent = os.environ['KUMWE_PARENT_NET_NS']
current = os.readlink('/proc/self/ns/net')
assert current != parent, 'The build still shares the network-enabled parent namespace.'
assert {name for _, name in socket.if_nameindex()} == {'lo'}, 'Unexpected network interface in isolated build.'
pathlib.Path(os.environ['KUMWE_NATIVE_EVIDENCE'], 'network-namespace.json').write_text(
    json.dumps({'parent_namespace': parent, 'build_namespace': current, 'interfaces': ['lo'],
                'network_disabled': True}, indent=2) + '\n')
PY
cd "$source_root"
cmake --version > "$evidence/cmake-version.txt"
"${CC:-cc}" --version > "$evidence/c-compiler-version.txt"
"${CXX:-c++}" --version > "$evidence/compiler-version.txt"
uname -a > "$evidence/platform.txt"
if [[ "$kind" == native_cpp ]]; then
  bash tools/check-architecture.sh
  cmake -S . -B "$evidence/build" -DCMAKE_BUILD_TYPE=Release -DBUILD_TESTING=ON
  cmake --build "$evidence/build" --parallel 2
  # The released CMake recipe verifies the ABI source closure while configuring.
  # CTest and the real CLI prove the executable ABI, corpus and capability inventories.
  ctest --test-dir "$evidence/build" --show-only=json-v1 > "$evidence/ctest-inventory.json"
  ctest --test-dir "$evidence/build" --no-tests=error --output-on-failure --output-junit "$evidence/ctest.xml"
  bash tools/check-consumer.sh
  "$evidence/build/kumwe-engine-conformance" --capabilities > "$evidence/actual-capabilities.json"
  "$evidence/build/kumwe-engine-conformance" --verify-bundle "$source_root/corpus" > "$evidence/corpus-results.json"
  cp "$evidence/build/generated/capabilities.json" "$evidence/expected-capabilities.json"
  python3 - <<'PY'
import json, os, pathlib, xml.etree.ElementTree as ET
p = pathlib.Path(os.environ['KUMWE_NATIVE_EVIDENCE'])
actual = json.loads((p/'actual-capabilities.json').read_text())
expected = json.loads((p/'expected-capabilities.json').read_text())
assert actual == expected, 'Installed native Engine reports a different complete capability tuple.'
inventory = json.loads((p/'ctest-inventory.json').read_text())['tests']
executed = ET.parse(p/'ctest.xml').getroot().findall('testcase')
assert inventory and sorted(test['name'] for test in inventory) == sorted(test.get('name') for test in executed), \
    'The complete configured CTest inventory must execute.'
assert all(test.get('status') == 'run' and test.find('failure') is None and test.find('skipped') is None
           for test in executed), 'A native CTest was failed or skipped.'
result = {'status': 'passed', 'network_disabled': True, 'kind': 'native_cpp',
          'actual_capabilities': actual, 'ctest': 'passed', 'installed_c11_consumer': 'passed',
          'ctest_count': len(executed),
          'corpus': json.loads((p/'corpus-results.json').read_text())}
(p/'verification.json').write_text(json.dumps(result, indent=2) + '\n')
PY
elif [[ "$kind" == php_extension ]]; then
  test -n "${KUMWE_PIE_PATH:?exact pinned PIE executable}"
  pie_path="$(readlink -f "$KUMWE_PIE_PATH")"
  printf '%s  %s\n' 'b88792235c8e80be568436d4cb043b49fd1869c89b64e83d23e2882ae19d70a8' "$pie_path" | sha256sum --check --strict
  "$pie_path" --version > "$evidence/pie-version.txt"
  sha256sum "$pie_path" > "$evidence/pie-installer.sha256"
  php -n -v > "$evidence/php-version.txt"
  php -n tools/generate-arginfo.php --check
  php -n tools/verify-binding.php
  php -n tools/verify-engine.php
  bash tools/offline-install.sh
  php -n tools/verify-native-linkage.php
  php -n tools/expected-tuple.php > "$evidence/expected-tuple.json"
  cp "$evidence/expected-tuple.json" candidate-compatibility.json
  cmake --build engine-build --target kumwe-engine-conformance --parallel 2
  engine-build/kumwe-engine-conformance --capabilities > engine-cli-capabilities.json
  php -n tools/verify-cli-tuple.php
  php tools/consumer.php > "$evidence/actual-tuple.json"
  php -n -d extension="$PWD/modules/kumwe_engine.so" tools/lifecycle.php
  NO_INTERACTION=1 REPORT_EXIT_STATUS=1 make test TESTS='tests' TEST_PHP_ARGS='-q'
  cp build-identity.json "$evidence/build-identity.json"
  cp engine-cli-capabilities.json "$evidence/engine-cli-capabilities.json"
  php -r '
    $classes = [];
    foreach ((new ReflectionExtension("kumwe_engine"))->getClasses() as $class) {
      if (!$class->isInternal()) { throw new RuntimeException("A runtime shadow was loaded."); }
      $classes[] = $class->getName();
    }
    sort($classes);
    echo json_encode($classes, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
  ' > "$evidence/module-reflection.json"
  python3 - <<'PY'
import json, os, pathlib, re
p = pathlib.Path(os.environ['KUMWE_NATIVE_EVIDENCE'])
expected = json.loads((p/'expected-tuple.json').read_text())
observed = json.loads((p/'actual-tuple.json').read_text())
tuple_ = observed['tuple']
capabilities = json.loads(pathlib.Path('engine-build/generated/capabilities.json').read_text())
assert capabilities['computation'] == expected['capabilities'], 'Computation source tuple differs.'
binding_source = pathlib.Path('src/kumwe_engine.c').read_text()
plans = re.search(r'^#define BINDING_MAX_PLANS (\d+)$', binding_source, re.M)
size = re.search(r'^#define BINDING_MAX_BYTES \(\(size_t\)(\d+)\)$', binding_source, re.M)
assert plans and size, 'Binding limits cannot be derived unambiguously from released source.'
complete = {**capabilities, **{k: v for k, v in expected.items() if k != 'capabilities'},
            'extension_package': 'kumwe/kumwe-engine', 'extension_module': 'kumwe_engine',
            'binding_max_plans': int(plans[1]), 'binding_max_bytes': int(size[1])}
(p/'expected-runtime-tuple.json').write_text(json.dumps(complete, indent=2) + '\n')
assert tuple_ == complete, 'Complete Runtime capability payload differs, including unexpected fields.'
api = json.loads(pathlib.Path('resources/api/v1.json').read_text())
assert sorted(api['classes']) == json.loads((p/'module-reflection.json').read_text()), 'Registered class inventory differs.'
compatibility = json.loads(pathlib.Path('resources/compatibility/v1.json').read_text())
assert observed['php'].startswith(compatibility['php'] + '.'), 'Built PHP minor is outside the released tuple.'
thread_model = 'ZTS' if observed['zts'] else 'NTS'
assert thread_model in compatibility['thread_models'], 'Released PHP thread mode is unsupported.'
assert thread_model == expected['binding_build']['thread_model'], 'Built PHP thread mode differs from its identity.'
assert observed['php'] == expected['binding_build']['php_version'], 'Built PHP patch differs from its identity.'
assert bool(observed['debug']) == expected['binding_build']['debug'], 'Built PHP debug mode differs from its identity.'
assert observed['os'] == compatibility['os'], 'Released operating system differs.'
assert observed['architecture'] == compatibility['architecture'], 'Released architecture differs.'
assert tuple_['extension_version'] == compatibility['version'], 'Actual module and release metadata versions differ.'
result = {'status': 'passed', 'network_disabled': True, 'kind': 'php_extension',
          'extension_version': tuple_['extension_version'], 'actual_tuple': tuple_,
          'php': observed['php'], 'zts': observed['zts'], 'debug': observed['debug'],
          'os': observed['os'], 'architecture': observed['architecture'],
          'pie': '1.4.10', 'phpt': 'passed', 'lifecycle': 'passed', 'full_independent_tuple': 'passed'}
(p/'verification.json').write_text(json.dumps(result, indent=2) + '\n')
PY
else
  echo 'Unsupported native kind.' >&2
  exit 1
fi
