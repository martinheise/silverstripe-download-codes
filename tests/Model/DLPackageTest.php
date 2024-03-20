<?php

namespace Mhe\DownloadCodes\Tests\Model;

use Mhe\DownloadCodes\Model\DLPackage;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
use SilverStripe\Dev\SapphireTest;

class DLPackageTest extends SapphireTest
{
    protected static $fixture_file = 'DLPackageTest.yml';

    public function setUp(): void
    {
        parent::setUp();

        // setup test file storage
        TestAssetStore::activate('Test_DownloadFiles');
        /** @var File $file */
        $files = File::get()->exclude('ClassName', Folder::class);
        foreach ($files as $file) {
            try {
                $sourcePath = __DIR__ . '/../downloads/' . $file->Name;
                $file->setFromLocalFile($sourcePath, $file->Filename);
                $file->publishSingle();
            } catch (\InvalidArgumentException $e) {
            }
        }
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * check if all files are protected
     * @return void
     */
    public function testCheckFilesAccess()
    {
        $package = new DLPackage();
        $this->assertTrue($package->filesAreProtected());
        $package->Files()->add($this->objFromFixture(Image::class, 'protectedimage1'));
        $package->Files()->add($this->objFromFixture(Image::class, 'protectedimage2'));
        $this->assertTrue($package->filesAreProtected());
        $package->Files()->add($this->objFromFixture(Image::class, 'publicimage1'));
        $this->assertFalse($package->filesAreProtected());

        $package->Files()->removeAll();
        $package->Files()->add($this->objFromFixture(Image::class, 'publicimage2'));
        $this->assertFalse($package->filesAreProtected());
        $package->Files()->add($this->objFromFixture(Image::class, 'protectedimage1'));
        $package->Files()->add($this->objFromFixture(Image::class, 'protectedimage2'));
        $this->assertFalse($package->filesAreProtected());
    }

    public function testGetCacheKey()
    {
        $hashs = [];
        /* @var DLPackage $package */
        $package = $this->objFromFixture(DLPackage::class, 'package1');
        $hashs[] = $package->getCacheKey();
        // modified package title
        $package->Title = $package->Title . " modified";
        $hashs[] = $package->getCacheKey();
        // modified file title
        /* @var File $file */
        $file = $package->Files()->first();
        $file->Title = $file->Title . " modified Title";
        $file->write();
        $hashs[] = $package->getCacheKey();
        // modified files
        $package->Files()->remove($package->Files()->first());
        $hashs[] = $package->getCacheKey();
        // assert unique and valid hashs
        $this->assertEquals(4, count(array_unique($hashs)));
        $this->assertEquals(4, count(array_filter($hashs, fn($hash) => strlen($hash) == 40)));
    }

    public function testGeneratedZip()
    {
        /* @var DLPackage $package */
        $package = $this->objFromFixture(DLPackage::class, 'package1');
        $this->assertEquals('Test Package', $package->Title);

        /* @var \SilverStripe\Assets\Storage\DBFile $zip */
        $zip = $package->getZippedFiles();
        $this->assertTrue($zip->exists());
        $this->assertEquals(40, strlen($zip->Hash));
        $this->assertEquals('application/zip', $zip->getMimeType());
        $this->assertEquals('test-package.zip', $zip->Filename);

        $package->EnableZip = false;
        $this->assertEmpty($package->getZippedFiles());
    }
}
