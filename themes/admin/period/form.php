<?php

use Skeletor\Form\InputGroup\InputGroup;
use Skeletor\Form\InputGroup\InputGroupWidth;
use Skeletor\Form\InputTypes\Input\Checkbox;
use Skeletor\Form\InputTypes\Input\Number;
use Skeletor\Form\InputTypes\Select\Collection\OptionCollection;
use Skeletor\Form\InputTypes\Select\Option;
use Skeletor\Form\InputTypes\Select\Select;
use Skeletor\Form\Renderer\TabbedFormRenderer;
use Skeletor\Form\Tab\Tab;
use Skeletor\Form\TabbedForm;
use Solidarity\Period\Entity\Period;

$form = new TabbedForm($data['formAction'], $data['dataAction'], $this->formTokenArray());

$action = $data['dataAction'] === 'create' ? 'Kreiraj' : 'Izmeni';

// A dropdown rather than a number box: the months are a closed set and the entity already
// names them for the table. Required, like the project select and for the same reason — the
// month column is NOT NULL, so a blank submission has nowhere to go. -1 is the sentinel this
// codebase uses for "nothing chosen" (OptionCollection's own "---" placeholder value).
$monthCollection = (new OptionCollection())->fromArray(Period::getHrMonths(), $data['model']?->month);
$month = (new Select('month', $monthCollection, 'Mesec'))
    ->required('Morate izabrati mesec', -1);
$year = (new Number('year', $data['model']?->year, 'Godina'));
$maxAmount = (new Number('maxAmount', $data['model']?->maxAmount, 'Max iznos', null, [], null, 'Ako je 0, primenjuje se globalni limit od: ' . \Solidarity\Beneficiary\Entity\Beneficiary::MONTHLY_LIMIT));
$types = [
    Period::TYPE_FIRST_HALF => 'First half',
    Period::TYPE_SECOND_HALF => 'Second half',
    Period::TYPE_FULL => 'Full',
];
$typeCollection = (new OptionCollection())->fromArray($types, $data['model']?->type);
$typeSelect = (new Select('type', $typeCollection, 'Type', [], null, 'Ceo mesec ili pola meseca'));
$projectCollection = (new OptionCollection())->fromArray($data['projects'], $data['model']?->project->id);
$projectSelect = (new Select('project', $projectCollection, 'Projekat'))
    ->required('Morate izabrati projekat', -1);

$active = (new Checkbox('active', $data['model']?->active ?? false, 'Active'));
$processing = (new Checkbox('processing', $data['model']?->processing ?? false, 'Processing'));

$form->addTab((new Tab('Osnovne Info'))
    ->addInputGroup((new InputGroup())
        ->addInput($month)
        ->addInput($active))
    ->addInputGroup((new InputGroup())
        ->addInput($year)
        ->addInput($processing))
    ->addInputGroup((new InputGroup())
        ->addInput($typeSelect)
        ->addInput($maxAmount))
    ->addInputGroup((new InputGroup())
        ->addInput($projectSelect))
);

$formRenderer = new TabbedFormRenderer($form, $data['formTitle']);
?>
<?= $formRenderer->render() ?>
