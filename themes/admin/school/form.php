<?php

use Skeletor\Form\InputGroup\InputGroup;
use Skeletor\Form\InputGroup\InputGroupWidth;
use Skeletor\Form\InputTypes\ContentEditor\ContentEditor;
use Skeletor\Form\InputTypes\Input\Email;
use Skeletor\Form\InputTypes\Input\Checkbox;
use Skeletor\Form\InputTypes\Input\Hidden;
use Skeletor\Form\InputTypes\Input\Password;
use Skeletor\Form\InputTypes\Input\Text;
use Skeletor\Form\InputTypes\Select\Collection\OptionCollection;
use Skeletor\Form\InputTypes\Select\Option;
use Skeletor\Form\InputTypes\Select\Select;
use Skeletor\Form\Renderer\TabbedFormRenderer;
use Skeletor\Form\Tab\Tab;
use Skeletor\Form\TabbedForm;

$form = new TabbedForm($data['formAction'], $data['dataAction'], $this->formTokenArray());

$action = $data['dataAction'] === 'create' ? 'Create' : 'Edit';
$readOnly = $data['readOnly'] ?? false;

$statuses = \Solidarity\Delegate\Entity\Delegate::getHrStatuses();
$formLinkSent = [1 => 'Yes', 0 => 'No'];
$cityCollection = (new OptionCollection())->fromArray($data['cities'], $data['model']?->city->id);
$citySelect = (new Select(name: 'city', optionsCollection: $cityCollection, label: 'City', readOnly: $readOnly));
$typeCollection = (new OptionCollection())->fromArray($data['types'], $data['model']?->type->id);
// Named for the entity property, not the table column: AbstractFactory matches posted
// keys against property names, so a 'schoolType' key left School::$type unmatched and
// fell through to its null branch - which silently wiped the type back when the
// property was nullable, and is a TypeError now that it is not.
$typeSelect = (new Select(name: 'type', optionsCollection: $typeCollection, label: 'School type', readOnly: $readOnly));

$name = (new Text(name: 'name', value: $data['model']?->name, label: 'Name', readOnly: $readOnly));

$inputGroup1 = (new InputGroup())
    ->addInput($name);
$inputGroup2 = (new InputGroup())
    ->addInput($citySelect);
$inputGroup3 = (new InputGroup())
    ->addInput($typeSelect);

$form->addTab((new Tab('Basic Info'))
    ->addInputGroup($inputGroup1)
    ->addInputGroup($inputGroup2)
    ->addInputGroup($inputGroup3)
);

$formRenderer = new TabbedFormRenderer($form, $data['formTitle']);

if ($data['dataAction'] === 'update' && !empty($data['schoolStats'])) {
    $statsTab = (new Tab('Statistika'))
        ->addInputGroup((new InputGroup(width: InputGroupWidth::FULL_WIDTH)));
    $statsHTML = $this->fetch('/school/statisticsInForm', ['schoolStats' => $data['schoolStats']]);
    $formRenderer->setAdditionalTabContent($statsTab, $statsHTML);
    $form->addTab($statsTab);
}
?>
<?= $formRenderer->render() ?>