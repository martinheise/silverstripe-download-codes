<?php

namespace Mhe\DownloadCodes\Model;

use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * A unique download code linked to a selection of files
 *
 * @property string $Code The actual code
 * @property string $Expires optional end date/time of validity
 * @property bool $Active Code is active, as long as limit and expiration allow it
 * @property bool $Limited usage is limited to configurable number of attempts
 * @property int $UsageCount internal counter
 * @property bool $Distributed usage is marked as distributed
 * @property string $Note internal note
 *
 * @method DLPackage Package()
 * @method HasManyList Redemptions()
 */
class DLCode extends DataObject implements PermissionProvider
{
    /**
     * Permission to edit DLCodes
     */
    public const string EDIT_ALL = 'DLCode_EDIT_ALL';

    private static string $table_name = 'DLCode';

    /**
     * Number of possible download attempts for limited codes
     * This means the use of the particular code in DLRequestForm, not the actual file downloads
     * @config
     */
    private static int $usage_limit = 5;

    /**
     * Code input is case-sensitive
     * @config
     */
    private static bool $case_sensitive = true;

    /**
     * Code input is stripped of trailing/leading whitespace
     * caution: valid shouldn’t contain such whitespace then of course
     * @config
     */
    private static bool $strip_whitespace = false;

    /**
     * Length of auto generated codes
     * @config
     * @var int
     */
    private static int $autogenerate_length = 8;

    /**
     * Characters used for auto generated codes
     * @config
     * @var string
     */
    private static string $autogenerate_chars = 'ABCDEFGHIJAKLMNOPQRSTUVWXYZ0123456789';

    private static array $db = [
        'Code' => 'Varchar(255)',
        'Expires' => 'Datetime',
        'Active' => 'Boolean',
        'Limited' => 'Boolean',
        'UsageCount' => 'Int',
        'Distributed' => 'Boolean',
        'Note' => 'Varchar(255)'
    ];

    private static array $defaults = [
        'Active' => true,
        'Limited' => true,
        'UsageCount' => 0,
        'Distributed' => false
    ];

    private static array $indexes = [
        'Code' => [
            'type' => 'unique',
            'columns' => ['Code'],
        ],
    ];

    private static array $has_one = [
        'Package' => DLPackage::class
    ];

    private static array $has_many = [
        'Redemptions' => DLRedemption::class,
    ];

    private static array $summary_fields = [
        'Code',
        'Package.Title',
        'Limited',
        'Active',
        'Distributed',
        'Note'
    ];


    private static array $searchable_fields = [
        'Package.Title',
        'Limited',
        'Active',
        'Distributed',
        'Note'
    ];

    /**
     * get CMS fields – using default scaffolding and keeping possibility for extension
     * @return FieldList
     */
    public function getCMSFields(): FieldList
    {
        $fields = $this->scaffoldFormFields([
            'includeRelations' => ($this->ID > 0),
            'tabbed' => true,
            'ajaxSafe' => true
        ]);
        $code = $fields->fieldByName('Root.Main.Code');
        $usagecount = $fields->fieldByName('Root.Main.UsageCount')->setReadonly(true);
        $fields->addFieldToTab('Root.Main', $usagecount);
        // simple readonly grid field for Redemptions
        $redemptions = $fields->fieldByName('Root.Redemptions.Redemptions');
        if ($redemptions) {
            $redemptions->setConfig(GridFieldConfig_Base::create());
        }

        $this->extend('updateCMSFields', $fields);
        return $fields;
    }

    /**
     * enhance field labels with custom values for summary/search fields
     * @param true $includerelations
     * @return array
     */
    public function fieldLabels($includerelations = true): array
    {
        $labels = parent::fieldLabels($includerelations);
        $labels['Package.Title'] = _t(
            __CLASS__ . '.has_one_Package',
            'Package'
        );
        return $labels;
    }


    /**
     * create a new unique DLCode
     * @param array $args optional default properties
     * @param boolean $doWrite If true (default) immediately save the object
     * @return DLCode
     */
    public static function autoGenerate(array $args = [], bool $doWrite = true): static
    {
        $code = static::create($args);
        $tries = 0;
        do {
            // ToDo: what is an appropriate count of tries?
            // ToDo: handle error in a user friendly way
            if ($tries > 10) {
                throw new \Exception('couldn’t get unique code – check options / configuration');
            }
            $tries++;
            $code->Code = self::randomCode();
        } while (
            // assure unique codes – force case-insensitive search (default for MySQL anyway)
            self::get()->filter('Code:nocase', $code->Code)->exists()
        );
        if ($doWrite) {
            $code->write();
        }
        return $code;
    }

