import fs from 'node:fs';
import path from 'node:path';
const [source, output] = process.argv.slice(2);
fs.mkdirSync(output, { recursive: true });
if (fs.existsSync(source)) {
  for (const p of fs.readdirSync(source)) {
    if (fs.statSync(path.join(source, p)).isFile() && /\.(json|zip|log)$/.test(p)) {
      fs.copyFileSync(path.join(source, p), path.join(output, p));
    }
  }
} else {
  fs.writeFileSync(path.join(output, 'FAILED-VERIFICATION.json'), JSON.stringify({status:'failed', reason:'Verification did not start.'}) + '\n');
}
