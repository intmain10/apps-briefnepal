/* =========================================================================
   OmniTools — Self-contained algorithm library (loaded only on tool pages)
   Includes: QR Code generator, CODE128 barcode, MD5, a small Markdown
   renderer and a minimal PDF writer. All dependency-free.

   QR generator is a compact port of Project Nayuki's public-domain
   "QR Code generator" (https://www.nayuki.io/page/qr-code-generator-library).
   ========================================================================= */
(function () {
  'use strict';

  /* =======================================================================
     QR CODE GENERATOR (byte mode, ECC L/M/Q/H, auto version)
     ==================================================================== */
  const QR = (function () {
    function QrCode(version, ecl, dataCodewords, mask) {
      this.version = version;
      this.errorCorrectionLevel = ecl;
      this.size = version * 4 + 17;
      this.modules = [];
      this.isFunction = [];
      const size = this.size;
      for (let i = 0; i < size; i++) {
        this.modules.push(new Array(size).fill(false));
        this.isFunction.push(new Array(size).fill(false));
      }
      this.drawFunctionPatterns();
      const allCodewords = this.addEccAndInterleave(dataCodewords);
      this.drawCodewords(allCodewords);
      if (mask === -1) {
        let minPenalty = Infinity;
        for (let i = 0; i < 8; i++) {
          this.applyMask(i); this.drawFormatBits(i);
          const penalty = this.getPenaltyScore();
          if (penalty < minPenalty) { mask = i; minPenalty = penalty; }
          this.applyMask(i);
        }
      }
      this.mask = mask;
      this.applyMask(mask);
      this.drawFormatBits(mask);
      this.isFunction = [];
    }

    QrCode.prototype.getModule = function (x, y) {
      return x >= 0 && x < this.size && y >= 0 && y < this.size && this.modules[y][x];
    };
    QrCode.prototype.setFunctionModule = function (x, y, isDark) {
      this.modules[y][x] = isDark; this.isFunction[y][x] = true;
    };
    QrCode.prototype.drawFunctionPatterns = function () {
      const size = this.size;
      for (let i = 0; i < size; i++) {
        this.setFunctionModule(6, i, i % 2 === 0);
        this.setFunctionModule(i, 6, i % 2 === 0);
      }
      this.drawFinderPattern(3, 3);
      this.drawFinderPattern(size - 4, 3);
      this.drawFinderPattern(3, size - 4);
      const alignPatPos = this.getAlignmentPatternPositions();
      const numAlign = alignPatPos.length;
      for (let i = 0; i < numAlign; i++)
        for (let j = 0; j < numAlign; j++)
          if (!((i === 0 && j === 0) || (i === 0 && j === numAlign - 1) || (i === numAlign - 1 && j === 0)))
            this.drawAlignmentPattern(alignPatPos[i], alignPatPos[j]);
      this.drawFormatBits(0);
      this.drawVersion();
    };
    QrCode.prototype.drawFormatBits = function (mask) {
      const ecl = this.errorCorrectionLevel;
      const data = (ecl.formatBits << 3) | mask;
      let rem = data;
      for (let i = 0; i < 10; i++) rem = (rem << 1) ^ ((rem >>> 9) * 0x537);
      const bits = ((data << 10) | rem) ^ 0x5412;
      for (let i = 0; i <= 5; i++) this.setFunctionModule(8, i, getBit(bits, i));
      this.setFunctionModule(8, 7, getBit(bits, 6));
      this.setFunctionModule(8, 8, getBit(bits, 7));
      this.setFunctionModule(7, 8, getBit(bits, 8));
      for (let i = 9; i < 15; i++) this.setFunctionModule(14 - i, 8, getBit(bits, i));
      const size = this.size;
      for (let i = 0; i < 8; i++) this.setFunctionModule(size - 1 - i, 8, getBit(bits, i));
      for (let i = 8; i < 15; i++) this.setFunctionModule(8, size - 15 + i, getBit(bits, i));
      this.setFunctionModule(8, size - 8, true);
    };
    QrCode.prototype.drawVersion = function () {
      if (this.version < 7) return;
      let rem = this.version;
      for (let i = 0; i < 12; i++) rem = (rem << 1) ^ ((rem >>> 11) * 0x1F25);
      const bits = (this.version << 12) | rem;
      for (let i = 0; i < 18; i++) {
        const bit = getBit(bits, i);
        const a = this.size - 11 + (i % 3), b = Math.floor(i / 3);
        this.setFunctionModule(a, b, bit);
        this.setFunctionModule(b, a, bit);
      }
    };
    QrCode.prototype.drawFinderPattern = function (x, y) {
      for (let dy = -4; dy <= 4; dy++)
        for (let dx = -4; dx <= 4; dx++) {
          const dist = Math.max(Math.abs(dx), Math.abs(dy));
          const xx = x + dx, yy = y + dy;
          if (xx >= 0 && xx < this.size && yy >= 0 && yy < this.size)
            this.setFunctionModule(xx, yy, dist !== 2 && dist !== 4);
        }
    };
    QrCode.prototype.drawAlignmentPattern = function (x, y) {
      for (let dy = -2; dy <= 2; dy++)
        for (let dx = -2; dx <= 2; dx++)
          this.setFunctionModule(x + dx, y + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
    };
    QrCode.prototype.getAlignmentPatternPositions = function () {
      if (this.version === 1) return [];
      const numAlign = Math.floor(this.version / 7) + 2;
      const step = (this.version === 32) ? 26 :
        Math.ceil((this.version * 4 + 4) / (numAlign * 2 - 2)) * 2;
      const result = [6];
      for (let pos = this.size - 7; result.length < numAlign; pos -= step) result.splice(1, 0, pos);
      return result;
    };
    QrCode.prototype.addEccAndInterleave = function (data) {
      const ver = this.version, ecl = this.errorCorrectionLevel;
      const numBlocks = QrCode.NUM_ERROR_CORRECTION_BLOCKS[ecl.ordinal][ver];
      const blockEccLen = QrCode.ECC_CODEWORDS_PER_BLOCK[ecl.ordinal][ver];
      const rawCodewords = Math.floor(getNumRawDataModules(ver) / 8);
      const numShortBlocks = numBlocks - rawCodewords % numBlocks;
      const shortBlockLen = Math.floor(rawCodewords / numBlocks);
      const blocks = [];
      const rsDiv = reedSolomonComputeDivisor(blockEccLen);
      for (let i = 0, k = 0; i < numBlocks; i++) {
        const dat = data.slice(k, k + shortBlockLen - blockEccLen + (i < numShortBlocks ? 0 : 1));
        k += dat.length;
        const ecc = reedSolomonComputeRemainder(dat, rsDiv);
        if (i < numShortBlocks) dat.push(0);
        blocks.push(dat.concat(ecc));
      }
      const result = [];
      for (let i = 0; i < blocks[0].length; i++)
        blocks.forEach((block, j) => {
          if (i !== shortBlockLen - blockEccLen || j >= numShortBlocks) result.push(block[i]);
        });
      return result;
    };
    QrCode.prototype.drawCodewords = function (data) {
      let i = 0;
      const size = this.size;
      for (let right = size - 1; right >= 1; right -= 2) {
        if (right === 6) right = 5;
        for (let vert = 0; vert < size; vert++)
          for (let j = 0; j < 2; j++) {
            const x = right - j;
            const upward = ((right + 1) & 2) === 0;
            const y = upward ? size - 1 - vert : vert;
            if (!this.isFunction[y][x] && i < data.length * 8) {
              this.modules[y][x] = getBit(data[i >>> 3], 7 - (i & 7));
              i++;
            }
          }
      }
    };
    QrCode.prototype.applyMask = function (mask) {
      for (let y = 0; y < this.size; y++)
        for (let x = 0; x < this.size; x++) {
          let invert;
          switch (mask) {
            case 0: invert = (x + y) % 2 === 0; break;
            case 1: invert = y % 2 === 0; break;
            case 2: invert = x % 3 === 0; break;
            case 3: invert = (x + y) % 3 === 0; break;
            case 4: invert = (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0; break;
            case 5: invert = x * y % 2 + x * y % 3 === 0; break;
            case 6: invert = (x * y % 2 + x * y % 3) % 2 === 0; break;
            case 7: invert = ((x + y) % 2 + x * y % 3) % 2 === 0; break;
          }
          if (!this.isFunction[y][x] && invert) this.modules[y][x] = !this.modules[y][x];
        }
    };
    QrCode.prototype.getPenaltyScore = function () {
      let result = 0;
      const size = this.size, modules = this.modules;
      for (let y = 0; y < size; y++) {
        let runColor = false, runX = 0; const runHistory = [0, 0, 0, 0, 0, 0, 0];
        for (let x = 0; x < size; x++) {
          if (modules[y][x] === runColor) { runX++; if (runX === 5) result += 3; else if (runX > 5) result++; }
          else { finder(runHistory, runX, runColor); runColor = modules[y][x]; runX = 1; }
        }
        result += terminate(runHistory, runX, runColor, size) * 40;
      }
      for (let x = 0; x < size; x++) {
        let runColor = false, runY = 0; const runHistory = [0, 0, 0, 0, 0, 0, 0];
        for (let y = 0; y < size; y++) {
          if (modules[y][x] === runColor) { runY++; if (runY === 5) result += 3; else if (runY > 5) result++; }
          else { finder(runHistory, runY, runColor); runColor = modules[y][x]; runY = 1; }
        }
        result += terminate(runHistory, runY, runColor, size) * 40;
      }
      for (let y = 0; y < size - 1; y++)
        for (let x = 0; x < size - 1; x++) {
          const c = modules[y][x];
          if (c === modules[y][x + 1] && c === modules[y + 1][x] && c === modules[y + 1][x + 1]) result += 3;
        }
      let dark = 0;
      for (const row of modules) dark += row.reduce((s, v) => s + (v ? 1 : 0), 0);
      const total = size * size;
      const k = Math.ceil(Math.abs(dark * 20 - total * 10) / total) - 1;
      result += k * 10;
      return result;
      function finder(hist, run, color) { hist.pop(); hist.unshift(run); if (!color) result += hasFinder(hist) * 40; }
      function terminate(hist, run, color, sz) {
        if (color) { finder(hist, run, color); run = 0; }
        run += sz; finder(hist, run, false);
        return 0;
      }
      function hasFinder(rh) {
        const n = rh[1];
        return n > 0 && rh[2] === n && rh[4] === n && rh[5] === n && rh[3] === n * 3 &&
          Math.max(rh[0], rh[6]) >= n * 4 ? 1 : 0;
      }
    };

    QrCode.encodeText = function (text, ecl) {
      const seg = makeBytesSegment(toUtf8(text));
      return encodeSegments([seg], ecl);
    };

    function encodeSegments(segs, ecl) {
      let version, dataUsedBits;
      for (version = 1; ; version++) {
        const dataCapacityBits = getNumDataCodewords(version, ecl) * 8;
        const usedBits = getTotalBits(segs, version);
        if (usedBits <= dataCapacityBits) { dataUsedBits = usedBits; break; }
        if (version >= 40) throw new Error('Data too long');
      }
      for (const newEcl of [ECC.MEDIUM, ECC.QUARTILE, ECC.HIGH]) {
        if (dataUsedBits <= getNumDataCodewords(version, newEcl) * 8) ecl = newEcl;
      }
      let bb = [];
      for (const seg of segs) {
        appendBits(seg.mode.modeBits, 4, bb);
        appendBits(seg.numChars, seg.mode.numCharCountBits(version), bb);
        for (const b of seg.bitData) bb.push(b);
      }
      const dataCapacityBits = getNumDataCodewords(version, ecl) * 8;
      appendBits(0, Math.min(4, dataCapacityBits - bb.length), bb);
      appendBits(0, (8 - bb.length % 8) % 8, bb);
      for (let padByte = 0xEC; bb.length < dataCapacityBits; padByte ^= 0xEC ^ 0x11)
        appendBits(padByte, 8, bb);
      const dataCodewords = [];
      while (dataCodewords.length * 8 < bb.length) dataCodewords.push(0);
      bb.forEach((b, i) => dataCodewords[i >>> 3] |= b << (7 - (i & 7)));
      return new QrCode(version, ecl, dataCodewords, -1);
    }

    function makeBytesSegment(data) {
      const bb = [];
      for (const b of data) appendBits(b, 8, bb);
      return { mode: MODE_BYTE, numChars: data.length, bitData: bb };
    }
    function getTotalBits(segs, version) {
      let result = 0;
      for (const seg of segs) {
        const ccbits = seg.mode.numCharCountBits(version);
        if (seg.numChars >= (1 << ccbits)) return Infinity;
        result += 4 + ccbits + seg.bitData.length;
      }
      return result;
    }
    const MODE_BYTE = { modeBits: 0x4, numCharCountBits: v => (v < 10 ? 8 : 16) };

    function appendBits(val, len, bb) { for (let i = len - 1; i >= 0; i--) bb.push((val >>> i) & 1); }
    function getBit(x, i) { return ((x >>> i) & 1) !== 0; }
    function toUtf8(str) { return Array.from(new TextEncoder().encode(str)); }
    function getNumRawDataModules(ver) {
      let result = (16 * ver + 128) * ver + 64;
      if (ver >= 2) { const numAlign = Math.floor(ver / 7) + 2; result -= (25 * numAlign - 10) * numAlign - 55; if (ver >= 7) result -= 36; }
      return result;
    }
    function getNumDataCodewords(ver, ecl) {
      return Math.floor(getNumRawDataModules(ver) / 8) -
        QrCode.ECC_CODEWORDS_PER_BLOCK[ecl.ordinal][ver] *
        QrCode.NUM_ERROR_CORRECTION_BLOCKS[ecl.ordinal][ver];
    }
    function reedSolomonComputeDivisor(degree) {
      const result = new Array(degree).fill(0); result[degree - 1] = 1;
      let root = 1;
      for (let i = 0; i < degree; i++) {
        for (let j = 0; j < result.length; j++) {
          result[j] = reedSolomonMultiply(result[j], root);
          if (j + 1 < result.length) result[j] ^= result[j + 1];
        }
        root = reedSolomonMultiply(root, 0x02);
      }
      return result;
    }
    function reedSolomonComputeRemainder(data, divisor) {
      const result = divisor.map(() => 0);
      for (const b of data) {
        const factor = b ^ result.shift();
        result.push(0);
        divisor.forEach((coef, i) => { result[i] ^= reedSolomonMultiply(coef, factor); });
      }
      return result;
    }
    function reedSolomonMultiply(x, y) {
      let z = 0;
      for (let i = 7; i >= 0; i--) { z = (z << 1) ^ ((z >>> 7) * 0x11D); z ^= ((y >>> i) & 1) * x; }
      return z & 0xFF;
    }

    function Ecc(ordinal, formatBits) { this.ordinal = ordinal; this.formatBits = formatBits; }
    const ECC = { LOW: new Ecc(0, 1), MEDIUM: new Ecc(1, 0), QUARTILE: new Ecc(2, 3), HIGH: new Ecc(3, 2) };

    QrCode.ECC_CODEWORDS_PER_BLOCK = [
      [-1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
      [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
      [-1, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
      [-1, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30]
    ];
    QrCode.NUM_ERROR_CORRECTION_BLOCKS = [
      [-1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
      [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
      [-1, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68],
      [-1, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81]
    ];
    return { encode: (text, level) => QrCode.encodeText(text, ECC[level || 'MEDIUM'] || ECC.MEDIUM) };
  })();

  /* Render a QrCode to a canvas. */
  function qrToCanvas(qr, scale, margin, dark, light) {
    scale = scale || 8; margin = margin == null ? 4 : margin;
    const size = qr.size + margin * 2;
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = size * scale;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = light || '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = dark || '#000000';
    for (let y = 0; y < qr.size; y++)
      for (let x = 0; x < qr.size; x++)
        if (qr.getModule(x, y))
          ctx.fillRect((x + margin) * scale, (y + margin) * scale, scale, scale);
    return canvas;
  }

  /* =======================================================================
     CODE128 BARCODE
     ==================================================================== */
  const CODE128 = (function () {
    const PATTERNS = ['11011001100', '11001101100', '11001100110', '10010011000', '10010001100', '10001001100', '10011001000', '10011000100', '10001100100', '11001001000', '11001000100', '11000100100', '10110011100', '10011011100', '10011001110', '10111001100', '10011101100', '10011100110', '11001110010', '11001011100', '11001001110', '11011100100', '11001110100', '11101101110', '11101001100', '11100101100', '11100100110', '11101100100', '11100110100', '11100110010', '11011011000', '11011000110', '11000110110', '10100011000', '10001011000', '10001000110', '10110001000', '10001101000', '10001100010', '11010001000', '11000101000', '11000100010', '10110111000', '10110001110', '10001101110', '10111011000', '10111000110', '10001110110', '11101110110', '11010001110', '11000101110', '11011101000', '11011100010', '11011101110', '11101011000', '11101000110', '11100010110', '11101101000', '11101100010', '11100011010', '11101111010', '11001000010', '11110001010', '10100110000', '10100001100', '10010110000', '10010000110', '10000101100', '10000100110', '10110010000', '10110000100', '10011010000', '10011000010', '10000110100', '10000110010', '11000010010', '11001010000', '11110111010', '11000010100', '10001111010', '10100111100', '10010111100', '10010011110', '10111100100', '10011110100', '10011110010', '11110100100', '11110010100', '11110010010', '11011011110', '11011110110', '11110110110', '10101111000', '10100011110', '10001011110', '10111101000', '10111100010', '11110101000', '11110100010', '10111011110', '10111101110', '11101011110', '11110101110', '11010000100', '11010010000', '11010011100', '11000111010', '11'];
    // Encode using Code Set B (printable ASCII 32-126).
    function encode(text) {
      let sum = 104; // Start B
      const codes = [104];
      for (let i = 0; i < text.length; i++) {
        const val = text.charCodeAt(i) - 32;
        codes.push(val); sum += val * (i + 1);
      }
      codes.push(sum % 103); // checksum
      codes.push(106); // stop
      return codes.map(c => PATTERNS[c]).join('');
    }
    function toCanvas(text, barWidth, height) {
      barWidth = barWidth || 2; height = height || 90;
      const bits = encode(text);
      const canvas = document.createElement('canvas');
      const quiet = 10;
      canvas.width = bits.length * barWidth + quiet * 2 * barWidth;
      canvas.height = height + 24;
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.fillStyle = '#000';
      let x = quiet * barWidth;
      for (const b of bits) { if (b === '1') ctx.fillRect(x, 4, barWidth, height); x += barWidth; }
      ctx.fillStyle = '#000'; ctx.font = '13px monospace'; ctx.textAlign = 'center';
      ctx.fillText(text, canvas.width / 2, height + 18);
      return canvas;
    }
    return { toCanvas };
  })();

  /* =======================================================================
     MD5 (for the hash generator; SHA family uses crypto.subtle)
     ==================================================================== */
  function md5(str) {
    function rl(n, c) { return (n << c) | (n >>> (32 - c)); }
    function cmn(q, a, b, x, s, t) { a = (((a + q) | 0) + ((x + t) | 0)) | 0; return (((rl(a, s)) | 0) + b) | 0; }
    function ff(a, b, c, d, x, s, t) { return cmn((b & c) | (~b & d), a, b, x, s, t); }
    function gg(a, b, c, d, x, s, t) { return cmn((b & d) | (c & ~d), a, b, x, s, t); }
    function hh(a, b, c, d, x, s, t) { return cmn(b ^ c ^ d, a, b, x, s, t); }
    function ii(a, b, c, d, x, s, t) { return cmn(c ^ (b | ~d), a, b, x, s, t); }
    const bytes = new TextEncoder().encode(str);
    const len = bytes.length;
    const words = [];
    for (let i = 0; i < len; i++) words[i >> 2] |= bytes[i] << ((i % 4) * 8);
    words[len >> 2] |= 0x80 << ((len % 4) * 8);
    words[(((len + 8) >> 6) + 1) * 16 - 2] = len * 8;
    let a = 1732584193, b = -271733879, c = -1732584194, d = 271733878;
    for (let i = 0; i < words.length; i += 16) {
      const oa = a, ob = b, oc = c, od = d; const x = i;
      a = ff(a, b, c, d, words[x + 0] | 0, 7, -680876936); d = ff(d, a, b, c, words[x + 1] | 0, 12, -389564586);
      c = ff(c, d, a, b, words[x + 2] | 0, 17, 606105819); b = ff(b, c, d, a, words[x + 3] | 0, 22, -1044525330);
      a = ff(a, b, c, d, words[x + 4] | 0, 7, -176418897); d = ff(d, a, b, c, words[x + 5] | 0, 12, 1200080426);
      c = ff(c, d, a, b, words[x + 6] | 0, 17, -1473231341); b = ff(b, c, d, a, words[x + 7] | 0, 22, -45705983);
      a = ff(a, b, c, d, words[x + 8] | 0, 7, 1770035416); d = ff(d, a, b, c, words[x + 9] | 0, 12, -1958414417);
      c = ff(c, d, a, b, words[x + 10] | 0, 17, -42063); b = ff(b, c, d, a, words[x + 11] | 0, 22, -1990404162);
      a = ff(a, b, c, d, words[x + 12] | 0, 7, 1804603682); d = ff(d, a, b, c, words[x + 13] | 0, 12, -40341101);
      c = ff(c, d, a, b, words[x + 14] | 0, 17, -1502002290); b = ff(b, c, d, a, words[x + 15] | 0, 22, 1236535329);
      a = gg(a, b, c, d, words[x + 1] | 0, 5, -165796510); d = gg(d, a, b, c, words[x + 6] | 0, 9, -1069501632);
      c = gg(c, d, a, b, words[x + 11] | 0, 14, 643717713); b = gg(b, c, d, a, words[x + 0] | 0, 20, -373897302);
      a = gg(a, b, c, d, words[x + 5] | 0, 5, -701558691); d = gg(d, a, b, c, words[x + 10] | 0, 9, 38016083);
      c = gg(c, d, a, b, words[x + 15] | 0, 14, -660478335); b = gg(b, c, d, a, words[x + 4] | 0, 20, -405537848);
      a = gg(a, b, c, d, words[x + 9] | 0, 5, 568446438); d = gg(d, a, b, c, words[x + 14] | 0, 9, -1019803690);
      c = gg(c, d, a, b, words[x + 3] | 0, 14, -187363961); b = gg(b, c, d, a, words[x + 8] | 0, 20, 1163531501);
      a = gg(a, b, c, d, words[x + 13] | 0, 5, -1444681467); d = gg(d, a, b, c, words[x + 2] | 0, 9, -51403784);
      c = gg(c, d, a, b, words[x + 7] | 0, 14, 1735328473); b = gg(b, c, d, a, words[x + 12] | 0, 20, -1926607734);
      a = hh(a, b, c, d, words[x + 5] | 0, 4, -378558); d = hh(d, a, b, c, words[x + 8] | 0, 11, -2022574463);
      c = hh(c, d, a, b, words[x + 11] | 0, 16, 1839030562); b = hh(b, c, d, a, words[x + 14] | 0, 23, -35309556);
      a = hh(a, b, c, d, words[x + 1] | 0, 4, -1530992060); d = hh(d, a, b, c, words[x + 4] | 0, 11, 1272893353);
      c = hh(c, d, a, b, words[x + 7] | 0, 16, -155497632); b = hh(b, c, d, a, words[x + 10] | 0, 23, -1094730640);
      a = hh(a, b, c, d, words[x + 13] | 0, 4, 681279174); d = hh(d, a, b, c, words[x + 0] | 0, 11, -358537222);
      c = hh(c, d, a, b, words[x + 3] | 0, 16, -722521979); b = hh(b, c, d, a, words[x + 6] | 0, 23, 76029189);
      a = hh(a, b, c, d, words[x + 9] | 0, 4, -640364487); d = hh(d, a, b, c, words[x + 12] | 0, 11, -421815835);
      c = hh(c, d, a, b, words[x + 15] | 0, 16, 530742520); b = hh(b, c, d, a, words[x + 2] | 0, 23, -995338651);
      a = ii(a, b, c, d, words[x + 0] | 0, 6, -198630844); d = ii(d, a, b, c, words[x + 7] | 0, 10, 1126891415);
      c = ii(c, d, a, b, words[x + 14] | 0, 15, -1416354905); b = ii(b, c, d, a, words[x + 5] | 0, 21, -57434055);
      a = ii(a, b, c, d, words[x + 12] | 0, 6, 1700485571); d = ii(d, a, b, c, words[x + 3] | 0, 10, -1894986606);
      c = ii(c, d, a, b, words[x + 10] | 0, 15, -1051523); b = ii(b, c, d, a, words[x + 1] | 0, 21, -2054922799);
      a = ii(a, b, c, d, words[x + 8] | 0, 6, 1873313359); d = ii(d, a, b, c, words[x + 15] | 0, 10, -30611744);
      c = ii(c, d, a, b, words[x + 6] | 0, 15, -1560198380); b = ii(b, c, d, a, words[x + 13] | 0, 21, 1309151649);
      a = ii(a, b, c, d, words[x + 4] | 0, 6, -145523070); d = ii(d, a, b, c, words[x + 11] | 0, 10, -1120210379);
      c = ii(c, d, a, b, words[x + 2] | 0, 15, 718787259); b = ii(b, c, d, a, words[x + 9] | 0, 21, -343485551);
      a = (a + oa) | 0; b = (b + ob) | 0; c = (c + oc) | 0; d = (d + od) | 0;
    }
    function hex(n) { let s = ''; for (let i = 0; i < 4; i++) s += ((n >> (i * 8)) & 255).toString(16).padStart(2, '0'); return s; }
    return hex(a) + hex(b) + hex(c) + hex(d);
  }

  /* =======================================================================
     MINIMAL MARKDOWN RENDERER (safe: escapes HTML first)
     ==================================================================== */
  function markdown(src) {
    const esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const lines = esc(src).replace(/\r\n/g, '\n').split('\n');
    let html = '', inList = false, inOrdered = false, inCode = false, para = [];
    const inline = t => t
      .replace(/`([^`]+)`/g, '<code>$1</code>')
      .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
      .replace(/(^|\W)\*([^*]+)\*/g, '$1<em>$2</em>')
      .replace(/(^|\W)_([^_]+)_/g, '$1<em>$2</em>')
      .replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img alt="$1" src="$2">')
      .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" rel="noopener">$1</a>');
    const flushPara = () => { if (para.length) { html += '<p>' + inline(para.join(' ')) + '</p>'; para = []; } };
    const closeLists = () => { if (inList) { html += '</ul>'; inList = false; } if (inOrdered) { html += '</ol>'; inOrdered = false; } };
    for (let line of lines) {
      if (/^```/.test(line)) { flushPara(); closeLists(); if (!inCode) { html += '<pre><code>'; inCode = true; } else { html += '</code></pre>'; inCode = false; } continue; }
      if (inCode) { html += line + '\n'; continue; }
      const h = line.match(/^(#{1,6})\s+(.*)$/);
      if (h) { flushPara(); closeLists(); html += `<h${h[1].length}>${inline(h[2])}</h${h[1].length}>`; continue; }
      if (/^\s*[-*+]\s+/.test(line)) { flushPara(); if (inOrdered) { html += '</ol>'; inOrdered = false; } if (!inList) { html += '<ul>'; inList = true; } html += '<li>' + inline(line.replace(/^\s*[-*+]\s+/, '')) + '</li>'; continue; }
      if (/^\s*\d+\.\s+/.test(line)) { flushPara(); if (inList) { html += '</ul>'; inList = false; } if (!inOrdered) { html += '<ol>'; inOrdered = true; } html += '<li>' + inline(line.replace(/^\s*\d+\.\s+/, '')) + '</li>'; continue; }
      if (/^\s*>\s?/.test(line)) { flushPara(); closeLists(); html += '<blockquote>' + inline(line.replace(/^\s*>\s?/, '')) + '</blockquote>'; continue; }
      if (/^\s*(---|\*\*\*)\s*$/.test(line)) { flushPara(); closeLists(); html += '<hr>'; continue; }
      if (line.trim() === '') { flushPara(); closeLists(); continue; }
      para.push(line.trim());
    }
    flushPara(); closeLists(); if (inCode) html += '</code></pre>';
    return html;
  }

  /* =======================================================================
     MINIMAL PDF WRITER — text pages + embedded JPEG images
     ==================================================================== */
  const PDF = (function () {
    // Build a PDF from an array of JPEG data (Uint8Array) sized to A4 pages.
    function fromJpegs(images) {
      const objs = [];
      const A4 = [595.28, 841.89];
      function add(str) { objs.push(str); return objs.length; }
      // Reserve: 1 catalog, 2 pages tree. Build page + image objects.
      const pageRefs = [];
      const chunks = [];
      let objNum = 2; // catalog=1, pages=2
      const parts = [];
      // We'll assemble raw with xref later. Use array of {num, data(Uint8Array|string)}.
      const objectList = [];
      objectList.push({ num: 1, data: '<< /Type /Catalog /Pages 2 0 R >>' });
      const kids = [];
      let n = 3;
      images.forEach(img => {
        const imgNum = n++, contentNum = n++, pageNum = n++;
        const sx = A4[0], sy = A4[1];
        // Fit image within page keeping aspect ratio.
        let w = img.width, h = img.height;
        const scale = Math.min(sx / w, sy / h);
        const dw = w * scale, dh = h * scale;
        const ox = (sx - dw) / 2, oy = (sy - dh) / 2;
        objectList.push({ num: imgNum, dict: `<< /Type /XObject /Subtype /Image /Width ${w} /Height ${h} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${img.bytes.length} >>`, stream: img.bytes });
        const content = `q\n${dw.toFixed(2)} 0 0 ${dh.toFixed(2)} ${ox.toFixed(2)} ${oy.toFixed(2)} cm\n/Im0 Do\nQ`;
        objectList.push({ num: contentNum, dict: `<< /Length ${content.length} >>`, stream: strBytes(content) });
        objectList.push({ num: pageNum, data: `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${sx} ${sy}] /Resources << /XObject << /Im0 ${imgNum} 0 R >> >> /Contents ${contentNum} 0 R >>` });
        kids.push(`${pageNum} 0 R`);
      });
      objectList.push({ num: 2, data: `<< /Type /Pages /Kids [${kids.join(' ')}] /Count ${images.length} >>` });
      return assemble(objectList, n - 1);
    }

    function fromText(text, title) {
      const A4 = [595.28, 841.89];
      const margin = 56, fontSize = 11, lineHeight = 15;
      const maxChars = 92;
      const rawLines = String(text).replace(/\r\n/g, '\n').split('\n');
      const lines = [];
      rawLines.forEach(l => {
        if (l.length <= maxChars) { lines.push(l); return; }
        let s = l;
        while (s.length > maxChars) { let cut = s.lastIndexOf(' ', maxChars); if (cut < 40) cut = maxChars; lines.push(s.slice(0, cut)); s = s.slice(cut).trimStart(); }
        lines.push(s);
      });
      const linesPerPage = Math.floor((A4[1] - margin * 2) / lineHeight);
      const pages = [];
      for (let i = 0; i < lines.length; i += linesPerPage) pages.push(lines.slice(i, i + linesPerPage));
      if (!pages.length) pages.push(['']);
      const objectList = [];
      objectList.push({ num: 1, data: '<< /Type /Catalog /Pages 2 0 R >>' });
      const fontNum = 3;
      objectList.push({ num: fontNum, data: '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>' });
      let n = 4; const kids = [];
      pages.forEach(pl => {
        const contentNum = n++, pageNum = n++;
        let content = 'BT\n/F1 ' + fontSize + ' Tf\n' + lineHeight + ' TL\n' + margin + ' ' + (A4[1] - margin) + ' Td\n';
        pl.forEach(line => { content += '(' + pdfEsc(line) + ') Tj\nT*\n'; });
        content += 'ET';
        objectList.push({ num: contentNum, dict: `<< /Length ${content.length} >>`, stream: strBytes(content) });
        objectList.push({ num: pageNum, data: `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${A4[0]} ${A4[1]}] /Resources << /Font << /F1 ${fontNum} 0 R >> >> /Contents ${contentNum} 0 R >>` });
        kids.push(`${pageNum} 0 R`);
      });
      objectList.push({ num: 2, data: `<< /Type /Pages /Kids [${kids.join(' ')}] /Count ${pages.length} >>` });
      return assemble(objectList, n - 1);
    }

    function pdfEsc(s) { return s.replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)').replace(/[^\x20-\x7E]/g, ''); }
    function strBytes(s) { const a = new Uint8Array(s.length); for (let i = 0; i < s.length; i++) a[i] = s.charCodeAt(i) & 0xff; return a; }

    function assemble(objectList, maxNum) {
      objectList.sort((a, b) => a.num - b.num);
      let out = []; let offset = 0;
      const header = '%PDF-1.5\n%\xFF\xFF\xFF\xFF\n';
      const parts = [strBytes(header)];
      offset += parts[0].length;
      const xref = new Array(maxNum + 1).fill(0);
      objectList.forEach(o => {
        xref[o.num] = offset;
        let head = o.num + ' 0 obj\n';
        if (o.stream) {
          head += o.dict + '\nstream\n';
          const hb = strBytes(head);
          const tail = strBytes('\nendstream\nendobj\n');
          parts.push(hb, o.stream, tail);
          offset += hb.length + o.stream.length + tail.length;
        } else {
          const b = strBytes(head + o.data + '\nendobj\n');
          parts.push(b); offset += b.length;
        }
      });
      const xrefStart = offset;
      let xrefStr = 'xref\n0 ' + (maxNum + 1) + '\n0000000000 65535 f \n';
      for (let i = 1; i <= maxNum; i++) xrefStr += String(xref[i]).padStart(10, '0') + ' 00000 n \n';
      xrefStr += 'trailer\n<< /Size ' + (maxNum + 1) + ' /Root 1 0 R >>\nstartxref\n' + xrefStart + '\n%%EOF';
      parts.push(strBytes(xrefStr));
      let total = 0; parts.forEach(p => total += p.length);
      const merged = new Uint8Array(total); let pos = 0;
      parts.forEach(p => { merged.set(p, pos); pos += p.length; });
      return new Blob([merged], { type: 'application/pdf' });
    }
    return { fromJpegs, fromText };
  })();

  /* Expose the library. */
  window.OmniLib = { QR, qrToCanvas, CODE128, md5, markdown, PDF };
})();
