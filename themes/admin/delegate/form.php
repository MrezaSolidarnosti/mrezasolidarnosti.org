<?php

use Skeletor\Form\InputGroup\InputGroup;
use Skeletor\Form\InputGroup\InputGroupWidth;
use Skeletor\Form\InputTypes\Input\Email;
use Skeletor\Form\InputTypes\Input\Text;
use Skeletor\Form\InputTypes\Select\Collection\OptionCollection;
use Skeletor\Form\InputTypes\Select\Select;
use Skeletor\Form\Renderer\TabbedFormRenderer;
use Skeletor\Form\Tab\Tab;
use Skeletor\Form\TabbedForm;

$form = new TabbedForm($data['formAction'], $data['dataAction'], $this->formTokenArray());

$action = $data['dataAction'] === 'create' ? 'Create' : 'Edit';

$delegateStatuses = \Solidarity\Delegate\Entity\Delegate::getHrStatuses();
$delegateStatusesCollection = (new OptionCollection())->fromArray($delegateStatuses, $data['model']?->status);
$delegateStatusesSelect = (new Select('status', $delegateStatusesCollection, 'Status'));
//    ->required('Status je obavezan');
$phone = (new Text('phone', $data['model']?->phone, 'Phone'));
$email = (new Email('email', $data['model']?->email, 'Email', null, [], null, null, ($action === 'Create') ? false:true))
    ->required('Email je obavezan')
    ->emailInvalidMessage('Email nije validan');
$name = (new Text('name', $data['model']?->name, 'Name'));
//    ->required('Phone is required');
$verifiedBy = (new Text('verifiedBy', $data['model']?->verifiedBy, 'Verified By'));
$comment = (new \Skeletor\Form\InputTypes\TextArea\TextArea('comment', $data['model']?->comment, 'Comment'));
$adminComment = (new \Skeletor\Form\InputTypes\TextArea\TextArea('adminComment', $data['model']?->adminComment, 'Admin comment'));
$schoolSelect = (new \Skeletor\Form\InputTypes\AjaxInputSearch\AjaxInputSearch(
    'school[id]',
    '/school/tableHandler/',
    'name',
    'id',
    'School',
    $data['model']?->school?->id ?? null,
    $data['model']?->school?->name,
    'Search schools...',
));

$basicInfoTab = (new Tab('Basic Info'))
    ->addInputGroup((new InputGroup())->addInput($name))
    ->addInputGroup((new InputGroup())->addInput($email))
    ->addInputGroup((new InputGroup())->addInput($delegateStatusesSelect))
    ->addInputGroup((new InputGroup())->addInput($phone))
    ->addInputGroup((new InputGroup())->addInput($schoolSelect))
    ->addInputGroup((new InputGroup())->addInput($verifiedBy))
    ->addInputGroup((new InputGroup(width: InputGroupWidth::HALF_WIDTH))->addInput($comment))
    ->addInputGroup((new InputGroup(width: InputGroupWidth::HALF_WIDTH))->addInput($adminComment));

$form->addTab($basicInfoTab);

$formRenderer = new TabbedFormRenderer($form, $data['formTitle']);
?>
<?= $formRenderer->render() ?>
