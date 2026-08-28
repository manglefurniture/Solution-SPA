#!/usr/bin/env node

import fs from 'node:fs';

const privateFiles = [
  'admin/index.html',
  'admin/login.html',
  'admin/register.html',
  'client/index.html',
];

const failures = [];
for (const file of privateFiles) {
  const html = fs.readFileSync(file, 'utf8');
  if (!/<meta\b[^>]*name\s*=\s*(["'])robots\1[^>]*content\s*=\s*(["'])[^"']*noindex/i.test(html)) {
    failures.push(`${file}: missing explicit noindex`);
  }
}

for (const file of ['index.html', 'admin/register.html']) {
  const html = fs.readFileSync(file, 'utf8');
  if (!/privacy\.html/i.test(html)) failures.push(`${file}: privacy notice is not linked from the data collection surface`);
}

if (failures.length) {
  for (const failure of failures) console.error(`SEO_HYBRID_FAIL ${failure}`);
  process.exit(1);
}

console.log('SEO_HYBRID_PRIVATE_OK');
