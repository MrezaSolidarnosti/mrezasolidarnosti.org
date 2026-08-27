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

// Always editable, and always posted under the same name.
//
// It used to render read-only whenever the stored school carried a delegate, on the grounds
// that the school owns it for MSP and the filter would override anything posted anyway. That
// locked the form: the school field above is editable, so clearing it left a delegate field
// that could not be filled in and posted nothing under `delegate` — the validator then
// refused the save with no way for the user to satisfy it. The branch was decided from the
// stored model, but the input it depended on can change in the browser.
//
// Precedence still lives in the filter, which is the only place that sees the submitted
// school and delegate together: a school's delegate wins when a school is chosen, and this
// field is the fallback when there is none. So for MSP nothing changes on save, and for MSPR
// (no school) the delegate is chosen here. The tooltip says as much, since a field that can
// be edited but is sometimes overridden needs to explain itself.
$delegateInput = (new \Skeletor\Form\InputTypes\AjaxInputSearch\AjaxInputSearch(
    'delegate',
    '/delegate/tableHandler/',
    'name',
    'id',
    'Delegat',
    $data['model']?->createdBy?->id ?? null,
    $data['model']?->createdBy?->name,
    'Traži delegate...',
    tooltip: 'Ako je izabrana škola, delegat se preuzima sa škole. Bez škole (MSPR), izaberite delegata ovde.',
));
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
