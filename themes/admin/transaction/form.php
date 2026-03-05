<?php

use Skeletor\Form\InputGroup\InputGroup;
use Skeletor\Form\InputGroup\InputGroupWidth;
use Skeletor\Form\InputTypes\ContentEditor\ContentEditor;
use Skeletor\Form\InputTypes\Input\Email;
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
$readonly = $data['dataAction'] === 'create' ? false : true;

$statuses = \Solidarity\Transaction\Entity\Transaction::getHrStatuses();
$statusCollection = (new OptionCollection(new Option('1', 'New')))->fromArray($statuses, $data['model']?->status);
$statusSelect = (new Select('status', $statusCollection, 'Status'))
    ->required('Status is required', '');
//$name = (new Text(name: 'name', value: $data['model']?->name, label:'Name', readOnly: true));
$amount = (new \Skeletor\Form\InputTypes\Input\Number(name: 'amount', value: $data['model']?->amount, label:'Amount'))
    ->required('amount is required');
$accountNumber = (new \Skeletor\Form\InputTypes\Input\Text(name: 'accountNumber', value: $data['model']?->accountNumber, label:'Account number', readOnly: $readonly));
$comment = (new \Skeletor\Form\InputTypes\TextArea\TextArea(name:'comment', value:$data['model']?->comment, label:'Comment'));
//$educator = (new Hidden(name: 'educator', value: $data['model']?->educator->id));
//$donor = (new Hidden(name: 'donor', value: $data['model']?->donor->id));

$donorSelect = (new \Skeletor\Form\InputTypes\AjaxInputSearch\AjaxInputSearch(
    'donor',
    '/donor/tableHandler/',
    'email',
    'id',
    'Donor',
    $data['model']?->donor?->id ?? null,
    $data['model']?->donor?->email,
    'Search donors...',
    [], [], null, null, $readonly
))->required('Donor is required');

$educatorSelect = (new \Skeletor\Form\InputTypes\AjaxInputSearch\AjaxInputSearch(
    'educator',
    '/educator/tableHandler/',
    'name',
    'id',
    'Educator',
    $data['model']?->educator?->id ?? null,
    $data['model']?->educator?->name,
    'Search educators...',
    [], [], null, null, $readonly
))->required('Educator is required');

$inputGroup = (new InputGroup())
    ->addInput($donorSelect);
if ($readonly) {
    $inputGroup->addInput($accountNumber);
}

$form->addTab((new Tab('Basic Info'))
    ->addInputGroup($inputGroup)
    ->addInputGroup((new InputGroup())
        ->addInput($educatorSelect))
    ->addInputGroup((new InputGroup())
        ->addInput($amount)
        ->addInput($comment))
    ->addInputGroup((new InputGroup())
        ->addInput($statusSelect))
);

$formRenderer = new TabbedFormRenderer($form, $data['formTitle']);
?>
<?= $formRenderer->render() ?>