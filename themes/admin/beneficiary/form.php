<?php

use Skeletor\Form\InputGroup\InputGroup;
use Skeletor\Form\InputGroup\InputGroupWidth;
use Skeletor\Form\InputTypes\Input\Text;
use Skeletor\Form\InputTypes\Select\Collection\OptionCollection;
use Skeletor\Form\InputTypes\Select\Option;
use Skeletor\Form\InputTypes\Select\Select;
use Skeletor\Form\Renderer\TabbedFormRenderer;
use Skeletor\Form\Tab\Tab;
use Skeletor\Form\TabbedForm;

$form = new TabbedForm($data['formAction'], $data['dataAction'], $this->formTokenArray());

$action = $data['dataAction'] === 'create' ? 'Kreiraj' : 'Izmeni';

$statuses = \Solidarity\Beneficiary\Entity\Beneficiary::getHrStatuses();
$statusCollection = (new OptionCollection(new Option('1', 'New')))->fromArray($statuses, $data['model']?->status);
$statusSelect = (new Select('status', $statusCollection, 'Status'));
$name = (new Text('name', $data['model']?->name, 'Name'))->required("Ime je obavezno");
$comment = (new \Skeletor\Form\InputTypes\TextArea\TextArea('comment', $data['model']?->comment, 'Komentar'));

$schoolSelect = (new \Skeletor\Form\InputTypes\AjaxInputSearch\AjaxInputSearch(
    'school',
    '/school/tableHandler/',
    'name',
    'id',
    'Škola',
    $data['model']?->school?->id ?? null,
    $data['model']?->school?->name,
    'Trazi škole...',
    ['delegate' => 'not_null'],
));

$schoolGroup = (new InputGroup())
    ->addInput($name)
    ->addInput($schoolSelect);

if ($data['dataAction'] === 'update' && $data['model']?->school?->delegate) {
    $delegateInfo = (new \Skeletor\Form\InputTypes\AjaxInputSearch\AjaxInputSearch(
        'delegateInfo',
        '/delegate/tableHandler/',
        'name',
        'id',
        'Delegat',
        $data['model']->school->delegate->id,
        $data['model']->school->delegate->name,
        '',
        [], [], null, null, true
    ));
    $schoolGroup->addInput($delegateInfo);
}

$basicInfo = (new Tab('Osnovne Info'))
    ->addInputGroup($schoolGroup)
    ->addInputGroup((new InputGroup())
        ->addInput($statusSelect))
    ->addInputGroup((new InputGroup(width: InputGroupWidth::HALF_WIDTH))
        ->addInput($comment)
    );

$form->addTab($basicInfo);

$formRenderer = new TabbedFormRenderer($form, $data['formTitle']);

$existingRegisteredPeriods = [];
if ($data['model']?->registeredPeriods) {
    foreach ($data['model']->registeredPeriods as $rp) {
        $existingRegisteredPeriods[] = [
            'period' => $rp->period->getId(),
            'project' => $rp->project->getId(),
            'amount' => $rp->amount,
        ];
    }
}

$registeredProjectsTab = (new Tab('Registrovani Projekti'))
    ->addInputGroup((new InputGroup(width: InputGroupWidth::FULL_WIDTH)));

$registeredPeriodsHTML = $this->fetch('/beneficiary/registeredProjectsInForm',
    ['projects' => $data['assignedProjects'], 'periods' => $data['assignedPeriods'], 'existingRegisteredPeriods' => $existingRegisteredPeriods]
);
$formRenderer->setAdditionalTabContent($registeredProjectsTab, $registeredPeriodsHTML);
$form->addTab($registeredProjectsTab);

$paymentMethodsTab = (new Tab('Načini plaćanja'))
    ->addInputGroup((new InputGroup(width: InputGroupWidth::FULL_WIDTH)));
$paymentMethodsHTML = $this->fetch('/beneficiary/paymentMethodsInForm', ['paymentMethods' => $data['paymentMethods']]);
$formRenderer->setAdditionalTabContent($paymentMethodsTab, $paymentMethodsHTML);
$form->addTab($paymentMethodsTab);

?>
<?= $formRenderer->render() ?>
