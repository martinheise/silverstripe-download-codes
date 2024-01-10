<?php

namespace Mhe\DownloadCodes\Forms;

use Mhe\DownloadCodes\Model\DLPageController;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextField;

/**
 * Form to request the redemption of a download code by a user
 * submission is handled by action @see DLPageController::redeem()
 */
class DLRequestForm extends Form
{
    public function __construct(RequestHandler $controller = null, $name = self::DEFAULT_NAME)
    {
        $fields = $this->getFormFields();
        $actions = new FieldList(
            FormAction::create('submitcode', _t(__CLASS__ . '.ACTION_submitcode', 'Submit'))
        );
        $validator = new RequiredFields('Code');;
        parent::__construct($controller, $name, $fields, $actions, $validator);
    }

    /**
     * Get the FieldList for the form, possibly using extensions
     *
     * @return FieldList
     */
    protected function getFormFields()
    {
        $fields = FieldList::create(
            TextField::create('Code', _t(__CLASS__ . '.FIELD_Code', 'Code'))
        );
        $this->extend('updateFormFields', $fields);
        return $fields;
    }
}
