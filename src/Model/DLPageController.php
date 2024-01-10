<?php

namespace Mhe\DownloadCodes\Model;

use Mhe\DownloadCodes\Forms\DLRequestForm;
use PageController;
use SilverStripe\Assets\File;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\ValidationResult;

class DLPageController extends PageController
{

    private static $allowed_actions = [
        "RequestForm",
        "redeem"
    ];

    protected function init()
    {
        parent::init();
    }

    /**
     * controller action – download page after successful code request
     * @param HTTPRequest $request
     * @return array
     */
    public function redeem(HTTPRequest $request)
    {
        $redemption = DLRedemption::get_by_query_params($this->request->getVars());
        if ($redemption) {
            // explicitely grant access to package files
            foreach ($redemption->Code()->Package()->Files() as $file) {
                /* @var File $file */
                $file->grantFile();
            }
            return ["Redemption" => $redemption];
        }
        return $this->httpError(404);
    }

    /**
     * form for code input
     * @return DLRequestForm
     */
    public function RequestForm()
    {
        return new DLRequestForm($this, 'RequestForm');
    }

    /**
     * handle post data of RequestForm
     * @param $data
     * @param DLRequestForm $form
     * @return \SilverStripe\Control\HTTPResponse|null
     */
    public function submitcode($data, DLRequestForm $form)
    {
        $data = $form->getData();
        // check if code is existing and valid
        $dlcode = DLCode::get_redeemable_code($data['Code']);
        if (!$dlcode) {
            $validationResult = new ValidationResult();
            $validationResult->addFieldError(
                'Code',
                _t(DLRequestForm::class . 'VALIDATION_Invalid_Code', 'Invalid code')
            );
            $form->setSessionValidationResult($validationResult);
            $form->setSessionData($form->getData());
            return $this->redirectBack();
        }
        $redemption = $dlcode->redeem();
        $url = Controller::join_links($this->owner->Link('redeem'), $redemption->getUrlParamString());
        return $this->redirect($url);
    }

}
