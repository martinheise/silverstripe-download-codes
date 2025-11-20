<?php

namespace Mhe\DownloadCodes\Controller;

use Colymba\BulkManager\BulkAction\DeleteHandler;
use Colymba\BulkManager\BulkManager;
use Mhe\DownloadCodes\Forms\GenerateCodesForm;
use Mhe\DownloadCodes\Forms\GridField\GenerateCodesButton;
use Mhe\DownloadCodes\Forms\GridField\RowValidation;
use Mhe\DownloadCodes\Model\DLCode;
use Mhe\DownloadCodes\Model\DLPackage;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Dev\CsvBulkLoader;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\View\Requirements;

class DLCodeAdmin extends ModelAdmin
{
    private static string $url_segment = 'dlcodes';

    private static array $managed_models = [
        'packages' => ['title' => "Download Packages", 'dataClass' => DLPackage::class],
        'codes' => ['title' => "Download Codes", 'dataClass' => DLCode::class]
    ];

    private static array $model_importers = [
        // duplicate entry because of bug in ModelAdmin, see https://github.com/silverstripe/silverstripe-admin/issues/1364
        'codes' => CsvBulkLoader::class,
        DLCode::class => CsvBulkLoader::class
    ];

    private static string $required_permission_codes = 'CMS_ACCESS_DLCodeAdmin';

    private static string $menu_icon_class = 'font-icon-down-circled';

    private static array $allowed_actions = [
        'GenerateForm'
    ];

    protected function init(): void
    {
        parent::init();
        Requirements::css('mhe/silverstripe-download-codes:client/dist/css/admin.css');
    }


    /**
     * Enhance Model tabs
     * @return ArrayList
     */
    protected function getManagedModelTabs(): ArrayList
    {
        $tabs = parent::getManagedModelTabs();
        foreach ($tabs as $tab) {
            // localize tab name
            $tab->Title = _t($tab->ClassName . '.ADMIN_TABNAME', $tab->Title);
        }
        return $tabs;
    }


    protected function getGridFieldConfig(): GridFieldConfig
    {
        $config = parent::getGridFieldConfig();
        if ($this->modelTab == 'packages') {
            $config->addComponent(RowValidation::create(), GridFieldEditButton::class);
        }
        if ($this->modelTab == 'codes') {
            if (singleton(DLCode::class)->canCreate()) {
                $config->addComponent(
                    $button = GenerateCodesButton::create('buttons-before-left')
                        ->setImportForm($this->GenerateForm())
                        ->setModalTitle(_t(__CLASS__ . '.GENERATE_CODES', 'Generate Codes'))
                );

                // enable bulk deletion if available
                if (class_exists('\Colymba\BulkManager\BulkManager')) {
                    $config->addComponent(BulkManager::create([], false)
                        ->addBulkAction(DeleteHandler::class)
                        ->addBulkAction(MarkDistributedHandler::class)
                        ->addBulkAction(UnmarkDistributedHandler::class));
                }
            }
        }
        return $config;
    }


    /**
     * Form for Generation of codes
     * @return GenerateCodesForm
     */
    public function GenerateForm(): GenerateCodesForm
    {
        $obj = singleton(DLCode::class);
        $form = new GenerateCodesForm(
            $this,
            'GenerateForm',
            $obj,
            $this->Link($this->sanitiseClassName($this->modelTab)),
        );
        $this->extend('updateGenerateForm', $form);
        return $form;
    }

    public function generate($data, $form, $request): HTTPResponse
    {
        // ToDo: regular form validation necessary?
        if ($data['Quantity'] > 0 && $data['PackageID'] > 0) {
            for ($i = 0; $i < $data['Quantity']; $i++) {
                $code = DLCode::autoGenerate(
                    [
                        'Limited' => $data['Limited'],
                        'Expires' => $data['Expires'],
                        'PackageID' => $data['PackageID'],
                        'Note' => $data['Note']
                    ]
                );
            }
        }
        return $this->redirectBack();
    }
}
