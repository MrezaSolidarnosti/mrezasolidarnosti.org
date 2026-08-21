# DataTable with static data


```javascript
const staticDT = new StaticDataTable({
    headers: [
        {label: 'ID', sortable: true},
        {label: 'Product'},
        {label: 'Price', sortable: true},
        {label: 'Status', filterable: true}
    ],
    data: [
        [1, 'Product 1', 100, 'Active'],
        [2, 'Product 2', 200, 'Inactive'],
        [3, 'Product 3', 300, 'Active'],
        [4, 'Product 4', 400, 'Inactive'],
        [5, 'Product 5', 500, 'Active'],
        [6, 'Product 6', 600, 'Active'],
        [7, 'Product 7', 700, 'Active'],
        [8, 'Product 8', 800, 'Inactive'],
        [9, 'Product 9', 900, 'Active'],
        [10, 'Product 10', 1000, 'Active'],
        [11, 'Product 11', 1100, 'Active'],
        [12, 'Product 12', 1200, 'Active'],
        [13, 'Product 13', 1300, 'Active'],
        [14, 'Product 14', 1400, 'Active'],
        [15, 'Product 15', 1500, 'Active'],
        [16, 'Product 16', 1600, 'Active'],
    ],
    target: document.getElementById('main')
});

staticDT.init();
```