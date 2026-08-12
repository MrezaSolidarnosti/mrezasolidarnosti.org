<?php $this->layout('layout::standard') ?>
<?php /** @var \Solidarity\Post\Entity\Post $post */ $post = $data['post'] ?? null; ?>
<?php if($post): ?>
    <div class="blogPostWrapper">
        <article class="blogPost">
            <header>
                <a id="backToBlog" title="Blog" href="/blog">
                    <svg width="29" height="29" viewBox="0 0 29 29" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M2.79552 11.9218L11.5333 2.68449C12.7754 1.37135 14.8519 1.33563 16.1614 2.60489L16.1623 2.64051C17.4717 3.90977 17.5265 6.01131 16.2844 7.32446L12.9863 10.8111L23.3143 10.9096C25.1177 10.8697 26.6259 12.3315 26.6737 14.1659C26.7301 15.9913 25.2995 17.5037 23.487 17.5348L12.7015 17.4441L16.684 21.3044C17.9935 22.5737 18.0483 24.6752 16.8061 25.9884C15.564 27.3015 13.4975 27.3469 12.1781 26.068L0.302677 14.5571L2.79552 11.9218Z" fill="#262185"/>
                    </svg>
                    <?=$this->t('Vratite se na Blog')?>
                </a>
                <h1><?=htmlspecialchars($post->title ?? '')?></h1>
                <span class="date"><?=($post->publishAt ?? $post->createdAt)->format('d.m.Y')?></span>
                <?php if($post->featuredImage):?>
                    <figure>
                        <picture>
                            <img src="/images<?=$post->featuredImage?->filename?>" alt="<?=$post->featuredImage?->alt ?? ''?>">
                        </picture>
                        <?php if($post->featuredImage->author !== ''):?>
                            <figcaption><?=$this->t('Autor forografije')?>: <?=$post->featuredImage->author?></figcaption>
                        <?php endif;?>
                    </figure>
                <?php endif;?>
            </header>
            <?php if(!empty($data['content'])):?>
                <div class="postContent"><?=$data['content']?></div>
            <?php endif;?>
        </article>
    </div>
    <script type="module" src="<?=FRONT_ASSET_URL?>/js/post.js?v=0.0.1"></script>
<?php endif;?>
