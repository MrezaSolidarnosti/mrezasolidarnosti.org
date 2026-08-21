<?php if(!empty($block['embedSrc'])): ?>
    <div<?=$this->blockAttributes($block, 'embedBlock', !empty($block['provider']) ? 'embed' . ucfirst($block['provider']) : '')?>>
        <iframe src="<?=htmlspecialchars($block['embedSrc'])?>"
                loading="lazy"
                allowfullscreen
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"></iframe>
    </div>
<?php endif;?>
