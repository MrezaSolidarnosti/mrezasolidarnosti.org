<?php if(isset($block) && $isDonorLoggedIn && isset($block['donor']) && $block['donor']): ?>
    <div id="profileData">
        <h1><?=htmlentities($block['title'] ?? '')?></h1>
        <div id="profileDataInfo">
            <div id="profileDataForm">
                <form id="formProfileData" action="/donor/updateProfileData" method="POST">
                    <?=$this->formToken(); ?>
                    <div class="messagesContainer">

                    </div>
                    <div class="inputContainer">
                        <label for="name"><?=$this->t('Ime')?></label>
                        <input type="text" id="name" name="firstName" disabled value="<?=$block['donor']->firstName?>">
                    </div>
                    <div class="inputContainer">
                        <label for="lastname"><?=$this->t('Prezime')?></label>
                        <input type="text" id="lastname" name="lastName" disabled value="<?=$block['donor']->lastName?>">
                    </div>
                    <div class="inputContainer">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" disabled value="<?=$block['donor']->email?>">
                    </div>
                    <p></p>
                    <button id="formSubmit" type="submit" class="buttonPrimary centered"><?=$this->t('Izmeni')?></button>
                </form>
            </div>
            <div id="profileDataStats">
                <div class="left">
                    <h2><?=$this->t('Ukupno donirano')?></h2>
                    <div class="amount big"><?=$block['totalDonated']?> RSD</div>
                    <div class="amount small"><?=$block['totalDonatedEUR']?> EUR</div>
                </div>
                <div class="right">
                    <h2><?=$this->t('Ukupno transakcija')?></h2>
                    <div class="amount big"><?=$block['totalTransactions']?></div>
                </div>
            </div>
            <div id="profileDataLinks">
                <p><?=$this->t('Pregled svih dosadašnjih kao i nove instrukcija možeš videti na stranici')?> <a href="<?=$this->localizeUrl('/instrukcije-za-uplatu')?>" title="<?=$this->t('Instrukcije za uplatu')?>"><?=$this->t('Instrukcije za uplatu')?></a>.</p>
                <a href="<?=$this->localizeUrl('/instrukcije-za-uplatu')?>" title="<?=$this->t('Instrukcije za uplatu')?>" class="buttonPrimary centered">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M11.4211 18.4706H4.47368C3.55241 18.4706 2.66886 18.0987 2.01742 17.4368C1.36598 16.775 1 15.8772 1 14.9412V5.52941C1 4.59335 1.36598 3.69563 2.01742 3.03374C2.66886 2.37185 3.55241 2 4.47368 2H18.3684C19.2897 2 20.1732 2.37185 20.8247 3.03374C21.4761 3.69563 21.8421 4.59335 21.8421 5.52941V10.8235M1 7.88235H21.8421M16.0526 18.4706H23M19.5263 22L23 18.4706L19.5263 14.9412M5.63737 13.7647H5.64316M10.2632 13.7647H12.5789" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <?=$this->t('Instrukcije za uplatu')?>
                </a>
            </div>
        </div>
    </div>
    <script type="module" src="<?=FRONT_ASSET_URL?>/js/profileData.js?v=0.0.2"></script>
<?php endif; ?>
