<?php // Plates layouts get only what is handed to them here, not the child's data,
// so without this the layout's logo <img> has no host and every client shows it broken.
$this->layout('emailTheme::email', ['data' => $data]) ?>
<?php
/**
 * The announcement sent to every donor by `php public/cli.php mailDonors run`.
 *
 * Deliberately not addressed by name: this is a letter to the base, and "Dragi donatori" is
 * its opening line. $data['displayName'] and $data['baseUrl'] are available if that changes.
 *
 * Inline styles, not classes — mail clients drop <style> blocks. The link style matches the
 * button in magicLink.php so the mails look like they come from one place.
 */
?>
<p>Dragi donatori,</p>

<p>Mreža solidarnosti je unapredila svoju digitalnu platformu i otvorila mogućnost da se u direktnu podršku ljudima u Srbiji uključe i naši ljudi koji žive van zemlje. Nema više prepreka na granici za vašu podršku.</p>

<p>Do sada je doniranje iz Srbije omogućavalo da se putem Mreže podržavaju ljudi koji se nađu pod udarom represije zbog svog društvenog angažmana. Doniranje u Mreži je pokazalo da podrška može da ide direktno od čoveka do čoveka, bez posrednika i bez čekanja da neko drugi reši problem.</p>

<p>Sada širimo krug podrške. Ljudi iz dijaspore od danas mogu da se uključe u isti sistem i direktno podrže ljude kojima je pomoć potrebna. Time se ne menja način na koji Mreža funkcioniše. Samo su uklonjene prepreke za širu solidarnost.</p>

<p>Mreža danas povezuje ljude kroz dva pravca podrške: prosvetne radnike u školama i zaposlene na univerzitetu, kao i građane pogođene represijom i institucionalnim pritiscima. U oba slučaja princip je isti, naime, kada neko zbog svog profesionalnog, društvenog ili građanskog angažmana ostane bez dovoljno podrške, drugi ljudi mogu da spreče da on ili ona ostanu sami. Ovo nije samo tehničko proširenje mogućnosti za donacije. Za nas je važno što se širi sama mreža ljudi koji mogu da budu deo zajedničkog odgovora. Solidarnost tako prelazi granice, povezuje ljude koji žive na različitim mestima i pokazuje da fizička udaljenost ne mora da znači i društvenu udaljenost. Zato ovu novinu želimo prvo da podelimo sa vama.</p>

<p>Bez ljudi koji su od početka verovali da direktna podrška može da funkcioniše, Mreža solidarnosti ne bi bila ono što je danas. Sada zajedno otvaramo prostor da joj se priključe i oni koji žele da pomognu izvan Srbije. Ne menjamo način na koji funkcionišemo. Samo širimo krug ljudi koji mogu da učestvuju.</p>

<p>Ako poznajete nekoga ko živi u dijaspori, a želi da direktno podrži ljude u Srbiji, pošaljite mu ovu vest.</p>

<p>Nova platforma dostupna je na:</p>
<p><a href="https://mrezasolidarnosti.org/" target="_blank" style="padding: 8px 16px;border-radius: 8px;background: #2700EB;text-decoration: none;color: #FFF;font-weight: 700;display: inline-block;">https://mrezasolidarnosti.org/</a></p>

<p>Hvala vam što ste već deo ove mreže.</p>

<p style="margin-top: 24px; padding: 12px; background: #F3F3F7; border-radius: 8px; font-size: 13px; color: #444;">
    ℹ️ <b>Tehnička napomena:</b> Ako ne vidite naš email sa instrukcijama za uplatu, proverite foldere „Promocije“, „Spam“ i druge kategorije u svom email nalogu. Preporučujemo da našu adresu dodate u svoje kontakte kako biste sigurno primali buduća obaveštenja.
</p>
