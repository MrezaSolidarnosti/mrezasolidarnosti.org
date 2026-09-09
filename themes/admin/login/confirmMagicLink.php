<?php $this->layout('layout/login', ['title' => $pageTitle ?? 'Login']) ?>
<h1>Prijava</h1>
<?php
/**
 * The button, not the link, spends the token.
 *
 * Mail clients and in-app browsers prefetch URLs to build previews, and while following the
 * link consumed the token that prefetch burned it before the recipient ever tapped it —
 * reliably on phones, never on desktop. Prefetchers issue GET and never POST.
 */
?>
<form id="loginForm" action="/login/<?=$this->e($data['entityType'])?>/verifyMagicLink/" method="post">
    <?php if (isset($messages) && $messages !== ''): ?>
        <div id="messageContainer"><?=$messages?></div>
    <?php endif; ?>
    <p>Kliknite da biste završili prijavu.</p>
    <input type="hidden" name="token" value="<?=$this->e($data['token'])?>">
    <button class="btn primary fullWidth" type="submit">Prijavi se</button>
</form>
