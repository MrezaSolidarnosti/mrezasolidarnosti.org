<?php if(isset($block)): ?>
    <div class="howItWorksBannerWrapper">
        <div class="howItWorksBanner">
            <svg width="469" height="466" viewBox="0 0 469 466" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M469 379.846L469 77.864C469 34.9352 433.957 4.20663e-05 390.895 4.39486e-05L390.513 0.319624C347.451 0.319626 312.196 35.5274 312.196 78.4562L312.196 192.44L133.417 23.3888C103.125 -7.10544 53.53 -7.10544 22.9414 23.3888C-7.64716 53.5871 -7.64716 103.029 22.9414 133.524L209.443 310.272L78.476 310.272C35.4144 310.272 0.371214 345.207 0.371216 388.136C0.371218 431.065 35.0878 466 78.476 466L469 466L469 379.846Z" fill="#CFF54D"></path>
            </svg>
            <div>
                <h2><?=htmlentities($block['title'] ?? '')?></h2>
                <p><?=nl2br(htmlentities($block['description'] ?? ''))?></p>
                <?php if(!empty($block['buttonText'])): ?>
                    <a href="<?=htmlentities($block['buttonLink'] ?? '')?>" title="<?=htmlentities($block['buttonText'])?>">
                        <?=htmlentities($block['buttonText'])?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