    /**
     * Generate a random code
     * @param int $length number of characters for the code
     * @return string
     */
    protected static function randomCode(): string
    {
        $chars = static::config()->get('autogenerate_chars');
        $length = static::config()->get('autogenerate_length');
        $c = '';
        for ($i = 0; $i < $length; $i++) {
            $c .= substr($chars, random_int(0, strlen($chars) - 1), 1);
        }
        return $c;
    }

    /**
     * custom validaton for unique Codes
     * @return ValidationResult
     */
    public function validate(): ValidationResult
    {
        $result = parent::validate();
        // assure unique codes – force case-insensitive search (default for MySQL anyway)
        $existing = self::get()->filter('Code:nocase', $this->Code);
        if ($this->ID) {
            $existing = $existing->exclude('ID', $this->ID);
        }
        if ($existing->exists()) {
            $result->addError(_t(__CLASS__ . '.ERROR_Unique_Code', 'Code is already in use'));
        }
        return $result;
    }

    /**
     * Get one redeeamable DLCode for given code string
     * @param string $code Code
     * @return ?DLCode
     */
    public static function get_redeemable_code(string $code): ?static
    {
        $modifier = static::config()->get('case_sensitive') ? ':case' : ':nocase';
        if (static::config()->get('strip_whitespace')) {
            $code = trim($code);
        }
        $obj = self::get()->filter([
            "Code{$modifier}" => $code,
            'Active' => 1])->first();
        /* @var DLCode $obj */
        if ($obj && $obj->isRedeeamable()) {
            return $obj;
        }
        return null;
    }

    /**
     * Code is active and not expired
     * @return bool
     */
    public function isRedeeamable(): bool
    {
        return $this->Active &&
            ($this->UsageCount < static::config()->get('usage_limit') || !$this->Limited) &&
            (!$this->Expires || $this->obj('Expires')->inFuture());
    }

    /**
     * increase UsageCount – if limit is reached, also set Active to false
     * @return DLCode
     */
    public function increaseUsageCount(): static
    {
        $this->UsageCount++;
        if ($this->Limited && $this->UsageCount >= static::config()->get('usage_limit')) {
            $this->Active = false;
        }
        return $this;
    }

    /**
     * Redeem this code – called after successful form submission
     * Creates/Gets a redemption object handling the secret URL
     * @return ?DLRedemption
     */
    public function redeem(): ?DLRedemption
    {
        if (!$this->isRedeeamable()) {
            return null;
        }
        $this->increaseUsageCount();
        if ($this->Limited) {
            $redemption = $this->Redemptions()->first();
            if (!$redemption) {
                $redemption = DLRedemption::create();
                $this->Redemptions()->add($redemption);
            }
        } else {
            $redemption = DLRedemption::create();
            $this->Redemptions()->add($redemption);
        }
        $this->write();
        return $redemption;
    }

    public function providePermissions(): array
    {
        return [
            self::EDIT_ALL => [
                'name' => _t(__CLASS__ . '.EDIT_ALL_NAME', 'Edit download codes'),
                'category' => _t('SilverStripe\\Security\\Permission.CONTENT_CATEGORY', 'Content permissions'),
                'help' => _t(__CLASS__ . '.EDIT_ALL_HELP', 'Manage download codes.'),
                'sort' => 201
            ]
        ];
    }

    public function canView($member = null): bool
    {
        $extended = $this->extendedCan('canView', $member);
        if ($extended !== null) {
            return $extended;
        }
        return Permission::checkMember($member, 'CMS_ACCESS_DLCodeAdmin');
    }

    public function canEdit($member = null): bool
    {
        $extended = $this->extendedCan('canEdit', $member);
        if ($extended !== null) {
            return $extended;
        }
        return Permission::checkMember($member, self::EDIT_ALL);
    }

    public function canDelete($member = null): bool
    {
        return $this->canEdit($member);
    }

    public function canCreate($member = null, $context = []): bool
    {
        return $this->canEdit($member);
    }
}
