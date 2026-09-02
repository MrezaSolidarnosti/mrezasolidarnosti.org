<?php if(isset($block)): ?>

    <div class="textHero">
        <?php if(!empty($block['title'])): ?>
            <h1><?=htmlentities($block['title'])?></h1>
        <?php endif; ?>
        <?php if(!empty($block['subtitle'])): ?>
            <h2><?=nl2br(htmlentities($block['subtitle']))?></h2>
        <?php endif; ?>
        <?php if(!empty($block['description'])): ?>
            <p><?=nl2br(htmlentities($block['description']))?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>
