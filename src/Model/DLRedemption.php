<?php

namespace Mhe\DownloadCodes\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Permission;

/**
 * A successful redemption of a valid download code
 * Holds secret URL for download access
 *
 * @property string URLSecret auto-generated secret URL part
 * @property string $Expires end date/time of validity
 *
 * @method DLCode Code()
 */
class DLRedemption extends DataObject
{
    private static $table_name = 'DLRedemption';

    /**
     * Validity duration of redeemed codes
     * @config
     */
    private static $validity_days = 7;

    private static $db = [
        'URLSecret' => 'Varchar(255)',
        'Expires' => 'Datetime'
    ];

    private static $has_one = [
        'Code' => DLCode::class
    ];

    private static $summary_fields = [
        'Created',
        'Expires'
    ];

    /**
     * on creation generate URLSecret and Expiration date
     * @return $this|DLRedemption
     * @throws \Exception
     */
    public function populateDefaults()
    {
        parent::populateDefaults();
        $this->URLSecret = bin2hex(random_bytes(32));
        $this->Expires = DBDatetime::now()->getTimestamp() + 3600 * 24 * static::config()->get('validity_days');
        return $this;
    }

    /**
     * check validity by expiration date
     * @return boolean
     */
    public function isValid()
    {
        return $this->Code->exists() && $this->obj('Expires')->inFuture();
    }

    /**
     * create URL query string from appropriate properties, will be added to download page link
     * @return string
     */
    public function getUrlParamString()
    {
        if (!$this->isValid()) return '';
        return "?" . http_build_query(['c' => $this->Code()->ID, 'r' => $this->ID, 's' => $this->URLSecret]);
    }

    /**
     * Get a (valid) redemption object for given GET vars
     * @param array $vars get vars from request
     * @param boolean $onlyvalid check validity of object
     * @return DataObject|null
     */
    public static function get_by_query_params($vars, $onlyvalid = true)
    {
        if (!isset($vars['r']) || !isset($vars['c']) || !isset($vars['s'])) return null;
        $redemption = self::get()->filter([
            'ID' => $vars['r'],
            'Code.ID' => $vars['c'],
            'URLSecret' => $vars['s']
        ])->first();
        if ($redemption && ($redemption->isValid() || !$onlyvalid)) {
            return $redemption;
        }
        return null;
    }


    /**
     * Redemptions are viewable for all BE users with access to DLCode area
     * @param $member
     * @return bool|int
     */
    public function canView($member = null)
    {
        $extended = $this->extendedCan('canView', $member);
        if ($extended !== null) {
            return $extended;
        }
        return Permission::checkMember($member, 'CMS_ACCESS_DLCodeAdmin');
    }

}
