<?php $this->layout('layout::standard') ?>
<?php
/**
 * Second step of the magic-link login.
 *
 * The link in the email only gets you here; the token is spent by the POST below. Mobile mail
 * clients and in-app browsers fetch links to build previews, and the token is single-use — so
 * when the GET consumed it, that prefetch logged nobody in and burned the link before the
 * donor could tap it. Tokens were being used up 14 seconds after being issued, which is not a
 * person reading their email.
 *
 * Prefetchers issue GET, never POST, so putting the consumption behind a form makes this
 * immune rather than merely less likely.
 *
 * No CSRF field on purpose: the magic-link token *is* the credential, and anyone able to
 * forge this request would have to already possess it.
 */
?>
<div id="confirmMagicLink">
    <h1><?=$this->t('Prijavite se')?></h1>
    <p><?=$this->t('Kliknite na dugme ispod da biste završili prijavu.')?></p>

    <form method="post" action="<?=$this->localizeUrl('/donor/verifyEmail')?>">
        <input type="hidden" name="token" value="<?=$this->e($token)?>">
        <button type="submit" class="buttonPrimary"><?=$this->t('Nastavi na prijavu')?></button>
    </form>
</div>
