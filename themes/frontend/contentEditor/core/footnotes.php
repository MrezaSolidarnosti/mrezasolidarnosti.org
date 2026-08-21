<?php if(!empty($block['items']) && is_array($block['items'])): ?>
    <div<?=$this->blockAttributes($block)?>>
        <ul class="footnotesList">
            <?php $number = 0; ?>
            <?php foreach($block['items'] as $item): ?>
                <?php $number++; ?>
                <?php $footnoteId = (string)($item['id'] ?? ''); ?>
                <li class="footnotesItem" id="footnote-<?=htmlspecialchars($footnoteId)?>" data-footnote-id="<?=htmlspecialchars($footnoteId)?>">
                    <span class="footnotesBacklink" data-footnote-id="<?=htmlspecialchars($footnoteId)?>"><?=$number?>.</span>
                    <div class="footnotesContent"><?=$item['html'] ?? ''?></div>
                </li>
            <?php endforeach;?>
        </ul>
    </div>
<?php endif;?>
