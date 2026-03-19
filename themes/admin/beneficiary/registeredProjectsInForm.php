<div id="registeredProjects">
    <button id="addRegisteredPeriod" class="btn small green">+Add Period</button>
    <div id="registeredProjectsList">
        <?php foreach($existingRegisteredPeriods as $registeredPeriod): ?>
            <div class="registeredPeriod">
                <div class="inputContainer">
                    <label>Projekat</label>
                    <select class="input registeredProjectSelect">
                        <option value="-1" disabled selected>Izaberite Projekat</option>
                        <?php foreach($projects as $project): ?>
                            <option <?=$project->id === $registeredPeriod['project'] ? 'selected' : ''?> value="<?=$project->id?>"><?=$project->name?></option>
                        <?php endforeach;?>
                    </select>
                </div>
                <div class="inputContainer">
                    <label>Period</label>
                    <select class="input registeredPeriodSelect">
                        <option disabled selected value="-1">Izaberite Period</option>
                        <?php foreach($periods as $period): ?>
                            <option <?=$period->id === $registeredPeriod['period'] ? 'selected' : ''?> data-project-id="<?=$period->project->id?>" value="<?=$period->id?>"><?=$period->getLabel()?></option>
                        <?php endforeach;?>
                    </select>
                </div>
                <div class="inputContainer">
                    <label>Amount</label>
                    <input class="input registeredAmountInput" type="number" value="<?=$registeredPeriod['amount']?>">
                </div>
                <button class="deleteRegisteredPeriod btn red">Delete</button>
            </div>
        <?php endforeach;?>
    </div>
</div>
<template id="registeredProjectTemplate">
    <div class="registeredPeriod">
        <div class="inputContainer">
            <label>Projekat</label>
            <select class="input registeredProjectSelect">
                <option value="-1" disabled selected>Izaberite Projekat</option>
                <?php foreach($projects as $project): ?>
                    <option value="<?=$project->id?>"><?=$project->name?></option>
                <?php endforeach;?>
            </select>
        </div>
        <div class="inputContainer">
            <label>Period</label>
            <select disabled class="input registeredPeriodSelect">
                <option disabled selected value="-1">Izaberite Period</option>
                <?php foreach($periods as $period): ?>
                    <option data-project-id="<?=$period->project->id?>" value="<?=$period->id?>"><?=htmlspecialchars($period->getLabel())?></option>
                <?php endforeach;?>
            </select>
        </div>
        <div class="inputContainer">
            <label>Amount</label>
            <input class="input registeredAmountInput" type="number">
        </div>
        <button class="deleteRegisteredPeriod btn red">Delete</button>
    </div>
</template>

