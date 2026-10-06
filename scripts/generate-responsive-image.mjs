import fs from 'node:fs/promises';
import path from 'node:path';
import sharp from 'sharp';

const [source, outputDir, stem, widthsCsv] = process.argv.slice(2);
if (!source || !outputDir || !stem || !widthsCsv) {
  throw new Error('Usage: node generate-responsive-image.mjs <source> <output-dir> <stem> <widths>');
}

const widths = [...new Set(widthsCsv.split(',').map(Number).filter((value) => Number.isInteger(value) && value >= 160 && value <= 2400))];
if (widths.length === 0) throw new Error('At least one safe responsive width is required.');

await fs.mkdir(outputDir, { recursive: true });

for (const width of widths) {
  const image = sharp(source, { failOn: 'warning', limitInputPixels: 80_000_000 })
    .rotate()
    .resize({ width, withoutEnlargement: true, fit: 'inside' });

  await image.clone().webp({ quality: 82, effort: 4 }).toFile(path.join(outputDir, `${stem}-${width}.webp`));
  await image.clone().avif({ quality: 58, effort: 4 }).toFile(path.join(outputDir, `${stem}-${width}.avif`));
}
