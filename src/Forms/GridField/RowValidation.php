<?php

namespace Mhe\DownloadCodes\Forms\GridField;

use SilverStripe\Forms\GridField\AbstractGridFieldComponent;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\View\HTML;

class RowValidation extends AbstractGridFieldComponent implements GridField_ColumnProvider
{
    public function augmentColumns($gridField, &$columns): void
    {
        if (!in_array('Validation', $columns ?? [])) {
            $columns[] = 'Validation';
        }
    }

    public function getColumnsHandled($gridField): array
    {
        return ['Validation'];
    }

    public function getColumnContent($gridField, $record, $columnName): string
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

    public function getColumnAttributes($gridField, $record, $columnName): array
    {
        return ['class' => 'grid-field__row-validation'];
    }

    public function getColumnMetadata($gridField, $columnName): array
    {
        return ['title' => _t(__CLASS__ . '.ColumnTitle', 'Validation')];
    }
}
