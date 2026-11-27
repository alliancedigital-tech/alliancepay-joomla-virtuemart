<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Tests\Fiscalization\Domain;

use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\MoneyConverter;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\ProportionalAllocator as A;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ProportionalAllocatorTest extends TestCase
{
    public function testSharesAddUpAndRemainderGoesToLastPositiveWeight(): void
    {
        $shares = A::allocate(['a' => 1, 'b' => 1, 'c' => 1, 'z' => 0], 100);

        $this->assertSame(['a' => 33, 'b' => 33, 'c' => 34, 'z' => 0], $shares);
        $this->assertSame(100, array_sum($shares));
    }

    public function testKeepsProportions(): void
    {
        $this->assertSame([0 => 2500, 1 => 7500], A::allocate([1, 3], 10000));
    }

    public function testRejectsBadInput(): void
    {
        foreach ([[[0, 0], 10], [[-1, 2], 10], [[1], -1]] as [$weights, $total]) {
            try {
                A::allocate($weights, $total);
                $this->fail('InvalidArgumentException expected');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testMoneyConverter(): void
    {
        $this->assertSame(12345, MoneyConverter::toKopecks('123.45000'));
        $this->assertSame(1999, MoneyConverter::toKopecks(19.99));
        $this->assertSame(0, MoneyConverter::toKopecks(null));
        $this->assertSame(120.0, MoneyConverter::kopecksToUah(12000));
        $this->assertSame(0.07, MoneyConverter::kopecksToUah(7));
    }
}
