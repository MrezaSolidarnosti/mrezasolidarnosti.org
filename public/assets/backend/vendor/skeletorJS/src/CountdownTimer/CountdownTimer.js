import {countdownTimerAssets} from "./countdownTimerAssets.js";

export default class CountdownTimer {

    #target;
    #until;                 // target time, ms since epoch
    #options;

    #root = null;
    #rings = new Map();     // unit -> { progress, value, circumference }
    #interval = null;
    #maxLargest = 1;        // full-ring value for the largest rendered unit
    #finished = false;

    static get UNITS() {
        return countdownTimerAssets.units;
    }

    constructor({target, until, options = {}}) {
        this.#target = target;
        this.#until = this.#toTime(until);
        this.#options = this.#mergeOptions(options);
    }

    init() {
        if (!this.#target) {
            console.warn('CountdownTimer: no target element provided. Did you pass { target } ?');
            return this;
        }
        this.#maxLargest = this.#resolveMax();
        this.#build();
        this.#update();
        this.start();
        return this;
    }


    start() {
        if (this.#finished || this.#interval !== null) {
            return this;
        }
        // Each tick recomputes from the target time rather than decrementing, so a
        // throttled/background tab self-corrects instead of drifting.
        this.#interval = setInterval(this.#update, 1000);
        return this;
    }

    stop() {
        if (this.#interval !== null) {
            clearInterval(this.#interval);
            this.#interval = null;
        }
        return this;
    }

    setUntil(until) {
        this.stop();
        this.#until = this.#toTime(until);
        this.#finished = false;
        this.#maxLargest = this.#resolveMax();
        if (this.#root) {
            this.#root.classList.remove(countdownTimerAssets.classes.complete);
        }
        this.#update();
        this.start();
        return this;
    }

    // { total (ms), days, hours, minutes, seconds } — the natural breakdown, regardless of
    // which units are rendered.
    getRemaining() {
        const total = this.#totalRemaining();
        return {total, ...this.#naturalValues(total)};
    }

    getContainer() {
        return this.#root;
    }

    destroy() {
        this.stop();
        if (this.#root) {
            this.#root.remove();
        }
        this.#rings.clear();
        this.#root = null;
    }


    #mergeOptions(options) {
        const requested = Array.isArray(options.units) && options.units.length
            ? options.units
            : countdownTimerAssets.units;
        return {
            // Always render largest → smallest, whatever order was passed.
            units: countdownTimerAssets.units.filter((u) => requested.includes(u)),
            size: options.size || 90,
            strokeWidth: options.strokeWidth || 6,
            labels: {...countdownTimerAssets.labels, ...(options.labels || {})},
            showLabels: options.showLabels !== false,
            pad: options.pad !== false,
            max: typeof options.max === 'number' ? options.max : null,
            onTick: typeof options.onTick === 'function' ? options.onTick : null,
            onComplete: typeof options.onComplete === 'function' ? options.onComplete : null,
        };
    }

    #toTime(until) {
        if (until instanceof Date) {
            return until.getTime();
        }
        if (typeof until === 'number') {
            return until;
        }
        const parsed = new Date(until).getTime();
        if (!isFinite(parsed)) {
            console.warn(`CountdownTimer: could not parse "${until}" — use an ISO string, Date, or timestamp.`);
            return Date.now();
        }
        return parsed;
    }

    // The largest rendered unit has no natural cycle, so its ring is full at its starting
    // value (or an explicit `options.max`).
    #resolveMax() {
        if (this.#options.max) {
            return Math.max(1, this.#options.max);
        }
        const largest = this.#options.units[0];
        const start = this.#displayValues(this.#totalRemaining());
        return Math.max(1, start[largest] || 1);
    }

    #build() {
        this.#root = document.createElement('div');
        this.#root.classList.add(countdownTimerAssets.classes.root);

        const {size, strokeWidth} = this.#options;
        // The viewBox is a fixed 100x100 box, so convert the px stroke into viewBox units.
        const stroke = (strokeWidth * 100) / size;
        const radius = (100 - stroke) / 2;
        const circumference = 2 * Math.PI * radius;

