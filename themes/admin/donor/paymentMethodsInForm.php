<div id="paymentMethodsDonor">
    <button id="addPaymentMethodDonor" class="btn small green">+Add Payment Method</button>
    <div id="paymentMethodsDonorList">
        <?php foreach($paymentMethods as $paymentMethod):?>
            <div class="paymentMethodDonor">
                <div class="inputContainer">
                    <label>Projekat</label>
                    <select class="input project">
                        <option value="-1" disabled selected>Izaberite Projekat</option>
                        <?php foreach($projects as $projectId => $label): ?>
                            <option <?=$paymentMethod->project->id === $projectId ? 'selected' : ''?> value="<?=$projectId?>"><?=htmlspecialchars($label)?></option>
                        <?php endforeach;?>
                    </select>
                </div>
                <div class="inputContainer">
                    <label>Način Plaćanja</label>
                    <select class="input paymentType">
                        <option disabled selected value="-1">Izaberite Način Plaćanja</option>
                        <?php foreach(\Solidarity\Donor\Entity\PaymentMethod::getHrTypes() as $type => $label): ?>
                            <option <?=$paymentMethod->type === $type ? 'selected' : ''?> value="<?=$type?>"><?=htmlspecialchars($label)?></option>
                        <?php endforeach;?>
                    </select>
                </div>
                <div class="inputContainer">
                    <label>Mesečno</label>
                    <select class="input monthly">
                        <option <?=$paymentMethod->monthly === 1 ? 'selected' : ''?> value="1">Da</option>
                        <option <?=$paymentMethod->monthly === 0 ? 'selected' : ''?> value="0">Ne</option>
                    </select>
                </div>
                <div class="inputContainer">
                    <label>Iznos</label>
                    <input class="input amount" type="number" value="<?=$paymentMethod->amount ?? ''?>">
                </div>
                <div class="inputContainer">
                    <label>Valuta</label>
                    <input readonly class="input currencyView" type="text" <?=$paymentMethod->currency ?? ''?>>
                    <input type="hidden" class="currencyValue">
                </div>
                <button class="deletePaymentMethod btn red">Delete</button>
            </div>
        <?php endforeach;?>
    </div>
</div>
<template id="donorPaymentMethodsTemplate">
    <div class="paymentMethodDonor">
        <div class="inputContainer">
            <label>Projekat</label>
            <select class="input project">
                <option value="-1" disabled selected>Izaberite Projekat</option>
                <?php foreach($projects as $projectId => $label): ?>
                    <option value="<?=$projectId?>"><?=htmlspecialchars($label)?></option>
                <?php endforeach;?>
            </select>
        </div>
        <div class="inputContainer">
            <label>Način Plaćanja</label>
            <select class="input paymentType">
                <option disabled selected value="-1">Izaberite Način Plaćanja</option>
                <?php foreach(\Solidarity\Donor\Entity\PaymentMethod::getHrTypes() as $type => $label): ?>
                    <option value="<?=$type?>"><?=htmlspecialchars($label)?></option>
                <?php endforeach;?>
            </select>
        </div>
        <div class="inputContainer">
            <label>Mesečno</label>
            <select class="input monthly">
                <option value="1">Da</option>
                <option value="0">Ne</option>
            </select>
        </div>
        <div class="inputContainer">
            <label>Iznos</label>
            <input class="input amount" type="number">
        </div>
        <div class="inputContainer">
            <label>Valuta</label>
            <input readonly class="input currencyView" type="text">
            <input type="hidden" class="currencyValue">
        </div>
        <button class="deletePaymentMethod btn red">Delete</button>
    </div>
</template>