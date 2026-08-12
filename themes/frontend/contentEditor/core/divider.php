<?php
$height = (int)($block['height'] ?? 1);
$height = max(1, min(20, $height));
$color = trim((string)($block['color'] ?? ''));
$style = '--dividerHeight:' . $height . 'px';
if(preg_match('/^#[0-9a-f]{3,8}$/i', $color)) {
    $style .= ';--dividerColor:' . $color;
}
$block['additionalData']['inlineCss'] = trim($style . ';' . ($block['additionalData']['inlineCss'] ?? ''), '; ');
?>
<hr<?=$this->blockAttributes($block, 'dividerBlock')?>>
