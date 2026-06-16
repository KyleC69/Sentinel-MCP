<?php

/**
 * Unit tests for SENTINEL_Media_Manager.
 *
 * @package Sentinel-MCP
 */

use PHPUnit\Framework\TestCase;
use SentinelMCP\SENTINEL_Media_Manager;

/**
 * Tests MIME type validation and constants.
 */
class MediaManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        reset_wp_state();
    }

    // ─── Constants ───────────────────────────────────────────────

    /** @test */
    public function allowed_mime_prefixes_include_image_video_audio(): void
    {
        $reflection = new \ReflectionClass(SENTINEL_Media_Manager::class);
        $prop       = $reflection->getProperty('ALLOWED_MIME_PREFIXES');
        $prop->setAccessible(true);
        $prefixes = $prop->getValue();

        $this->assertContains('image/', $prefixes);
        $this->assertContains('video/', $prefixes);
        $this->assertContains('audio/', $prefixes);
        $this->assertContains('application/pdf', $prefixes);
    }

    /** @test */
    public function allowed_mime_prefixes_include_office_types(): void
    {
        $reflection = new \ReflectionClass(SENTINEL_Media_Manager::class);
        $prop       = $reflection->getProperty('ALLOWED_MIME_PREFIXES');
        $prop->setAccessible(true);
        $prefixes = $prop->getValue();

        $this->assertContains('application/msword', $prefixes);
        $this->assertContains('application/vnd.openxmlformats-officedocument', $prefixes);
        $this->assertContains('text/csv', $prefixes);
    }

    // ─── MIME validation (via reflection) ────────────────────────

    /** @test */
    public function is_allowed_mime_accepts_image_jpeg(): void
    {
        $reflection = new \ReflectionClass(SENTINEL_Media_Manager::class);
        $method     = $reflection->getMethod('is_allowed_mime');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null, 'image/jpeg'));
    }

    /** @test */
    public function is_allowed_mime_rejects_executable(): void
    {
        $reflection = new \ReflectionClass(SENTINEL_Media_Manager::class);
        $method     = $reflection->getMethod('is_allowed_mime');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(null, 'application/x-msdownload'));
    }

    /** @test */
    public function is_allowed_mime_accepts_pdf(): void
    {
        $reflection = new \ReflectionClass(SENTINEL_Media_Manager::class);
        $method     = $reflection->getMethod('is_allowed_mime');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null, 'application/pdf'));
    }

    /** @test */
    public function is_allowed_mime_accepts_csv(): void
    {
        $reflection = new \ReflectionClass(SENTINEL_Media_Manager::class);
        $method     = $reflection->getMethod('is_allowed_mime');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null, 'text/csv'));
    }
}
