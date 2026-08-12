<?php
/**
 * A single blog post card.
 *
 * Rendered both by the blog block and by the load more endpoint
 * (Frontend\Action\Blog\LoadMore), so the markup lives in one place only.
 *
 * @var \Solidarity\Post\Entity\Post $post
 */
?>
<div class="post">
    <div class="top">
        <?php if($post->featuredImage):?>
            <a href="/blog/<?=htmlspecialchars($post->slug ?? '')?>" title="<?=htmlspecialchars($post->title ?? '')?>">
                <picture>
                    <img src="/images<?=$post->featuredImage?->filename?>" alt="<?=$post->featuredImage?->alt ?? ''?>">
                </picture>
            </a>
        <?php endif;?>
    </div>
    <div class="bottom">
        <span class="date"><?=($post->publishAt ?? $post->createdAt)->format('d.m.Y')?></span>
        <a href="/blog/<?=htmlspecialchars($post->slug ?? '')?>" title="<?=htmlspecialchars($post->title ?? '')?>">
            <h2><?=htmlspecialchars($post->title ?? '')?></h2>
        </a>
        <p><?=htmlspecialchars($post->shortDescription ?? '')?></p>
        <a class="readMore" href="/blog/<?=htmlspecialchars($post->slug ?? '')?>" title="<?=$this->t('čitaj više')?>">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M6.66783 1.3322C7.40231 0.597716 8.59327 0.593246 9.32226 1.32223L14.4503 6.45029C13.7158 5.84748 12.6256 5.90204 11.9367 6.59093C11.2478 7.27982 11.1928 8.49116 11.9066 9.22525L11.9519 9.2705C12.6809 9.9793 13.8568 9.96984 14.5862 9.24043L9.25233 14.5743C8.51278 15.3138 7.32689 15.3132 6.5979 14.5842C5.86891 13.8553 5.87338 12.6643 6.60786 11.9298L8.8417 9.69598L2.65871 9.87613C1.61914 9.88003 0.779545 9.04044 0.788475 8.0059C0.792377 6.96633 1.6383 6.12041 2.6728 6.12157L8.59287 5.94291L6.65728 4.00732C5.92829 3.27833 5.93277 2.08737 6.66725 1.35289L6.66732 1.3327L6.66783 1.3322Z" fill="#FE5101"/>
                <path d="M12.877 10.3279L13.8878 9.91322L15.8964 7.90458L13.499 5.50716L11.905 6.19399L10.5443 7.78794L12.877 10.3279Z" fill="#FE5101"/>
            </svg>
            <?=$this->t('čitaj više')?>
        </a>
    </div>
</div>
