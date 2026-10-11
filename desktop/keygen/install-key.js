// Browser + Node implementation of the Thai2D3D install pass key derivation.
// Must stay byte-identical with app/Services/InstallKey.php (see the shared
// known-answer vector in desktop/test/install-key.test.js).

const INSTALL_KEY_PEPPER = '44ac1d7eaa014d7672430368ca925b6d87d38c9081a508fd9c697cb8524a1eb3';
const INSTALL_KEY_GROUPS = 4;
const INSTALL_KEY_GROUP_SIZE = 5;

const SHA256_K = [
    0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
    0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
    0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
    0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
    0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
    0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
    0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
    0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2,
];

const SHA256_INITIAL_STATE = [
    0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a,
    0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19,
];

const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function rotr(value, shift) {
    return ((value >>> shift) | (value << (32 - shift))) >>> 0;
}

function sha256(bytes) {
    const length = bytes.length;
    const padded = new Uint8Array((((length + 9) + 63) >> 6) << 6);
    padded.set(bytes);
    padded[length] = 0x80;
    const view = new DataView(padded.buffer);
    view.setUint32(padded.length - 8, Math.floor((length * 8) / 0x100000000));
    view.setUint32(padded.length - 4, (length * 8) >>> 0);

    const state = SHA256_INITIAL_STATE.slice();
    const w = new Uint32Array(64);

    for (let offset = 0; offset < padded.length; offset += 64) {
        for (let i = 0; i < 16; i++) w[i] = view.getUint32(offset + i * 4);
        for (let i = 16; i < 64; i++) {
            const s0 = (rotr(w[i - 15], 7) ^ rotr(w[i - 15], 18) ^ (w[i - 15] >>> 3)) >>> 0;
            const s1 = (rotr(w[i - 2], 17) ^ rotr(w[i - 2], 19) ^ (w[i - 2] >>> 10)) >>> 0;
            w[i] = (w[i - 16] + s0 + w[i - 7] + s1) >>> 0;
        }

        let [a, b, c, d, e, f, g, h] = state;
        for (let i = 0; i < 64; i++) {
            const bigS1 = (rotr(e, 6) ^ rotr(e, 11) ^ rotr(e, 25)) >>> 0;
            const ch = ((e & f) ^ (~e & g)) >>> 0;
            const t1 = (h + bigS1 + ch + SHA256_K[i] + w[i]) >>> 0;
            const bigS0 = (rotr(a, 2) ^ rotr(a, 13) ^ rotr(a, 22)) >>> 0;
            const maj = ((a & b) ^ (a & c) ^ (b & c)) >>> 0;
            const t2 = (bigS0 + maj) >>> 0;

            h = g; g = f; f = e; e = (d + t1) >>> 0;
            d = c; c = b; b = a; a = (t1 + t2) >>> 0;
        }

        state[0] = (state[0] + a) >>> 0; state[1] = (state[1] + b) >>> 0;
        state[2] = (state[2] + c) >>> 0; state[3] = (state[3] + d) >>> 0;
        state[4] = (state[4] + e) >>> 0; state[5] = (state[5] + f) >>> 0;
        state[6] = (state[6] + g) >>> 0; state[7] = (state[7] + h) >>> 0;
    }

    const out = new Uint8Array(32);
    const outView = new DataView(out.buffer);
    for (let i = 0; i < 8; i++) outView.setUint32(i * 4, state[i]);

    return out;
}

function hmacSha256(keyBytes, messageBytes) {
    const blockSize = 64;
    const key = keyBytes.length > blockSize ? sha256(keyBytes) : keyBytes;
    const inner = new Uint8Array(blockSize + messageBytes.length);
    const outer = new Uint8Array(blockSize + 32);

    for (let i = 0; i < blockSize; i++) {
        const byte = i < key.length ? key[i] : 0;
        inner[i] = byte ^ 0x36;
        outer[i] = byte ^ 0x5c;
    }

    inner.set(messageBytes, blockSize);
    outer.set(sha256(inner), blockSize);

    return sha256(outer);
}

function base32(bytes) {
    let bits = '';
    for (const byte of bytes) bits += byte.toString(2).padStart(8, '0');

    let encoded = '';
    for (let i = 0; i < bits.length; i += 5) {
        encoded += BASE32_ALPHABET[parseInt(bits.slice(i, i + 5).padEnd(5, '0'), 2)];
    }

    return encoded;
}

// ASCII-only variants that match PHP's trim() and strtolower() exactly:
// PHP trims ' \t\n\r\0\x0B' and lowercases ASCII letters only, while
// JavaScript trim()/toLowerCase() would also touch other Unicode characters.
function asciiTrim(value) {
    const edge = ' \t\n\r\u0000\u000B';
    let start = 0;
    let end = value.length;
    while (start < end && edge.indexOf(value[start]) !== -1) start++;
    while (end > start && edge.indexOf(value[end - 1]) !== -1) end--;

    return value.slice(start, end);
}

function asciiLower(value) {
    return value.replace(/[A-Z]/g, character => String.fromCharCode(character.charCodeAt(0) + 32));
}

function deriveInstallKey(email) {
    const payload = asciiLower(asciiTrim(email));
    const digest = hmacSha256(new TextEncoder().encode(INSTALL_KEY_PEPPER), new TextEncoder().encode(payload));
    const encoded = base32(digest).slice(0, INSTALL_KEY_GROUPS * INSTALL_KEY_GROUP_SIZE);

    const parts = [];
    for (let i = 0; i < encoded.length; i += INSTALL_KEY_GROUP_SIZE) {
        parts.push(encoded.slice(i, i + INSTALL_KEY_GROUP_SIZE));
    }

    return parts.join('-');
}

function fingerprint(key) {
    return String(key).toUpperCase().replace(/[^A-Z0-9]/g, '');
}

function matchesInstallKey(email, candidate) {
    return fingerprint(deriveInstallKey(email)) === fingerprint(candidate);
}

const installKeyApi = { deriveInstallKey, matchesInstallKey };
if (typeof module !== 'undefined' && module.exports) module.exports = installKeyApi;
if (typeof window !== 'undefined') window.installKey = installKeyApi;
