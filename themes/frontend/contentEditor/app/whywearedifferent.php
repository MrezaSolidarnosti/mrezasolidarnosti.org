<?php if(isset($block)): ?>
    <div id="whyWeAreDifferentWrapper">
        <div id="whyWeAreDifferent">
            <div id="whyWeAreDifferentLeft">
                <h2><?=htmlentities($block['title'] ?? '')?></h2>
                <h3>
                    <?=htmlentities($block['subtitle'] ?? '')?>
                    <?php if(!empty($block['coloredSubtitle'])): ?>
                        <br>
                        <b><?=htmlentities($block['coloredSubtitle'])?></b>
                    <?php endif; ?>
                </h3>
                <p><?=nl2br(htmlentities($block['description'] ?? ''))?></p>
            </div>
            <div id="whyWeAreDifferentRight">
                <?php foreach(($block['reasons'] ?? []) as $reason): ?>
                    <div class="reason">
                        <h4><?=htmlentities($reason['title'] ?? '')?></h4>
                        <p><?=nl2br(htmlentities($reason['description'] ?? ''))?></p>
                    </div>
                <?php endforeach; ?>
                <?php if(!empty($block['footerText'])): ?>
                    <span><?=htmlentities($block['footerText'])?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
