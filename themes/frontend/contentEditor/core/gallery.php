<?php if(!empty($block['images']) && is_array($block['images'])): ?>
    <?php $options = !empty($block['options']) && is_array($block['options']) ? $block['options'] : []; ?>
    <div<?=$this->blockAttributes($block, 'galleryBlock')?> data-gallery-options="<?=htmlspecialchars(json_encode($options), ENT_QUOTES)?>">
        <?php foreach($block['images'] as $image): ?>
            <?php if(empty($image['src'])) { continue; } ?>
            <?php
            $src = '/images' . $image['src'];
            $alt = trim((string)($image['alt'] ?? ''));
            $author = trim((string)($image['author'] ?? ''));
            $caption = trim((string)($image['label'] ?? ''));
            if($author !== '') {
                $caption = $caption !== '' ? $caption . ' — ' . $author : $author;
            }
            ?>
            <a class="galleryItem" href="<?=htmlspecialchars($src)?>" data-id="<?=htmlspecialchars((string)($image['mediaId'] ?? ''))?>" data-caption="<?=htmlspecialchars($caption)?>">
                <img class="galleryImage" src="<?=htmlspecialchars($src)?>" alt="<?=htmlspecialchars($alt)?>">
            </a>
        <?php endforeach;?>
    </div>
<?php endif;?>
