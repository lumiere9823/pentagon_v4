<?php

namespace Tests\Unit\DocumentEngine;

use App\Domains\DocumentEngine\Sanitization\HtmlSanitizer;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new HtmlSanitizer;
    }

    public function test_safe_html_formatting_is_preserved(): void
    {
        $input = '<p>Hello <strong>World</strong>, this is <em>italic</em> and <a href="https://example.com" target="_blank">a safe link</a>.</p>';
        $output = $this->sanitizer->sanitize($input);

        $this->assertStringContainsString('Hello', $output);
        $this->assertStringContainsString('<strong>World</strong>', $output);
        $this->assertStringContainsString('<em>italic</em>', $output);
        $this->assertStringContainsString('href="https://example.com"', $output);
        $this->assertStringContainsString('target="_blank"', $output);
    }

    public function test_scripts_and_malicious_tags_are_stripped(): void
    {
        $input = '<p>Text<script>alert("xss")</script><iframe src="https://evil.com"></iframe><object data="bad"></object></p>';
        $output = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('<script', $output);
        $this->assertStringNotContainsString('alert(', $output);
        $this->assertStringNotContainsString('<iframe', $output);
        $this->assertStringNotContainsString('<object', $output);
    }

    public function test_event_handlers_are_stripped(): void
    {
        $input = '<p onclick="stealCookies()" onmouseover="evil()">Hover me</p><img src="x" onerror="alert(1)" />';
        $output = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('onclick', $output);
        $this->assertStringNotContainsString('onmouseover', $output);
        $this->assertStringNotContainsString('onerror', $output);
        $this->assertStringNotContainsString('stealCookies', $output);
    }

    public function test_javascript_and_data_schemes_in_links_are_stripped(): void
    {
        $input = '<p><a href="javascript:alert(1)">Click 1</a><a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">Click 2</a></p>';
        $output = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('javascript:', $output);
        $this->assertStringNotContainsString('data:text', $output);
    }

    public function test_is_safe_url(): void
    {
        // Safe URLs
        $this->assertTrue($this->sanitizer->isSafeUrl('https://example.com'));
        $this->assertTrue($this->sanitizer->isSafeUrl('http://example.com/path?q=1'));
        $this->assertTrue($this->sanitizer->isSafeUrl('/storage/images/hero.png'));
        $this->assertTrue($this->sanitizer->isSafeUrl('/about-us'));
        $this->assertTrue($this->sanitizer->isSafeUrl('#section-2'));
        $this->assertTrue($this->sanitizer->isSafeUrl('mailto:contact@example.com'));
        $this->assertTrue($this->sanitizer->isSafeUrl('tel:+123456789'));
        $this->assertTrue($this->sanitizer->isSafeUrl(null));
        $this->assertTrue($this->sanitizer->isSafeUrl(''));

        // Unsafe URLs
        $this->assertFalse($this->sanitizer->isSafeUrl('javascript:alert(1)'));
        $this->assertFalse($this->sanitizer->isSafeUrl('JAVASCRIPT:void(0)'));
        $this->assertFalse($this->sanitizer->isSafeUrl('data:text/html;base64,xxx'));
        $this->assertFalse($this->sanitizer->isSafeUrl('vbscript:msgbox(1)'));
        $this->assertFalse($this->sanitizer->isSafeUrl("https://example.com\x00/hidden"));
        $this->assertFalse($this->sanitizer->isSafeUrl('http://example.com:bad'));
    }
}
