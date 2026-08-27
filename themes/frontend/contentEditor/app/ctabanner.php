<?php if(isset($block)): ?>

    <div class="bannerWrapper">
        <div class="banner">
            <div class="left">
                <h2><?=htmlentities($block['title'] ?? '')?></h2>
                <p><?=nl2br(htmlentities($block['description'] ?? ''))?></p>
            </div>
            <?php if(!empty($block['buttons'])): ?>
                <div class="buttons">
                    <?php foreach($block['buttons'] as $button): ?>
                        <a href="<?=htmlentities($button['buttonUrl'] ?? '')?>"
                           title="<?=htmlentities($button['buttonTitle'] ?? '')?>"
                           class="<?=htmlentities($button['type'] ?: 'primary')?>"><?=htmlentities($button['buttonTitle'] ?? '')?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
