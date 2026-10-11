const assert = require('node:assert/strict');
const test = require('node:test');

const { deriveInstallKey, matchesInstallKey } = require('../keygen/install-key.js');

// The same known-answer vectors live in the PHP test suite; if either
// implementation drifts, one of the two suites fails.
test('the shared known-answer vectors match the PHP implementation', () => {
    assert.equal(deriveInstallKey('owner@thai2d3d.local'), 'HNIMR-YCWQS-NY3PQ-FURJE');
    assert.equal(deriveInstallKey('chief@thai2d3d.local'), 'E6NTS-RDA57-6KUV3-LIVBJ');
});

test('email case and edge whitespace are normalised', () => {
    assert.equal(
        deriveInstallKey('  OWNER@Thai2D3D.LOCAL\t'),
        deriveInstallKey('owner@thai2d3d.local'),
    );
});

test('a different email produces a different key', () => {
    assert.notEqual(
        deriveInstallKey('owner@thai2d3d.local'),
        deriveInstallKey('chief@thai2d3d.local'),
    );
    assert.notEqual(
        deriveInstallKey('owner@thai2d3d.local'),
        deriveInstallKey('owner@thai2d3d.online'),
    );
});

test('verification ignores case, dashes and spaces in the entered key', () => {
    assert.equal(
        matchesInstallKey('owner@thai2d3d.local', 'hnimr ycwqs ny3pq furje'),
        true,
    );
    assert.equal(
        matchesInstallKey('owner@thai2d3d.local', 'AAAAA-BBBBB-CCCCC-DDDDD'),
        false,
    );
    assert.equal(
        matchesInstallKey('other@thai2d3d.local', 'HNIMR-YCWQS-NY3PQ-FURJE'),
        false,
    );
});
