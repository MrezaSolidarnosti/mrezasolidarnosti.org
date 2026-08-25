/**
 * Shared scoping for the block registration APIs — `Block.registerSidebarControl()` and
 * `BlockSideToggle.registerAction()`. Both let a project say *which* blocks a thing applies to,
 * and both answer that the same way:
 *
 *   blocks:        ['core/image', 'core/table']   // only these (a whitelist)
 *   excludeBlocks: ['core/table']                 // all except these (a blacklist)
 *
 * Give neither and it applies everywhere. Give both and the whitelist is applied first, then
 * the blacklist removes from it — so `excludeBlocks` always wins for a type named in both.
 *
 * Two kinds of block are excluded by default, each with its own opt-in — they're different
 * situations and shouldn't be governed by one flag:
 *
 *   showOnHidden   hidden/system blocks (footnotes) — auto-managed, but real content
 *   showOnUnknown  the unknown-block placeholder — inert preserved content that this editor
 *                  can't interpret, so most actions and controls are meaningless on it
 *
 * The unknown placeholder is *also* `hidden`, so it gets its own branch: `showOnUnknown` alone
 * is enough to opt in, without having to set `showOnHidden` as well.
 */
export function appliesToBlock(definition, blockClass) {
    if (!definition || !blockClass) {
        return false;
    }
    if (blockClass.isUnknown) {
        if (!definition.showOnUnknown) {
            return false;
        }
    } else if (blockClass.hidden && !definition.showOnHidden) {
        return false;
    }
    if (Array.isArray(definition.blocks) && !definition.blocks.includes(blockClass.name)) {
        return false;
    }
    if (Array.isArray(definition.excludeBlocks) && definition.excludeBlocks.includes(blockClass.name)) {
        return false;
    }
    return true;
}
