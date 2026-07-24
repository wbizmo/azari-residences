<?php
namespace Tests\Feature;

use App\Models\BookingHold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AzariSprints0506CompletionTest extends TestCase {
    use RefreshDatabase;
    public function test_routes_exist(): void {
        foreach(['azari.availability.results','azari.availability.hold','azari.booking.checkout','azari.booking.store','azari.booking.summary','azari.admin.bookings.index','azari.admin.s56.calendar'] as $name) $this->assertTrue(Route::has($name),$name);
    }
    public function test_pagination_is_ten(): void {
        $this->assertSame(10,config('azari.pagination.per_page'));
        $this->assertSame(10,(new BookingHold)->getPerPage());
    }
}
