import Config from "../../../../vendor/skeletorjs/src/Config/Config.js";

export const config = Object.freeze({
    contentEditor: {
        appBlocksPath: Config.get('appBlocksPath'),
        blocks: [
            // 'core/accordion',
            // 'core/banner',
            // 'core/divider',
            // 'core/embed',
            // 'core/gallery',
            // 'core/heading',
            // 'core/hero',
            // 'core/html',
            // 'core/image',
            // 'core/listpoints',
            // 'core/navigationtabs',
            // 'core/pricing',
            // 'core/testimonials',
            'core/text',
            // 'core/quote',
            // 'core/slider',
            // 'core/tabs',
            // 'core/table',
            'app/herostats',
            'app/find',
            'app/direction',
            'app/connect',
            'app/whywearedifferent',
            'app/howitworks',
            'app/testimonials',
            'app/faq',
            'app/herotext',
            'app/contactcards',
            'app/sidebyside',
            'app/projectsdisplay',
            'app/threepillars',
            'app/banner',
            'app/whotocall',
            'app/ctabanner',
            'app/about',
            'app/valuecards',
            'app/howitworkstimeline',
            'app/registerform',
            'app/registerconfirmemail',
            'app/registersuccessbox',
            'app/login',
            'app/loginsuccess',
            'app/profiledata',
            'app/donate',
            'app/instructionsintro',
            'app/instructionstable',
            'app/blog'
        ],
    },
    page: {
        // pass the JS page name, for example User, Page, etc.
        // Page: {
        //     contentEditor: {
        //         lockBlockActions: ['delete', 'moveUp', 'moveDown', 'viewControl'],
        //         blackList: [
        //             'core/heading',
        //             'core/embed',
        //         ],
        //         lockedBlocks: [
        //             {
        //                 block: 'core/image',
        //                 data: {
        //                     imageId: 12345,
        //                     filename: 'https://placehold.co/558x832'
        //                 }
        //             },
        //             {
        //                 block: 'core/image',
        //             },
        //         ],
        //
        //     }
        // }
    }

});