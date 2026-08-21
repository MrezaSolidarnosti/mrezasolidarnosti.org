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
$projects = [];
if ($data['model']?->projects) {
    foreach ($data['model']->projects as $project) {
        $projects[] = $project->id;
    }
}

$projectCollection = (new OptionCollection())->fromArray($data['projects'], $projects);
$projectSelect = (new \Skeletor\Form\InputTypes\Select\MultipleSelect('projects[]', $projectCollection, 'Project'))
    ->required('Project is required');

$assignedSchools = [];
if ($data['model']?->schools) {
    foreach ($data['model']->schools as $school) {
        $assignedSchools[] = $school;
    }
}

// Three school inputs are always rendered, so the list is padded to three: a delegate
// with fewer schools left the higher indexes undefined. `?? null` did not cover it -
// that suppresses the notice only for a plain variable/property chain, and a method
// call is not one, which is why only the ?->getId() lines ever warned and the
// ?->name lines beside them did not.
$assignedSchools = array_pad($assignedSchools, 3, null);

$school1 = (new \Skeletor\Form\InputTypes\AjaxInputSearch\AjaxInputSearch(
    'schools[0]',
    '/school/tableHandler/',
    'name',
    'id',
    'School 1',
    $assignedSchools[0]?->getId() ?? null,
    $assignedSchools[0]?->name ?? null,
    'Search schools...',
));
$school2 = (new \Skeletor\Form\InputTypes\AjaxInputSearch\AjaxInputSearch(
    'schools[1]',
    '/school/tableHandler/',
    'name',
    'id',
    'School 2',
    $assignedSchools[1]?->getId() ?? null,
    $assignedSchools[1]?->name ?? null,
    'Search schools...',
));
    $school3 = (new \Skeletor\Form\InputTypes\AjaxInputSearch\AjaxInputSearch(
        'schools[2]',
        '/school/tableHandler/',
        'name',
        'id',
        'School 3',
        $assignedSchools[2]?->getId() ?? null,
        $assignedSchools[2]?->name ?? null,
        'Search schools...',
    ));

$basicInfoTab = (new Tab('Basic Info'))
    ->addInputGroup((new InputGroup())->addInput($name)->addInput($email)->addInput($phone))
    ->addInputGroup((new InputGroup())->addInput($school1)->addInput($school2)->addInput($school3))
    ->addInputGroup((new InputGroup())->addInput($projectSelect))
    ->addInputGroup((new InputGroup())->addInput($delegateStatusesSelect)->addInput($verifiedBy))
    ->addInputGroup((new InputGroup(width: InputGroupWidth::HALF_WIDTH))->addInput($comment))
    ->addInputGroup((new InputGroup(width: InputGroupWidth::HALF_WIDTH))->addInput($adminComment));

$form->addTab($basicInfoTab);

$formRenderer = new TabbedFormRenderer($form, $data['formTitle']);
?>
<?= $formRenderer->render() ?>
