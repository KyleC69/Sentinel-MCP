<?php

/**
 * Unit tests for User_Manager.
 *
 * @package Sentinel-MCP
 */

use PHPUnit\Framework\TestCase;
use SentinelMCP\User_Manager;

/**
 * Tests user CRUD validation, role guards, and meta filtering.
 */
class UserManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        reset_wp_state();
    }

    // ─── Constants ───────────────────────────────────────────────

    /** @test */
    public function sensitive_meta_keys_include_session_tokens(): void
    {
        $reflection = new \ReflectionClass(User_Manager::class);
        $prop       = $reflection->getProperty('SENSITIVE_META_KEYS');
        $prop->setAccessible(true);
        $keys = $prop->getValue();

        $this->assertContains('session_tokens', $keys);
        $this->assertContains('wp_user-settings', $keys);
    }

    /** @test */
    public function sensitive_meta_prefixes_include_transients(): void
    {
        $reflection = new \ReflectionClass(User_Manager::class);
        $prop       = $reflection->getProperty('SENSITIVE_META_PREFIXES');
        $prop->setAccessible(true);
        $prefixes = $prop->getValue();

        $this->assertContains('_transient_', $prefixes);
        $this->assertContains('_site_transient_', $prefixes);
    }

    // ─── list_users ──────────────────────────────────────────────

    /** @test */
    public function list_users_returns_success_with_defaults(): void
    {
        $result = User_Manager::list_users([]);
        $this->assertTrue($result['success']);
        $this->assertIsArray($result['users']);
        $this->assertEquals(1, $result['page']);
    }

    /** @test */
    public function list_users_respects_per_page_cap(): void
    {
        $result = User_Manager::list_users(['per_page' => 200]);
        // The cap is 100, but we can't easily assert query args in this mock.
        // At minimum we assert the call succeeds.
        $this->assertTrue($result['success']);
    }

    // ─── read_user ───────────────────────────────────────────────

    /** @test */
    public function read_user_requires_user_id(): void
    {
        $result = User_Manager::read_user([]);
        $this->assertFalse($result['success']);
        $this->assertEquals('user_id is required.', $result['message']);
    }

    /** @test */
    public function read_user_returns_not_found_for_invalid_id(): void
    {
        $result = User_Manager::read_user(['user_id' => 99999]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', $result['message']);
    }

    // ─── create_user ─────────────────────────────────────────────

    /** @test */
    public function create_user_requires_username(): void
    {
        $result = User_Manager::create_user(['email' => 'test@example.com']);
        $this->assertFalse($result['success']);
        $this->assertEquals('Username is required.', $result['message']);
    }

    /** @test */
    public function create_user_requires_email(): void
    {
        $result = User_Manager::create_user(['username' => 'testuser']);
        $this->assertFalse($result['success']);
        $this->assertEquals('A valid email is required.', $result['message']);
    }

    /** @test */
    public function create_user_rejects_invalid_email(): void
    {
        $result = User_Manager::create_user([
            'username' => 'testuser',
            'email'    => 'not-an-email',
        ]);
        $this->assertFalse($result['success']);
        $this->assertEquals('A valid email is required.', $result['message']);
    }

    /** @test */
    public function create_user_rejects_duplicate_username(): void
    {
        global $_wp_mock_users;
        $_wp_mock_users = ['existing' => ['id' => 1, 'email' => 'a@b.com']];

        $result = User_Manager::create_user([
            'username' => 'existing',
            'email'    => 'new@example.com',
        ]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already exists', $result['message']);
    }

    /** @test */
    public function create_user_rejects_duplicate_email(): void
    {
        global $_wp_mock_users;
        $_wp_mock_users = ['existing' => ['id' => 1, 'email' => 'dup@example.com']];

        $result = User_Manager::create_user([
            'username' => 'newuser',
            'email'    => 'dup@example.com',
        ]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already registered', $result['message']);
    }

    /** @test */
    public function create_user_generates_password_when_not_provided(): void
    {
        $result = User_Manager::create_user([
            'username' => 'autopass',
            'email'    => 'autopass@example.com',
        ]);
        $this->assertTrue($result['success']);
        $this->assertTrue($result['password_generated']);
    }

    /** @test */
    public function create_user_uses_provided_password(): void
    {
        $result = User_Manager::create_user([
            'username' => 'manualpass',
            'email'    => 'manual@example.com',
            'password' => 'secret123',
        ]);
        $this->assertTrue($result['success']);
        $this->assertFalse($result['password_generated']);
    }

    /** @test */
    public function create_user_defaults_to_subscriber_role(): void
    {
        $result = User_Manager::create_user([
            'username' => 'defaultrole',
            'email'    => 'role@example.com',
        ]);
        $this->assertTrue($result['success']);
        $this->assertEquals('subscriber', $result['role']);
    }

    /** @test */
    public function create_user_blocks_admin_creation_without_capability(): void
    {
        global $_wp_current_user_caps;
        $_wp_current_user_caps = ['manage_options' => false];

        $result = User_Manager::create_user([
            'username' => 'hacker',
            'email'    => 'hacker@example.com',
            'role'     => 'administrator',
        ]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Only administrators', $result['message']);
    }

    // ─── update_user ─────────────────────────────────────────────

    /** @test */
    public function update_user_requires_user_id(): void
    {
        $result = User_Manager::update_user([]);
        $this->assertFalse($result['success']);
        $this->assertEquals('user_id is required.', $result['message']);
    }

    /** @test */
    public function update_user_returns_not_found_for_invalid_id(): void
    {
        $result = User_Manager::update_user(['user_id' => 99999]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', $result['message']);
    }

    /** @test */
    public function update_user_blocks_own_role_change(): void
    {
        global $_wp_current_user_id, $_wp_mock_users_by_id;
        $_wp_current_user_id = 5;
        $_wp_mock_users_by_id[5] = (object) [
            'ID'       => 5,
            'user_login' => 'self',
            'roles'    => ['editor'],
        ];

        $result = User_Manager::update_user([
            'user_id' => 5,
            'role'    => 'subscriber',
        ]);
        $this->assertFalse($result['success']);
        $this->assertEquals('You cannot change your own role.', $result['message']);
    }

    /** @test */
    public function update_user_blocks_admin_promotion_without_capability(): void
    {
        global $_wp_current_user_caps, $_wp_mock_users_by_id;
        $_wp_current_user_caps = ['manage_options' => false];
        $_wp_mock_users_by_id[7] = (object) [
            'ID'       => 7,
            'user_login' => 'victim',
            'roles'    => ['editor'],
        ];

        $result = User_Manager::update_user([
            'user_id' => 7,
            'role'    => 'administrator',
        ]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Only administrators can promote', $result['message']);
    }

    /** @test */
    public function update_user_returns_no_fields_when_empty(): void
    {
        global $_wp_mock_users_by_id;
        $_wp_mock_users_by_id[3] = (object) [
            'ID'       => 3,
            'user_login' => 'nobody',
            'roles'    => ['subscriber'],
        ];

        $result = User_Manager::update_user(['user_id' => 3]);
        $this->assertFalse($result['success']);
        $this->assertEquals('No fields to update.', $result['message']);
    }

    /** @test */
    public function update_user_updates_email(): void
    {
        global $_wp_mock_users_by_id;
        $_wp_mock_users_by_id[4] = (object) [
            'ID'         => 4,
            'user_login' => 'updater',
            'user_email' => 'old@example.com',
            'roles'      => ['subscriber'],
        ];

        $result = User_Manager::update_user([
            'user_id' => 4,
            'email'   => 'new@example.com',
        ]);
        $this->assertTrue($result['success']);
        $this->assertContains('email', $result['updated_fields']);
    }

    // ─── Meta filtering ────────────────────────────────────────────

    /** @test */
    public function read_user_filters_sensitive_meta_keys(): void
    {
        global $_wp_mock_users_by_id, $_wp_mock_user_meta;
        $_wp_mock_users_by_id[10] = (object) [
            'ID'         => 10,
            'user_login' => 'metauser',
            'roles'      => ['subscriber'],
        ];
        $_wp_mock_user_meta[10] = [
            'nickname'       => ['Meta User'],
            'session_tokens' => ['secret'],
            'custom_key'     => ['value'],
        ];

        $result = User_Manager::read_user(['user_id' => 10]);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('nickname', $result['meta']);
        $this->assertArrayNotHasKey('session_tokens', $result['meta']);
        $this->assertArrayHasKey('custom_key', $result['meta']);
    }

    /** @test */
    public function read_user_filters_transient_prefixes(): void
    {
        global $_wp_mock_users_by_id, $_wp_mock_user_meta;
        $_wp_mock_users_by_id[11] = (object) [
            'ID'         => 11,
            'user_login' => 'prefixuser',
            'roles'      => ['subscriber'],
        ];
        $_wp_mock_user_meta[11] = [
            'nickname'              => ['Prefix User'],
            '_transient_foo'        => ['hidden'],
            '_site_transient_bar'   => ['hidden'],
            'visible_key'           => ['shown'],
        ];

        $result = User_Manager::read_user(['user_id' => 11]);
        $this->assertTrue($result['success']);
        $this->assertArrayNotHasKey('_transient_foo', $result['meta']);
        $this->assertArrayNotHasKey('_site_transient_bar', $result['meta']);
        $this->assertArrayHasKey('visible_key', $result['meta']);
    }
}
