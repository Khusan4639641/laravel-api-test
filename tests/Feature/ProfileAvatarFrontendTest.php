<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProfileAvatarFrontendTest extends TestCase
{
    public function test_profile_avatar_upload_is_saved_from_save_handler(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/safi/pages/dashboard/Profile.tsx'));

        $this->assertStringContainsString('setSelectedAvatarFile(file);', $source);
        $this->assertStringContainsString('const handleSaveProfile = async () =>', $source);
        $this->assertStringContainsString('await uploadDashboardAvatar(selectedAvatarFile);', $source);
        $this->assertStringContainsString('await refreshCurrentUser();', $source);
        $this->assertStringContainsString("showToast(selectedAvatarFile ? 'Фото профиля обновлено' : 'Профиль сохранён')", $source);
        $this->assertStringContainsString('cursor-pointer', $source);
    }
}
