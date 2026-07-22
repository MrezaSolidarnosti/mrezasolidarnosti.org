<?php $this->layout('emailTheme::email') ?>
<p><?=$this->t('Zdravo')?> <?=htmlentities($data['displayName'] ?? '')?>,</p>
<p><?=$this->t('Klikni na dugme ispod kako bi se ulogovao/la na svoj nalog')?>:</p>
<a href="<?=$data['loginUrl'] ?? ''?>" style="padding: 8px;border-radius: 8px;background: #2700EB;text-decoration: none;color: #FFF;font-weight: 700;"><?=$this->t('Uloguj se')?></a>

<p>
    <?=$this->t('Ovaj link važi samo jednom i ističe za 10 minuta iz bezbednosnih razloga.
    Ako ga nisi ti tražio/la, slobodno ignoriši ovu poruku.')?>
</p>
