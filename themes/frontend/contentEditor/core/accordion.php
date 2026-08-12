<?php if(!empty($block['items']) && is_array($block['items'])): ?>
    <?php $settings = !empty($block['settings']) && is_array($block['settings']) ? $block['settings'] : []; ?>
    <div<?=$this->blockAttributes($block, 'accordionBlock')?> data-allow-multiple="<?=!isset($settings['allowMultiple']) || $settings['allowMultiple'] ? 'true' : 'false'?>">
        <?php foreach($block['items'] as $index => $item): ?>
            <?php $open = !empty($item['open']) || ($index === 0 && !empty($settings['firstItemOpen'])); ?>
            <div class="faqSection">
                <div class="faqQuestion">
                    <h3><?=$item['summary'] ?? ''?></h3>
                    <svg class="<?=$open ? 'active' : ''?>" width="24" height="25" viewBox="0 0 24 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M13.8462 1.92308C13.8462 0.859375 13.0212 0 12 0C10.9788 0 10.1538 0.859375 10.1538 1.92308V10.5769H1.84615C0.825 10.5769 0 11.4363 0 12.5C0 13.5637 0.825 14.4231 1.84615 14.4231H10.1538V23.0769C10.1538 24.1406 10.9788 25 12 25C13.0212 25 13.8462 24.1406 13.8462 23.0769V14.4231H22.1538C23.175 14.4231 24 13.5637 24 12.5C24 11.4363 23.175 10.5769 22.1538 10.5769H13.8462V1.92308Z" fill="#FE5101"></path>
                    </svg>
                </div>
                <div class="faqAnswer<?=$open ? ' active' : ''?>">
                    <div class="accordionContent"><?=$item['content'] ?? ''?></div>
                </div>
            </div>
        <?php endforeach;?>
    </div>
<?php endif;?>
