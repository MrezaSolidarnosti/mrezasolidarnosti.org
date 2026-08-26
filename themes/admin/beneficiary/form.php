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

// The delegate is always on the form now, but which way round depends on whether a school
// carries one. MSP: the school owns the delegate, so it stays read-only and informational —
// posting it would change nothing, since the filter takes the school's delegate first.
// MSPR: there are no schools (MigrateLegacyMspr sets school = null), so nothing can be
// derived and the delegate is chosen here directly. Deciding this server-side off the stored
// model keeps it free of JS; the filter applies the same precedence on save either way.
if ($data['model']?->school?->delegate) {
    $delegateInput = (new \Skeletor\Form\InputTypes\AjaxInputSearch\AjaxInputSearch(
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
} else {
    $delegateInput = (new \Skeletor\Form\InputTypes\AjaxInputSearch\AjaxInputSearch(
        'delegate',
        '/delegate/tableHandler/',
        'name',
        'id',
        'Delegat',
        $data['model']?->createdBy?->id ?? null,
        $data['model']?->createdBy?->name,
        'Traži delegate...'
    ));
}
$schoolGroup->addInput($delegateInput);

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
            // Posted back as a hidden input so the save can match this row to its stored
            // counterpart instead of rebuilding the list from the dropdowns — see
            // BeneficiaryFactory::syncRegisteredPeriods().
            'id' => $rp->getId(),
            'period' => $rp->period->getId(),
            'project' => $rp->project->getId(),
            'amount' => $rp->amount,
        ];
    }
}

$registeredProjectsTab = (new Tab('Registrovani Projekti'))
    ->addInputGroup((new InputGroup(width: InputGroupWidth::FULL_WIDTH)));

$registeredPeriodsHTML = $this->fetch('/beneficiary/registeredProjectsInForm',
    ['projects' => $data['assignedProjects'], 'periods' => $data['assignedPeriods'], 'existingRegisteredPeriods' => $existingRegisteredPeriods, 'confirmedAmounts' => $data['confirmedAmounts'] ?? []]
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
