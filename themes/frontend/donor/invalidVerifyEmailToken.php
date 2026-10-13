<?php $this->layout('layout::standard') ?>
<?php
// Literal t() calls so the translation scanner still sees every string.
$messages = [
    'used' => $this->t('Ovaj link za prijavu je već iskorišćen.'),
    'expired' => $this->t('Ovaj link za prijavu je istekao.'),
    'superseded' => $this->t('Ovaj link za prijavu više ne važi jer je u međuvremenu poslat noviji.'),
    'unknown' => $this->t('Ovaj link za prijavu nije validan.'),
];
?>
<div id="notFound">
    <h1><?=$messages[$data['reason'] ?? 'unknown'] ?? $messages['unknown']?></h1>
    <p>
        <a href="<?=$this->localizeUrl('/logovanje')?>" title="<?=$this->t('Prijavi se')?>"><?=$this->t('Zatražite novi link za prijavu')?></a>.
    </p>
</div>
