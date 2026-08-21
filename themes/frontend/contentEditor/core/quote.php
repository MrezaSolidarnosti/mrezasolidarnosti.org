<?php if(!empty($block['html'])): ?>
    <figure<?=$this->blockAttributes($block, 'quoteBlock')?>>
        <blockquote class="quoteText"><?=$block['html']?></blockquote>
        <?php if(!empty($block['cite'])): ?>
            <cite class="quoteCite"><?=htmlspecialchars($block['cite'])?></cite>
        <?php endif;?>
    </figure>
<?php endif;?>
