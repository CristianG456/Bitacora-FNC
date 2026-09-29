import test from 'node:test';
import assert from 'node:assert/strict';

import {
    iosVersion,
    isIosDevice,
    isStandaloneApp,
    pushReadyKey,
    supportsIosWebPushVersion,
} from '../../resources/js/push-platform.js';

const iphoneSafari = {
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 Version/17.5 Mobile/15E148 Safari/604.1',
    platform: 'iPhone',
    maxTouchPoints: 5,
};

test('detects iPhone Safari and its iOS version', () => {
    assert.equal(isIosDevice(iphoneSafari), true);
    assert.deepEqual(iosVersion(iphoneSafari), { major: 17, minor: 5 });
});

test('detects Chrome on iPhone as iOS', () => {
    assert.equal(isIosDevice({ ...iphoneSafari, userAgent: iphoneSafari.userAgent.replace('Version/17.5', 'CriOS/126.0.6478.54') }), true);
});

test('detects classic iPad and desktop-mode iPadOS', () => {
    assert.equal(isIosDevice({ userAgent: 'Mozilla/5.0 (iPad; CPU OS 16_6 like Mac OS X)', platform: 'iPad', maxTouchPoints: 5 }), true);
    assert.equal(isIosDevice({ userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', platform: 'MacIntel', maxTouchPoints: 5 }), true);
});

test('does not confuse a desktop Mac with iPadOS', () => {
    assert.equal(isIosDevice({ userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', platform: 'MacIntel', maxTouchPoints: 0 }), false);
});

test('requires iOS 16.4 when the real version is exposed', () => {
    const device = version => ({ ...iphoneSafari, userAgent: iphoneSafari.userAgent.replace('17_5', version) });
    assert.equal(supportsIosWebPushVersion(device('16_3')), false);
    assert.equal(supportsIosWebPushVersion(device('16_4')), true);
    assert.equal(supportsIosWebPushVersion(device('17_0')), true);
});

test('uses feature detection for desktop-mode iPadOS with hidden version', () => {
    assert.equal(supportsIosWebPushVersion({ userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', platform: 'MacIntel', maxTouchPoints: 5 }), true);
});

test('detects standalone from display mode or the iOS navigator flag', () => {
    assert.equal(isStandaloneApp({ matchMedia: () => ({ matches: true }) }, { standalone: false }), true);
    assert.equal(isStandaloneApp({ matchMedia: () => ({ matches: false }) }, { standalone: true }), true);
    assert.equal(isStandaloneApp({ matchMedia: () => ({ matches: false }) }, { standalone: false }), false);
});

test('scopes the non-sensitive ready marker to the authenticated user', () => {
    assert.equal(pushReadyKey(42), 'webpush_ready:42');
});
