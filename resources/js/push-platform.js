export function isIosDevice(navigatorLike = navigator) {
    const userAgent = navigatorLike.userAgent || '';
    const classicIos = /iPhone|iPad|iPod/i.test(userAgent);
    const desktopIpad = navigatorLike.platform === 'MacIntel' && Number(navigatorLike.maxTouchPoints) > 1;

    return classicIos || desktopIpad;
}

export function iosVersion(navigatorLike = navigator) {
    if (!isIosDevice(navigatorLike)) return null;

    const match = (navigatorLike.userAgent || '').match(/(?:CPU (?:iPhone )?OS|iPhone OS) (\d+)[._](\d+)/i);
    if (!match) return null;

    return { major: Number(match[1]), minor: Number(match[2]) };
}

export function supportsIosWebPushVersion(navigatorLike = navigator) {
    const version = iosVersion(navigatorLike);

    // iPadOS en modo desktop no expone de forma fiable su version real.
    // En ese caso se continua con standalone + feature detection.
    return version === null || version.major > 16 || (version.major === 16 && version.minor >= 4);
}

export function isStandaloneApp(windowLike = window, navigatorLike = navigator) {
    return windowLike.matchMedia?.('(display-mode: standalone)').matches === true
        || navigatorLike.standalone === true;
}

export function pushReadyKey(userId) {
    return `webpush_ready:${String(userId ?? 'guest')}`;
}

export function shouldSuppressInstallPrompt({ standalone, installed, dismissedUntil, now = Date.now() }) {
    return standalone === true || installed === true || Number(dismissedUntil || 0) > now;
}
