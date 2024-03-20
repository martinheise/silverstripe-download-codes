<?php

namespace Mhe\DownloadCodes\Tests\Model;

use Mhe\DownloadCodes\Model\DLCode;
use Mhe\DownloadCodes\Model\DLRedemption;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBField;

class DLCodeTest extends SapphireTest
{
    protected static $fixture_file = 'DLCodeTest.yml';

    public function setUp(): void
    {
        parent::setUp();
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    public function testCodeRedeemable()
    {
        $code = DLCode::create(['Active' => true]);
        $this->assertTrue($code->isRedeeamable(), 'Default active DLCode is redeemable');
        $code = DLCode::create(['Active' => false]);
        $this->assertFalse($code->isRedeeamable(), 'Deactivated DLCode is not redeemable');

        $code = DLCode::create(['Active' => true, 'Expires' => DBField::create_field('Datetime', time() + 10) ]);
        $this->assertTrue($code->isRedeeamable(), 'DLCode with expiration in future is redeemable');
        $code = DLCode::create(['Active' => true, 'Expires' => DBField::create_field('Datetime', time() - 10) ]);
        $this->assertFalse($code->isRedeeamable(), 'DLCode with expiration in past is not redeemable');

        $code = DLCode::create(['Active' => true, 'Limited' => true, 'UsageCount' => 10]);
        $this->assertFalse($code->isRedeeamable(), 'Limited DLCode with UsageCount above limit is not redeemable');
        $code = DLCode::create(['Active' => true, 'Limited' => false, 'UsageCount' => 10]);
        $this->assertTrue($code->isRedeeamable(), 'Unlimited DLCode with UsageCount above limit is redeemable');
    }

    public function testRedeemLimited()
    {
        $code = $this->objFromFixture(DLCode::class, 'valid_default');
        $result = $code->redeem();
        $this->assertEquals(1, $code->UsageCount, 'Code redemption updates usage count');
        $this->assertTrue($result instanceof DLRedemption, 'Code redemption returns a DLRedemption object');
        $this->assertEquals($code->ID, $result->Code()->ID, 'New DLRedemption object is related to the code');
        $this->assertTrue($result->exists(), 'a new redemption is written');

        $code = $this->objFromFixture(DLCode::class, 'valid_used_once');
        $redemption = $this->objFromFixture(DLRedemption::class, 'used_once');
        $result = $code->redeem();
        $this->assertEquals(2, $code->UsageCount, 'Code redemption updates usage count');
        $this->assertEquals($redemption->URLSecret, $result->URLSecret, 'An existing redemption is re-used');
        $this->assertEquals($redemption->ID, $result->ID, 'An existing redemption is re-used');
    }

    public function testRedeemUnlimited()
    {
        $code = $this->objFromFixture(DLCode::class, 'valid_unlimited');
        $result = $code->redeem();
        $this->assertEquals(1, $code->UsageCount, 'Code redemption updates usage count');
        $this->assertTrue($result instanceof DLRedemption, 'Code redemption returns a DLRedemption object');
        $this->assertEquals($code->ID, $result->Code()->ID, 'New DLRedemption object is related to the code');
        $this->assertNotEmpty($result->URLSecret);
        $this->assertNotEmpty($result->Expires);
        $this->assertTrue($result->exists(), 'a new redemption is written');

        $code = $this->objFromFixture(DLCode::class, 'valid_unlimited_used');
        $redemption = $this->objFromFixture(DLRedemption::class, 'unlimited_used');
        $result = $code->redeem();
        $this->assertEquals(51, $code->UsageCount, 'Code redemption updates usage count');
        $this->assertTrue($result->exists(), 'a new redemption is written');
        $this->assertEquals($code->ID, $result->Code()->ID, 'New DLRedemption object is related to the code');
        $this->assertNotEquals($redemption->URLSecret, $result->URLSecret, 'a new redemption is written instead of the existing one');
        $this->assertGreaterThan($redemption->ID, $result->ID, 'a new redemption is written instead of the existing one');
    }

    /**
     * test validation (for creation of new DLCodes)
     */
    public function testValidateUnique()
    {
        $code = new DLCode([ 'Code' => 'abc']);
        $code->write();
        $code = new DLCode([ 'Code' => 'abc1234']);
        $this->assertTrue($code->validate()->isValid());
        $code = new DLCode([ 'Code' => 'abc']);
        $this->assertFalse($code->validate()->isValid());
        // validation is always case-insensitive:
        $code = new DLCode([ 'Code' => 'aBC']);
        $this->assertFalse($code->validate()->isValid());
    }

    /**
     * test getting a redemption for code as entered by a user
     */
    public function testGetRedeemable()
    {
        Config::modify()->set(DLCode::class, 'case_sensitive', true);
        $code = DLCode::get_redeemable_code('VALIDCODE');
        $this->assertNotEmpty($code);
        $this->assertTrue($code->isRedeeamable());
        $code = DLCode::get_redeemable_code('vAlIDcoDE');
        $this->assertEmpty($code);

        Config::modify()->set(DLCode::class, 'case_sensitive', false);
        $code = DLCode::get_redeemable_code('VALIDCODE');
        $this->assertNotEmpty($code);
        $this->assertTrue($code->isRedeeamable());
        $code = DLCode::get_redeemable_code('vAlIDcoDE');
        $this->assertNotEmpty($code);
        $this->assertTrue($code->isRedeeamable());
    }

    public function testAutoGenerate()
    {
        Config::modify()->set(DLCode::class, 'autogenerate_chars', 'abcde');
        Config::modify()->set(DLCode::class, 'autogenerate_length', 6);
        for ($i = 0; $i < 5; $i++) {
            $code = DLCode::autoGenerate([]);
            $this->assertTrue($code->Active);
            $this->assertTrue($code->Limited);
            $codecheck = DLCode::get()->filter(['Code' => $code->Code]);
            $this->assertEquals(1, $codecheck->count());
            $this->assertEquals($code->ID, $codecheck->first()->ID);
            // hard to test random values reliably, but let’s give it a try ...
            $this->assertMatchesRegularExpression('!^[abcde]{6}$!', $code->Code);
        }
        Config::modify()->set(DLCode::class, 'autogenerate_chars', 'ABC012');
        Config::modify()->set(DLCode::class, 'autogenerate_length', 4);
        for ($i = 0; $i < 5; $i++) {
            $code = DLCode::autoGenerate([]);
            $this->assertTrue($code->Active);
            $this->assertTrue($code->Limited);
            $codecheck = DLCode::get()->filter(['Code' => $code->Code]);
            $this->assertEquals(1, $codecheck->count());
            $this->assertEquals($code->ID, $codecheck->first()->ID);
            // hard to test random values reliably, but let’s give it a try ...
            $this->assertMatchesRegularExpression('!^[ABC012]{4}$!', $code->Code);
        }
    }
}
