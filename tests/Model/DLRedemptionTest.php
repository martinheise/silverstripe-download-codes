<?php

namespace Mhe\DownloadCodes\Tests\Model;

use Mhe\DownloadCodes\Model\DLCode;
use Mhe\DownloadCodes\Model\DLRedemption;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBDatetime;

class DLRedemptionTest extends SapphireTest
{
    protected static $fixture_file = 'DLRedemptionTest.yml';

    public function setUp(): void
    {
        parent::setUp();
        DBDatetime::set_mock_now('2022-01-15T08:15:00');
    }

    public function tearDown(): void
    {
        DBDatetime::clear_mock_now();
        parent::tearDown();
    }

    public function testPopulateDefault()
    {
        $redemption = DLRedemption::create();
        $this->assertMatchesRegularExpression("![a-f0-9]{64}!", $redemption->URLSecret);
        $this->assertEquals(strtotime('2022-01-22T08:15:00'), $redemption->Expires);
        // modified expiration
        Config::modify()->set(DLRedemption::class, 'validity_days', 14);
        $redemption = DLRedemption::create();
        $this->assertEquals(strtotime('2022-01-29T08:15:00'), $redemption->Expires);
    }

    public function testValid()
    {
        $redemption = $this->objFromFixture(DLRedemption::class, 'valid');
        $this->assertTrue($redemption->isValid());
        $redemption = $this->objFromFixture(DLRedemption::class, 'expired_today');
        $this->assertFalse($redemption->isValid());
        $redemption = $this->objFromFixture(DLRedemption::class, 'expired');
        $this->assertFalse($redemption->isValid());
        $redemption = $this->objFromFixture(DLRedemption::class, 'incomplete');
        $this->assertFalse($redemption->isValid());
    }

    public function testUrlParamString()
    {
        $code = $this->objFromFixture(DLCode::class, 'valid_unlimited');
        $redemption = $this->objFromFixture(DLRedemption::class, 'valid');
        $this->assertEquals("?c={$code->ID}&r={$redemption->ID}&s=a123", $redemption->getUrlParamString());
    }

    public function testGetByQueryParams()
    {
        $c = $this->idFromFixture(DLCode::class, 'valid_unlimited');
        $r = $this->idFromFixture(DLRedemption::class, 'valid');
        // get redemption with correct parameters
        $this->assertEquals($r, DLRedemption::get_by_query_params(['c' => $c, 'r' => $r, 's' => 'a123'])->ID);
        // wrong parameters
        $this->assertEmpty(DLRedemption::get_by_query_params(['c' => $c + 1, 'r' => $r, 's' => 'a123']));
        $this->assertEmpty(DLRedemption::get_by_query_params(['c' => $c, 'r' => $r + 1, 's' => 'a123']));
        $this->assertEmpty(DLRedemption::get_by_query_params(['c' => $c, 'r' => $r, 's' => 'b123']));

        // expired redemption:
        $r = $this->idFromFixture(DLRedemption::class, 'expired');
        $this->assertEmpty(DLRedemption::get_by_query_params(['c' => $c, 'r' => $r, 's' => 'c123']));
        // ignoring validity check
        $this->assertEquals($r, DLRedemption::get_by_query_params(['c' => $c, 'r' => $r, 's' => 'c123'], false)->ID);
    }
}
