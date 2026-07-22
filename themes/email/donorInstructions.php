<?php $this->layout('emailTheme::email') ?>
<p><?=$this->t('Zdravo')?> <?=htmlentities($data['displayName'] ?? '')?>,</p>
<p><?=$this->t('Postoje nove instrukcije za uplatu donacije podržanim građanima.
Da bi ih video/la, prijavi se na svoj nalog i poseti stranicu Instrukcije za uplatu')?>:</p>
<a href="<?=$data['loginUrl'] ?? ''?>" style="padding: 8px;border-radius: 8px;background: #2700EB;text-decoration: none;color: #FFF;font-weight: 700;"><?=$this->t('Prijavi se')?></a>

<p>⚠️
    <?=$this->t('Važno: Rok za uplatu je najviše 72 sata (3 dana) od prijema ovog mejla. Ako uplata ne bude realizovana u tom roku, ove instrukcije će isteći. Utom slučaju, dobićeš nove instrukcije čim budu dostupne.')?><br /><br />
    <?=$this->t('Hvala ti što si deo naše zajednice i što se zajedno borimo protiv represije! ✊')?>
</p>
