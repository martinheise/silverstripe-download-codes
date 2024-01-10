<?php

namespace Mhe\DownloadCodes\Model;

use Bummzack\SortableFile\Forms\SortableUploadField;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Assets\Storage\DBFile;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\Security\InheritedPermissions;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionChecker;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\View\Parsers\URLSegmentFilter;
use ZipArchive;

/**
 * Package of files and additional information for user download
 *
 * @property string $Title Display name of package
 * @property boolean $EnableZip Provide Zip download to user
 *
 * @method Image PreviewImage() Image for preview purposes
 * @method ManyManyList Files() Files available for download
 */
class DLPackage extends DataObject implements PermissionProvider, Flushable
{

    /**
     * Permission to edit DLCodes
     */
    const EDIT_ALL = 'DLPackage_EDIT_ALL';

    private static $table_name = 'DLPackage';

    private static $db = [
        'Title' => 'Varchar(255)',
        'EnableZip' => 'Boolean'
    ];

    private static $has_one = [
        'PreviewImage' => Image::class,
    ];

    private static $many_many = [
        'Files' => File::class,
    ];

    private static $many_many_extraFields = [
        'Files' => [
            'Sort' => 'Int'
        ]
    ];

    private static $belongs_many_many = [
        'Code' => DLCode::class
    ];

    private static $defaults = [
        'EnableZip' => true
    ];

    private static $summary_fields = [
        'Title',
        'Files.Count',
        'Files.First.Title'
    ];

    private static $searchable_fields = [
        'Title'
    ];

    /**
     * @var CacheInterface
     */
    private $cache;

    public function getCMSFields()
    {
        if (class_exists('\\Bummzack\\SortableFile\\Forms\\SortableUploadField')) {
            $filesfields = SortableUploadField::create('Files', $this->fieldLabel('Files'))->setSortColumn('Sort');
        } else {
            $filesfields = UploadField::create('Files', $this->fieldLabel('Files'));
        }

        $fields = new FieldList(
            $rootTab = new TabSet(
                "Root",
                $tabMain = new Tab(
                    'Main',
                    TextField::create("Title", $this->fieldLabel('Title')),
                    UploadField::create('PreviewImage', $this->fieldLabel('PreviewImage')),
                    $filesfields,
                    CheckboxField::create('EnableZip', $this->fieldLabel('EnableZip'))
                )
            )
        );

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
        $labels['Files.Count'] = _t(
            __CLASS__ . '.FILES_COUNT',
            'Files Count'
        );
        $labels['Files.First.Title'] = _t(
            __CLASS__ . '.FILES_FIRST_TITLE',
            'First File Title'
        );
        return $labels;
    }


    public function providePermissions()
    {
        $perms = [
            self::EDIT_ALL => [
                'name' => _t(__CLASS__ . '.EDIT_ALL_NAME', 'Edit download packages'),
                'category' => _t('SilverStripe\\Security\\Permission.CONTENT_CATEGORY', 'Content permissions'),
                'help' => _t(__CLASS__ . '.EDIT_ALL_HELP', 'Manage download packages.'),
                'sort' => 211
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

    public function filesAreProtected()
    {
        $protected = true;
        $checker = Injector::inst()->get(PermissionChecker::class.'.file');
        /* @var \SilverStripe\Assets\File $file */
        foreach ($this->Files() as $file) {
            if ($file->CanViewType == InheritedPermissions::ANYONE) return false;
            if ($file->CanViewType === InheritedPermissions::INHERIT && $file->ParentID) {
                if ($checker->canView($file->ParentID, null)) return false;
            }
        }
        return $protected;
    }

    public function gridFieldValidation()
    {
        return $this->filesAreProtected();
    }

    public function gridFieldValidationMessage() {
        return _t(__CLASS__ . '.UnprotectedFiles', 'unprotected files');
    }

    /**
     * get a zipped version of all or selected package files if EnableZip is activated for the package
     *
     * @param array|null $filter optionally filter files to Zip, @see \SilverStripe\ORM\DataList::filter()
     * @return DBFile|null
     */
    public function getZippedFiles($filter = null) {
        $files = is_array($filter) ? $this->Files()->filter($filter) : $this->Files();
        if (!$this->EnableZip || $files->count() < 1) {
            return null;
        }
        if (class_exists(ZipArchive::class)) {
            /* @var AssetStore $store */
            $store = Injector::inst()->get(AssetStore::class);

            $filevalue = null;
            $filter = URLSegmentFilter::create();
            $filename = $filter->filter($this->Title) . ".zip";
            $tempFile = null;

            // try to get file reference from hash
            $cachekey = $this->getCacheKey() . "_zip";
            $hash = $this->getCache()->get($cachekey);

            // create ZIP if not already cashed
            if (!$store->exists($filename, $hash)) {
                $tempPath = TEMP_PATH;
                $zip = new ZipArchive();
                $tempFile = tempnam($tempPath, 'dl');
                if ($zip->open($tempFile, ZipArchive::OVERWRITE|ZipArchive::CREATE)!== TRUE) {
                    user_error("Could not open temp file for ZIP creation", E_USER_WARNING);
                    return null;
                }

                /* @var \SilverStripe\Assets\File $file */
                foreach ($files as $file) {
                    $zip->addFromString($file->Name, $file->getString());
                }
                $zip->close();

                // write file to AssetStore
                $filevalue = $store->setFromLocalFile($tempFile, $filename);
                // write hash to cache
                if (isset($filevalue['Hash']) && $filevalue['Hash'] != '') {
                    $this->getCache()->set($cachekey, $filevalue['Hash']);
                }
            } else {
                $filevalue = [
                    'Filename' => $filename,
                    'Hash' => $hash
                ];
            }

            /* @var DBFile $file */
            $file = DBField::create_field('DBFile', $filevalue);

            // clean up temp file
            if (file_exists($tempFile ?? '')) {
                unlink($tempFile);
            }

            return $file;
        }
        return null;
    }

    /**
     * get cache instance for generated file results
     * @return CacheInterface
     */
    public function getCache()
    {
        if (!$this->cache) {
            $this->cache = Injector::inst()->get(CacheInterface::class . '.DLPackage_Generated');
        }
        return $this->cache;
    }

    /**
     * get cache key, build from Title and package files
     * @return string
     */
    public function getCacheKey() {
        $key = hash_init('sha1');
        hash_update($key, $this->ID . $this->Title);
        /* @var \SilverStripe\Assets\File $file */
        foreach ($this->Files() as $file) {
            hash_update($key, $file->Title);
            hash_update($key, $file->getHash());
        }
        return hash_final($key);
    }

    /**
     * remove Zip file caches on flush
     * @return void
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public static function flush()
    {
        $cache = Injector::inst()->get(CacheInterface::class . '.DLPackage_Generated');
        $cache->clear();
    }
}
