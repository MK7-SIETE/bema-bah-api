// apply-api-fixes-2.mjs  (API patch 2, part B)
//
//   node apply-api-fixes-2.mjs --dry    preview only
//   node apply-api-fixes-2.mjs          apply
//
// Run from the API folder. Adds ONE method to app/Services/CarImageService.php:
//   deleteRemoteAfterResponse($urls)
// which removes photos from Cloudinary AFTER the response has been sent, so deleting
// a car (or a whole dealer) is instant instead of waiting for one web call per photo.
// All-or-nothing: if the anchor line is not found exactly once, nothing is changed.

import fs from 'node:fs';

const DRY = process.argv.includes('--dry');
const file = 'app/Services/CarImageService.php';

const METHOD = [
  '    /**',
  '     * Remove photos from Cloudinary AFTER the response has been sent to the browser.',
  '     * Pass the stored photo addresses (read them BEFORE deleting the car rows).',
  '     */',
  '    public function deleteRemoteAfterResponse(iterable $urls): void',
  '    {',
  '        $urls = collect($urls)->filter()->values();',
  '        if ($urls->isEmpty()) {',
  '            return;',
  '        }',
  '',
  '        app()->terminating(function () use ($urls) {',
  '            foreach ($urls as $url) {',
  '                $this->deleteRemote((string) $url);',
  '            }',
  '        });',
  '    }',
  '',
  '',
].join('\n');

if (!fs.existsSync(file)) { console.log(`MISSING FILE  ${file}  (run this from the API folder)`); process.exit(1); }

const original = fs.readFileSync(file, 'utf8');
if (original.includes('deleteRemoteAfterResponse')) { console.log(`already done  ${file}`); process.exit(0); }

const eol = original.includes('\r\n') ? '\r\n' : '\n';
const anchor = '    public function deleteRemote(string $url): void';
const hits = original.split(anchor).length - 1;
if (hits !== 1) { console.log(`SKIPPED  ${file}  found ${hits} anchor lines, expected 1. Nothing was changed.`); process.exit(1); }

const updated = original.replace(anchor, () => METHOD.replace(/\n/g, eol) + anchor);
console.log(`${DRY ? 'would change' : 'changed     '}  ${file}  [added deleteRemoteAfterResponse]`);
if (!DRY) fs.writeFileSync(file, updated, 'utf8'); else console.log('Dry run only: nothing was written.');
