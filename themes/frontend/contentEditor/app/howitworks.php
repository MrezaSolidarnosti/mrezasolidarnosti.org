<?php
if (isset($block)): ?>
    <div id="howItWorksWrapper">
        <div id="howItWorks">
            <div id="howItWorksLeft">
                <h2><?= htmlentities($block['title'] ?? '') ?></h2>
                <p><?= nl2br(htmlentities($block['description'] ?? '')) ?></p>
                <?php
                if (!empty($block['linkText'])): ?>
                    <a class="linkWithArrow" href="<?= htmlentities($block['linkUrl'] ?? '') ?>"
                       title="<?= htmlentities($block['linkText']) ?>">
                        <svg width="17" height="17" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6.85054 1.3687C7.60514 0.614094 8.82874 0.609501 9.5777 1.35846L14.8463 6.62703C14.0916 6.00771 12.9715 6.06376 12.2638 6.77153C11.556 7.4793 11.4995 8.72383 12.2329 9.47803L12.2794 9.52452C13.0284 10.2527 14.2365 10.243 14.9859 9.49362L9.50585 14.9736C8.74604 15.7334 7.52765 15.7328 6.77869 14.9839C6.02972 14.2349 6.03432 13.0113 6.78892 12.2567L9.08397 9.96166L2.73156 10.1467C1.6635 10.1508 0.800904 9.28816 0.810079 8.22527C0.814088 7.15721 1.68319 6.28811 2.74604 6.28931L8.82833 6.10575L6.8397 4.11713C6.09074 3.36816 6.09533 2.14457 6.84994 1.38996L6.85001 1.36922L6.85054 1.3687Z"
                                  fill="#FE5101"></path>
                            <path d="M13.2294 10.611L14.2679 10.185L16.3316 8.12127L13.8685 5.65816L12.2309 6.36381L10.8329 8.00145L13.2294 10.611Z"
                                  fill="#FE5101"></path>
                        </svg>
                        <?= htmlentities($block['linkText']) ?>
                    </a>
                <?php
                endif; ?>
            </div>
            <div id="howItWorksRight">
                <?php
                if (!empty($block['filename'])): ?>
                    <img src="/images<?= htmlentities($block['filename']) ?>"
                         alt="<?= htmlentities($block['alt'] ?: ($block['title'] ?? '')) ?>">
                <?php
                endif; ?>
                <?php
                foreach (($block['steps'] ?? []) as $index => $step): ?>
                    <div class="step">
                        <div class="stepNumber"><?= $index + 1 ?></div>
                        <h3><?= htmlentities($step['title'] ?? '') ?></h3>
                        <p><?= nl2br(htmlentities($step['description'] ?? '')) ?></p>
                        <?php
                        if ($index === 0 && !empty($block['buttonText'])): ?>
                            <a href="<?= htmlentities($block['buttonLink'] ?? '') ?>"
                               title="<?= htmlentities($block['buttonText']) ?>">
                                <?= htmlentities($block['buttonText']) ?>
                            </a>
                        <?php
                        endif; ?>
                    </div>
                <?php
                endforeach; ?>
            </div>
        </div>
    </div>
<?php
endif; ?>
