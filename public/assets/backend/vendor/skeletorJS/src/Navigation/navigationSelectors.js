export const navigationSelectors = Object.freeze({
    ids: {
        navigation: 'navigation',
        itemsContainer: 'items',
        toggleButton: 'toggleNavigation',
        searchButton: 'navigationSearch',
        searchInput: 'navigationSearchInput',
        navigationUserButton: 'navigationUser',
        noResults: 'noResultsSearchNavigation',
        settingsItem: 'navigationSettings',
        modeToggleInput: 'modeToggleInput',
        hamburger: 'hamburger'
    },
    classes: {
        active: 'active',
        item: 'item',
        itemAnchor: 'itemAnchor',
        tooltip: 'tooltip',
        hidden: 'hidden',
        subItem: 'subItem',
        arrow: 'subItemsIndicator',
    },
    attributes: {
        href: 'data-href',
        customBehavior: 'data-custom-behavior'
    }
});