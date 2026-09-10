import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import YAML from 'yaml';
import { coordinate, safePath, handoffRecord, canonicalDocument, canonicalManifests, finalize, serializeAttestation } from './verify-release.mjs';
const syntheticReceipt = { schema: 'kumwe-release-attestation/v2', artifact_kind: 'framework_php',
  migration_id: 'KUMWE-MIG-2026-999', change_set: 'KUMWE-CS-2026-999', repository: 'https://github.com/kumwe/synthetic-only',
  merge_commit: 'a'.repeat(40), version: '0.0.1', tag: 'v0.0.1',
  source_archive: { url: 'https://example.invalid/synthetic.zip', sha256: 'b'.repeat(64) },
  artifacts: [{ identity: 'SYNTHETIC UNIT TEST ONLY', url: 'https://example.invalid/synthetic.zip', sha256: 'b'.repeat(64) }],
  manifests_and_corpora: [{ path: 'MIGRATION-HANDOFF.md', sha256: 'c'.repeat(64) }], abi_and_capabilities: null,
  sbom: { url: 'https://example.invalid/synthetic.spdx.json', sha256: 'd'.repeat(64) },
  provenance: 'SYNTHETIC UNIT TEST ONLY: ' + 'long quoted provenance: "value"; '.repeat(20),
  release_workflow: 'SYNTHETIC UNIT TEST ONLY: ' + 'long workflow reference; '.repeat(20),
  registry_or_pie_verification: ['SYNTHETIC UNIT TEST ONLY: ' + 'long registry reference; '.repeat(20)],
  clean_consumer_or_build_verification: ['SYNTHETIC UNIT TEST ONLY: ' + 'long consumer reference; '.repeat(20)],
  verified_at: 'synthetic-unit-test', verified_by: 'SYNTHETIC UNIT TEST ONLY', status: 'verified' };
const serializedReceipt = serializeAttestation(syntheticReceipt);
assert.deepEqual(YAML.parse(serializedReceipt), syntheticReceipt);
assert.equal(serializedReceipt.includes('\\\n'), false, 'Quoted scalars must not wrap across lines.');
assert.ok(serializedReceipt.split('\n').find(line => line.startsWith('provenance: ')).length > 500);
assert.ok(serializedReceipt.split('\n').find(line => line.startsWith('release_workflow: ')).length > 400);
assert.throws(() => serializeAttestation({ ...syntheticReceipt, status: 'invented' }));
const capabilities=JSON.parse(fs.readFileSync(new URL('./fixtures/capabilities.json',import.meta.url),'utf8'));
canonicalDocument('capability_manifest',capabilities);
assert.throws(()=>canonicalDocument('capability_manifest',{...capabilities,native_requirements:[]}));
assert.throws(()=>canonicalDocument('capability_manifest',{...capabilities,namespace:'Kumwe'}));
const source = {name:'kumwe/computation',version:'0.1.1',source_commit:'f'.repeat(40)};
assert.equal(coordinate(source).tag,'v0.1.1');
for (const name of ['other/computation','kumwe/../bad','kumwe/x;bad']) assert.throws(()=>coordinate({...source,name}));
for (const version of ['dev-main','0.1.x','^0.1','0.1.1-rc1']) assert.throws(()=>coordinate({...source,version}));
for (const source_commit of ['main','f'.repeat(39),'F'.repeat(40)]) assert.throws(()=>coordinate({...source,source_commit}));
assert.equal(safePath('resources/public-api.json'),'resources/public-api.json');
for (const p of ['/etc/passwd','../LICENSE','resources/../LICENSE','a\\b','a//b','./a','x\0y']) assert.throws(()=>safePath(p));
assert.throws(()=>handoffRecord('no front matter',source));
assert.throws(()=>handoffRecord('---\nschema: bad\n---\n',source));
assert.throws(()=>handoffRecord('---\nschema: one\nschema: two\n---\n',source));
const schemaFixture=fs.mkdtempSync(path.join(os.tmpdir(),'release-canonical-schema-'));
try {
  for(const f of ['api.json','capabilities.json','services.json']) fs.writeFileSync(path.join(schemaFixture,f),'{}');
  assert.throws(()=>canonicalManifests(schemaFixture,{framework_php:{public_api_manifest:'api.json',capability_manifest:'capabilities.json',service_map:'services.json'}}));
} finally {fs.rmSync(schemaFixture,{recursive:true,force:true});}
const dir=fs.mkdtempSync(path.join(os.tmpdir(),'release-verifier-refusal-'));
try {
  fs.writeFileSync(path.join(dir,'verification.json'),JSON.stringify({status:'failed',verifier:{run_url:'https://github.com/kumwe/extension-sdk/actions/runs/1'}}));
  assert.throws(()=>finalize(dir,'https://github.com/kumwe/extension-sdk/actions/runs/1/artifacts/1'));
  assert.throws(()=>finalize(dir,'https://example.com/evidence'));
  fs.writeFileSync(path.join(dir,'verification.json'),JSON.stringify({status:'passed',verifier:{run_url:null}}));
  assert.throws(()=>finalize(dir,'https://github.com/kumwe/extension-sdk/actions/runs/1/artifacts/1'));
  assert.equal(fs.existsSync(path.join(dir,'RELEASE-ATTESTATION.yaml')),false);
} finally {fs.rmSync(dir,{recursive:true,force:true});}
console.log('Independent verifier hostile coordinates, handoff and false-attestation regressions passed.');
