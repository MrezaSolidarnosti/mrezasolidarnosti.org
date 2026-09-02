<?php if(isset($block)): ?>

    <div class="threePillars">
        <h2><?=htmlentities($block['title'] ?? '')?></h2>
        <p><?=nl2br(htmlentities($block['description'] ?? ''))?></p>
        <div class="pillars">
            <?php if(!empty($block['imageDesktopSvg'])): ?>
                <?=$block['imageDesktopSvg']?>
            <?php elseif(!empty($block['imageDesktopFilename'])): ?>
                <img class="illustrationDesktop" src="/images<?=htmlentities($block['imageDesktopFilename'])?>"
                     alt="<?=htmlentities($block['imageDesktopAlt'] ?: ($block['title'] ?? ''))?>">
            <?php endif; ?>
            <?php if(!empty($block['imageMobileSvg'])): ?>
                <?=$block['imageMobileSvg']?>
            <?php elseif(!empty($block['imageMobileFilename'])): ?>
                <img class="illustrationMobile" src="/images<?=htmlentities($block['imageMobileFilename'])?>"
                     alt="<?=htmlentities($block['imageMobileAlt'] ?: ($block['title'] ?? ''))?>">
            <?php endif; ?>
            <div class="pillarSections">
                <?php foreach(($block['pillars'] ?? []) as $pillar): ?>
                    <div class="pillar">
                        <h3 id="<?=htmlentities($pillar['title'] ?? '')?>"><?=htmlentities($pillar['title'] ?? '')?></h3>
                        <div><?=$pillar['description'] ?? ''?></div>
                        <?php if(!empty($pillar['buttonText'])): ?>
                            <a href="<?=htmlentities($pillar['buttonLink'] ?? '')?>" title="<?=htmlentities($pillar['buttonText'])?>">
                                <svg width="17" height="17" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M6.85054 1.36864C7.60514 0.614033 8.82874 0.60944 9.5777 1.3584L14.8463 6.62697C14.0916 6.00765 12.9715 6.0637 12.2638 6.77147C11.556 7.47924 11.4995 8.72377 12.2329 9.47797L12.2794 9.52446C13.0284 10.2527 14.2365 10.243 14.9859 9.49356L9.50585 14.9736C8.74604 15.7334 7.52765 15.7328 6.77869 14.9838C6.02972 14.2348 6.03432 13.0113 6.78892 12.2566L9.08397 9.9616L2.73156 10.1467C1.6635 10.1507 0.800904 9.2881 0.810079 8.22521C0.814088 7.15715 1.68319 6.28805 2.74604 6.28925L8.82833 6.10569L6.8397 4.11707C6.09074 3.3681 6.09533 2.14451 6.84994 1.3899L6.85001 1.36916L6.85054 1.36864Z" fill="#FE5101"></path>
                                    <path d="M13.2292 10.611L14.2677 10.185L16.3314 8.12127L13.8683 5.65816L12.2306 6.36381L10.8326 8.00145L13.2292 10.611Z" fill="#FE5101"></path>
                                </svg>
                                <?=htmlentities($pillar['buttonText'])?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
