# SilverStripe Download Codes

An extension for SilverStripe for generating download codes to give frontend users special access to protected files. A typical use case would be the download of digital music albums via codes provided with the LP version or sold separately.

## Requirements

Requires Silverstripe 5.x, still works with Silverstripe 4 – see respective branches `4` and `5`


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


## Backend usage

The administration of download codes is done in the Admin area “Download Codes”.

Basically you will create a Download Package with the desired files, create or generate Download Codes connected to this package, and provide these codes to users / customers in some way.


### Download Packages

A download package is a collection of files and common metadata that is provided as one code protected download.

The downloadable files should be protected against public access either directly or by inherited access from their parent folder. The admin grid view will give a warning if unprotected files are part of a package.

*Fields:*

- _Title_: Name of the package – can be displayed to users to describe the whole download – e.g. name of a music album
- _Preview Image_: Image representing thre whole download – e.g. a LP cover
- _Files_: The actual downloadable content files
- _Provide ZIP download_: Allow users to download all package files as ZIP


### Download Codes

A download code represents the download permission for a specific package.

A download code can either be limited (meant to be used be exactly one user) or un-limited (meant to be sent to multiple persons, e.g. for some marketing event)

Standard CSV export is enabled, which often will be helpful to distribute generated codes.

*Fields:*

- _Code_: The actual code you will send to a user / customer
- _Expiration Date_: (optional) The code can’t be redeemed after this date
- _Active_: If false, the code can’t be redeemed. Is set automatically to false when the usage_limit was reached (only for limited codes)
- _Limited_: Limited usage by one user
- _Distributed_: Mark the code as distributed to users / customers, to keep track of required codes
- _Package_: The download package handled by this code
- _Usage count_: (readonly) count of successful redemptions
- _Note_: Arbitrary note for internal use

#### Tab Redemptions

Read-only list of successful redemptions with dates of creation and their expiration

#### Action “Generate Codes”

With this action a bigger number of codes can be create/prepared in one step. The desired number will be created, filled with common settings, the Code field will be set each to unique value according to configured length and characters. (see „Configuration options“ below)

*Dialog Fields:*

- _Quantitiy_: number of download codes to generate
- _Expiration Date_: (optional) Set for all generated codes
- _Limited_: (optional) Set for all generated codes
- _Package_: (required) Set for all generated codes
- _Note_: Arbitrary note for internal use

#### Batch actions

Perform actions on multiple selection Download codes: (requires [colymba/gridfield-bulk-editing-tools](https://packagist.org/packages/colymba/gridfield-bulk-editing-tools))

- Delete
- Mark as distributed
- Remove distributed mark


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
