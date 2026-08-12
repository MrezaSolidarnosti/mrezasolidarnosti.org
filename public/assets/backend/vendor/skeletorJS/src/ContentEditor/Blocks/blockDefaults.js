/**
 * Applies a project's starting values for a block type, from `config.blockDefaults`.
 *
 * The defaults go *under* the block's own data, so anything the payload already carries wins.
 * That one decision covers every case without having to tell a new block from a loaded one:
 *
 *   - a fresh insert carries nothing, so it takes the defaults whole;
 *   - a duplicated or pasted block keeps the values it was copied from;
 *   - a saved block only picks up keys it never had.
 *
 * That last case is deliberate. A divider saved before anyone chose a height should follow the
 * project's default rather than the library's, and changing the default should reach it. The
 * flip side is that it *does* reach existing content — which is the point of a default, but
 * worth knowing before setting one.
 *
 * Shallow on purpose: a deep merge would have to decide what to do with arrays, and "combine
 * the default images with the saved ones" is never what anyone means.
 *
 * A key the block doesn't know is harmless — no block spreads its input into `getData()`, so a
 * stray one is ignored and never saved. `id` is the exception, and it is dropped: see below.
 *
 * @param {object|undefined} blockDefaults  the whole `config.blockDefaults` map.
 * @param {string} name  the block type being rendered, e.g. 'core/divider'.
 * @param {object} data  the block's payload.
 * @returns {object} the payload with defaults filled in behind it.
 */
export function withBlockDefaults(blockDefaults, name, data) {
    const defaults = blockDefaults && blockDefaults[name];
    if (!defaults || typeof defaults !== 'object') {
        return data;
    }
    return {...stripIdentity(defaults, name), ...(data || {})};
}

/**
 * Removes `id` from a defaults entry.
 *
 * renderBlock reads `data.id` and only mints a new one when it is absent, so defaulting it would
 * give every block of that type the same identity — and `Blocks.blocks` is a Map keyed by id, so
 * the second one would displace the first. Ids are also what revisions match on and what footnote
 * reminting depends on, which makes the damage quiet and awkward to trace back.
 *
 * Only `id`. The other keys renderBlock reads off the payload are either deliberate (`align`) or
 * legitimately useful to default (`html`, for "new paragraphs start with this"), so a blanket ban
 * on structural keys would remove capability to prevent one mistake.
 */
function stripIdentity(defaults, name) {
    if (!('id' in defaults)) {
        return defaults;
    }
    // Once per block type, not once per render: this runs for every block created, so a config
    // left uncorrected would otherwise bury the console under one line per divider on the page.
    if (!warnedAbout.has(name)) {
        warnedAbout.add(name);
        console.warn(
            `ContentEditor: blockDefaults for "${name}" sets \`id\`, which is ignored — every `
            + 'block needs its own. Remove it from the config.'
        );
    }
    const {id, ...rest} = defaults;
    return rest;
}

const warnedAbout = new Set();
