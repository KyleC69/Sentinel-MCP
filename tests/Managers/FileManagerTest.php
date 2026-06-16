<?php

/**
 * Unit tests for SENTINEL_File_Manager.
 *
 * @package Sentinel-MCP
 */

use PHPUnit\Framework\TestCase;
use SentinelMCP\SENTINEL_File_Manager;

/**
 * Tests path validation, constants, and backup helpers.
 */
class FileManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        reset_wp_state();
    }

    // ─── Constants ───────────────────────────────────────────────

    /** @test */
    public function max_file_size_is_5_mb(): void
    {
        $this->assertEquals(5 * 1024 * 1024, SENTINEL_File_Manager::MAX_FILE_SIZE);
    }

    /** @test */
    public function allowed_extensions_include_common_web_types(): void
    {
        $this->assertContains('php', SENTINEL_File_Manager::ALLOWED_EXTENSIONS);
        $this->assertContains('css', SENTINEL_File_Manager::ALLOWED_EXTENSIONS);
        $this->assertContains('js', SENTINEL_File_Manager::ALLOWED_EXTENSIONS);
        $this->assertContains('json', SENTINEL_File_Manager::ALLOWED_EXTENSIONS);
    }

    /** @test */
    public function allowed_abspath_files_are_htaccess_and_maintenance(): void
    {
        $this->assertEquals(['.htaccess', '.maintenance'], SENTINEL_File_Manager::ALLOWED_ABSPATH_FILES);
    }

    // ─── Path validation ───────────────────────────────────────────

    /** @test */
    public function validate_path_rejects_traversal(): void
    {
        $result = SENTINEL_File_Manager::validate_path('../../../etc/passwd');
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('path_traversal', $result->get_error_code());
    }

    /** @test */
    public function validate_path_rejects_null_bytes(): void
    {
        $result = SENTINEL_File_Manager::validate_path("themes/style\0.css");
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('invalid_path', $result->get_error_code());
    }

    /** @test */
    public function validate_path_rejects_stream_wrappers(): void
    {
        $result = SENTINEL_File_Manager::validate_path('phar://malicious.phar');
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('invalid_path', $result->get_error_code());
    }

    /** @test */
    public function validate_path_rejects_disallowed_extension(): void
    {
        $result = SENTINEL_File_Manager::validate_path('uploads/image.exe');
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('extension_not_allowed', $result->get_error_code());
    }

    /** @test */
    public function validate_path_accepts_allowed_abspath_file(): void
    {
        $result = SENTINEL_File_Manager::validate_path('.htaccess', false);
        $this->assertIsString($result);
        $this->assertStringEndsWith('.htaccess', $result);
    }

    /** @test */
    public function get_backup_dir_returns_uploads_subdirectory(): void
    {
        $dir = SENTINEL_File_Manager::get_backup_dir();
        $this->assertStringContainsString('sentinel-backups', $dir);
    }

    /** @test */
    public function get_display_path_returns_relative_for_content(): void
    {
        $content = wp_normalize_path(WP_CONTENT_DIR);
        $path    = $content . '/themes/twentytwentysix/style.css';
        $display = SENTINEL_File_Manager::get_display_path($path);
        $this->assertEquals('themes/twentytwentysix/style.css', $display);
    }
}
