<?php
$headers = !empty($block['headers']) && is_array($block['headers']) ? array_values($block['headers']) : [];
$rows = !empty($block['rows']) && is_array($block['rows']) ? array_values($block['rows']) : [];
$settings = !empty($block['settings']) && is_array($block['settings']) ? $block['settings'] : [];

$styleVariables = [
    'headerBackground' => '--table-header-bg',
    'headerColor' => '--table-header-color',
    'oddRowBackground' => '--table-odd-bg',
    'oddRowColor' => '--table-odd-color',
    'evenRowBackground' => '--table-even-bg',
    'evenRowColor' => '--table-even-color',
];
$style = '';
foreach($styleVariables as $key => $variable) {
    $value = trim((string)($settings[$key] ?? ''));
    if(preg_match('/^#[0-9a-f]{3,8}$/i', $value)) {
        $style .= $variable . ':' . $value . ';';
    }
}
if($style !== '') {
    $block['additionalData']['inlineCss'] = trim($style . ($block['additionalData']['inlineCss'] ?? ''), '; ');
}

$enableSort = !empty($settings['enableSort']);
$enableSearch = !empty($settings['enableSearch']);
$filterColumns = [];
if(!empty($settings['enableFilters']) && !empty($settings['filterColumns']) && is_array($settings['filterColumns'])) {
    foreach($settings['filterColumns'] as $columnIndex) {
        $columnIndex = (int)$columnIndex;
        if(isset($headers[$columnIndex])) {
            $filterColumns[] = $columnIndex;
        }
    }
}
?>
<?php if(!empty($headers) || !empty($rows)): ?>
    <div<?=$this->blockAttributes($block, 'tableBlock')?>>
        <?php if($enableSearch || !empty($filterColumns)): ?>
            <div class="tableToolbar">
                <?php if($enableSearch): ?>
                    <input type="search" class="tableSearch" placeholder="<?=$this->t('Pretraga')?>" aria-label="<?=$this->t('Pretraga')?>">
                <?php endif;?>
                <?php foreach($filterColumns as $columnIndex): ?>
                    <?php
                    $values = [];
                    foreach($rows as $row) {
                        $value = trim((string)($row[$columnIndex] ?? ''));
                        if($value !== '') {
                            $values[$value] = true;
                        }
                    }
                    $values = array_keys($values);
                    natcasesort($values);
                    ?>
                    <select class="tableFilter" data-column="<?=$columnIndex?>" aria-label="<?=htmlspecialchars($headers[$columnIndex])?>">
                        <option value=""><?=htmlspecialchars($headers[$columnIndex])?></option>
                        <?php foreach($values as $value): ?>
                            <option value="<?=htmlspecialchars($value)?>"><?=htmlspecialchars($value)?></option>
                        <?php endforeach;?>
                    </select>
                <?php endforeach;?>
            </div>
        <?php endif;?>
        <div class="tableScroll">
            <table>
                <?php if(!empty($headers)): ?>
                    <thead>
                        <tr>
                            <?php foreach($headers as $columnIndex => $header): ?>
                                <?php if($enableSort): ?>
                                    <th class="sortable" data-column="<?=$columnIndex?>" tabindex="0" role="button" aria-sort="none"><?=htmlspecialchars($header)?></th>
                                <?php else: ?>
                                    <th><?=htmlspecialchars($header)?></th>
                                <?php endif;?>
                            <?php endforeach;?>
                        </tr>
                    </thead>
                <?php endif;?>
                <tbody>
                    <?php foreach($rows as $row): ?>
                        <tr>
                            <?php foreach((array)$row as $columnIndex => $cell): ?>
                                <td data-label="<?=htmlspecialchars($headers[$columnIndex] ?? '')?>"><?=htmlspecialchars((string)$cell)?></td>
                            <?php endforeach;?>
                        </tr>
                    <?php endforeach;?>
                </tbody>
            </table>
        </div>
        <p class="tableNoResults" hidden><?=$this->t('Nema rezultata')?></p>
    </div>
<?php endif;?>
