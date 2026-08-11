import {chartAssets} from "./chartAssets.js";

export default class Chart {

    #target;
    #type;
    #data;
    #options;

    #root;
    #canvas;
    #svg;
    #legend;
    #tooltip;
    #resizeObserver;

    #width = 0;
    #height = 0;
    #pinned = false;
    #hidden = new Set();
    #pieRaf = null;
    #pieCurrent = null;

    static get TYPES() {
        return chartAssets.types;
    }

    constructor({target, type = 'bar', data, options = {}}) {
        this.#target = target;
        this.#type = chartAssets.types.includes(type) ? type : 'bar';
        this.#data = this.#normalizeData(data);
        this.#options = this.#mergeOptions(options);
    }

    init() {
        if (!this.#target) {
            console.warn('Chart: no target element provided. Did you pass { target } ?');
            return this;
        }
        this.#build();
        this.#observeResize();
        document.addEventListener('click', this.#handleDocumentClick);
        this.#render(true);
        return this;
    }

    update(data, type) {
        if (type && chartAssets.types.includes(type)) {
            this.#type = type;
            if (this.#root) {
                this.#root.setAttribute(chartAssets.attributes.type, this.#type);
            }
        }
        if (data !== undefined) {
            this.#data = this.#normalizeData(data);
            this.#hidden.clear(); // indices may no longer map to the new data
        }
        this.#render(true);
        return this;
    }

    setOptions(options = {}) {
        this.#options = this.#mergeOptions({...this.#options, ...options});
        this.#render(true);
        return this;
    }

    getContainer() {
        return this.#root;
    }

    destroy() {
        this.#cancelPieAnim();
        if (this.#resizeObserver) {
            this.#resizeObserver.disconnect();
        }
        document.removeEventListener('click', this.#handleDocumentClick);
        if (this.#root) {
            this.#root.remove();
        }
        this.#resizeObserver = null;
        this.#root = null;
        this.#canvas = null;
        this.#svg = null;
        this.#legend = null;
        this.#tooltip = null;
    }


    #mergeOptions(options) {
        return {
            title: options.title || '',
            height: options.height || 320,
            colors: Array.isArray(options.colors) && options.colors.length ? options.colors : chartAssets.palette,
            stacked: options.stacked === true,
            showLegend: options.showLegend !== false,
            legendToggle: options.legendToggle !== false,
            showGrid: options.showGrid !== false,
            showValues: options.showValues === true,
            showTooltip: options.showTooltip !== false,
            animate: options.animate !== false,
            yTicks: options.yTicks || 5,
            donutRatio: typeof options.donutRatio === 'number' ? options.donutRatio : 0.6,
            sliceGap: typeof options.sliceGap === 'number' ? options.sliceGap : 2,
            padding: {
                top: 16,
                right: 18,
                bottom: 34,
                left: 46,
                ...(options.padding || {}),
            },
            formatValue: typeof options.formatValue === 'function' ? options.formatValue : (v) => this.#formatNumber(v),
        };
    }

    #normalizeData(data) {
        if (!data) {
            return {labels: [], series: []};
        }
        // Shorthand for single-series / pie: [{ label, value }]
        if (Array.isArray(data)) {
            return {
                labels: data.map((d) => `${d.label ?? ''}`),
                series: [{name: '', values: data.map((d) => this.#toNumber(d.value))}],
            };
        }
        return {
            labels: (data.labels || []).map((l) => `${l}`),
            series: (data.series || []).map((s) => ({
                name: s.name || '',
                values: (s.values || []).map((v) => this.#toNumber(v)),
            })),
        };
    }

    #build() {
        this.#root = document.createElement('div');
        this.#root.classList.add(chartAssets.classes.root);
        this.#root.setAttribute(chartAssets.attributes.type, this.#type);

        if (this.#options.title) {
            const title = document.createElement('div');
            title.classList.add(chartAssets.classes.title);
            title.textContent = this.#options.title;
            this.#root.appendChild(title);
        }

        this.#canvas = document.createElement('div');
        this.#canvas.classList.add(chartAssets.classes.canvas);
        this.#canvas.style.height = `${this.#options.height}px`;

        this.#svg = this.#svgEl('svg', {'class': chartAssets.classes.svg});
        this.#svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
        this.#canvas.appendChild(this.#svg);

        this.#tooltip = document.createElement('div');
        this.#tooltip.classList.add(chartAssets.classes.tooltip);
        this.#canvas.appendChild(this.#tooltip);

        this.#root.appendChild(this.#canvas);

        this.#legend = document.createElement('div');
        this.#legend.classList.add(chartAssets.classes.legend);
        this.#root.appendChild(this.#legend);

        this.#target.appendChild(this.#root);
    }

    #observeResize() {
        if (typeof ResizeObserver === 'undefined') {
            return;
        }
        this.#resizeObserver = new ResizeObserver(() => this.#render());
        this.#resizeObserver.observe(this.#canvas);
    }


    #render(animate = false) {
        if (!this.#svg) {
            return;
        }
        this.#width = this.#canvas.clientWidth;
        this.#height = this.#options.height;
        if (this.#width <= 0) {
            return; // container not laid out yet (hidden / detached)
        }

        this.#cancelPieAnim();
        const isPie = this.#type === 'pie' || this.#type === 'donut';

        // Animate only on first paint / explicit update / legend toggle — never on
        // resize re-renders. `animate === 'toggle'` uses a lighter fade than the entry
        // grow. Pie/donut toggles use a bespoke angle tween instead of the CSS fade.
        const canAnimate = this.#options.animate;
        this.#root.classList.toggle(chartAssets.classes.animate, animate === true && canAnimate);
        this.#root.classList.toggle(chartAssets.classes.animateToggle, animate === 'toggle' && canAnimate && !isPie);

        this.#svg.setAttribute('viewBox', `0 0 ${this.#width} ${this.#height}`);
        this.#pinned = false;
        this.#clear();
        this.#hideTooltip();

        if (!this.#hasData()) {
            this.#renderEmpty();
            this.#legend.innerHTML = '';
            return;
        }

        if (isPie && animate === 'toggle' && canAnimate) {
            this.#animatePie(); // tweens slice angles, then draws the final interactive pie
        } else {
            switch (this.#type) {
                case 'pie':
                case 'donut':
                    this.#renderPie();
                    break;
                case 'line':
                case 'area':
                    this.#renderLine();
                    break;
                case 'horizontalBar':
                    this.#renderHorizontalBar();
                    break;
                case 'bar':
                default:
                    this.#renderBar();
                    break;
            }
        }

        this.#renderLegend();
    }

    #renderEmpty() {
        const text = this.#svgEl('text', {
            x: this.#width / 2,
            y: this.#height / 2,
            'text-anchor': 'middle',
            'dominant-baseline': 'middle',
            'class': chartAssets.classes.empty,
        });
        text.textContent = 'No data';
        this.#svg.appendChild(text);
    }


    #renderBar() {
        const plot = this.#plotArea();
        const scale = this.#valueScale();
        this.#drawGrid(plot, scale);
        this.#drawXCategories(plot);

        const {labels} = this.#data;
        const bandWidth = plot.width / labels.length;
        const baseY = this.#valueToY(this.#clampBase(scale), plot, scale);

        if (this.#options.stacked) {
            const visible = this.#visibleSeries();
            const posTops = new Array(labels.length).fill(0);
            const negTops = new Array(labels.length).fill(0);
            const barWidth = bandWidth * 0.62;
            labels.forEach((label, i) => {
                const cx = plot.left + bandWidth * (i + 0.5);
                visible.forEach(({s, si}) => {
                    const value = s.values[i] || 0;
                    if (value === 0) return;
                    const start = value >= 0 ? posTops[i] : negTops[i];
                    const end = start + value;
                    const yStart = this.#valueToY(start, plot, scale);
                    const yEnd = this.#valueToY(end, plot, scale);
                    const rect = this.#svgEl('rect', {
                        x: cx - barWidth / 2,
                        y: Math.min(yStart, yEnd),
                        width: barWidth,
                        height: Math.max(1, Math.abs(yEnd - yStart)),
                        rx: 3,
                        fill: this.#color(si),
                        'class': chartAssets.classes.bar,
                    });
                    this.#svg.appendChild(rect);
                    let valueLabel = null;
                    if (this.#options.showValues) {
                        valueLabel = this.#valueLabel(cx, (yStart + yEnd) / 2 + 4, value, 'middle', true);
                        valueLabel.classList.add(chartAssets.classes.valueHover);
                        this.#svg.appendChild(valueLabel);
                    }
                    this.#bindTooltip(rect, this.#tooltipRows(label, [[s.name || 'Value', value, this.#color(si)]]), valueLabel);
                    if (value >= 0) posTops[i] = end; else negTops[i] = end;
                });
            });
            return;
        }

        // Grouped bars
        const visible = this.#visibleSeries();
        const groupWidth = bandWidth * 0.72;
        const barWidth = groupWidth / (visible.length || 1);
        labels.forEach((label, i) => {
            const groupLeft = plot.left + bandWidth * (i + 0.5) - groupWidth / 2;
            visible.forEach(({s, si}, vi) => {
                const value = s.values[i] || 0;
                const y = this.#valueToY(value, plot, scale);
                const x = groupLeft + barWidth * vi;
                const rect = this.#svgEl('rect', {
                    x: x + barWidth * 0.08,
                    y: Math.min(y, baseY),
                    width: barWidth * 0.84,
                    height: Math.max(1, Math.abs(baseY - y)),
                    rx: 3,
                    fill: this.#color(si),
                    'class': chartAssets.classes.bar,
                });
                this.#svg.appendChild(rect);
                let valueLabel = null;
                if (this.#options.showValues) {
                    const labelY = value >= 0 ? y - 6 : y + 13;
                    valueLabel = this.#valueLabel(x + barWidth / 2, labelY, value, 'middle');
                    valueLabel.classList.add(chartAssets.classes.valueHover);
                    this.#svg.appendChild(valueLabel);
                }
                this.#bindTooltip(rect, this.#tooltipRows(label, [[s.name || 'Value', value, this.#color(si)]]), valueLabel);
            });
        });
    }


    #renderHorizontalBar() {
        const plot = this.#plotArea(true);
        const scale = this.#valueScale();
        this.#drawGridHorizontal(plot, scale);
        this.#drawYCategories(plot);

        const {labels} = this.#data;
        const visible = this.#visibleSeries();
        const bandHeight = plot.height / labels.length;
        const baseX = this.#valueToX(this.#clampBase(scale), plot, scale);
        const groupHeight = bandHeight * 0.72;
        const barHeight = groupHeight / (visible.length || 1);

        labels.forEach((label, i) => {
            const groupTop = plot.top + bandHeight * (i + 0.5) - groupHeight / 2;
            visible.forEach(({s, si}, vi) => {
                const value = s.values[i] || 0;
                const x = this.#valueToX(value, plot, scale);
                const y = groupTop + barHeight * vi;
                const rect = this.#svgEl('rect', {
                    x: Math.min(x, baseX),
                    y: y + barHeight * 0.08,
                    width: Math.max(1, Math.abs(x - baseX)),
                    height: barHeight * 0.84,
                    rx: 3,
                    fill: this.#color(si),
                    'class': chartAssets.classes.bar,
                });
                this.#svg.appendChild(rect);
                let valueLabel = null;
                if (this.#options.showValues) {
                    const labelY = y + barHeight / 2 + 4;
                    valueLabel = value >= 0
                        ? this.#valueLabel(x + 6, labelY, value, 'start')
                        : this.#valueLabel(x - 6, labelY, value, 'end');
                    valueLabel.classList.add(chartAssets.classes.valueHover);
                    this.#svg.appendChild(valueLabel);
                }
                this.#bindTooltip(rect, this.#tooltipRows(label, [[s.name || 'Value', value, this.#color(si)]]), valueLabel);
            });
        });
    }


    #renderLine() {
        const plot = this.#plotArea();
        const scale = this.#valueScale(this.#type === 'line');
        this.#drawGrid(plot, scale);
        this.#drawXCategories(plot);

        const {labels} = this.#data;
        const bandWidth = plot.width / labels.length;
        const xAt = (i) => plot.left + bandWidth * (i + 0.5);
        const isArea = this.#type === 'area';
        const multiSeries = this.#data.series.length > 1;

        this.#visibleSeries().forEach(({s, si}) => {
            const color = this.#color(si);
            const points = s.values.map((v, i) => [xAt(i), this.#valueToY(v, plot, scale)]);
            if (!points.length) return;

            if (isArea) {
                const baseY = this.#valueToY(this.#clampBase(scale), plot, scale);
                let d = `M ${points[0][0]} ${baseY}`;
                points.forEach(([x, y]) => (d += ` L ${x} ${y}`));
                d += ` L ${points[points.length - 1][0]} ${baseY} Z`;
                this.#svg.appendChild(this.#svgEl('path', {
                    d,
                    fill: color,
                    'fill-opacity': multiSeries ? 0.18 : 0.22,
                    stroke: 'none',
                    'class': chartAssets.classes.area,
                }));
            }

            const line = points.map(([x, y], i) => `${i === 0 ? 'M' : 'L'} ${x} ${y}`).join(' ');
            this.#svg.appendChild(this.#svgEl('path', {
                d: line,
                fill: 'none',
                stroke: color,
                'class': chartAssets.classes.line,
            }));

            points.forEach(([x, y], i) => {
                const dot = this.#svgEl('circle', {
                    cx: x,
                    cy: y,
                    r: 3.5,
                    fill: color,
                    'class': chartAssets.classes.point,
                });
                this.#svg.appendChild(dot);
                if (this.#options.showValues) {
                    this.#svg.appendChild(this.#valueLabel(x, y - 8, s.values[i], 'middle'));
                }
                // Larger invisible target so points are easy to hover / tap.
                const hit = this.#svgEl('circle', {
                    cx: x,
                    cy: y,
                    r: 12,
                    'class': chartAssets.classes.hit,
                });
                this.#svg.appendChild(hit);
                this.#bindTooltip(hit, this.#tooltipRows(labels[i], [[s.name || 'Value', s.values[i], color]]), dot);
            });
        });
    }


    #renderPie() {
        const {labels} = this.#data;
        // Hidden slices (toggled off in the legend) contribute 0, so the rest rescale.
        const values = this.#pieTargets();
        this.#pieCurrent = values.slice(); // remember for the next toggle's tween
        const total = values.reduce((a, b) => a + b, 0);
        if (total <= 0) {
            this.#renderEmpty();
            return;
        }

        const {cx, cy, rOuter, rInner} = this.#pieGeometry();

        // A real, transparent, constant-width gap between slices (background shows
        // through). Only when there's more than one slice.
        const sliceCount = values.filter((v) => v > 0).length;
        const gap = sliceCount > 1 ? this.#options.sliceGap : 0;

        let angle = 0;
        values.forEach((value, i) => {
            if (value <= 0) return;
            const fraction = value / total;
            const start = angle;
            const end = angle + fraction * Math.PI * 2;
            angle = end;
            const color = this.#color(i);

            const path = this.#buildSlicePath(cx, cy, rOuter, rInner, start, end, gap, fraction);
            path.setAttribute('fill', color);
            path.setAttribute('class', chartAssets.classes.slice);
            this.#svg.appendChild(path);

            const pct = `${this.#formatNumber(fraction * 100, 1)}%`;
            this.#bindTooltip(path, this.#tooltipRows(labels[i] || `#${i + 1}`, [[pct, value, color]]));

            if (this.#options.showValues && fraction > 0.06) {
                const mid = (start + end) / 2;
                const rLabel = rInner > 0 ? (rOuter + rInner) / 2 : rOuter * 0.62;
                const lx = cx + rLabel * Math.sin(mid);
                const ly = cy - rLabel * Math.cos(mid);
                const label = this.#svgEl('text', {
                    x: lx,
                    y: ly,
                    'text-anchor': 'middle',
                    'dominant-baseline': 'middle',
                    'class': chartAssets.classes.sliceLabel,
                });
                label.textContent = pct;
                this.#svg.appendChild(label);
            }
        });
    }

    #pieTargets() {
        return (this.#data.series[0]?.values || [])
            .map((v, i) => this.#hidden.has(i) ? 0 : Math.max(0, v));
    }

    #pieGeometry() {
        const cx = this.#width / 2;
        const cy = this.#height / 2;
        const rOuter = Math.min(this.#width, this.#height) / 2 - 8;
        const rInner = this.#type === 'donut' ? rOuter * this.#options.donutRatio : 0;
        return {cx, cy, rOuter, rInner};
    }

    #buildSlicePath(cx, cy, rOuter, rInner, start, end, gap, fraction) {
        return fraction >= 0.9999
            ? this.#fullRingPath(cx, cy, rOuter, rInner)
            : this.#svgEl('path', {d: this.#slicePath(cx, cy, rOuter, rInner, start, end, gap)});
    }

    // Draws just the slice paths (no labels/tooltips) for an animation frame.
    #drawPieFrame(values) {
        const total = values.reduce((a, b) => a + b, 0);
        if (total <= 0) {
            return;
        }
        const {cx, cy, rOuter, rInner} = this.#pieGeometry();
        const sliceCount = values.filter((v) => v > 1e-6).length;
        const gap = sliceCount > 1 ? this.#options.sliceGap : 0;

        let angle = 0;
        values.forEach((value, i) => {
            if (value <= 1e-6) return;
            const fraction = value / total;
            const start = angle;
            const end = angle + fraction * Math.PI * 2;
            angle = end;
            const path = this.#buildSlicePath(cx, cy, rOuter, rInner, start, end, gap, fraction);
            path.setAttribute('fill', this.#color(i));
            path.setAttribute('class', chartAssets.classes.slice);
            this.#svg.appendChild(path);
        });
    }

    // Tweens each slice value toward its target (hidden → 0), so unchecked slices
    // compress to nothing while the rest sweep to fill the circle. Ends by drawing the
    // final interactive pie (labels + tooltips).
    #animatePie() {
        const to = this.#pieTargets();
        const from = (this.#pieCurrent && this.#pieCurrent.length === to.length)
            ? this.#pieCurrent.slice()
            : to.slice();
        const duration = 340;
        const startTime = performance.now();
        const ease = (t) => 1 - Math.pow(1 - t, 3);

        this.#drawPieFrame(from); // paint the start state now to avoid a blank frame

        const tick = (now) => {
            const t = Math.min(1, (now - startTime) / duration);
            const k = ease(t);
            const current = from.map((f, i) => f + (to[i] - f) * k);
            this.#clear();
            this.#drawPieFrame(current);
            this.#pieCurrent = current;
            if (t < 1) {
                this.#pieRaf = requestAnimationFrame(tick);
            } else {
                this.#pieRaf = null;
                this.#clear();
                this.#renderPie();
            }
        };
        this.#pieRaf = requestAnimationFrame(tick);
    }

    #cancelPieAnim() {
        if (this.#pieRaf !== null) {
            cancelAnimationFrame(this.#pieRaf);
            this.#pieRaf = null;
        }
    }


    #plotArea(horizontal = false) {
        const p = this.#options.padding;
        // Horizontal bars need room on the left for category labels.
        const left = horizontal ? Math.max(p.left, this.#longestLabelWidth() + 14) : p.left;
        return {
            left,
            top: p.top,
            width: Math.max(1, this.#width - left - p.right),
            height: Math.max(1, this.#height - p.top - p.bottom),
        };
    }

    #drawGrid(plot, scale) {
        const ticks = this.#ticks(scale);
        ticks.forEach((t) => {
            const y = this.#valueToY(t, plot, scale);
            if (this.#options.showGrid) {
                this.#svg.appendChild(this.#svgEl('line', {
                    x1: plot.left, y1: y, x2: plot.left + plot.width, y2: y,
                    'class': t === 0 ? chartAssets.classes.zeroLine : chartAssets.classes.gridLine,
                }));
            }
            const label = this.#svgEl('text', {
                x: plot.left - 8, y: y + 4, 'text-anchor': 'end', 'class': chartAssets.classes.tickLabel,
            });
            label.textContent = this.#formatTick(t);
            this.#svg.appendChild(label);
        });
    }

    #drawGridHorizontal(plot, scale) {
        const ticks = this.#ticks(scale);
        ticks.forEach((t) => {
            const x = this.#valueToX(t, plot, scale);
            if (this.#options.showGrid) {
                this.#svg.appendChild(this.#svgEl('line', {
                    x1: x, y1: plot.top, x2: x, y2: plot.top + plot.height,
                    'class': t === 0 ? chartAssets.classes.zeroLine : chartAssets.classes.gridLine,
                }));
            }
            const label = this.#svgEl('text', {
                x, y: plot.top + plot.height + 18, 'text-anchor': 'middle', 'class': chartAssets.classes.tickLabel,
            });
            label.textContent = this.#formatTick(t);
            this.#svg.appendChild(label);
        });
    }

    #drawXCategories(plot) {
        const {labels} = this.#data;
        const bandWidth = plot.width / labels.length;
        const step = Math.ceil(labels.length / Math.max(1, Math.floor(plot.width / 46)));
        labels.forEach((label, i) => {
            if (i % step !== 0) return;
            const text = this.#svgEl('text', {
                x: plot.left + bandWidth * (i + 0.5),
                y: plot.top + plot.height + 20,
                'text-anchor': 'middle',
                'class': chartAssets.classes.tickLabel,
            });
            text.textContent = label;
            this.#svg.appendChild(text);
        });
    }

    #drawYCategories(plot) {
        const {labels} = this.#data;
        const bandHeight = plot.height / labels.length;
        labels.forEach((label, i) => {
            const text = this.#svgEl('text', {
                x: plot.left - 10,
                y: plot.top + bandHeight * (i + 0.5) + 4,
                'text-anchor': 'end',
                'class': chartAssets.classes.tickLabel,
            });
            text.textContent = label;
            this.#svg.appendChild(text);
        });
    }

    #valueScale(allowNonZeroFloor = false) {
        let min = Infinity;
        let max = -Infinity;
        const series = this.#visibleSeries().map((v) => v.s);

        if (this.#options.stacked) {
            const labelCount = this.#data.labels.length;
            for (let i = 0; i < labelCount; i++) {
                let pos = 0;
                let neg = 0;
                series.forEach((s) => {
                    const v = s.values[i] || 0;
                    if (v >= 0) pos += v; else neg += v;
                });
                max = Math.max(max, pos);
                min = Math.min(min, neg);
            }
        } else {
            series.forEach((s) => s.values.forEach((v) => {
                min = Math.min(min, v);
                max = Math.max(max, v);
            }));
        }

        if (!isFinite(min) || !isFinite(max)) {
            min = 0;
            max = 1;
        }
        if (!allowNonZeroFloor) {
            min = Math.min(0, min);
            max = Math.max(0, max);
        }
        return this.#niceScale(min, max, this.#options.yTicks);
    }

    #ticks(scale) {
        const out = [];
        const count = Math.round((scale.max - scale.min) / scale.step);
        for (let i = 0; i <= count; i++) {
            out.push(this.#round(scale.min + i * scale.step));
        }
        return out;
    }

    #valueToY(value, plot, scale) {
        const ratio = (value - scale.min) / (scale.max - scale.min);
        return plot.top + plot.height - ratio * plot.height;
    }

    #valueToX(value, plot, scale) {
        const ratio = (value - scale.min) / (scale.max - scale.min);
        return plot.left + ratio * plot.width;
    }

    #clampBase(scale) {
        return Math.min(Math.max(0, scale.min), scale.max);
    }


    // Series (cartesian) or slices (pie/donut) not toggled off via the legend. Keeps
    // each item's original index so colors stay stable when others are hidden.
    #visibleSeries() {
        return this.#data.series
            .map((s, si) => ({s, si}))
            .filter(({si}) => !this.#hidden.has(si));
    }

    #toggleEntry(key) {
        if (this.#hidden.has(key)) {
            this.#hidden.delete(key);
        } else {
            this.#hidden.add(key);
        }
        this.#render('toggle');
    }

    #renderLegend() {
        this.#legend.innerHTML = '';
        if (!this.#options.showLegend) {
            return;
        }

        // Each entry's `key` is the index used to hide it (slice index for pie/donut,
        // series index otherwise).
        let entries = [];
        if (this.#type === 'pie' || this.#type === 'donut') {
            entries = this.#data.labels.map((label, i) => ({label: label || `#${i + 1}`, color: this.#color(i), key: i}));
        } else {
            const named = this.#data.series.filter((s) => s.name);
            if (named.length < 2) {
                return; // nothing meaningful to legend for a single/unnamed series
            }
            entries = this.#data.series.map((s, i) => ({label: s.name, color: this.#color(i), key: i}));
        }

        entries.forEach((entry) => {
            const item = document.createElement('div');
            item.classList.add(chartAssets.classes.legendItem);
            if (this.#hidden.has(entry.key)) {
                item.classList.add(chartAssets.classes.legendItemHidden);
            }
            const swatch = document.createElement('span');
            swatch.classList.add(chartAssets.classes.legendSwatch);
            swatch.style.background = entry.color;
            const text = document.createElement('span');
            text.textContent = entry.label;
            item.appendChild(swatch);
            item.appendChild(text);

            if (this.#options.legendToggle) {
                item.style.cursor = 'pointer';
                item.addEventListener('click', () => this.#toggleEntry(entry.key));
            }
            this.#legend.appendChild(item);
        });
    }

    // Works for mouse (hover) and touch/pen (tap-to-pin). `highlight` is an optional
    // element (e.g. a hidden value label) that gets the active class while this mark is
    // engaged. Interaction is wired whenever there's a tooltip OR a highlight to reveal.
    #bindTooltip(el, html, highlight = null) {
        const showTip = this.#options.showTooltip;
        if (!showTip && !highlight) {
            return;
        }
        el.style.cursor = 'pointer';

        const activate = () => {
            if (showTip) {
                this.#tooltip.innerHTML = html;
                this.#tooltip.classList.add(chartAssets.classes.tooltipVisible);
            }
            if (highlight) {
                highlight.classList.add(chartAssets.classes.active);
            }
        };
        const deactivate = () => {
            if (highlight) {
                highlight.classList.remove(chartAssets.classes.active);
            }
        };
        const positionFromEvent = (e) => {
            if (!showTip) {
                return;
            }
            const rect = this.#canvas.getBoundingClientRect();
            let x = e.clientX - rect.left;
            let y = e.clientY - rect.top;
            if (!e.clientX && !e.clientY) { // synthetic / keyboard — fall back to the mark's centre
                const box = el.getBoundingClientRect();
                x = box.left + box.width / 2 - rect.left;
                y = box.top + box.height / 2 - rect.top;
            }
            this.#positionTooltip(x, y);
        };


        // Hover (mouse / pen)
        el.addEventListener('mouseenter', () => {
            if (!this.#pinned) activate();
        });
        el.addEventListener('mousemove', (e) => {
            if (!this.#pinned) positionFromEvent(e);
        });
        el.addEventListener('mouseleave', () => {
            if (!this.#pinned) {
                this.#hideTooltip();
                deactivate();
            }
        });

        // Tap / click — pins the tooltip so it works on touch devices (no hover there)
        el.addEventListener('click', (e) => {
            e.stopPropagation();
            this.#clearActive();
            this.#pinned = true;
            activate();
            positionFromEvent(e);
        });
    }

    #clearActive() {
        if (!this.#svg) {
            return;
        }
        this.#svg.querySelectorAll(`.${chartAssets.classes.active}`)
            .forEach((el) => el.classList.remove(chartAssets.classes.active));
    }

    #handleDocumentClick = (e) => {
        // Tapping anywhere outside the plot dismisses a pinned tooltip.
        if (this.#pinned && this.#svg && !this.#svg.contains(e.target)) {
            this.#pinned = false;
            this.#hideTooltip();
            this.#clearActive();
        }
    };

    #positionTooltip(x, y) {
        const tw = this.#tooltip.offsetWidth;
        const th = this.#tooltip.offsetHeight;
        let left = x + 12;
        let top = y + 12;
        if (left + tw > this.#width) {
            left = x - tw - 12;
        }
        if (top + th > this.#height) {
            top = y - th - 12;
        }
        this.#tooltip.style.left = `${Math.max(0, left)}px`;
        this.#tooltip.style.top = `${Math.max(0, top)}px`;
    }

    #hideTooltip() {
        if (this.#tooltip) {
            this.#tooltip.classList.remove(chartAssets.classes.tooltipVisible);
        }
    }

    #tooltipRows(title, rows) {
        const head = title ? `<strong>${this.#escape(title)}</strong>` : '';
        const body = rows.map(([name, value, color]) =>
            `<span class="chartTooltipRow"><span class="chartTooltipDot" style="background:${color}"></span>` +
            `${this.#escape(name)}: ${this.#escape(this.#options.formatValue(value))}</span>`
        ).join('');
        return head + body;
    }


    #svgEl(tag, attrs = {}) {
        const el = document.createElementNS(chartAssets.ns, tag);
        for (const [key, value] of Object.entries(attrs)) {
            el.setAttribute(key, value);
        }
        return el;
    }

    #valueLabel(x, y, value, anchor, onFill = false) {
        const cls = onFill
            ? `${chartAssets.classes.valueLabel} ${chartAssets.classes.valueLabelOnFill}`
            : chartAssets.classes.valueLabel;
        const text = this.#svgEl('text', {
            x, y, 'text-anchor': anchor, 'class': cls,
        });
        text.textContent = this.#options.formatValue(value);
        return text;
    }

    #arcPath(cx, cy, rOuter, rInner, start, end) {
        const point = (r, a) => [cx + r * Math.sin(a), cy - r * Math.cos(a)];
        const large = end - start > Math.PI ? 1 : 0;
        const [ox0, oy0] = point(rOuter, start);
        const [ox1, oy1] = point(rOuter, end);
        if (rInner <= 0) {
            return `M ${cx} ${cy} L ${ox0} ${oy0} A ${rOuter} ${rOuter} 0 ${large} 1 ${ox1} ${oy1} Z`;
        }
        const [ix1, iy1] = point(rInner, end);
        const [ix0, iy0] = point(rInner, start);
        return `M ${ox0} ${oy0} A ${rOuter} ${rOuter} 0 ${large} 1 ${ox1} ${oy1} ` +
            `L ${ix1} ${iy1} A ${rInner} ${rInner} 0 ${large} 0 ${ix0} ${iy0} Z`;
    }

    // A slice inset by a constant `gap` (px): each straight edge is offset toward the
    // slice's interior by gap/2, perpendicular to the radius, so the gap between two
    // neighbours stays an even `gap` wide from rim to centre (no taper). Falls back to
    // a gapless arc when the slice is too thin to inset.
    #slicePath(cx, cy, rOuter, rInner, a0, a1, gap) {
        const P = (ang, r) => [cx + r * Math.sin(ang), cy - r * Math.cos(ang)];
        const half = gap / 2;
        // Angular offset at radius r that yields a perpendicular gap of `half`.
        const phi = (r) => (r > half ? Math.asin(half / r) : Math.PI / 2);

        const oPhi = phi(rOuter);
        if (gap <= 0 || a1 - a0 <= 2 * oPhi) {
            return this.#arcPath(cx, cy, rOuter, rInner, a0, a1);
        }

        const oStart = a0 + oPhi;
        const oEnd = a1 - oPhi;
        const [ox0, oy0] = P(oStart, rOuter);
        const [ox1, oy1] = P(oEnd, rOuter);
        const outerLarge = oEnd - oStart > Math.PI ? 1 : 0;

        if (rInner > 0) {
            const iPhi = phi(rInner);
            const [ix1, iy1] = P(a1 - iPhi, rInner);
            const [ix0, iy0] = P(a0 + iPhi, rInner);
            const innerLarge = (a1 - iPhi) - (a0 + iPhi) > Math.PI ? 1 : 0;
            return `M ${ox0} ${oy0} A ${rOuter} ${rOuter} 0 ${outerLarge} 1 ${ox1} ${oy1} ` +
                `L ${ix1} ${iy1} A ${rInner} ${rInner} 0 ${innerLarge} 0 ${ix0} ${iy0} Z`;
        }

        // Pie: the two offset edges meet at a tip pulled slightly off-centre.
        const mid = rOuter * 0.5;
        const tip = this.#lineIntersection(
            P(oEnd, rOuter), P(a1 - phi(mid), mid),   // end edge
            P(oStart, rOuter), P(a0 + phi(mid), mid), // start edge
        ) || [cx, cy];
        return `M ${ox0} ${oy0} A ${rOuter} ${rOuter} 0 ${outerLarge} 1 ${ox1} ${oy1} ` +
            `L ${tip[0]} ${tip[1]} Z`;
    }

    #lineIntersection(p1, p2, p3, p4) {
        const [x1, y1] = p1, [x2, y2] = p2, [x3, y3] = p3, [x4, y4] = p4;
        const denom = (x1 - x2) * (y3 - y4) - (y1 - y2) * (x3 - x4);
        if (Math.abs(denom) < 1e-9) {
            return null;
        }
        const t = ((x1 - x3) * (y3 - y4) - (y1 - y3) * (x3 - x4)) / denom;
        return [x1 + t * (x2 - x1), y1 + t * (y2 - y1)];
    }

    // A single 100% slice can't be drawn as one arc — use full circles instead.
    #fullRingPath(cx, cy, rOuter, rInner) {
        const outer = this.#circlePath(cx, cy, rOuter, 1);
        if (rInner <= 0) {
            return this.#svgEl('path', {d: outer, 'fill-rule': 'evenodd'});
        }
        return this.#svgEl('path', {d: `${outer} ${this.#circlePath(cx, cy, rInner, 0)}`, 'fill-rule': 'evenodd'});
    }

    #circlePath(cx, cy, r, sweep) {
        return `M ${cx} ${cy - r} A ${r} ${r} 0 1 ${sweep} ${cx} ${cy + r} A ${r} ${r} 0 1 ${sweep} ${cx} ${cy - r} Z`;
    }

    #niceScale(min, max, ticks) {
        if (min === max) {
            max = min + 1;
        }
        const range = this.#niceNum(max - min, false);
        const step = this.#niceNum(range / Math.max(1, ticks - 1), true);
        return {
            min: Math.floor(min / step) * step,
            max: Math.ceil(max / step) * step,
            step,
        };
    }

    #niceNum(range, round) {
        const exponent = Math.floor(Math.log10(range || 1));
        const fraction = range / Math.pow(10, exponent);
        let niceFraction;
        if (round) {
            niceFraction = fraction < 1.5 ? 1 : fraction < 3 ? 2 : fraction < 7 ? 5 : 10;
        } else {
            niceFraction = fraction <= 1 ? 1 : fraction <= 2 ? 2 : fraction <= 5 ? 5 : 10;
        }
        return niceFraction * Math.pow(10, exponent);
    }

    #color(index) {
        const colors = this.#options.colors;
        return colors[index % colors.length];
    }

    #formatTick(value) {
        const abs = Math.abs(value);
        if (abs >= 1000000) return `${this.#round(value / 1000000)}M`;
        if (abs >= 1000) return `${this.#round(value / 1000)}k`;
        return this.#formatNumber(value);
    }

    #formatNumber(value, decimals = 2) {
        if (typeof value !== 'number' || !isFinite(value)) {
            return `${value}`;
        }
        return Number.isInteger(value) ? `${value}` : `${this.#round(value, decimals)}`;
    }

    #round(value, decimals = 4) {
        const factor = Math.pow(10, decimals);
        return Math.round(value * factor) / factor;
    }

    #longestLabelWidth() {
        // Rough estimate — ~6.5px per character at the tick font size.
        const longest = this.#data.labels.reduce((max, l) => Math.max(max, `${l}`.length), 0);
        return Math.min(140, longest * 6.5);
    }

    #toNumber(value) {
        const n = Number(value);
        return isFinite(n) ? n : 0;
    }

    #hasData() {
        return this.#data.labels.length > 0 &&
            this.#data.series.some((s) => s.values.length > 0);
    }

    #escape(value) {
        return `${value}`.replace(/[&<>"]/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
    }

    #clear() {
        while (this.#svg.firstChild) {
            this.#svg.removeChild(this.#svg.firstChild);
        }
    }
}
