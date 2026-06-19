import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { join, extname } from 'node:path';

const root = process.cwd();
const docsDir = join(root, 'docs');
const configPath = join(root, 'docmd.config.json');
const forbiddenHtml = /<\/?[a-z][\w:-]*(?:\s[^>]*)?>/i;
const forbiddenButton = /^:::\s*button\b/im;
const errors = [];

function walk(dir) {
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const path = join(dir, entry.name);
    if (entry.isDirectory()) {
      walk(path);
      continue;
    }
    if (extname(entry.name) !== '.md') {
      continue;
    }
    const text = readFileSync(path, 'utf8');
    if (forbiddenHtml.test(text)) {
      errors.push(`${path}: raw HTML is not allowed`);
    }
    if (forbiddenButton.test(text)) {
      errors.push(`${path}: ::: button is not allowed`);
    }
  }
}

if (!existsSync(configPath)) {
  errors.push('docmd.config.json is missing');
}

if (!existsSync(docsDir)) {
  errors.push('docs directory is missing');
} else {
  walk(docsDir);
}

if (errors.length > 0) {
  console.error(errors.join('\n'));
  process.exit(1);
}

console.log('docmd docs guard passed');
