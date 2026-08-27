<?php if(isset($block)): ?>
    <div class="messagesContainer">
        <?php if (isset($_GET['message'])): ?>
            <?php if ($_GET['message'] == 'saved'): ?>
            <span class="success">Uspešno ste sačuvali podešavanja za mesečne donacije.</span>
            <?php else: ?>
            <span class="success">Uspešno ste kreirali instrukciju.</span>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="whatIsSection instructionsWhatIs">
        <div>
            <h1><?=nl2br(htmlentities($block['title'] ?? ''))?></h1><br />
            <p><?=nl2br(htmlentities($block['description'] ?? ''))?></p><br />
            <?php if(!empty($block['linkText'])): ?>
                <div class="instructionsLink">
                    <svg width="17" height="17" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6.85054 1.36864C7.60514 0.614033 8.82874 0.60944 9.5777 1.3584L14.8463 6.62697C14.0916 6.00765 12.9715 6.0637 12.2638 6.77147C11.556 7.47924 11.4995 8.72377 12.2329 9.47797L12.2794 9.52446C13.0284 10.2527 14.2365 10.243 14.9859 9.49356L9.50585 14.9736C8.74604 15.7334 7.52765 15.7328 6.77869 14.9838C6.02972 14.2348 6.03432 13.0113 6.78892 12.2566L9.08397 9.9616L2.73156 10.1467C1.6635 10.1507 0.800904 9.2881 0.810079 8.22521C0.814088 7.15715 1.68319 6.28805 2.74604 6.28925L8.82833 6.10569L6.8397 4.11707C6.09074 3.3681 6.09533 2.14451 6.84994 1.3899L6.85001 1.36916L6.85054 1.36864Z" fill="#FE5101"/>
                        <path d="M13.2302 10.611L14.2687 10.185L16.3323 8.12127L13.8692 5.65816L12.2316 6.36381L10.8336 8.00145L13.2302 10.611Z" fill="#FE5101"/>
                    </svg>
                    <?=$block['linkText'] ?? ''?>
                </div>
            <?php endif; ?>
        </div>

    </div>
    <div class="whatIsSection instructionsWhatIs infoWhatIs">
        <div class="infoSection">
            <svg width="25" height="25" viewBox="0 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M15.7148 0.149597C16.7319 0.149597 17.6891 0.547095 18.4082 1.26776L23.2773 6.1496C23.9965 6.87047 24.3936 7.82951 24.3936 8.84882V15.7512C24.3936 16.7705 23.9965 17.7295 23.2773 18.4504L21.4326 20.298L21.4336 20.299L21.3281 20.4045L21.2227 20.5109L21.2217 20.5099L18.4082 23.3322C17.6891 24.0529 16.7319 24.4504 15.7148 24.4504H8.8291C7.81209 24.4504 6.85485 24.0529 6.13574 23.3322L1.2666 18.4504C0.547458 17.7295 0.150391 16.7705 0.150391 15.7512V8.84882C0.150391 7.8295 0.547458 6.87047 1.2666 6.1496L6.13574 1.26776C6.85485 0.547119 7.81209 0.149597 8.8291 0.149597H15.7148ZM8.8291 1.91718C8.28275 1.91718 7.76946 2.13018 7.38281 2.51776L2.51367 7.39862C2.12706 7.78589 1.91406 8.30079 1.91406 8.84882V15.7512C1.91406 16.2992 2.12701 16.8138 2.51367 17.2014L7.38281 22.0822C7.76913 22.4697 8.28272 22.6828 8.8291 22.6828H15.7148C16.2612 22.6828 16.7745 22.4698 17.1611 22.0822L20.0811 19.1555L20.1875 19.049L22.0303 17.2014C22.4169 16.8141 22.6299 16.2992 22.6299 15.7512V8.84882C22.6299 8.30082 22.4169 7.78622 22.0303 7.39862L17.1611 2.51776C16.7748 2.13023 16.2612 1.91718 15.7148 1.91718H8.8291Z" fill="#CF3443" stroke="#CF3443" stroke-width="0.3"/>
                <path d="M13.2949 6.5199H11.2461V14.2069H13.2949V6.5199Z" fill="#CF3443"/>
                <path d="M13.2949 16.3781H11.2461V18.0798H13.2949V16.3781Z" fill="#CF3443"/>
            </svg>
            <div>
                <p><?=$this->t('<b>Ukoliko ste odabrali mesečne donacije</b>, instrukcije za uplatu se automatski generišu ponedeljkom i četvrtkom u 7:00 časova i važe ograničeno vreme.')?></p>
                <p><?=$this->t('<b>Instrukcije se mogu generisati i drugim danima</b>, kada postoji potreba za donacijom. Za ovu opciju je potrebno izabrati jednokratnu donaciju.')?></p>
                <p><?=$this->t('<b>Sve aktivne instrukcije</b> možete videti i na ovoj stranici u tabeli ispod, u zelenom polju, gde se nalazi dugme "Uplati". Klikom na dugme možete preuzeti instrukcije za uplatu.')?></p><br />
                <h2><?=htmlentities($block['infoTitle'] ?? '')?></h2>
                <p><?=nl2br(htmlentities($block['infoDescription'] ?? ''))?></p>
            </div>
        </div>
    </div>
<?php endif; ?>
