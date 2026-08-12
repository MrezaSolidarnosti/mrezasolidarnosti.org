import Gallery from "/assets/backend/vendor/skeletorJS/src/Gallery/Gallery.js";
import Faq from "./faq/faq.js";
import TabbedContent from "/assets/backend/vendor/skeletorJS/src/TabbedContent/TabbedContent.js";
import Chart from "/assets/backend/vendor/skeletorJS/src/Chart/Chart.js";

// The block saves the type the editor's picker uses; the Chart component only knows its own
// types plus the stacked flag, so the picker keys map onto those here (same table as
// ContentEditor/Blocks/Chart/Chart.js CHART_TYPES).
// The component's default palette is the admin theme's (colorPrimary / colorSuccess / ...),
// which this project doesn't define - these are the site's own colours, most distinct first.
const CHART_COLORS = [
    'var(--colorDarkBlue)',
    'var(--colorOrange)',
    'var(--colorGrassGreen)',
    'var(--colorYellow)',
    'var(--colorPurple)',
    'var(--colorBlue)',
    'var(--colorLightGreen)',
    'var(--colorPink)',
];

const CHART_TYPES = {
    area: {type: 'area', stacked: false},
    multiline: {type: 'line', stacked: false},
    line: {type: 'line', stacked: false},
    groupedbar: {type: 'bar', stacked: false},
    stackedbar: {type: 'bar', stacked: true},
    bar: {type: 'bar', stacked: false},
    donut: {type: 'donut', stacked: false},
    pie: {type: 'pie', stacked: false},
};

document.addEventListener('DOMContentLoaded', () => {
    //Footnotes
    const footnotesInContent = document.querySelectorAll('.footnoteRef');
    const footnotesBacklinks = document.querySelectorAll('.footnotesBacklink');

    footnotesInContent.forEach((elem) => {
        elem.addEventListener('click', () => {
            const target = document.querySelector(`.footnotesBacklink[data-footnote-id="${elem.getAttribute('data-footnote-id')}"]`);
            if(target) {
                scrollToTargetWithOffset(target, 120);
            }
        });
    });

    footnotesBacklinks.forEach((elem) => {
        elem.addEventListener('click', () => {
            const target = document.querySelector(`.footnotesBacklink[data-footnote-id="${elem.getAttribute('data-footnote-id')}"]`);
            if(target) {
                scrollToTargetWithOffset(target, 120);
            }
        });
    });

    // Gallery
    document.querySelectorAll('.galleryBlock').forEach((container) => {
        let options = {};
        if(container.dataset.galleryOptions) {
            try {
                options = JSON.parse(container.dataset.galleryOptions);
            } catch (e) {
                options = {};
            }
        }
        new Gallery({container, options}).init();
    });

    // Accordion - the block renders the same markup the FAQ uses, so the Faq component drives it.
    document.querySelectorAll('.accordionBlock').forEach((container) => {
        if(container.dataset.allowMultiple === 'false') {
            // Faq's own click handler calls stopImmediatePropagation(), so a listener registered
            // after init() would never run - this has to be attached first to see the click.
            container.querySelectorAll('.faqQuestion').forEach((question) => {
                question.addEventListener('click', () => {
                    closeOtherSections(container, question.closest('.faqSection'));
                });
            });
        }
        new Faq(container).init();
    });

    // Tabs
    document.querySelectorAll('.tabsBlock').forEach((container) => {
        new TabbedContent(container).init();
    });

    // Charts
    document.querySelectorAll('.chartBlock').forEach((container) => {
        let config = null;
        try {
            config = JSON.parse(container.dataset.chart);
        } catch (e) {
            return;
        }
        if(!config || !Array.isArray(config.series) || !config.series.length) {
            return;
        }
        const chartType = CHART_TYPES[config.type] ?? CHART_TYPES.bar;
        new Chart({
            target: container,
            type: chartType.type,
            data: {labels: config.labels ?? [], series: config.series},
            options: {stacked: chartType.stacked, colors: CHART_COLORS},
        }).init();
    });

    // Tables - the rows are server rendered, this only adds search / filter / sort on top.
    document.querySelectorAll('.tableBlock').forEach((container) => {
        const table = container.querySelector('table');
        const body = container.querySelector('tbody');
        if(!table || !body) {
            return;
        }
        const rows = [...body.querySelectorAll('tr')];
        const search = container.querySelector('.tableSearch');
        const filters = [...container.querySelectorAll('.tableFilter')];
        const noResults = container.querySelector('.tableNoResults');
        const sortableHeaders = [...container.querySelectorAll('th.sortable')];
        // numeric:true so "10" sorts after "9" instead of alphabetically before it.
        const collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
        let sortColumn = null;
        let sortDirection = 1;

        const cellText = (row, column) => (row.children[column]?.textContent ?? '').trim();

        const applyFilters = () => {
            const term = (search?.value ?? '').trim().toLowerCase();
            const activeFilters = filters.filter((filter) => filter.value !== '');
            let visible = 0;
            rows.forEach((row) => {
                const matchesSearch = term === '' || row.textContent.toLowerCase().includes(term);
                const matchesFilters = activeFilters.every(
                    (filter) => cellText(row, Number(filter.dataset.column)) === filter.value
                );
                row.hidden = !(matchesSearch && matchesFilters);
                if(!row.hidden) {
                    visible++;
                }
            });
            if(noResults) {
                noResults.hidden = visible !== 0;
            }
        };

        const sortByColumn = (header) => {
            const column = Number(header.dataset.column);
            sortDirection = sortColumn === column ? -sortDirection : 1;
            sortColumn = column;
            sortableHeaders.forEach((other) => other.setAttribute('aria-sort', 'none'));
            header.setAttribute('aria-sort', sortDirection === 1 ? 'ascending' : 'descending');
            [...rows]
                .sort((a, b) => sortDirection * collator.compare(cellText(a, column), cellText(b, column)))
                .forEach((row) => body.appendChild(row));
        };

        search?.addEventListener('input', applyFilters);
        filters.forEach((filter) => filter.addEventListener('change', applyFilters));
        sortableHeaders.forEach((header) => {
            header.addEventListener('click', () => sortByColumn(header));
            header.addEventListener('keydown', (e) => {
                if(e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    sortByColumn(header);
                }
            });
        });
    });

    function closeOtherSections(container, current) {
        container.querySelectorAll('.faqSection').forEach((section) => {
            if(section === current) {
                return;
            }
            section.querySelector('.faqAnswer')?.classList.remove('active');
            section.querySelector('.faqQuestion svg')?.classList.remove('active');
        });
    }

    function scrollToTargetWithOffset(element, offset) {
        if (!element) return;
        const elementPosition = element.getBoundingClientRect().top;
        const offsetPosition = elementPosition + window.scrollY - offset;

        window.scrollTo({
            top: offsetPosition,
        });
    }
});
