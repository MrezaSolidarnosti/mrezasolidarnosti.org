<div id="paymentMethodsBeneficiary">
    <div id="injectedInputsForPaymentMethods"></div>
    <?php
    use Solidarity\Beneficiary\Entity\PaymentMethod;

    foreach(PaymentMethod::getHrTypes() as $type => $label):?>
        <?php
            $paymentMethod = getPaymentMethod($type, $paymentMethods);
        ?>
        <div class="paymentMethod">
            <div class="paymentMethodHandle">
                <label>
                    <input <?=$paymentMethod ? 'checked': ''?> type="checkbox" name="paymentMethod" value="<?=$type?>">
                    <span><?=htmlspecialchars($label)?></span>
                </label>
            </div>
            <?php if($type === PaymentMethod::TYPE_BANK_TRANSFER):?>
                <div class="inputContainer<?=$paymentMethod ? '' : ' hidden'?>">
                    <input value="<?=$paymentMethod ? htmlspecialchars($paymentMethod->accountNumber) : ''?>" class="input bankAccount" type="text" placeholder="Bankovni Račun">
                </div>
            <?php endif;?>
            <?php if($type === PaymentMethod::TYPE_WIRE_TRANSFER):?>
                <div class="inputContainer<?=$paymentMethod ? '' : ' hidden'?>">
                    <textarea class="input wireInstructions" placeholder="Instrukcije Za Plaćanje"><?=$paymentMethod ? $paymentMethod->wireInstructions : ''?></textarea>
                </div>

            <?php endif;?>
        </div>
    <?php endforeach;?>
</div>
<?php
function getPaymentMethod($type, $paymentMethods = []) {
        foreach($paymentMethods as $paymentMethod) {
            if($paymentMethod->type === $type) {
                return $paymentMethod;
            }
        }
        return null;
}
?>

