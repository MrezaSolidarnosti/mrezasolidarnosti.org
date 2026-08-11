# Chart Class

A dependency-free, SVG-based mini charting library. Renders into a target element and
scales responsively (via `ResizeObserver`). Colors, grid, axes and tooltips all use the
project's CSS variables, so charts follow the active theme automatically.

Supported types: `bar`, `horizontalBar`, `line`, `area`, `pie`, `donut`.

## Usage

```javascript
import Chart from "../src/Chart/Chart.js";

const chart = new Chart({
    target: document.getElementById('main'),
    type: 'bar',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr'],
        series: [
            {name: 'Revenue', values: [120, 200, 150, 280]},
            {name: 'Cost', values: [80, 130, 100, 160]}
        ]
    },
    options: {title: 'Q1 Overview', stacked: false}
});

chart.init();
```

For a single-series or pie/donut chart you can pass the shorthand data shape:

```javascript
new Chart({
    target: el,
    type: 'donut',
    data: [
        {label: 'Direct', value: 40},
        {label: 'Referral', value: 25},
        {label: 'Social', value: 35}
    ]
}).init();
```

## Data

| Shape | Description |
| --- | --- |
| `{ labels: string[], series: [{ name, values: number[] }] }` | Full shape. `values` align to `labels` by index; multiple series are grouped (or stacked). |
| `[{ label, value }]` | Shorthand — becomes a single unnamed series. Ideal for pie/donut. |

## Options

| Option | Default | Description |
| --- | --- | --- |
| `title` | `''` | Optional heading rendered above the chart. |
| `height` | `320` | Chart height in px (width fills the target). |
| `colors` | theme palette | Array of colors (any CSS color, incl. `var(--...)`). |
| `stacked` | `false` | Stack series for `bar` / `horizontalBar`. |
| `showLegend` | `true` | Show the legend (auto-hidden for single unnamed series). |
| `legendToggle` | `true` | Click a legend item to hide/show that series (bar/line/area) or slice (pie/donut); the chart rescales to what's left. |
| `showGrid` | `true` | Show value-axis gridlines. |
| `showValues` | `false` | Value labels on marks. Bars (all variants) reveal theirs on hover/tap; line/area points and pie/donut % are always shown. |
| `showTooltip` | `true` | Hover + tap tooltips. |
| `animate` | `true` | Entry animation (first render / `update()` only, not on resize). |
| `yTicks` | `5` | Target number of value-axis ticks. |
| `donutRatio` | `0.6` | Inner/outer radius ratio for `donut`. |
| `sliceGap` | `2` | Constant-width gap (px) between pie/donut slices. |
| `padding` | `{top:16,right:18,bottom:34,left:46}` | Plot-area padding (merged). |
| `formatValue` | number formatter | `(value) => string` for tooltips/value labels. |

## Methods

`init()` - Build and render the chart into `target`. Returns the instance.

`update(data, type)` - Re-render with new `data` and/or a new `type`. Both args are optional.

`setOptions(options)` - Merge in new options and re-render.

`getContainer()` - The root `.chart` element.

`destroy()` - Disconnect the resize observer, remove the element, and null references.

## Notes

- Styles live in `css/_chart.scss` (`@use`'d by `css/style.scss`). Recompile the SCSS
  after changing them.
- All class names live in `src/Chart/chartAssets.js` (`classes`, `attributes`,
  `palette`, SVG `ns`) — nothing is hardcoded in `Chart.js`.
- The DOM is the source of truth; `update()` re-renders from the supplied data.
