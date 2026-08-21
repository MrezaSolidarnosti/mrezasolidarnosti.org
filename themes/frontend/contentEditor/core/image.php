<?php if(!empty($block['src'])): ?>
    <?php
    $align = (string)($block['align'] ?? '');
    $author = trim((string)($block['author'] ?? ''));
    $link = $block['link'] ?? null;
    $href = trim((string)($link['href'] ?? ''));
    $rel = trim((string)($link['rel'] ?? ''));
    if(!empty($link['newTab']) && !str_contains($rel, 'noopener')) {
        $rel = trim($rel . ' noopener');
    }
    ?>
    <figure<?=$this->blockAttributes($block, 'imageBlock', $align !== '' ? 'imageAlign' . ucfirst($align) : '')?>>
        <?php if($href !== ''): ?>
            <a href="<?=htmlspecialchars($href)?>"<?=!empty($link['newTab']) ? ' target="_blank"' : ''?><?=$rel !== '' ? ' rel="' . htmlspecialchars($rel) . '"' : ''?>>
        <?php endif;?>
            <img src="/images<?=htmlspecialchars($block['src'])?>" alt="<?=htmlspecialchars($block['alt'] ?? '')?>">
        <?php if($href !== ''): ?>
            </a>
        <?php endif;?>
        <?php if($author !== ''): ?>
            <figcaption><?=$this->t('Autor fotografije')?>: <?=htmlspecialchars($author)?></figcaption>
        <?php endif;?>
    </figure>
<?php endif;?>
