<?php if(isset($block)): ?>
    <div id="directionWrapper">
        <div id="direction">
            <div id="directionLeft">
                <h2><?=htmlentities($block['title'] ?? '')?></h2>
                <p><?=nl2br(htmlentities($block['description'] ?? ''))?></p>
                <?php if(!empty($block['footerText'])): ?>
                    <span><?=htmlentities($block['footerText'])?></span>
                <?php endif; ?>
            </div>
            <div id="directionRight">
                <?php foreach(($block['projects'] ?? []) as $project): ?>
                    <div class="directionProject"<?=!empty($project['projectHTMLId']) ? ' id="' . htmlentities($project['projectHTMLId']) . '"' : ''?>>
                        <span><?=$this->t('Mreža solidarnosti')?></span>
                        <h3><?=htmlentities($project['title'] ?? '')?></h3>
                        <div class="directionProjectDescription">
                            <?php if(!empty($project['description'])): ?>
                                <p><?=nl2br(htmlentities($project['description']))?></p>
                            <?php endif; ?>
                            <?php if(!empty($project['items']) && is_array($project['items'])): ?>
                                <ol>
                                    <?php foreach($project['items'] as $item): ?>
                                        <li><?=htmlentities(is_array($item) ? ($item['text'] ?? '') : $item)?></li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        </div>
                        <?php if(!empty($project['linkText'])): ?>
                            <a class="linkWithArrow" href="<?=htmlentities($project['linkUrl'] ?? '')?>" title="<?=htmlentities($project['linkText'])?>">
                                <svg width="15" height="15" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M6.00795 1.20023C6.66974 0.538441 7.74284 0.534413 8.39969 1.19126L13.0202 5.81182C12.3584 5.26866 11.3761 5.31782 10.7554 5.93854C10.1347 6.55926 10.0851 7.65071 10.7283 8.31216L10.7691 8.35293C11.426 8.99158 12.4854 8.98306 13.1427 8.32583L8.33668 13.1318C7.67032 13.7982 6.60178 13.7976 5.94494 13.1408C5.28809 12.484 5.29212 11.4109 5.95392 10.7491L7.96668 8.7363L2.39559 8.89862C1.4589 8.90214 0.702397 8.14563 0.710443 7.21347C0.713959 6.27679 1.47616 5.51458 2.40829 5.51563L7.74248 5.35465L5.99845 3.61062C5.3416 2.95377 5.34563 1.88067 6.00742 1.21888L6.00749 1.20069L6.00795 1.20023Z" fill="#FE5101"/>
                                    <path d="M11.6023 9.30586L12.5131 8.93222L14.3229 7.12235L12.1628 4.9622L10.7266 5.58105L9.50054 7.01727L11.6023 9.30586Z" fill="#FE5101"/>
                                </svg>
                                <?=htmlentities($project['linkText'])?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
