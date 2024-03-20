<?php

namespace Mhe\DownloadCodes\Model;

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
    public const EDIT_ALL = 'DLCode_EDIT_ALL';

    private static $table_name = 'DLCode';

    /**
     * Number of possible download attempts for limited codes
     * This means the use of the particular code in DLRequestForm, not the actual file downloads
     * @config
     */
    private static $usage_limit = 5;

    /**
     * Code input is case-sensitive
     * @config
     */
    private static $case_sensitive = true;

    /**
     * Code input is stripped of trailing/leading whitespace
     * caution: valid shouldn’t contain such whitespace then of course
     * @config
     */
    private static $strip_whitespace = false;

    /**
     * Length of auto generated codes
     * @config
     * @var int
     */
    private static $autogenerate_length = 8;

    /**
     * Characters used for auto generated codes
     * @config
     * @var string
     */
    private static $autogenerate_chars = 'ABCDEFGHIJAKLMNOPQRSTUVWXYZ0123456789';

    private static $db = [
        'Code' => 'Varchar(255)',
        'Expires' => 'Datetime',
        'Active' => 'Boolean',
        'Limited' => 'Boolean',
        'UsageCount' => 'Int',
        'Distributed' => 'Boolean',
        'Note' => 'Varchar(255)'
    ];

    private static $defaults = [
        'Active' => true,
        'Limited' => true,
        'UsageCount' => 0,
        'Distributed' => false
    ];

    private static $indexes = [
        'Code' => [
            'type' => 'unique',
            'columns' => ['Code'],
        ],
    ];

    private static $has_one = [
        'Package' => DLPackage::class
    ];

    private static $has_many = [
        'Redemptions' => DLRedemption::class,
    ];

    private static $summary_fields = [
        'Code',
        'Package.Title',
        'Limited',
        'Active',
        'Distributed',
        'Note'
    ];


    private static $searchable_fields = [
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
    public function getCMSFields()
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
     * @param $includerelations
     * @return array
     */
    public function fieldLabels($includerelations = true)
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
     * @throws \SilverStripe\ORM\ValidationException
     */
    public static function autoGenerate($args = [], $doWrite = true)
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
    protected static function randomCode()
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
     * @return \SilverStripe\ORM\ValidationResult
     */
    public function validate()
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
     * @return DataObject|null
     */
    public static function get_redeemable_code($code)
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
     * @return boolean
     */
    public function isRedeeamable()
    {
        return $this->Active &&
            ($this->UsageCount < static::config()->get('usage_limit') || !$this->Limited) &&
            (!$this->Expires || $this->obj('Expires')->inFuture());
    }

    /**
     * increase UsageCount – if limit is reached, also set Active to false
     * @return DLCode
     */
    public function increaseUsageCount()
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
     * @return DLRedemption|DataObject|null
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function redeem()
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

    public function providePermissions()
    {
        $perms = [
            self::EDIT_ALL => [
                'name' => _t(__CLASS__ . '.EDIT_ALL_NAME', 'Edit download codes'),
                'category' => _t('SilverStripe\\Security\\Permission.CONTENT_CATEGORY', 'Content permissions'),
                'help' => _t(__CLASS__ . '.EDIT_ALL_HELP', 'Manage download codes.'),
                'sort' => 201
            ]
        ];
        return $perms;
    }

    public function canView($member = null)
    {
        $extended = $this->extendedCan('canView', $member);
        if ($extended !== null) {
            return $extended;
        }
        return Permission::checkMember($member, 'CMS_ACCESS_DLCodeAdmin');
    }

    public function canEdit($member = null)
    {
        $extended = $this->extendedCan('canEdit', $member);
        if ($extended !== null) {
            return $extended;
        }
        return Permission::checkMember($member, self::EDIT_ALL);
    }

    public function canDelete($member = null)
    {
        return $this->canEdit($member);
    }

    public function canCreate($member = null, $context = [])
    {
        return $this->canEdit($member);
    }
}
