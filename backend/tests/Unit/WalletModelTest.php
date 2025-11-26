<?php

namespace Tests\Unit;

use App\Models\Wallet;
use PHPUnit\Framework\TestCase;

class WalletModelTest extends TestCase
{
    public function test_wallet_can_check_sufficient_balance(): void
    {
        $wallet = new Wallet([
            'balance' => 100,
            'myxn_balance' => 1000,
        ]);

        $this->assertTrue($wallet->hasSufficientBalance(50, 'SOL'));
        $this->assertFalse($wallet->hasSufficientBalance(150, 'SOL'));
        $this->assertTrue($wallet->hasSufficientBalance(500, 'MYXN'));
        $this->assertFalse($wallet->hasSufficientBalance(1500, 'MYXN'));
    }
}
