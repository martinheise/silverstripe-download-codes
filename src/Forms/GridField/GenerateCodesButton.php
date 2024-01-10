<?php

namespace Mhe\DownloadCodes\Forms\GridField;


use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridFieldImportButton;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;

/**
 * extending standard GridFieldImportButton, which provides functionality for the modal dialog, but actually does nothing “Import” specific by itself
 * The actual data logic is handled by the provided Form
 * We just need to overwrite the button label, ID etc
 */
class GenerateCodesButton extends GridFieldImportButton
{

    /**
     * @param GridField $gridField
     * @return array
     */
    public function getHTMLFragments($gridField)
    {
        $modalID = $gridField->ID() . '_GenerateModal';

        // Check for form message prior to rendering form (which clears session messages)
        $form = $this->getImportForm();
        $hasMessage = $form && $form->getMessage();

        // Render modal
        $template = SSViewer::get_templates_by_class(static::class, '_Modal');
        $viewer = new ArrayData([
            'ImportModalTitle' => $this->getModalTitle(),
            'ImportModalID' => $modalID,
            'ImportIframe' => $this->getImportIframe(),
            'ImportForm' => $this->getImportForm(),
        ]);
        $modal = $viewer->renderWith($template)->forTemplate();

        // Build action button
        $button = new GridField_FormAction(
            $gridField,
            'import',
            _t('Mhe\\DownloadCodes\\Controller\\DLCodeAdmin.GENERATE_CODES', 'Generate Codes'),
            'import',
            []
        );
        $button
            ->addExtraClass('btn btn-secondary font-icon-sync btn--icon-large action_import') // action_import: important for using standard modal funcionality
            ->setForm($gridField->getForm())
            ->setAttribute('data-toggle', 'modal')
            ->setAttribute('aria-controls', $modalID)
            ->setAttribute('data-target', "#{$modalID}")
            ->setAttribute('data-modal', $modal);

        // If form has a message, trigger it to automatically open
        if ($hasMessage) {
            $button->setAttribute('data-state', 'open');
        }

        return [
            $this->targetFragment => $button->Field()
        ];
    }




}
