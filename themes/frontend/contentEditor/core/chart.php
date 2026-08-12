<?php
$labels = !empty($block['labels']) && is_array($block['labels']) ? array_values($block['labels']) : [];
$series = [];
if(!empty($block['series']) && is_array($block['series'])) {
    foreach($block['series'] as $item) {
        if(!isset($item['values']) || !is_array($item['values'])) {
            continue;
        }
        $series[] = [
            'name' => (string)($item['name'] ?? ''),
            'values' => array_map('floatval', array_values($item['values'])),
        ];
    }
}
?>
<?php if(!empty($series)): ?>
    <?php
    $chartData = [
        'type' => (string)($block['chartType'] ?? ''),
        'labels' => array_map('strval', $labels),
        'series' => $series,
    ];
    ?>
    <div<?=$this->blockAttributes($block, 'chartBlock')?> data-chart="<?=htmlspecialchars(json_encode($chartData), ENT_QUOTES)?>"></div>
<?php endif;?>
