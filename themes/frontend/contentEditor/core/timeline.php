<?php if(!empty($block['items']) && is_array($block['items'])): ?>
    <div<?=$this->blockAttributes($block, 'timelineBlock')?>>
        <div class="timelineItems">
            <?php foreach($block['items'] as $item): ?>
                <div class="timelineItem">
                    <?php if(!empty($item['time'])): ?>
                        <div class="timelineItemTime"><?=$item['time']?></div>
                    <?php endif;?>
                    <div class="timelineItemContent"><?=$item['content'] ?? ''?></div>
                </div>
            <?php endforeach;?>
        </div>
    </div>
<?php endif;?>
