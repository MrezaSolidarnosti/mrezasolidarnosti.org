<?php if(isset($block)): ?>

    <?php
        $whatIsContentClasses = 'whatIsContent';
        if(($block['topPadding'] ?? 'big') === 'small') {
            $whatIsContentClasses .= ' smallTop';
        }
        if(($block['bottomPadding'] ?? 'big') === 'small') {
            $whatIsContentClasses .= ' smallBottom';
        }
    ?>
    <div class="<?=$whatIsContentClasses?>">
        <div class="whatIsSection">
            <h2><?=nl2br(htmlentities($block['title'] ?? ''))?></h2>
            <div>
                <div class="sideBySideDescription"><?=$block['description'] ?? ''?></div>
                <?php if(!empty($block['items']) && is_array($block['items'])): ?>
                    <ul>
                        <?php foreach($block['items'] as $item): ?>
                            <li><?=htmlentities(is_array($item) ? ($item['text'] ?? '') : $item)?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if(!empty($block['linkText'])): ?>
                    <a href="<?=htmlentities($block['linkUrl'] ?? '')?>" title="<?=htmlentities($block['linkText'])?>">
                        <svg width="17" height="17" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6.85054 1.36864C7.60514 0.614033 8.82874 0.60944 9.5777 1.3584L14.8463 6.62697C14.0916 6.00765 12.9715 6.0637 12.2638 6.77147C11.556 7.47924 11.4995 8.72377 12.2329 9.47797L12.2794 9.52446C13.0284 10.2527 14.2365 10.243 14.9859 9.49356L9.50585 14.9736C8.74604 15.7334 7.52765 15.7328 6.77869 14.9838C6.02972 14.2348 6.03432 13.0113 6.78892 12.2566L9.08397 9.9616L2.73156 10.1467C1.6635 10.1507 0.800904 9.2881 0.810079 8.22521C0.814088 7.15715 1.68319 6.28805 2.74604 6.28925L8.82833 6.10569L6.8397 4.11707C6.09074 3.3681 6.09533 2.14451 6.84994 1.3899L6.85001 1.36916L6.85054 1.36864Z" fill="#FE5101"></path>
                            <path d="M13.2297 10.611L14.2682 10.185L16.3318 8.12127L13.8687 5.65816L12.2311 6.36381L10.8331 8.00145L13.2297 10.611Z" fill="#FE5101"></path>
                        </svg>
                        <?=htmlentities($block['linkText'])?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
