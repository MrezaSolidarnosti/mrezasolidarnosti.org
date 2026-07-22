<?php $this->layout('emailTheme::email') ?>
<p style="max-width:600px;padding:20px 20px 0 20px;margin:15px auto;color:#505050;">
<?=$this->t('Zdravo')?> <?=$data['name']?>,<br><br>
<?=$this->t('Hvala ti što si se prijavio/la kao donator!')?><br><br>
<?=$this->t('Tvoja registracija je uspešno završena i od sada si deo naše solidarne mreže. Drago nam je da si sa nama.')?><br><br>
<?=$this->t('Naš model solidarne podrške funkcioniše bez centralizovanog računa – umesto toga, kao donator/ka direktno pomažeš osobama kojima je podrška potrebna. Zbog toga nećeš dobiti jedan zajednički račun za uplatu, već instrukcije za uplatu na račun jednog ili više oštećenih, koje algoritam dodeljuje u skladu sa iznosom koji si odabrao/la.')?><br><br>

<?=$this->t('Da bismo potvrdili da je ova adresa e-pošte ispravna i da pripada Vama, molimo Vas da kliknete na dugme ispod.')?><br><br>

<a href="<?=$data['baseUrl']?>/donor/verifyEmail?token=<?=$data['token']?>" title="<?=$this->t('Potvrdite adresu e-pošte')?>"><?=$this->t('Potvrdite adresu e-pošte')?></a><br /><br />
    <?=$this->t('Ako niste Vi kreirali ovaj nalog, nije potrebno da preduzimate bilo kakvu radnju. Slobodno zanemarite ovu poruku.')?>
</p>