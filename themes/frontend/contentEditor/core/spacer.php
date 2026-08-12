<?php
$height = (int)($block['height'] ?? 100);
$height = max(24, min(300, $height));
$block['additionalData']['inlineCss'] = trim('height:' . $height . 'px;' . ($block['additionalData']['inlineCss'] ?? ''), '; ');
?>
<div<?=$this->blockAttributes($block, 'spacerBlock')?> aria-hidden="true"></div>
