<?php if(!empty($block['items']) && is_array($block['items'])): ?>
    <?php
    $items = array_values($block['items']);
    $activeIndex = 0;
    foreach($items as $index => $item) {
        if(!empty($item['active'])) {
            $activeIndex = $index;
            break;
        }
    }
    ?>
    <div<?=$this->blockAttributes($block, 'tabsBlock')?>>
        <div class="tabs">
            <?php foreach($items as $index => $item): ?>
                <div class="tab<?=$index === $activeIndex ? ' active' : ''?>" data-tab="<?=$index?>"><?=$item['label'] ?? ''?></div>
            <?php endforeach;?>
        </div>
        <?php foreach($items as $index => $item): ?>
            <div class="tabContent<?=$index === $activeIndex ? '' : ' hidden'?>" data-tab="<?=$index?>"><?=$item['content'] ?? ''?></div>
        <?php endforeach;?>
    </div>
<?php endif;?>
