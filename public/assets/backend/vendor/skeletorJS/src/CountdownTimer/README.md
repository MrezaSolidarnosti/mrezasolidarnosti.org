# CountdownTimer Class

A dependency-free circular countdown timer. Renders one SVG ring per unit (days, hours,
minutes, seconds), each depleting as time runs out, with the value in the middle. Colors
come from the project's CSS variables, so it follows the active theme.

## Usage

```javascript
import CountdownTimer from "../src/CountdownTimer/CountdownTimer.js";

const timer = new CountdownTimer({
    target: document.getElementById('main'),
    until: '2026-12-31T23:59:59',
    options: {
        units: ['days', 'hours', 'minutes', 'seconds'],
        onComplete: () => console.log('Done!')
    }
});

timer.init();
```

`until` accepts an **ISO string** (preferred), a `Date`, or a timestamp in ms.

Show only the units you need — the largest one absorbs everything above it, so nothing is
lost. A timer 2 days out rendered as `['minutes', 'seconds']` shows `2880` minutes, not `0`:

```javascript
new CountdownTimer({
    target: el,
    until: Date.now() + 90_000,
    options: {units: ['minutes', 'seconds'], size: 70}
}).init();
```

## Options

| Option | Default | Description |
| --- | --- | --- |
| `units` | all four | Which units to render: `'days'`, `'hours'`, `'minutes'`, `'seconds'`. Always drawn largest → smallest, whatever order you pass. |
| `size` | `90` | Diameter of each ring in px. |
| `strokeWidth` | `6` | Ring thickness in px. |
| `labels` | `{days:'Days', …}` | Override the label text (merged). |
| `showLabels` | `true` | Show the label under each ring. |
| `pad` | `true` | Zero-pad values to 2 digits (the largest unit is never padded). |
| `max` | `null` | Full-ring value for the **largest** unit. Defaults to its value when the timer starts, so the ring begins full. |
| `onTick` | — | `({ total, days, hours, minutes, seconds }) => {}`, called every second. |
| `onComplete` | — | `() => {}`, called once when it reaches zero. |

Each ring fills against its natural cycle (seconds/60, minutes/60, hours/24). The largest
unit has no natural cycle, so it uses `max`.

## Methods

`init()` - Build and render into `target`, then start ticking. Returns the instance.

`start()` - Start (or resume) ticking. No-op if already running or finished.

`stop()` - Pause ticking. The display keeps its current values.

`setUntil(until)` - Point at a new target time, reset the completed state, and restart.

`getRemaining()` - `{ total, days, hours, minutes, seconds }` — `total` in ms, and the
**natural** breakdown (`hours` 0–23, `minutes`/`seconds` 0–59) regardless of which units are
rendered.

`getContainer()` - The root `.countdownTimer` element.

`destroy()` - Stop the timer, remove the element, and release references.

## Notes

- Styles live in `css/_countdownTimer.scss` (`@use`'d by `css/style.scss`). Recompile the
  SCSS after changing them.
- All class names live in `src/CountdownTimer/countdownTimerAssets.js` (`classes`,
  `attributes`, `units`, `cycles`, `labels`, SVG `ns`) — nothing is hardcoded in the class.
- Every tick recomputes from the target time rather than decrementing, so a throttled or
  backgrounded tab self-corrects instead of drifting.
- When it hits zero the root gets a `countdownComplete` class (rings and values dim), the
  interval stops, and `onComplete` fires once. A timer created with a past `until` renders
  zeros and completes immediately.
