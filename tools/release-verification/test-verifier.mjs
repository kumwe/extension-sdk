import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { coordinate, safePath, handoffRecord, finalize } from './verify-release.mjs';
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
