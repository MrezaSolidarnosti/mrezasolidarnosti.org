<?php if(isset($block)): ?>
    <?=$this->formToken();?>
    <div class="instructionsTable">
    </div>
    <div id="loadMoreContainer">
        <div id="loadMore" class="buttonSecondary">
            <?=$this->t('Učitaj još')?>
        </div>
    </div>

    <template id="desktopTemplateInstructionsTable">
        <table>
            <thead>
            <tr>
                <th><?=$this->t('Oštećeni/a')?></th>
                <th><?=$this->t('Iznos')?></th>
                <th><?=$this->t('Poziv na broj')?></th>
                <th><?=$this->t('Kreirano')?></th>
                <th><?=$this->t('Status')?></th>
            </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </template>
    <template id="mobileTemplateInstructionsTable">
        <table id="mobileTableInstructions">
            <tr>
                <th><?=$this->t('Oštećeni/a')?></th>
            </tr>
            <tr>
                <th><?=$this->t('Iznos')?></th>
            </tr>
            <tr>
                <th><?=$this->t('Poziv na broj')?></th>
            </tr>
            <tr>
                <th><?=$this->t('Kreirano')?></th>
            </tr>
            <tr>
                <th><?=$this->t('Status')?></th>
            </tr>
        </table>
    </template>
    <div id="overlay"></div>
    <dialog id="paymentDialog">
        <div id="closePaymentDialog">
            <svg width="66" height="66" viewBox="0 0 66 66" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M21.2676 16.2635C19.8836 14.8795 17.6474 14.8795 16.2635 16.2635C14.8795 17.6474 14.8795 19.8836 16.2635 21.2676L27.5228 32.5269L16.2635 43.7862C14.8795 45.1702 14.8795 47.4064 16.2635 48.7904C17.6474 50.1743 19.8836 50.1743 21.2676 48.7904L32.5269 37.5311L43.7862 48.7904C45.1702 50.1743 47.4064 50.1743 48.7904 48.7904C50.1743 47.4064 50.1743 45.1702 48.7904 43.7862L37.5311 32.5269L48.7904 21.2676C50.1743 19.8836 50.1743 17.6474 48.7904 16.2635C47.4064 14.8795 45.1702 14.8795 43.7862 16.2635L32.5269 27.5228L21.2676 16.2635Z" fill="#262185"/>
            </svg>
        </div>
        <div class="paymentDialogTop">
            <svg width="63" height="63" viewBox="0 0 63 63" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M29.4814 54.6735C28.883 54.3856 28.3579 53.9655 27.9459 53.4447C27.5339 52.924 27.2457 52.3164 27.1031 51.6679C26.9353 50.9754 26.6064 50.3323 26.1431 49.791C25.6798 49.2497 25.0953 48.8254 24.437 48.5527C23.7788 48.28 23.0654 48.1665 22.3551 48.2216C21.6447 48.2767 20.9573 48.4987 20.349 48.8696C16.2986 51.3371 11.6602 46.7014 14.1277 42.6484C14.4981 42.0403 14.7198 41.3534 14.7748 40.6435C14.8297 39.9336 14.7164 39.2207 14.444 38.5629C14.1716 37.905 13.7478 37.3207 13.2071 36.8575C12.6664 36.3942 12.024 36.0651 11.3321 35.8969C6.72262 34.7786 6.72262 28.2214 11.3321 27.1031C12.0246 26.9353 12.6677 26.6064 13.209 26.1431C13.7503 25.6798 14.1746 25.0953 14.4473 24.437C14.72 23.7788 14.8335 23.0654 14.7784 22.3551C14.7233 21.6447 14.5013 20.9573 14.1304 20.349C11.6629 16.2986 16.2986 11.6602 20.3516 14.1277C22.9766 15.7237 26.3786 14.3115 27.1031 11.3321C28.2214 6.72262 34.7786 6.72262 35.8969 11.3321C36.0647 12.0246 36.3936 12.6677 36.8569 13.209C37.3202 13.7503 37.9047 14.1746 38.563 14.4473C39.2212 14.72 39.9346 14.8335 40.6449 14.7784C41.3553 14.7233 42.0426 14.5013 42.651 14.1304C46.7014 11.6629 51.3398 16.2986 48.8723 20.3516C48.5019 20.9597 48.2802 21.6466 48.2252 22.3565C48.1703 23.0664 48.2836 23.7793 48.556 24.4371C48.8284 25.095 49.2522 25.6793 49.7929 26.1425C50.3336 26.6058 50.976 26.9349 51.6679 27.1031C53.004 27.426 53.9516 28.2082 54.5134 29.1821" stroke="#262185" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M39.0659 29.3081C38.7051 28.0611 38.0417 26.9225 37.1347 25.9938C36.2277 25.065 35.1052 24.3748 33.8671 23.9845C32.6289 23.5942 31.3135 23.516 30.0379 23.7567C28.7622 23.9974 27.5658 24.5497 26.5551 25.3644C25.5443 26.1791 24.7506 27.2309 24.2445 28.4264C23.7384 29.6218 23.5355 30.9238 23.654 32.2166C23.7724 33.5093 24.2086 34.7528 24.9235 35.8364C25.6385 36.9199 26.6102 37.81 27.7522 38.4274" stroke="#262185" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M47.2503 57.75L56.0441 49.1295C56.5833 48.6062 57.0123 47.9802 57.3056 47.2884C57.5989 46.5966 57.7507 45.853 57.7519 45.1016C57.7531 44.3501 57.6038 43.6061 57.3127 42.9133C57.0217 42.2206 56.5947 41.5932 56.0572 41.0681C54.9603 39.9954 53.4878 39.3934 51.9536 39.3905C50.4193 39.3875 48.9446 39.9838 47.8436 41.0524L47.2556 41.6299L46.6702 41.0524C45.5734 39.9804 44.1015 39.3789 42.5679 39.376C41.0343 39.373 39.5601 39.9689 38.4592 41.0366C37.9198 41.5597 37.4906 42.1856 37.197 42.8773C36.9035 43.569 36.7515 44.3126 36.75 45.064C36.7485 45.8154 36.8976 46.5595 37.1885 47.2524C37.4793 47.9452 37.9061 48.5728 38.4434 49.098L47.2503 57.75Z" stroke="#262185" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <h2><?=$this->t('Instrukcije za uplatu donacije')?></h2>
            <div class="info">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2ZM12 4C7.58172 4 4 7.58172 4 12C4 16.4183 7.58172 20 12 20C16.4183 20 20 16.4183 20 12C20 7.58172 16.4183 4 12 4ZM12 11C12.5523 11 13 11.4477 13 12V15H13.5C14.0523 15 14.5 15.4477 14.5 16C14.5 16.5523 14.0523 17 13.5 17H10.5C9.94772 17 9.5 16.5523 9.5 16C9.5 15.4477 9.94772 15 10.5 15H11V13H10.5C9.94772 13 9.5 12.5523 9.5 12C9.5 11.4477 9.94772 11 10.5 11H12ZM11.75 7C12.4404 7 13 7.55964 13 8.25C13 8.94036 12.4404 9.5 11.75 9.5C11.0596 9.5 10.5 8.94036 10.5 8.25C10.5 7.55964 11.0596 7 11.75 7Z" fill="#262185"/>
                </svg>
                <p><?=$this->t('Ukoliko se uplata ne izvrši u roku od 72 časa, instrukcije su otkazane. Zatim možete ručno generisati instrukcije klikom na dugme na prethodnom ekranu “Generiši instrukcije” ')?></p>
            </div>
        </div>
        <div class="messagesContainer">

        </div>
        <div id="paymentInfo">
            <div id="paymentInfoLogo">
            </div>
            <div id="paymentInfoData">
                <div class="column">
                    <div class="inputContainer name">
                        <label><?=$this->t('Ime i prezime oštećenog:')?></label>
                        <div class="inputWrapper">
                            <input id="nameInput" type="text" readonly>
                            <div class="copyInput">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M9 18C8.45 18 7.97933 17.8043 7.588 17.413C7.19667 17.0217 7.00067 16.5507 7 16V4C7 3.45 7.196 2.97933 7.588 2.588C7.98 2.19667 8.45067 2.00067 9 2H18C18.55 2 19.021 2.196 19.413 2.588C19.805 2.98 20.0007 3.45067 20 4V16C20 16.55 19.8043 17.021 19.413 17.413C19.0217 17.805 18.5507 18.0007 18 18H9ZM9 16H18V4H9V16ZM5 22C4.45 22 3.97933 21.8043 3.588 21.413C3.19667 21.0217 3.00067 20.5507 3 20V7C3 6.71667 3.096 6.47933 3.288 6.288C3.48 6.09667 3.71733 6.00067 4 6C4.28267 5.99933 4.52033 6.09533 4.713 6.288C4.90567 6.48067 5.00133 6.718 5 7V20H15C15.2833 20 15.521 20.096 15.713 20.288C15.905 20.48 16.0007 20.7173 16 21C15.9993 21.2827 15.9033 21.5203 15.712 21.713C15.5207 21.9057 15.2833 22.0013 15 22H5Z" fill="#262185"/>
                                </svg>
                            </div>

                        </div>
                    </div>
                    <div class="inputContainer accNum">
                        <label><?=$this->t('Broj žiro računa:')?></label>
                        <div class="inputWrapper">
                            <input id="accNumInput" type="text" readonly>
                            <div class="copyInput">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M9 18C8.45 18 7.97933 17.8043 7.588 17.413C7.19667 17.0217 7.00067 16.5507 7 16V4C7 3.45 7.196 2.97933 7.588 2.588C7.98 2.19667 8.45067 2.00067 9 2H18C18.55 2 19.021 2.196 19.413 2.588C19.805 2.98 20.0007 3.45067 20 4V16C20 16.55 19.8043 17.021 19.413 17.413C19.0217 17.805 18.5507 18.0007 18 18H9ZM9 16H18V4H9V16ZM5 22C4.45 22 3.97933 21.8043 3.588 21.413C3.19667 21.0217 3.00067 20.5507 3 20V7C3 6.71667 3.096 6.47933 3.288 6.288C3.48 6.09667 3.71733 6.00067 4 6C4.28267 5.99933 4.52033 6.09533 4.713 6.288C4.90567 6.48067 5.00133 6.718 5 7V20H15C15.2833 20 15.521 20.096 15.713 20.288C15.905 20.48 16.0007 20.7173 16 21C15.9993 21.2827 15.9033 21.5203 15.712 21.713C15.5207 21.9057 15.2833 22.0013 15 22H5Z" fill="#262185"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="inputContainer refNum">
                        <label><?=$this->t('Referentni broj plaćanja (MTCN broj):')?></label>
                        <div class="inputWrapper">
                            <input id="refNumInput" placeholder="<?=$this->t('Upiši  MTCN broj ')?>" type="text">
                        </div>
                    </div>
                </div>
                <div class="column">
                    <div class="inputContainer amount">
                        <label><?=$this->t('Iznos:')?></label>
                        <div class="inputWrapper">
                            <input id="amountInput" type="text" readonly>
                        </div>
                    </div>
                    <?php // Dinarski transfer only — shown/hidden per payment type in InstructionsTable.js ?>
                    <div class="inputContainer refCode">
                        <label><?=$this->t('Poziv na broj:')?></label>
                        <div class="inputWrapper">
                            <input id="refCodeInput" type="text" readonly>
                            <div class="copyInput">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M9 18C8.45 18 7.97933 17.8043 7.588 17.413C7.19667 17.0217 7.00067 16.5507 7 16V4C7 3.45 7.196 2.97933 7.588 2.588C7.98 2.19667 8.45067 2.00067 9 2H18C18.55 2 19.021 2.196 19.413 2.588C19.805 2.98 20.0007 3.45067 20 4V16C20 16.55 19.8043 17.021 19.413 17.413C19.0217 17.805 18.5507 18.0007 18 18H9ZM9 16H18V4H9V16ZM5 22C4.45 22 3.97933 21.8043 3.588 21.413C3.19667 21.0217 3.00067 20.5507 3 20V7C3 6.71667 3.096 6.47933 3.288 6.288C3.48 6.09667 3.71733 6.00067 4 6C4.28267 5.99933 4.52033 6.09533 4.713 6.288C4.90567 6.48067 5.00133 6.718 5 7V20H15C15.2833 20 15.521 20.096 15.713 20.288C15.905 20.48 16.0007 20.7173 16 21C15.9993 21.2827 15.9033 21.5203 15.712 21.713C15.5207 21.9057 15.2833 22.0013 15 22H5Z" fill="#262185"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="column">
                    <div class="inputContainer qrCode">
                        <label><?=$this->t('Ili skeniraj NBS QR Code:')?></label>
                        <div class="inputWrapper">
                            <img id="qrCodeImage" class="QR" src="" alt="">
                        </div>
                    </div>
                    <?php // Umami tracks data-umami-event on click with no JS. Names match
                          // the legacy app so historical funnel comparisons still line up. ?>
                    <div class="print" data-umami-event="donor_transaction_print">
                        <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6 0.5H22C25.0376 0.5 27.5 2.96243 27.5 6V22C27.5 25.0376 25.0376 27.5 22 27.5H6C2.96243 27.5 0.5 25.0376 0.5 22V6C0.5 2.96243 2.96243 0.5 6 0.5Z" fill="white"/>
                            <path d="M6 0.5H22C25.0376 0.5 27.5 2.96243 27.5 6V22C27.5 25.0376 25.0376 27.5 22 27.5H6C2.96243 27.5 0.5 25.0376 0.5 22V6C0.5 2.96243 2.96243 0.5 6 0.5Z" stroke="#262185"/>
                            <g clip-path="url(#clip0_640_3973)">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M11.4297 8.42857C11.2024 8.42857 10.9843 8.51888 10.8236 8.67962C10.6629 8.84037 10.5725 9.05839 10.5725 9.28572V10.5714H17.4297V9.28572C17.4297 9.05839 17.3393 8.84037 17.1786 8.67962C17.0179 8.51888 16.7999 8.42857 16.5725 8.42857H11.4297ZM9.7154 11.6429C9.3744 11.6429 9.04738 11.7783 8.80626 12.0194C8.56515 12.2606 8.42969 12.5876 8.42969 12.9286V16.5714C8.42969 16.9124 8.56515 17.2395 8.80626 17.4806C9.04738 17.7217 9.3744 17.8571 9.7154 17.8571H10.3583V16.3571C10.3583 15.5287 11.0298 14.8571 11.8583 14.8571H16.144C16.9724 14.8571 17.644 15.5287 17.644 16.3571V17.8571H18.2868C18.6278 17.8571 18.9549 17.7217 19.196 17.4806C19.4371 17.2395 19.5725 16.9124 19.5725 16.5714V12.9286C19.5725 12.5876 19.4371 12.2606 19.196 12.0194C18.9549 11.7783 18.6278 11.6429 18.2868 11.6429H9.7154ZM16.5725 12.8214C16.2767 12.8214 16.0368 13.0613 16.0368 13.3571C16.0368 13.653 16.2767 13.8929 16.5725 13.8929H17.4297C17.7256 13.8929 17.9654 13.653 17.9654 13.3571C17.9654 13.0613 17.7256 12.8214 17.4297 12.8214H16.5725ZM11.8583 15.9286C11.6216 15.9286 11.4297 16.1205 11.4297 16.3571V18.7143C11.4297 18.9416 11.52 19.1597 11.6807 19.3204C11.8415 19.4811 12.0595 19.5714 12.2868 19.5714H15.7154C15.9427 19.5714 16.1607 19.4811 16.3215 19.3204C16.4822 19.1597 16.5725 18.9416 16.5725 18.7143V16.3571C16.5725 16.1205 16.3807 15.9286 16.144 15.9286H11.8583Z" fill="#262185"/>
                            </g>
                            <defs>
                                <clipPath id="clip0_640_3973">
                                    <rect width="12" height="12" fill="white" transform="translate(8 8)"/>
                                </clipPath>
                            </defs>
                        </svg>
                        <span><?=$this->t('Štampaj ove instrukcije')?></span>
                    </div>
                </div>
            </div>
            <div class="paymentContact">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10.8989 4.8609L7.03444 0.996428C6.48508 0.447066 5.58697 0.451047 5.03271 1.0053L5.03264 1.02052C4.47839 1.57478 4.47441 2.47289 5.02377 3.02225L6.48242 4.4809L2.01797 4.61866C1.23784 4.61831 0.599493 5.25666 0.596019 6.04061C0.588755 6.82077 1.22147 7.45348 2.00542 7.45L6.66776 7.31137L4.98207 8.99706C4.42781 9.55132 4.42383 10.4494 4.97319 10.9988C5.52256 11.5482 6.41646 11.5484 6.97492 10.9899L12.0014 5.96342L10.8989 4.8609Z" fill="#CF3443"/>
                </svg>
                <?=$this->t('Naišli ste na problem?')?> <a href="<?=$this->localizeUrl('/kontakt')?>" title="<?=$this->t('Kontakt')?>">
                    <?=$this->t('Kontaktirajte nas')?>
                </a>
            </div>
        </div>
        <div class="donateButton" id="confirmPayment" data-umami-event="donor_transaction_confirm_payment"><?=$this->t('Potvrdi uplatu')?></div>
    </dialog>
    <div id="legend">
        <h3><?=$this->t('Legenda')?></h3>
        <ul>
            <li>
                <div class="donateButton"><svg width="13" height="12" viewBox="0 0 13 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M11.2479 1.62712C10.9699 1.34905 10.6399 1.12846 10.2767 0.977961C9.9135 0.827462 9.52419 0.75 9.13103 0.75C8.73787 0.75 8.34856 0.827462 7.98535 0.977961C7.62213 1.12846 7.29212 1.34905 7.01418 1.62712L6.43735 2.20395L5.86053 1.62712C5.2991 1.0657 4.53765 0.750291 3.74368 0.750291C2.9497 0.750291 2.18825 1.0657 1.62683 1.62712C1.0654 2.18854 0.75 2.95 0.75 3.74397C0.75 4.53794 1.0654 5.29939 1.62683 5.86082L6.43735 10.6713L11.2479 5.86082C11.526 5.58288 11.7465 5.25287 11.897 4.88965C12.0475 4.52644 12.125 4.13713 12.125 3.74397C12.125 3.35081 12.0475 2.9615 11.897 2.59828C11.7465 2.23507 11.526 1.90506 11.2479 1.62712Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg> <?=$this->t('UPLATI')?></div>
                <span><?=$this->t('Potrebno izvršiti uplatu po generisanim instrukcijama.')?></span>
            </li>
            <li>
                <div class="status2"><?=$this->t('Čeka se potvrda građana')?></div>
                <span><?=$this->t('Čeka se na delegata ili partnera, da potvrdi da je oštećeni građanin primio uplatu.')?></span>
            </li>
            <li>
                <div class="status3"><?=$this->t('Potvrđeno')?></div>
                <span><?=$this->t('Uplata je potvrđena')?></span>
            </li>
            <li>
                <div class="status6"><?=$this->t('Isteklo')?></div>
                <span><?=$this->t('Uplata je istekla jer nije potvrđena uplata u roku od 72h od kako je kreirana instrukcija za uplatu.')?></span>
            </li>
            <li>
                <div class="status5"><?=$this->t('Otkazano')?></div>
                <span><?=$this->t('Uplata je otkazana')?></span>
            </li>
            <li>
                    <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 0.5H22C25.0376 0.5 27.5 2.96243 27.5 6V22C27.5 25.0376 25.0376 27.5 22 27.5H6C2.96243 27.5 0.5 25.0376 0.5 22V6C0.5 2.96243 2.96243 0.5 6 0.5Z" fill="white"/>
                        <path d="M6 0.5H22C25.0376 0.5 27.5 2.96243 27.5 6V22C27.5 25.0376 25.0376 27.5 22 27.5H6C2.96243 27.5 0.5 25.0376 0.5 22V6C0.5 2.96243 2.96243 0.5 6 0.5Z" stroke="#262185"/>
                        <g clip-path="url(#clip0_638_2787)">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M11.4297 8.42856C11.2024 8.42856 10.9843 8.51886 10.8236 8.67961C10.6629 8.84035 10.5725 9.05837 10.5725 9.2857V10.5714H17.4297V9.2857C17.4297 9.05837 17.3393 8.84036 17.1786 8.67961C17.0179 8.51886 16.7999 8.42856 16.5725 8.42856H11.4297ZM9.7154 11.6428C9.3744 11.6428 9.04738 11.7783 8.80626 12.0194C8.56515 12.2605 8.42969 12.5876 8.42969 12.9286V16.5714C8.42969 16.9124 8.56515 17.2395 8.80626 17.4806C9.04738 17.7217 9.3744 17.8571 9.7154 17.8571H10.3583V16.3571C10.3583 15.5287 11.0298 14.8571 11.8583 14.8571H16.144C16.9724 14.8571 17.644 15.5287 17.644 16.3571V17.8571H18.2868C18.6278 17.8571 18.9549 17.7217 19.196 17.4806C19.4371 17.2395 19.5725 16.9124 19.5725 16.5714V12.9286C19.5725 12.5876 19.4371 12.2605 19.196 12.0194C18.9549 11.7783 18.6278 11.6428 18.2868 11.6428H9.7154ZM16.5725 12.8214C16.2767 12.8214 16.0368 13.0613 16.0368 13.3571C16.0368 13.653 16.2767 13.8928 16.5725 13.8928H17.4297C17.7256 13.8928 17.9654 13.653 17.9654 13.3571C17.9654 13.0613 17.7256 12.8214 17.4297 12.8214H16.5725ZM11.8583 15.9286C11.6216 15.9286 11.4297 16.1204 11.4297 16.3571V18.7143C11.4297 18.9416 11.52 19.1596 11.6807 19.3204C11.8415 19.4811 12.0595 19.5714 12.2868 19.5714H15.7154C15.9427 19.5714 16.1607 19.4811 16.3215 19.3204C16.4822 19.1596 16.5725 18.9416 16.5725 18.7143V16.3571C16.5725 16.1204 16.3807 15.9286 16.144 15.9286H11.8583Z" fill="#262185"/>
                        </g>
                        <defs>
                            <clipPath id="clip0_638_2787">
                                <rect width="12" height="12" fill="white" transform="translate(8 8)"/>
                            </clipPath>
                        </defs>
                    </svg>
                <span><?=$this->t('Oštampajte instrukcije za uplatu')?></span>
            </li>
            <li>
                <?php // A picture of the button, not the button. No id: it duplicated the real
                      // one in #paymentDialog, which is invalid HTML and worked only because
                      // getElementById returns the first match and the real one comes first in
                      // the document. No data-umami-event either — clicks here are people
                      // reading the legend, not confirming a payment. The #confirmPayment CSS
                      // is scoped to #paymentDialog, so dropping the id changes nothing visually. ?>
                <div class="donateButton"><?=$this->t('Potvrdi uplatu')?></div>
                <span><?=$this->t('Donator klikom potvrđuje da je izvršio uplatu')?></span>
            </li>
        </ul>
    </div>
<?php endif; ?>
