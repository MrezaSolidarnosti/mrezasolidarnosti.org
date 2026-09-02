<?php if(isset($block)): ?>
    <div id="connectWrapper">
        <div id="connect">
            <div id="connectLeft">
                <h2><?=htmlentities($block['title'] ?? '')?></h2>
                <p><?=nl2br(htmlentities($block['description'] ?? ''))?></p>
            </div>
            <div id="connectRight">
                <?php foreach(($block['segments'] ?? []) as $segment): ?>
                    <div class="connectSegment">
                        <h3><?=htmlentities($segment['title'] ?? '')?></h3>
                        <p><?=nl2br(htmlentities($segment['description'] ?? ''))?></p>
                    </div>
                <?php endforeach; ?>
                <?php if(!empty($block['buttonText'])): ?>
                    <a class="linkWithArrow" href="<?=htmlentities($block['buttonLink'] ?? '')?>" title="<?=htmlentities($block['buttonText'])?>">
                        <svg width="17" height="17" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6.85054 1.36858C7.60514 0.613972 8.82874 0.609379 9.5777 1.35834L14.8463 6.62691C14.0916 6.00759 12.9715 6.06364 12.2638 6.77141C11.556 7.47918 11.4995 8.72371 12.2329 9.47791L12.2794 9.5244C13.0284 10.2526 14.2365 10.2429 14.9859 9.4935L9.50585 14.9735C8.74604 15.7333 7.52765 15.7327 6.77869 14.9838C6.02972 14.2348 6.03432 13.0112 6.78892 12.2566L9.08397 9.96154L2.73156 10.1466C1.6635 10.1506 0.800904 9.28803 0.810079 8.22515C0.814088 7.15709 1.68319 6.28799 2.74604 6.28919L8.82833 6.10563L6.8397 4.11701C6.09074 3.36804 6.09533 2.14445 6.84994 1.38984L6.85001 1.3691L6.85054 1.36858Z" fill="#FE5101"></path>
                            <path d="M13.2294 10.611L14.2679 10.185L16.3316 8.12127L13.8685 5.65816L12.2309 6.36381L10.8329 8.00145L13.2294 10.611Z" fill="#FE5101"></path>
                        </svg>
                        <?=htmlentities($block['buttonText'])?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
