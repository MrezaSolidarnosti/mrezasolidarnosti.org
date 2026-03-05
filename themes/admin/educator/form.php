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

$statuses = \Solidarity\Educator\Entity\Educator::getHrStatuses();
$statusCollection = (new OptionCollection(new Option('1', 'New')))->fromArray($statuses, $data['model']?->status);
$statusSelect = (new Select('status', $statusCollection, 'Status'));
$name = (new Text('name', $data['model']?->name, 'Name'));
$amount = (new \Skeletor\Form\InputTypes\Input\Number('amount', $data['model']?->amount, 'Amount'));
$accountNumber = (new \Skeletor\Form\InputTypes\Input\Text('accountNumber', $data['model']?->accountNumber, 'Account number'));
$comment = (new \Skeletor\Form\InputTypes\TextArea\TextArea('comment', $data['model']?->comment, 'Comment'));
$periodCollection = (new OptionCollection())->fromArray($data['periods'], $data['model']?->period->id);
$periodSelect = (new Select('period', $periodCollection, 'Period'));

$delegateSelect = (new \Skeletor\Form\InputTypes\AjaxInputSearch\AjaxInputSearch(
    'createdBy',
    '/delegate/tableHandler/',
    'name',
    'id',
    'Created By',
    $data['model']?->createdBy?->id ?? null,
    $data['model']?->createdBy?->name,
    'Search delegates...',
))->required('Delegate is required');


$schoolSelect = (new \Skeletor\Form\InputTypes\AjaxInputSearch\AjaxInputSearch(
    'school',
    '/school/tableHandler/',
    'name',
    'id',
    'School',
    $data['model']?->school?->id ?? null,
    $data['model']?->school?->name,
    'Search schools...',
))->required('School is required');

$form->addTab((new Tab('Basic Info'))
    ->addInputGroup((new InputGroup())
        ->addInput($name))
    ->addInputGroup((new InputGroup())
        ->addInput($accountNumber))
    ->addInputGroup((new InputGroup())
        ->addInput($amount))
    ->addInputGroup((new InputGroup())
        ->addInput($statusSelect))
    ->addInputGroup((new InputGroup(width: InputGroupWidth::HALF_WIDTH))
        ->addInput($schoolSelect)
        ->addInput($delegateSelect)
    )
    ->addInputGroup((new InputGroup())
        ->addInput($periodSelect))
    ->addInputGroup((new InputGroup())
        ->addInput($comment))
);

$formRenderer = new TabbedFormRenderer($form, $data['formTitle']);
?>
<?= $formRenderer->render() ?>