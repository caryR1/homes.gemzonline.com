import { readFileSync, writeFileSync } from 'fs';

const path = process.argv[2];
const buf = readFileSync(path);

const BACKSLASH = 0x5c;
const FORWARDSLASH = 0x2f;

let pos = 0;
let fixedCount = 0;

function fixNameBytes(start, len) {
  let changed = false;
  for (let i = 0; i < len; i++) {
    if (buf[start + i] === BACKSLASH) {
      buf[start + i] = FORWARDSLASH;
      changed = true;
    }
  }
  if (changed) fixedCount++;
}

// Walk local file headers (PK\x03\x04)
while (pos + 4 <= buf.length && buf.readUInt32LE(pos) === 0x04034b50) {
  const compressedSize = buf.readUInt32LE(pos + 18);
  const nameLen = buf.readUInt16LE(pos + 26);
  const extraLen = buf.readUInt16LE(pos + 28);
  const nameStart = pos + 30;

  fixNameBytes(nameStart, nameLen);

  pos = nameStart + nameLen + extraLen + compressedSize;
}

// Walk central directory headers (PK\x01\x02)
while (pos + 4 <= buf.length && buf.readUInt32LE(pos) === 0x02014b50) {
  const nameLen = buf.readUInt16LE(pos + 28);
  const extraLen = buf.readUInt16LE(pos + 30);
  const commentLen = buf.readUInt16LE(pos + 32);
  const nameStart = pos + 46;

  fixNameBytes(nameStart, nameLen);

  pos = nameStart + nameLen + extraLen + commentLen;
}

if (pos + 4 <= buf.length && buf.readUInt32LE(pos) === 0x06054b50) {
  console.log('Reached End Of Central Directory correctly, parse was clean.');
} else {
  console.error('WARNING: did not land cleanly on EOCD signature at pos', pos, '- aborting, not writing.');
  process.exit(1);
}

writeFileSync(path, buf);
console.log(`Patched ${fixedCount} entry name(s) with backslashes -> forward slashes.`);
