<?php

namespace Mhe\DownloadCodes\Tests\Model;

use Mhe\DownloadCodes\Model\DLCode;
use Mhe\DownloadCodes\Model\DLRedemption;
use Page;
use PHPUnit\Util\Test;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\View\SSViewer;

class DLPageTest extends FunctionalTest
{
    protected static $fixture_file = 'DLPageTest.yml';

    protected $autoFollowRedirection = true;

    protected static $testtheme = 'test-downloads';

    protected function setUp(): void
    {
        parent::setUp();
        // Director::config()->update('alternate_base_url', 'http://www.mysite.com/');

        // setup test theme
        $themeBaseDir = realpath(__DIR__ . '/..');
        if (strpos($themeBaseDir, BASE_PATH) === 0) {
            $themeBaseDir = substr($themeBaseDir, strlen(BASE_PATH));
        }
        SSViewer::config()->set('theme_enabled', true);
        SSViewer::set_themes([$themeBaseDir . '/themes/' . self::$testtheme, '$default']);

        /** @var Page $page */
        foreach (Page::get() as $page) {
            $page->publishSingle();
        }

        // setup test file storage
        TestAssetStore::activate('DownloadFiles');
        // test response code like in live mode
        TestAssetStore::config()->set('denied_response_code', 404);
        /** @var File $file */
        $files = File::get()->exclude('ClassName', Folder::class);
        foreach ($files as $file) {
            $sourcePath = __DIR__ . '/../downloads/' . $file->Name;
            $file->setFromLocalFile($sourcePath, $file->Filename);
            $file->publishSingle();
        }
    }

    protected function tearDown(): void
    {
        TestAssetStore::reset();
        parent::tearDown();
    }

    public function testFormIsPresent()
    {
        $this->get('download');
        $form = $this->cssParser()->getBySelector('form#DLRequestForm_RequestForm');
        $this->assertNotEmpty($form);
    }

    public function testFormErrorForInValidCodes()
    {
        $this->get('download');
        $this->submitForm("DLRequestForm_RequestForm", "action_submitcode", array("Code" => "unknown code"));
        $this->assertPartialMatchBySelector('.message', 'Invalid code', 'Unknown code is rejected');

        $this->get('download');
        $this->submitForm("DLRequestForm_RequestForm", "action_submitcode", array("Code" => "DEACTIVATED"));
        $this->assertPartialMatchBySelector('.message', 'Invalid code', 'Deactivated code is rejected');

        $this->get('download');
        $this->submitForm("DLRequestForm_RequestForm", "action_submitcode", array("Code" => "USEDLIMIT"));
        $this->assertPartialMatchBySelector('.message', 'Invalid code', 'Code exceeding limit is rejected');

        $this->get('download');
        $this->submitForm("DLRequestForm_RequestForm", "action_submitcode", array("Code" => "EXPIRED"));
        $this->assertPartialMatchBySelector('.message', 'Invalid code', 'Expired code is rejected');
    }

    private function assertFormSuccess(HTTPResponse $response, $title) {
        $this->assertStringStartsWith('download/redeem', $this->mainSession->lastUrl(), 'Valid code redirects to Redeem action');
        $this->assertEquals(200, $response->getStatusCode());
        // contains title of package
        $this->assertPartialMatchBySelector('h2', $title);
    }

    public function testFormForValidCode()
    {
        $this->get('download');
        $response = $this->submitForm("DLRequestForm_RequestForm", "action_submitcode", array("Code" => "VALIDCODE"));
        $this->assertFormSuccess($response, 'Two Files');

        $this->get('download');
        $response = $this->submitForm("DLRequestForm_RequestForm", "action_submitcode", array("Code" => "EXPIRE_FUTURE"));
        $this->assertFormSuccess($response, 'Two Files');

        $this->get('download');
        $response = $this->submitForm("DLRequestForm_RequestForm", "action_submitcode", array("Code" => "FREE"));
        $this->assertFormSuccess($response, 'Two Files');
    }

    public function testFormForValidCodeWithWhitespace() {
        DLCode::config()->set('strip_whitespace', false);
        $this->get('download');
        $response = $this->submitForm("DLRequestForm_RequestForm", "action_submitcode", array("Code" => "   FREE  "));
        $this->assertPartialMatchBySelector('.message', 'Invalid code', 'Unknown code is rejected');

        DLCode::config()->set('strip_whitespace', true);
        $this->get('download');
        $response = $this->submitForm("DLRequestForm_RequestForm", "action_submitcode", array("Code" => "   FREE  "));
        $this->assertFormSuccess($response, 'Two Files');
    }

    public function testRedeemPage()
    {
        $c = $this->idFromFixture(DLCode::class, 'valid_unlimited');
        $r = $this->idFromFixture(DLRedemption::class, 'valid');
        $this->get("download/redeem?c=$c&r=$r&s=a1234567890b");

        // page contains title of package
        $this->assertPartialMatchBySelector('h2', 'Two Files');

        // page contains preview image
        $img = $this->cssParser()->getBySelector('img')[0];
        $this->assertEquals('/assets/DownloadFiles/preview.jpg', $img['src']);

        // page contains label + links for all package files
        $this->assertExactHTMLMatchBySelector('ul.downloads a',
            ['<a href="/assets/c129504a35/download1.mp3">Download 1</a>',
             '<a href="/assets/6e11ccf096/download2.wav">Download 2</a>']);
    }

    public function testRedeemPageInvalid()
    {
        $c = $this->idFromFixture(DLCode::class, 'valid_unlimited');
        $r = $this->idFromFixture(DLRedemption::class, 'valid');

        $response = $this->get("download/redee");
        $this->assertEquals(404, $response->getStatusCode());
        $response = $this->get("download/redeem?c=$c&r=999&s=a1234567890b");
        $this->assertEquals(404, $response->getStatusCode());
        $response = $this->get("download/redeem?c=999&r=$r&s=a1234567890b");
        $this->assertEquals(404, $response->getStatusCode());
        $response = $this->get("download/redeem?c=$c&r=$r&s=aaaa12345");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testFileAccess()
    {
        // File is protected
        $response = $this->get("/assets/c129504a35/download1.mp3");
        $this->assertEquals(404, $response->getStatusCode());

        // File access is granted
        $c = $this->idFromFixture(DLCode::class, 'valid_unlimited');
        $r = $this->idFromFixture(DLRedemption::class, 'valid');
        $this->get("download/redeem?c=$c&r=$r&s=a1234567890b");
        $response = $this->get("/assets/c129504a35/download1.mp3");
        $this->assertEquals(200, $response->getStatusCode());
    }

}
