<?php

namespace Tests\Unit;

use App\Support\CountryCodes;
use Tests\TestCase;

class CountryCodesTest extends TestCase
{
    public function test_supported_country_code_is_normalized_to_upper_case(): void
    {
        $this->assertSame('PL', CountryCodes::normalize(' pl '));
    }

    public function test_kosovo_code_is_supported(): void
    {
        $this->assertSame('XK', CountryCodes::normalize('xk'));
    }

    public function test_country_code_not_assigned_in_iso_is_rejected(): void
    {
        $this->assertNull(CountryCodes::normalize('ZZ'));
        $this->assertNull(CountryCodes::normalize('zz'));
    }

    public function test_country_code_with_wrong_length_is_rejected(): void
    {
        $this->assertNull(CountryCodes::normalize('POL'));
        $this->assertNull(CountryCodes::normalize('P'));
        $this->assertNull(CountryCodes::normalize(''));
    }

    public function test_non_string_country_code_is_rejected(): void
    {
        $this->assertNull(CountryCodes::normalize(null));
        $this->assertNull(CountryCodes::normalize(['PL']));
        $this->assertNull(CountryCodes::normalize(42));
    }
}
