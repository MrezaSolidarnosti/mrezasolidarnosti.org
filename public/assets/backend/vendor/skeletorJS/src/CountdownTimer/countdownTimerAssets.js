export const countdownTimerAssets = Object.freeze({
    ns: 'http://www.w3.org/2000/svg',
    // Canonical order — a rendered timer always reads largest → smallest.
    units: Object.freeze(['days', 'hours', 'minutes', 'seconds']),
    // How many of each unit make up the next one up (a full ring). `days` has no natural
    // cycle, so the largest rendered unit uses its value at start (or `options.max`).
    cycles: Object.freeze({
        hours: 24,
        minutes: 60,
        seconds: 60,
    }),
    labels: Object.freeze({
        days: 'Days',
        hours: 'Hours',
        minutes: 'Minutes',
        seconds: 'Seconds',
    }),
    classes: {
        root: 'countdownTimer',
        unit: 'countdownUnit',
        ringWrap: 'countdownRingWrap',
        ring: 'countdownRing',
        track: 'countdownTrack',
        progress: 'countdownProgress',
        value: 'countdownValue',
        label: 'countdownLabel',
        complete: 'countdownComplete',
    },
    attributes: {
        unit: 'data-unit',
    },
});
