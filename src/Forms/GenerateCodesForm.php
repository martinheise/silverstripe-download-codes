<?php

namespace Mhe\DownloadCodes\Forms;

use Mhe\DownloadCodes\Model\DLCode;
use SilverStripe\Control\Controller;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;

/**
 * Form for bulk code generation in Admin
 */
class GenerateCodesForm extends Form
{
    public function __construct(RequestHandler $controller, $name, DLCode $obj, $link)
    {
        $fields = new FieldList(
        // ToDo: limit quantity?
            NumericField::create(
                'Quantity',
                _t(__CLASS__ . '.FORM_Quantity', 'Quantity')
            )->setValue(10),
            $obj->dbObject('Expires')->scaffoldFormField(),
            $obj->dbObject('Limited')->scaffoldFormField()->setValue(true),
            $obj->dbObject('PackageID')->scaffoldFormField(),
            $obj->dbObject('Note')->scaffoldFormField()
        );

        $actions = new FieldList(
            FormAction::create(
                'generate',
                _t(__CLASS__ . '.GENERATE_CODES', 'Generate Codes'),
                'Generate Codes'
            )->addExtraClass('btn btn-outline-secondary font-icon-upload')
        );

        $validator = new RequiredFieldsValidator(['Quantity', 'PackageID']);

        parent::__construct($controller, $name, $fields, $actions, $validator);

        $this->setFormAction(
            Controller::join_links($link, $name)
        );
    }
}
