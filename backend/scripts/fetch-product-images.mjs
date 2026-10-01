/**
 * One-time helper: downloads a small, commercially-licensed photo for every
 * seeded product from the Openverse API (https://api.openverse.org) and saves
 * it to frontend/public/img/products/<slug>.<ext>.
 *
 * Idempotent — existing files are skipped, so it can be re-run safely.
 *
 * Usage: node scripts/fetch-product-images.mjs
 */

import { mkdir, writeFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT_DIR = path.resolve(__dirname, '../../frontend/public/img/products');

const API = 'https://api.openverse.org/v1/images/';
const PACE_MS = 3400; // stay under the anonymous burst limit (20/min)
const UA = 'MLC-Shop-Demo/1.0 (local development; image seeding)';

/** slug => search query (brand-neutral for better hits) */
const QUERIES = {
  'smart-watch-wh22-6-fitness-tracker': 'smartwatch',
  'tennis-rackets-for-beginners': 'tennis racket',
  'premium-boxing-gloves-for-pro-training': 'boxing gloves',
  'club-kit-1-recurve-archery-bow': 'archery bow',
  'lightweight-white-nike-training-shoes': 'running shoes',
  'pro-grip-badminton-shuttlecocks-12-pack': 'badminton shuttlecock',
  'adidas-training-duffel-bag': 'sports bag',
  'asics-gel-quantum-running-shoes': 'trainers shoes',
  'adjustable-dumbbell-set-20-kg': 'dumbbells',
  'nike-white-thermo-fit-pullover-training-hoodie': 'hoodie',
  'columbia-rapid-shield-windbreaker': 'jacket',
  'adidas-originals-graphic-tee': 't-shirt',
  'new-balance-574-classic-sneakers': 'sneakers',
  'asics-sportstyle-running-cap': 'cap hat',
  'columbia-steens-fleece-pullover': 'wool sweater',
  'xiaomi-mi-smart-body-scale': 'bathroom scale',
  'aroma-mist-diffuser-lamp': 'aroma diffuser',
  'resistance-bands-set-5-pieces': 'resistance band',
  'insulated-steel-water-bottle-750ml': 'water bottle',
  'minimalist-led-desk-lamp': 'desk lamp',
  'ceramic-nonstick-cookware-set-5-pc': 'pots and pans',
  'cozy-knit-throw-blanket': 'knitted blanket',
  'botanical-scented-candle-trio': 'scented candle',
  'wireless-studio-headphones-pro': 'wireless headphones',
  'acoustic-guitar-starter-kit': 'acoustic guitar',
  'portable-bluetooth-speaker-splashproof': 'bluetooth speaker',
  'rgb-mechanical-gaming-keyboard': 'computer keyboard',
  'pro-wireless-controller-gamepad': 'game controller',
  'ultralight-gaming-mouse-8k': 'computer mouse',
  'watercolor-paint-set-36-colors': 'watercolor paint',
  'adjustable-wooden-canvas-easel': 'easel painting',
  'secure-element-hardware-wallet': 'usb flash drive',
  'crypto-trader-enamel-mug': 'mug cup',
};

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function fetchWithRetry(url, tries = 3) {
  for (let attempt = 1; attempt <= tries; attempt++) {
    try {
      const res = await fetch(url, { headers: { 'User-Agent': UA } });
      if (res.status === 429) {
        console.log('   rate limited, waiting 30s…');
        await sleep(30_000);
        continue;
      }
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return res;
    } catch (error) {
      if (attempt === tries) throw error;
      await sleep(2_000 * attempt);
    }
  }
  throw new Error('unreachable');
}

async function download(url, filePath) {
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      const res = await fetch(url, { headers: { 'User-Agent': UA } });
      if (res.status === 429) {
        console.log('   rate limited, waiting 30s…');
        await sleep(30_000);
        continue;
      }
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const buffer = Buffer.from(await res.arrayBuffer());
      if (buffer.length < 1024) throw new Error('suspiciously small file');
      await writeFile(filePath, buffer);
      return path.basename(filePath);
    } catch (error) {
      if (attempt === 3) throw error;
      await sleep(1_500);
    }
  }
  throw new Error('unreachable');
}

const extFor = (contentType) =>
  contentType?.includes('png') ? '.png' : contentType?.includes('webp') ? '.webp' : '.jpg';

await mkdir(OUT_DIR, { recursive: true });

const failures = [];
let done = 0;

for (const [slug, query] of Object.entries(QUERIES)) {
  done++;
  console.log(`[${done}/${Object.keys(QUERIES).length}] ${slug} — "${query}"`);

  // Existing files (any extension) are kept.
  const existing = ['jpg', 'jpeg', 'png', 'webp'].some((ext) =>
    existsSync(path.join(OUT_DIR, `${slug}.${ext}`)),
  );
  if (existing) {
    console.log('   already downloaded, skipping');
    continue;
  }

  let saved = null;
  try {
    const searchUrl = `${API}?q=${encodeURIComponent(query)}&license_type=commercial&size=small&page_size=8`;
    const res = await fetchWithRetry(searchUrl);
    await sleep(PACE_MS);

    const { results } = await res.json();
    for (const candidate of results.slice(0, 5)) {
      if (!candidate.thumbnail) continue;
      try {
        const thumbRes = await fetchWithRetry(candidate.thumbnail);
        const ext = extFor(thumbRes.headers.get('content-type'));
        const buffer = Buffer.from(await thumbRes.arrayBuffer());
        if (buffer.length < 1024) throw new Error('suspiciously small file');
        await writeFile(path.join(OUT_DIR, `${slug}${ext}`), buffer);
        saved = `${slug}${ext}`;
        console.log(`   saved ${saved}  (${candidate.license.toUpperCase()}, ${candidate.title ?? 'untitled'})`);
        await sleep(PACE_MS);
        break;
      } catch (error) {
        console.log(`   candidate failed: ${error.message}`);
        await sleep(PACE_MS);
      }
    }
  } catch (error) {
    console.log(`   search failed: ${error.message}`);
  }

  if (!saved) {
    failures.push(slug);
    console.log('   !! no usable image');
  }
}

console.log('');
if (failures.length) {
  console.log(`Failed (${failures.length}): ${failures.join(', ')}`);
  console.log('Re-run the script to retry just the failures.');
} else {
  console.log('All product images downloaded.');
}
