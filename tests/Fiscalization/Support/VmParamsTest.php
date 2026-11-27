<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Tests\Fiscalization\Support;

use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Support\VmParams;
use PHPUnit\Framework\TestCase;

class VmParamsTest extends TestCase
{
    public function testParsesJsonEncodedValues(): void
    {
        $params = VmParams::parse('tax_code="2"|excise_labels="AA1\nBB2"|classification_code="2203000000"|flag=1|');

        $this->assertSame('2', $params['tax_code']);
        $this->assertSame("AA1\nBB2", $params['excise_labels']);
        $this->assertSame('2203000000', $params['classification_code']);
        $this->assertSame(1, $params['flag']);
    }

    public function testDecodesUnicodeEscapes(): void
    {
        $this->assertSame('АБВ123', VmParams::parse('excise_labels="АБВ123"')['excise_labels']);
    }

    public function testToleratesGarbage(): void
    {
        $this->assertSame([], VmParams::parse(null));
        $this->assertSame([], VmParams::parse(''));
        $this->assertSame([], VmParams::parse('|NULL|noequals|=x|'));
        $this->assertSame('plain', VmParams::parse('k=plain')['k']);
    }

    public function testValueMayContainEqualsSign(): void
    {
        $this->assertSame('a=b', VmParams::parse('k="a=b"')['k']);
    }

    public function testUnescape(): void
    {
        $this->assertSame('plain', VmParams::unescape('plain'));
        $this->assertSame('a/b', VmParams::unescape('a\/b'));
        $this->assertSame("A\nB", VmParams::unescape('A\nB'));
        $this->assertSame('АБВ', VmParams::unescape('АБВ'));
        $this->assertSame('bad\\', VmParams::unescape('bad\\'));
    }
}
