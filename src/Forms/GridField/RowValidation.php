<?php

namespace Mhe\DownloadCodes\Forms\GridField;

use SilverStripe\Forms\GridField\AbstractGridFieldComponent;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\View\HTML;

class RowValidation extends AbstractGridFieldComponent implements GridField_ColumnProvider
{

    public function augmentColumns($gridField, &$columns)
    {
        if (!in_array('Validation', $columns ?? [])) {
            $columns[] = 'Validation';
        }
    }

    public function getColumnsHandled($gridField)
    {
        return ['Validation'];
    }

    public function getColumnContent($gridField, $record, $columnName)
    {
        $attributes = [];
        $content = '';
        if ($record && $record->hasMethod('gridFieldValidation')) {
            $valid = $record->gridFieldValidation();
            if ($valid) {
                $attributes['class'] = 'font-icon-check-mark-circle';
            } else {
                $attributes['class'] = 'font-icon-cancel-circled';
                if ($record->hasMethod('gridFieldValidationMessage')) {
                    $content = $record->gridFieldValidationMessage();
                } else {
                    $content = _t(__CLASS__ . '.DefaultMessage', 'Warning');
                }
            }
        }
        return HTML::createTag('span', $attributes, $content);
    }

    public function getColumnAttributes($gridField, $record, $columnName)
    {
        return ['class' => 'grid-field__row-validation'];
    }

    public function getColumnMetadata($gridField, $columnName)
    {
        return ['title' => _t(__CLASS__ . '.ColumnTitle', 'Validation')];
    }
}
