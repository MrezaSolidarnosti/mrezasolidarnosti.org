<?php if(isset($block)): ?>

    <div class="projectsWrapper">
        <?php if(!empty($block['title'])): ?>
            <h2><?=htmlentities($block['title'])?></h2>
        <?php endif; ?>
        <div class="projectsDisplay">
            <?php foreach(($block['projects'] ?? []) as $project): ?>
                <div class="projectDisplay<?=!empty($project['className']) ? ' ' . htmlentities($project['className']) : ''?>">
                    <?php if(!empty($project['filename'])): ?>
                        <img src="/images<?=htmlentities($project['filename'])?>"
                             alt="<?=htmlentities($project['alt'] ?: ($project['title'] ?? ''))?>">
                    <?php endif; ?>
                    <h2><?=htmlentities($project['title'] ?? '')?></h2>
                    <div>
                        <?php if(!empty($project['description'])): ?>
                            <div class="projectDisplayDescription"><?=$project['description']?></div>
                        <?php endif; ?>
                        <?php if(!empty($project['items']) && is_array($project['items'])): ?>
                            <ul>
                                <?php foreach($project['items'] as $item): ?>
                                    <li><?=htmlentities(is_array($item) ? ($item['text'] ?? '') : $item)?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if(!empty($project['note'])): ?>
                            <div class="projectDisplayNote"><?=$project['note']?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
