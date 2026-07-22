<!doctype html>
<html lang="<?=htmlentities($currentLocale ?? 'sr')?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=2.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <?php if(isset($title)):?>
        <title><?=$title?></title>
        <meta property="og:title" content="<?=$title?>">
    <?php else:?>
        <title>Mreža solidarnosti</title>
        <meta property="og:title" content="Mreža solidarnosti">
    <?php endif;?>
    <?php if(isset($description)):?>
        <meta name="description" content="<?=$description?>">
        <meta property="og:description" content="<?=$description?>">
    <?php else:?>
        <meta name="description" content="<?=$this->t('Mreža solidarnosti je inicijativa grupe neformalnih stručnjaka iz raznih oblasti. Služi za  direktnu finansijsku podršku nastavnicima i vannastavnom osoblju, kao i za donacije protiv represije. Pridruži se, doniraj i podrži one koji se bore za bolje obrazovanje i protiv represije!')?>">
        <meta property="og:description" content="<?=$this->t('Mreža solidarnosti je inicijativa grupe neformalnih stručnjaka iz raznih oblasti. Služi za  direktnu finansijsku podršku nastavnicima i vannastavnom osoblju, kao i za donacije protiv represije. Pridruži se, doniraj i podrži one koji se bore za bolje obrazovanje i protiv represije!')?>">
    <?php endif;?>
    <?php if(isset($canonical)):?>
        <link rel="canonical" href="<?=$canonical?>">
    <?php endif;?>
    <?php if(!empty($localeAlternates)):?>
        <?php foreach($localeAlternates as $hrefLocale => $hrefUrl):?>
            <link rel="alternate" hreflang="<?=htmlentities($hrefLocale)?>" href="<?=htmlentities(($url ?? '') . $hrefUrl)?>">
        <?php endforeach;?>
        <?php if(isset($localeAlternates[$defaultLocale ?? 'sr'])):?>
            <link rel="alternate" hreflang="x-default" href="<?=htmlentities(($url ?? '') . $localeAlternates[$defaultLocale ?? 'sr'])?>">
        <?php endif;?>
    <?php endif;?>
    <?php if(isset($seoImageSrc, $seoImageAlt)):?>
        <meta property="og:image" content="<?=$seoImageSrc?>">
        <meta property="og:image:alt" content="<?=$seoImageAlt?>">

        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="<?=$seoImageSrc?>">
        <meta name="twitter:image:alt" content="<?=$seoImageAlt?>">
    <?php else:?>
        <meta property="og:image" content="/assets/img/social.webp">
        <meta property="twitter:card" content="summary_large_image">
        <meta property="twitter:image" content="/assets/img/social.webp">
    <?php endif;?>

	<meta name="keywords" content="<?=$this->t('Mreža solidarnosti, prosveta, donacije, podrška nastavnicima, solidarnost, pomoć nastavnicima, obustava rada, direktna podrška')?>">
	<!-- Open Graph -->
	<meta property="og:type" content="website">
	<meta property="og:url" content="https://mrezasolidarnosti.org/">

    <meta name="description" content="<?=$this->t('Mreža solidarnosti je inicijativa grupe neformalnih stručnjaka iz raznih oblasti. Služi za  direktnu finansijsku podršku nastavnicima i vannastavnom osoblju, kao i za donacije protiv represije. Pridruži se, doniraj i podrži one koji se bore za bolje obrazovanje i protiv represije!')?>">
    <meta name="keywords" content="<?=$this->t('Mreža solidarnosti, prosveta, donacije, podrška nastavnicima, solidarnost, pomoć nastavnicima, obustava rada, direktna podrška')?>">
    <!-- Open Graph / Facebook --><meta property="og:type" content="website">
    <meta property="og:url" content="https://mrezasolidarnosti.org/">
    <meta property="og:title" content="Mreža solidarnosti">
    <meta property="og:description" content="<?=$this->t('Mreža solidarnosti je inicijativa grupe neformalnih stručnjaka iz raznih oblasti. Služi za  direktnu finansijsku podršku nastavnicima i vannastavnom osoblju, kao i za donacije protiv represije. Pridruži se, doniraj i podrži one koji se bore za bolje obrazovanje i protiv represije!')?>">
    <meta property="og:image" content="/social.webp"><!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://mrezasolidarnosti.org/">
    <meta property="twitter:title" content="Mreža solidarnosti">
    <meta property="twitter:description" content="<?=$this->t('Mreža solidarnosti je inicijativa grupe neformalnih stručnjaka iz raznih oblasti. Služi za  direktnu finansijsku podršku nastavnicima i vannastavnom osoblju, kao i za donacije protiv represije. Pridruži se, doniraj i podrži one koji se bore za bolje obrazovanje i protiv represije!')?>">
    <meta property="twitter:image" content="/assets/img/social.webp">

    <script defer src="https://cloud.umami.is/script.js" data-website-id="775ac248-d1eb-4779-9e22-1ae08ede6310"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
    <!-- Tabler Icons -->

	<link rel="icon" href="/favicon.ico" sizes="any">
	<link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <link rel="stylesheet" href="<?=FRONT_ASSET_URL?>/css/style.css?v=0.0.7">
</head>
<body>
<?=$this->section('header', $this->fetch('partialsGlobal::header'))?>

<?php
$mainClasses = [];
if(!empty($isHome)) {
    $mainClasses[] = 'home';
}
if(!empty($isDonorLoggedIn)) {
    $mainClasses[] = 'loggedIn';
}
?>
<main <?=!empty($mainClasses) ? 'class="' . implode(' ', $mainClasses) .'"' : ''?>>
    <?=$this->section('content')?>
</main>

<?=$this->section('footer', $this->fetch('partialsGlobal::footer'))?>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script type="module" src="<?=FRONT_ASSET_URL?>/js/global.js?v=0.0.5"></script>
<?php if (isset($jsPath) && $jsPath != ""): ?>
    <script src="<?=$jsPath?>" type="module"></script>
<?php endif; ?>
</body>
</html>