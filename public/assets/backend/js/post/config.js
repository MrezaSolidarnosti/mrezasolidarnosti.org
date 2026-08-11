export const config = Object.freeze({
    contentEditor: {
        // Relative to ModuleLoader.js (src/ContentEditor/Content/ModuleLoader.js).
        imagePath: '/images',
        appBlocksPath: '../../../../../js/post/Blocks/',
        // width: '80%', // 60%  by default
        // Optional modules this editor uses. Drop one (e.g. remove 'categories') and its
        // handler is never built and its [data-module] section is removed from the DOM.
        // A "pages" editor might be: ['title', 'slug', 'seo'].
        // readOnly: true, // defaults to false
        modules: ['title', 'slug', 'seo', 'featuredImage', 'status'],
        // unsavedGuard: false, // true by default
        // Per-person editor preferences (font size, content width), kept in localStorage under
        // this key. Give the posts and pages editors the *same* key and a writer sets their
        // preferences once for both; give them different keys and each remembers separately.
        userSettings: {
            key: 'solidarity.post.userSettings',
        },
        // commandTrigger: ';;', //  "//" by default
        // Ctrl/Cmd+K palette. Opt-in: it exists only because this key is present. `enabled` can
        // also be flipped at runtime. The search endpoint can go here or be set in index.js.
        commandPalette: {
            enabled: true,// default is false
            //     search: async (query) => { // just an example of a backend api call, the important thing is to return an array of objects with category, label, url and an optional subtitle
            //         const results = await fetch(`/api/search?q=${encodeURIComponent(query)}`)
            //             .then((response) => response.json());
            //         return results.map((result) => ({
            //             category: result.type,      // 'Posts', 'Pages', 'Authors',
            //             label: result.title,
            //             subtitle: result.subtitle,  // optional muted second line
            //             url: result.editUrl,        // Enter navigates; Ctrl/Cmd+Enter → new tab
            //         }));
            //     },
        },
        // Starting values per block type, so blocks begin at this project's house style
        // instead of the library's. Merged UNDER the block's own data, so anything a payload
        // already carries wins — a new block takes these whole, a duplicate keeps what it was
        // copied from, and a saved block only picks up keys it never had.
        // blockDefaults: {
        //     'core/image': {
        //         // Every image starts centred. `align` is a top-level field rather than part of
        //         // the image's own data — it rides the same data-align attribute the format
        //         // toolbar uses — and renderBlock applies it from the merged payload, so a
        //         // default reaches it exactly like a saved value would.
        //         align: 'left',
        //         // A default link makes no sense, but it is settable: {href, newTab, rel}.
        //     },
        //     'core/divider': {height: 4, color: '#b30000'},
        //     'core/accordion': {settings: {allowMultiple: false, firstItemOpen: true}},
        //     // Shallow merge: a saved `options` replaces this outright rather than combining
        //     // with it, so these apply to galleries that have never been configured.
        //     'core/gallery': {options: {layout: 'masonry', gap: 16, captions: false}},
        // },
        blocks: [
            'core/paragraph',
            'core/heading',
            'core/headingtwo',
            'core/headingthree',
            'core/headingfour',
            'core/headingfive',
            'core/headingsix',
            'core/unorderedList',
            'core/orderedList',
            'core/quote',
            'core/html',
            'core/image',
            'core/gallery',
            'core/divider',
            'core/embed',
            'core/spacer',
            'core/columns',
            'core/file',
            'core/table',
            'core/chart',
            'core/footnotes',
            'core/accordion',
            'core/tabs',
            'core/timeline',
        ],
    }
});