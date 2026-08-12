<?php if(!empty(trim($block['value'] ?? ''))): ?>
    <div<?=$this->blockAttributes($block, 'htmlBlock')?>><?=$block['value']?></div>
<?php endif;?>
