# SilverStripe Download Codes

An extension for SilverStripe for generating download codes to give frontend users special access to protected files. A typical use case would be the download of digital music albums via codes provided with the LP version or sold separately.

## Requirements

Requires Silverstripe 5.x – for a version compatible with Silverstripe 4 see respective branch `4`


## Installation and setup

Install with composer:

    composer require mhe/silverstripe-download-codes ^1.0

Perform `dev/build` task

Add a page of type “Download Page” to your site. This will provide a form where users can redeem their code. You might want to deactivate the “Show in menus” setting and only communicate the URL together with the download codes.


### Recommended extensions

These extensions will improve the experience, but are optional:

- [bummzack/sortablefile](https://packagist.org/packages/bummzack/sortablefile): Support for sortable UploadFields, to sort Download Package files
- [colymba/gridfield-bulk-editing-tools](https://packagist.org/packages/colymba/gridfield-bulk-editing-tools): Support for bulk actions in Admin area
- PHP with enabled [ext/zip](https://www.php.net/manual/en/book.zip.php): Providing download packages as zip


## Usage and administration

- see [User guide](docs/en/userguide.md)


## Configuration

The following templates are included providing the basic funcionality. You can adjust them via a theme or project templates to adjust the layout to your needs.

### Templates

- `Mhe/DownloadCodes/Model/Layout/DLPage_redeem.ss`: Layout of the DownloadPage containing the main code redemption form
- `Mhe/DownloadCodes/Model/Layout/DLPage.ss`: Displayed after successful entering of a valid code, containing the actual links to downloadable files

### Configuration options via Silverstripe YAML configuration

#### Mhe\DownloadCodes\Model\DLCode

- _autogenerate_chars_: string containing valid characters for auto generated codes (default: "ABCDEFGHIJAKLMNOPQRSTUVWXYZ0123456789")
- _autogenerate_length_: length of auto generated codes (default: 8)
- _strip_whitespace_: if true strips whitespace from user input for codes (default: false)
- _case_sensitive_: user’s code input needs to match the case of the valid code (default: true), otherwise matches case-insensitive
- _usage_limit_: number of attempts a regular code can be redeemed (default: 5). The actual file download after redemption (in case of download problems etc) is not limited by this.

#### Mhe\DownloadCodes\Model\DLRedemption

- _validity_days_: number of days a code redemption with the unique URL part will be valid and can be used for download


## Available Backend Permissions

- _Access to 'Download Codes' section_: view download codes and packages
- _Edit download packages_: create, edit, delete download packages
- _Edit download codes_: create, edit, delete download codes
