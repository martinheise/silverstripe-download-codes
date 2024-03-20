# SilverStripe Download Codes – User guide

Download codes can give frontend users special access to protected files. A typical use case would be the download of digital music albums via codes provided with the LP version or sold separately.

Optionally the module generates a ZIP for complete download of a comeplete package at once.


## Precondition / frontend usage

After installation of the module a page of type “Download Page” should be added to your site. This will provide a form where users can redeem their code. You might want to deactivate the “Show in menus” setting and only communicate the URL together with the download codes.

You can also add regular content to the page that will be displayed in addition the form.


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

A download code can either be _limited_ (meant to be used be exactly one user) or unlimited (meant to be sent to multiple persons, e.g. for some marketing event)

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

Read-only list of successful redemptions with dates of creation and their expiration. For _limited_ codes there will be only one (or no) redemption.

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
