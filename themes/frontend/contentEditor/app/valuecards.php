<?php if(isset($block)): ?>

    <div class="whatIsVisual">
        <?php foreach(($block['cards'] ?? []) as $card): ?>
            <div>
                <?php if(!empty($card['filename'])): ?>
                    <img src="/images<?=htmlentities($card['filename'])?>"
                         alt="<?=htmlentities($card['alt'] ?: ($card['title'] ?? ''))?>">
                <?php endif; ?>
                <h2><?=htmlentities($card['title'] ?? '')?></h2>
                <div>
                    <?php if(!empty($card['description'])): ?>
                        <div class="valueCardDescription"><?=$card['description']?></div>
                    <?php endif; ?>
                    <?php if(!empty($card['items']) && is_array($card['items'])): ?>
                        <ul>
                            <?php foreach($card['items'] as $item): ?>
                                <li><?=htmlentities(is_array($item) ? ($item['text'] ?? '') : $item)?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if(!empty($card['note'])): ?>
                        <div class="valueCardNote"><?=$card['note']?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
