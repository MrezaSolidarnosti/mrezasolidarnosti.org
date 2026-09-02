<?php if(isset($block)): ?>
    <div class="faq<?=$isHome ? ' home' : ''?>">
        <div class="faqLeft">
            <h2><?=htmlentities($block['title'] ?? '')?></h2>
            <?php if(!empty($block['buttonText'])): ?>
                <a class="allFAQ linkWithArrow" href="<?=htmlentities($block['buttonLink'] ?? '')?>" title="<?=htmlentities($block['buttonText'])?>">
                    <svg width="17" height="17" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6.85054 1.3687C7.60514 0.614094 8.82874 0.609501 9.5777 1.35846L14.8463 6.62703C14.0916 6.00771 12.9715 6.06376 12.2638 6.77153C11.556 7.4793 11.4995 8.72383 12.2329 9.47803L12.2794 9.52452C13.0284 10.2527 14.2365 10.243 14.9859 9.49362L9.50585 14.9736C8.74604 15.7334 7.52765 15.7328 6.77869 14.9839C6.02972 14.2349 6.03432 13.0113 6.78892 12.2567L9.08397 9.96166L2.73156 10.1467C1.6635 10.1508 0.800904 9.28816 0.810079 8.22527C0.814088 7.15721 1.68319 6.28811 2.74604 6.28931L8.82833 6.10575L6.8397 4.11713C6.09074 3.36816 6.09533 2.14457 6.84994 1.38996L6.85001 1.36922L6.85054 1.3687Z" fill="#262185"></path>
                        <path d="M13.2294 10.611L14.2679 10.185L16.3316 8.12127L13.8685 5.65816L12.2309 6.36381L10.8329 8.00145L13.2294 10.611Z" fill="#262185"></path>
                    </svg>
                    <span><?=htmlentities($block['buttonText'])?></span>
                </a>
            <?php endif; ?>
        </div>
        <div class="faqRight">
            <?php foreach(($block['sections'] ?? []) as $key => $section): ?>
                <div class="faqSection">
                    <div class="faqQuestion">
                        <h3><?=htmlentities($section['question'] ?? '')?></h3>
                        <svg class="<?=$isHome && $key === 0 ? 'active' : ''?>" width="24" height="25" viewBox="0 0 24 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M13.8462 1.92308C13.8462 0.859375 13.0212 0 12 0C10.9788 0 10.1538 0.859375 10.1538 1.92308V10.5769H1.84615C0.825 10.5769 0 11.4363 0 12.5C0 13.5637 0.825 14.4231 1.84615 14.4231H10.1538V23.0769C10.1538 24.1406 10.9788 25 12 25C13.0212 25 13.8462 24.1406 13.8462 23.0769V14.4231H22.1538C23.175 14.4231 24 13.5637 24 12.5C24 11.4363 23.175 10.5769 22.1538 10.5769H13.8462V1.92308Z" fill="#FE5101"></path>
                        </svg>
                    </div>
                    <div class="faqAnswer<?=$isHome && $key == 0 ? ' active' : ''?>">
                        <p><?=nl2br(htmlentities($section['answer'] ?? ''))?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
