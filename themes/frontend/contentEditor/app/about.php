<?php if(isset($block)): ?>
    <div class="whatIsContent aboutContent">
        <div class="whatIsArrow">
            <svg width="320" height="461" viewBox="0 0 320 461" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M234.956 2.30828e-05L-63.1385 -2.97746e-06C-105.515 -6.6821e-06 -140 34.4454 -140 76.7725C-140 119.1 -104.93 154.129 -62.554 154.129L49.9619 154.129L-116.912 329.859C-147.014 359.634 -147.014 408.383 -116.912 438.45C-87.1029 468.517 -38.2974 468.517 -8.19574 438.45L166.277 255.13L166.277 383.863C166.277 426.19 200.762 460.635 243.138 460.635C285.515 460.635 320 426.511 320 383.863L320 3.05176e-05L234.956 2.30828e-05Z" fill="#CFF54D"></path>
            </svg>
        </div>
        <div class="howItBecame whatIsSection">
            <h2><?=htmlentities($block['firstTitle'] ?? '')?></h2>
            <div>
                <?=$block['firstDescription'] ?? ''?>
                <?=$block['firstFooterText'] ?? ''?>
            </div>
        </div>
        <div class="whatWeDo whatIsSection">
            <h2><?=htmlentities($block['secondTitle'] ?? '')?></h2>
            <div>
                <?=$block['secondDescription'] ?? ''?>
                <?php if(!empty($block['projects'])): ?>
                    <div class="whatWeDoProjects">
                        <?php foreach($block['projects'] as $project): ?>
                            <div class="whatWeDoProject">
                                <?=$project['svg'] ?? ''?>
                                <h3><?=htmlentities($project['title'] ?? '')?></h3>
                                <p><?=nl2br(htmlentities($project['description'] ?? ''))?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?=$block['secondFooterText'] ?? ''?>
            </div>
        </div>
    </div>
<?php endif; ?>
