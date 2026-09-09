import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import fs from 'node:fs';
import { nativeFixtureScope, packageSetNativeBinding } from './native-fixture-scope.mjs';

const [fixture, input, report] = process.argv.slice(2);
if (process.argv.length !== 5) throw new Error('Usage: run-native-package-set.mjs QUALIFIED_FIXTURE_JSON PACKAGE_SET_JSON REPORT_JSON');
const here = path.dirname(fileURLToPath(import.meta.url));
const scoped = nativeFixtureScope(path.resolve(fixture), path.join(path.dirname(path.resolve(report)), 'native-php-scope'));
packageSetNativeBinding(JSON.parse(fs.readFileSync(input, 'utf8')), scoped.result);
const result = spawnSync('php', [path.resolve(here, '../verify-package-set-consumer.php'), path.resolve(input), path.resolve(report)],
  { stdio: 'inherit', env: { ...process.env, ...scoped.environment } });
if (result.error || result.status !== 0) throw new Error('Native package-set consumer failed.');
