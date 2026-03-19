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

$action = $data['dataAction'] === 'create' ? 'Kreiraj' : 'Izmeni';

$statuses = \Solidarity\Donor\Entity\Donor::getHrStatuses();
$statusCollection = (new OptionCollection(new Option('1', 'New')))->fromArray($statuses, $data['model']?->status);
$statusSelect = (new Select('status', $statusCollection, 'Status'))
    ->required('Status je neophodan', '');
$email = (new Email('email', $data['model']?->email, 'Email'));
//    ->emailInvalidMessage('Email is invalid');
$firstName = (new Text('firstName', $data['model']?->firstName, 'Ime'))
    ->required('Ime je neophodno')
    ->minLength(2, 'Ime mora da sadrži bar 2 karaktera');
$lastName = (new Text('lastName', $data['model']?->lastName, 'Prezime'))
    ->required('Prezime je neophodno')
    ->minLength(2, 'Prezime mora da sadrži bar 2 karaktera');
$comment = (new \Skeletor\Form\InputTypes\TextArea\TextArea('comment', $data['model']?->comment, 'Comment'));
$donationOptionsCollection = (new OptionCollection(new Option('1', 'Svima')))->fromArray(\Solidarity\Donor\Entity\Donor::getHrDonationOptions(), $data['model']?->wantsToDonateTo);
$donationOptionsSelect = (new Select('wantsToDonateTo', $donationOptionsCollection, 'Bira da donira za'));
$isActive = [1 => 'Da', 0 => 'Ne'];
$isActiveCollection = (new OptionCollection(new Option('1', 'Da')))->fromArray($isActive, $data['model']?->isActive);
$isActiveSelect = (new Select('isActive', $isActiveCollection, 'Aktivno'));
$projects = [];
foreach ($data['model']?->projects ?? [] as $project) {
    $projects[] = $project->id;
}
$projectCollection = (new OptionCollection())->fromArray($data['projects'], $projects);
$projectSelect = (new \Skeletor\Form\InputTypes\Select\MultipleSelect('projects[]', $projectCollection, 'Project'))
    ->required('Projekat je neophodan');

$inputGroup1 = (new InputGroup())
    ->addInput($email)
    ->addInput($comment);

$form->addTab((new Tab('Osnovne Info'))
    ->addInputGroup($inputGroup1)
    ->addInputGroup((new InputGroup())
        ->addInput($firstName)
        ->addInput($isActiveSelect))
    ->addInputGroup((new InputGroup())
        ->addInput($lastName)
        ->addInput($donationOptionsSelect))
    ->addInputGroup((new InputGroup())
        ->addInput($statusSelect)
        ->addInput($projectSelect))
);

$formRenderer = new TabbedFormRenderer($form, $data['formTitle']);
$paymentMethodsTab = (new Tab('Načini plaćanja'))
    ->addInputGroup((new InputGroup(width: InputGroupWidth::FULL_WIDTH)));
$paymentMethodsHTML = $this->fetch('/donor/paymentMethodsInForm',
    ['projects' => $data['projects'], 'paymentMethods' => $data['paymentMethods']
]);

$form->addTab($paymentMethodsTab);
$formRenderer->setAdditionalTabContent($paymentMethodsTab, $paymentMethodsHTML);
?>
<?= $formRenderer->render() ?>