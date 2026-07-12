// One-off generator for the email logo PNG - no image-generation tool is
// available in this environment, and the logo is simple flat geometry (a
// rounded square + rounded sub-squares), so a small hand-rolled PNG encoder
// is more reliable than guessing at a third-party asset. Keeps this design
// in sync with Front-end's <Logo> component and public/favicon.svg.
const zlib = require('zlib')
const fs = require('fs')
const path = require('path')

const SIZE = 256
const NAVY = [0x1a, 0x1a, 0x2e]
const WHITE = [0xff, 0xff, 0xff]

// Design is authored in a 32x32 unit grid (matching the SVG), scaled 8x.
const SCALE = SIZE / 32
function inRoundedRect(px, py, x, y, w, h, r) {
  x *= SCALE; y *= SCALE; w *= SCALE; h *= SCALE; r *= SCALE
  if (px < x || py < y || px >= x + w || py >= y + h) return false
  const cx = Math.min(Math.max(px, x + r), x + w - r)
  const cy = Math.min(Math.max(py, y + r), y + h - r)
  return (px - cx) ** 2 + (py - cy) ** 2 <= r * r
}

const SHAPES = [
  { x: 0, y: 0, w: 32, h: 32, r: 8, color: NAVY },
  { x: 7, y: 7, w: 8, h: 8, r: 1.5, color: WHITE },
  { x: 17, y: 7, w: 8, h: 8, r: 1.5, color: WHITE },
  { x: 7, y: 17, w: 8, h: 8, r: 1.5, color: WHITE },
  { x: 19, y: 19, w: 4, h: 4, r: 1, color: WHITE },
]

const raw = Buffer.alloc(SIZE * (1 + SIZE * 3)) // filter byte + RGB per row
for (let py = 0; py < SIZE; py++) {
  const rowStart = py * (1 + SIZE * 3)
  raw[rowStart] = 0 // filter: none
  for (let px = 0; px < SIZE; px++) {
    let color = [255, 255, 255] // page background outside the mark
    for (const shape of SHAPES) {
      if (inRoundedRect(px + 0.5, py + 0.5, shape.x, shape.y, shape.w, shape.h, shape.r)) color = shape.color
    }
    const offset = rowStart + 1 + px * 3
    raw[offset] = color[0]; raw[offset + 1] = color[1]; raw[offset + 2] = color[2]
  }
}

function chunk(type, data) {
  const len = Buffer.alloc(4); len.writeUInt32BE(data.length)
  const typeData = Buffer.concat([Buffer.from(type, 'ascii'), data])
  const crc = Buffer.alloc(4); crc.writeUInt32BE(zlib.crc32 ? zlib.crc32(typeData) : crc32(typeData))
  return Buffer.concat([len, typeData, crc])
}

// Node's zlib doesn't expose crc32 in all versions - plain JS fallback.
function crc32(buf) {
  let c, crc = 0xffffffff
  for (let i = 0; i < buf.length; i++) {
    c = (crc ^ buf[i]) & 0xff
    for (let j = 0; j < 8; j++) c = (c & 1) ? (0xedb88320 ^ (c >>> 1)) : (c >>> 1)
    crc = (crc >>> 8) ^ c
  }
  return (crc ^ 0xffffffff) >>> 0
}

const ihdr = Buffer.alloc(13)
ihdr.writeUInt32BE(SIZE, 0)
ihdr.writeUInt32BE(SIZE, 4)
ihdr[8] = 8 // bit depth
ihdr[9] = 2 // color type: truecolor RGB
ihdr[10] = 0; ihdr[11] = 0; ihdr[12] = 0

const idat = zlib.deflateSync(raw)
const png = Buffer.concat([
  Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
  chunk('IHDR', ihdr),
  chunk('IDAT', idat),
  chunk('IEND', Buffer.alloc(0)),
])

const outDir = path.join(__dirname, '..', 'public')
fs.mkdirSync(outDir, { recursive: true })
fs.writeFileSync(path.join(outDir, 'logo-email.png'), png)
console.log('Wrote', path.join(outDir, 'logo-email.png'), png.length, 'bytes')
