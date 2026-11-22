<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\VietnameseText;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_locale_switcher_renders_on_all_role_navbars_and_persists_session_and_cookie(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff', 'employee_code' => 'NV501']);

        // Mặc định là tiếng Việt trên trang khách
        $home = $this->get('/');
        $home->assertOk();
        $home->assertSee('<html lang="vi">', false);
        $home->assertSee('data-lang-btn="vi"', false);
        $home->assertSee('data-lang-btn="en"', false);

        // Chuyển sang tiếng Anh qua POST /locale -> lưu session và cookie cinema_locale=en
        $res = $this->postJson('/locale', ['locale' => 'en']);
        $res->assertOk()
            ->assertJson(['locale' => 'en'])
            ->assertPlainCookie('cinema_locale', 'en');

        // Khách tải lại trang vẫn giữ lang="en"
        $homeEn = $this->get('/');
        $homeEn->assertOk();
        $homeEn->assertSee('<html lang="en">', false);

        // Admin thấy nút chuyển đổi ngôn ngữ trên navbar Admin
        $this->actingAs($admin)
            ->get('/admin/staff/schedule')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('data-lang-btn="vi"', false)
            ->assertSee('data-lang-btn="en"', false);

        // Nhân viên thấy nút chuyển đổi ngôn ngữ trên navbar Staff
        $this->actingAs($staff)
            ->get('/staff/payroll')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('data-lang-btn="vi"', false)
            ->assertSee('data-lang-btn="en"', false);

        // Chuyển lại tiếng Việt qua GET /locale/vi
        $this->get('/locale/vi')
            ->assertRedirect()
            ->assertPlainCookie('cinema_locale', 'vi');

        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="vi">', false);
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $this->postJson('/locale', ['locale' => 'fr'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('locale');
    }

    public function test_vietnamese_proper_names_strip_diacritics_cleanly(): void
    {
        $this->assertSame('Nguyen Van An', VietnameseText::stripAccents('Nguyễn Văn An'));
        $this->assertSame('Do My Duyen', VietnameseText::stripAccents('Đỗ Mỹ Duyên'));
        $this->assertSame('Truong Ngoc Anh', VietnameseText::stripAccents('Trương Ngọc Ánh'));
        $this->assertSame(
            '140 Le Trong Tan, Tan Phu, TP.HCM',
            VietnameseText::stripAccents('140 Lê Trọng Tấn, Tân Phú, TP.HCM')
        );
    }

    public function test_movie_titles_expose_english_translations_and_support_english_search(): void
    {
        \App\Models\Movie::create([
            'tmdb_id' => '969681',
            'title' => 'Người Nhện: Khởi Đầu Mới',
            'title_en' => 'Spider-Man: Brand New Day',
            'description' => 'Mô tả tiếng Việt',
            'description_en' => 'Official English synopsis',
            'status' => 'showing',
            'duration_minutes' => 130,
        ]);
        \App\Support\MovieTranslations::forgetCache();

        $map = \App\Support\MovieTranslations::titleMap();
        $this->assertSame('Spider-Man: Brand New Day', $map['Người Nhện: Khởi Đầu Mới'] ?? null);

        $this->get('/')
            ->assertOk()
            ->assertSee('Spider-Man: Brand New Day', false);

        $this->getJson('/api/movies?search=Spider-Man')
            ->assertOk()
            ->assertJsonPath('data.0.title_en', 'Spider-Man: Brand New Day');
    }
}