        this.#options.units.forEach((unit) => {
            this.#root.appendChild(this.#buildUnit(unit, size, stroke, radius, circumference));
        });

        this.#target.appendChild(this.#root);
    }

    #buildUnit(unit, size, stroke, radius, circumference) {
        const container = document.createElement('div');
        container.classList.add(countdownTimerAssets.classes.unit);
        container.setAttribute(countdownTimerAssets.attributes.unit, unit);

        const wrap = document.createElement('div');
        wrap.classList.add(countdownTimerAssets.classes.ringWrap);
        wrap.style.width = `${size}px`;
        wrap.style.height = `${size}px`;

        const svg = this.#svgEl('svg', {viewBox: '0 0 100 100', 'class': countdownTimerAssets.classes.ring});
        svg.appendChild(this.#svgEl('circle', {
            cx: 50, cy: 50, r: radius, 'stroke-width': stroke,
            'class': countdownTimerAssets.classes.track,
        }));
        const progress = this.#svgEl('circle', {
            cx: 50, cy: 50, r: radius, 'stroke-width': stroke,
            'stroke-dasharray': circumference,
            'stroke-dashoffset': circumference,
            'class': countdownTimerAssets.classes.progress,
        });
        svg.appendChild(progress);

        const value = document.createElement('span');
        value.classList.add(countdownTimerAssets.classes.value);
        value.style.fontSize = `${Math.round(size * 0.28)}px`;

        wrap.append(svg, value);
        container.appendChild(wrap);

        if (this.#options.showLabels) {
            const label = document.createElement('span');
            label.classList.add(countdownTimerAssets.classes.label);
            label.textContent = this.#options.labels[unit];
            container.appendChild(label);
        }

        this.#rings.set(unit, {progress, value, circumference});
        return container;
    }


    #update = () => {
        const total = this.#totalRemaining();
        this.#render(total);

        if (this.#options.onTick) {
            this.#options.onTick({total, ...this.#naturalValues(total)});
        }
        if (total <= 0 && !this.#finished) {
            this.#finished = true;
            this.stop();
            this.#root.classList.add(countdownTimerAssets.classes.complete);
            if (this.#options.onComplete) {
                this.#options.onComplete();
            }
        }
    };

    #render(total) {
        const values = this.#displayValues(total);
        this.#options.units.forEach((unit) => {
            const ring = this.#rings.get(unit);
            if (!ring) {
                return;
            }
            const value = values[unit];
            const text = this.#format(unit, value);
            if (ring.value.textContent !== text) {
                ring.value.textContent = text;
            }
            const fraction = Math.min(1, Math.max(0, value / this.#cycleFor(unit)));
            ring.progress.setAttribute('stroke-dashoffset', `${ring.circumference * (1 - fraction)}`);
        });
    }

    #totalRemaining() {
        return Math.max(0, this.#until - Date.now());
    }

    // Natural breakdown: days, then hours 0-23, minutes 0-59, seconds 0-59.
    #naturalValues(total) {
        const seconds = Math.floor(total / 1000);
        return {
            days: Math.floor(seconds / 86400),
            hours: Math.floor(seconds / 3600) % 24,
            minutes: Math.floor(seconds / 60) % 60,
            seconds: seconds % 60,
        };
    }

    // Same, except the largest rendered unit absorbs everything above it — so a timer
    // showing only minutes+seconds still accounts for the days, instead of silently
    // dropping them.
    #displayValues(total) {
        const seconds = Math.floor(total / 1000);
        const values = this.#naturalValues(total);
        switch (this.#options.units[0]) {
            case 'hours':
                values.hours = Math.floor(seconds / 3600);
                break;
            case 'minutes':
                values.minutes = Math.floor(seconds / 60);
                break;
            case 'seconds':
                values.seconds = seconds;
                break;
            default:
                break; // 'days' is already the largest
        }
        return values;
    }

    #cycleFor(unit) {
        return unit === this.#options.units[0]
            ? this.#maxLargest
            : countdownTimerAssets.cycles[unit];
    }

    #format(unit, value) {
        // Only pad units with a known cycle; the largest can exceed two digits.
        const pad = this.#options.pad && unit !== this.#options.units[0];
        return pad ? `${value}`.padStart(2, '0') : `${value}`;
    }

    #svgEl(tag, attrs = {}) {
        const el = document.createElementNS(countdownTimerAssets.ns, tag);
        for (const [key, value] of Object.entries(attrs)) {
            el.setAttribute(key, value);
        }
        return el;
    }
}
