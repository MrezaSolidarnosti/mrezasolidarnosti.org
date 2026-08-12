<?php if(isset($block)): ?>
    <div class="blogWrapper">
        <div class="blog" id="blogPosts">
            <?php foreach(($block['posts'] ?? []) as $post): ?>
                <?=$this->insert('partialsGlobal::postCard', ['post' => $post])?>
            <?php endforeach;?>
        </div>
        <?php if(!empty($block['hasMore'])): ?>
            <button id="loadMoreBlogPosts"
                    data-url="<?=htmlspecialchars($block['loadMoreUrl'] ?? '/blog/load-more')?>"
                    data-offset="<?=count($block['posts'] ?? [])?>">
                <?=$this->t('Prikaži više')?>
            </button>
        <?php endif;?>
    </div>
    <script type="module" src="<?=FRONT_ASSET_URL?>/js/blog.js?v=0.0.2"></script>
<?php endif;?>
