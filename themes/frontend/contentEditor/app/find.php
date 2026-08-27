<?php if(isset($block)): ?>
    <div id="findWrapper">
        <div id="find">
            <h2><?=htmlentities($block['title'] ?? '')?></h2>
            <div id="findSegments">
                <?php foreach(($block['segments'] ?? []) as $segment): ?>
                    <div class="findSegment">
                        <?php if(!empty($segment['filename'])): ?>
                            <img src="/images<?=htmlentities($segment['filename'])?>"
                                 alt="<?=htmlentities($segment['alt'] ?: ($segment['title'] ?? ''))?>">
                        <?php endif; ?>
                        <h3><?=htmlentities($segment['title'] ?? '')?></h3>
                        <p><?=nl2br(htmlentities($segment['description'] ?? ''))?></p>
                        <?php if(!empty($segment['buttonText'])): ?>
                            <a class="linkWithArrow" href="<?=htmlentities($segment['buttonLink'] ?? '')?>" title="<?=htmlentities($segment['buttonText'])?>">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M6.66783 1.33214C7.40231 0.597655 8.59327 0.593185 9.32226 1.32217L14.4503 6.45023C13.7158 5.84742 12.6256 5.90198 11.9367 6.59087C11.2478 7.27976 11.1928 8.4911 11.9066 9.22519L11.9519 9.27044C12.6809 9.97924 13.8568 9.96978 14.5862 9.24036L9.25233 14.5742C8.51278 15.3138 7.32689 15.3132 6.5979 14.5842C5.86891 13.8552 5.87338 12.6642 6.60786 11.9298L8.8417 9.69592L2.65871 9.87607C1.61914 9.87997 0.779545 9.04038 0.788475 8.00584C0.792377 6.96627 1.6383 6.12035 2.6728 6.12151L8.59287 5.94285L6.65728 4.00726C5.92829 3.27827 5.93277 2.08731 6.66725 1.35283L6.66732 1.33264L6.66783 1.33214Z" fill="#F9F3DC"/>
                                    <path d="M12.8767 10.328L13.8875 9.91334L15.8962 7.9047L13.4987 5.50728L11.9048 6.19411L10.5441 7.78807L12.8767 10.328Z" fill="#F9F3DC"/>
                                </svg>
                                <?=htmlentities($segment['buttonText'])?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
