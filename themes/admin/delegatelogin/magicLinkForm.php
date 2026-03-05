<?php $this->layout('layout/login', ['title' => $pageTitle ?? 'Magic Link Login']) ?>
<h1>Login za delegate</h1>

<?php if ($sent ?? false): ?>
    <div class="alert alert-success">Link za prijavu je poslat na vašu email adresu.</div>
<?php endif; ?>
<form id="loginForm"  action="/login/<?=$data['entityType']?>/requestMagicLink/" method="post">
    <?php if(isset($messages) && $messages !== ''):?>
        <div id="messageContainer">
            <?=$messages?>
        </div>
    <?php endif;?>
    <div class="inputContainer">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
            <path d="M224 256A128 128 0 1 0 224 0a128 128 0 1 0 0 256zm-45.7 48C79.8 304 0 383.8 0 482.3C0 498.7 13.3 512 29.7 512H418.3c16.4 0 29.7-13.3 29.7-29.7C448 383.8 368.2 304 269.7 304H178.3z"/>
        </svg>
        <input class="input" data-required="true" data-required-text="Email je obavezno polje." data-validation-strategy="email" data-validation-strategy-message="Invalid email provided" aria-label="Email" type="text" name="email" autofocus placeholder="Email">
        <input type="hidden" name="entityType" value="<?=$data['entityType']?>" />
    </div>

    <div id="loginActions">
        <label id="rememberMe">
            <input type="checkbox" class="input" name="rememberMe">
            Zapamti me
        </label>
    </div>
    <button class="btn primary fullWidth" type="submit">Uloguj se</button>
</form>