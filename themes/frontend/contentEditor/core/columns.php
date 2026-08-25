<?php
$columns = !empty($block['columns']) && is_array($block['columns']) ? array_values($block['columns']) : [];
// Same ratios the editor's layout picker uses (ContentEditor/Blocks/Columns/Columns.js LAYOUTS).
$layouts = [
    '50-50' => [1, 1],
    '33-66' => [1, 2],
    '66-33' => [2, 1],
    '33-33-33' => [1, 1, 1],
    '25-50-25' => [1, 2, 1],
    '25-25-25-25' => [1, 1, 1, 1],
];
$ratios = $layouts[$block['layout'] ?? ''] ?? [];
?>
<?php if(!empty($columns)): ?>
    <?php
    $template = [];
    foreach($columns as $index => $column) {
        $template[] = ($ratios[$index] ?? 1) . 'fr';
    }
    $block['additionalData']['inlineCss'] = trim(
        '--columnsTemplate:' . implode(' ', $template) . ';' . ($block['additionalData']['inlineCss'] ?? ''),
        '; '
    );
    ?>
    <div<?=$this->blockAttributes($block, 'columnsBlock')?>>
        <?php foreach($columns as $column): ?>
            <div class="column"><?=$this->contentEditorBlocks(is_array($column) ? $column : [])?></div>
        <?php endforeach;?>
    </div>
<?php endif;?>
