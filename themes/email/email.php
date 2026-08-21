<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html charset=UTF-8" />

    <title>Mreža solidarnosti</title>
</head>
<body style="margin: 0; padding: 0;">
<div style="background: #2700EC; padding: 20px;">
    <?php // Mailer::render() passes everything as ['data' => …], so the layout sees $data,
          // never bare variables. The ?? guard keeps a sender that forgets baseUrl from
          // emitting a warning into cron output on every message it sends.
          //
          // The asset path is written out rather than using FRONT_ASSET_URL: constants.php
          // is included only by public/index.php, so the constant does not exist when the
          // cron renders this template and referencing it would be a fatal, not a warning.
          //
          // Raster, not the SVG the site uses: Gmail strips SVG <img> sources and Outlook's
          // renderer ignores them. The #2700EC band is baked into the JPG because JPEG has
          // no alpha, so it has to match the wrapper colour above. ?>
    <img src="<?=$data['baseUrl'] ?? ''?>/assets/frontend/images/logoEmail.jpg" alt="Mreža solidarnosti" style="width: 200px;">
</div>

<div style="padding: 10px 20px 20px 20px; background: #FFFFFF;">
    <div style="margin: 24px 0;">
        <?=$this->section('content')?>
    </div>

    <div>
        <div><?=$this->t('Solidarno')?>,</div>
        <div><b>Mreža solidarnosti</b></div>
        <div><a target="_blank" href="https://mrezasolidarnosti.org">https://mrezasolidarnosti.org</a></div>
    </div>
</div>
</body>
</html>