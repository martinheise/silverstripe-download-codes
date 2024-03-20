<?php

namespace Mhe\DownloadCodes\Forms;

use Mhe\DownloadCodes\Model\DLCode;
use SilverStripe\Control\Controller;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\RequiredFields;

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
            $obj->dbObject('Expires')->scaffoldFormField(null, []),
            $obj->dbObject('Limited')->scaffoldFormField(null, [])->setValue(true),
            $obj->dbObject('PackageID')->scaffoldFormField(null, []),
            $obj->dbObject('Note')->scaffoldFormField(null, [])
        );

        $actions = new FieldList(
            FormAction::create(
                'generate',
                _t(__CLASS__ . '.GENERATE_CODES', 'Generate Codes'),
                'Generate Codes'
            )->addExtraClass('btn btn-outline-secondary font-icon-upload')
        );

        $validator = new RequiredFields(['Quantity', 'PackageID']);

        parent::__construct($controller, $name, $fields, $actions, $validator);

        $this->setFormAction(
            Controller::join_links($link, $name)
        );
    }
}
