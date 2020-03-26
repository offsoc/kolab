<?php

namespace Tests;

use Facebook\WebDriver\WebDriverKeys;
use PHPUnit\Framework\Assert;
use Tests\Browser\Components\Error;

/**
 * Laravel Dusk Browser extensions
 */
class Browser extends \Laravel\Dusk\Browser
{
    /**
     * Assert number of (visible) elements
     */
    public function assertElementsCount($selector, $expected_count, $visible = true)
    {
        $elements = $this->elements($selector);
        $count = count($elements);

        if ($visible) {
            foreach ($elements as $element) {
                if (!$element->isDisplayed()) {
                    $count--;
                }
            }
        }

        Assert::assertEquals($expected_count, $count, "Count of [$selector] elements is not $count");

        return $this;
    }

    /**
     * Assert Tip element content
     */
    public function assertTip($selector, $content)
    {
        return $this->click($selector)
            ->withinBody(function ($browser) use ($content) {
                $browser->assertSeeIn('div.tooltip .tooltip-inner', $content);
            })
            ->click($selector);
    }

    /**
     * Assert specified error page is displayed.
     */
    public function assertErrorPage(int $error_code)
    {
        $this->with(new Error($error_code), function ($browser) {
            // empty, assertions will be made by the Error component itself
        });

        return $this;
    }

    /**
     * Assert that the given element has specified class assigned.
     */
    public function assertHasClass($selector, $class_name)
    {
        $element = $this->resolver->findOrFail($selector);
        $classes = explode(' ', (string) $element->getAttribute('class'));

        Assert::assertContains($class_name, $classes, "[$selector] has no class '{$class_name}'");

        return $this;
    }

    /**
     * Assert that the given element is readonly
     */
    public function assertReadonly($selector)
    {
        $element = $this->resolver->findOrFail($selector);
        $value = $element->getAttribute('readonly');

        Assert::assertTrue($value == 'true', "Element [$selector] is not readonly");

        return $this;
    }

    /**
     * Assert that the given element is not readonly
     */
    public function assertNotReadonly($selector)
    {
        $element = $this->resolver->findOrFail($selector);
        $value = $element->getAttribute('readonly');

        Assert::assertTrue($value != 'true', "Element [$selector] is not readonly");

        return $this;
    }

    /**
     * Assert that the given element contains specified text,
     * no matter it's displayed or not.
     */
    public function assertText($selector, $text)
    {
        $element = $this->resolver->findOrFail($selector);

        Assert::assertTrue(strpos($element->getText(), $text) !== false, "No expected text in [$selector]");

        return $this;
    }

    /**
     * Remove all toast messages
     */
    public function clearToasts()
    {
        $this->script("jQuery('.toast-container > *').remove()");

        return $this;
    }

    /**
     * Check if in Phone mode
     */
    public static function isPhone()
    {
        return getenv('TESTS_MODE') == 'phone';
    }

    /**
     * Check if in Tablet mode
     */
    public static function isTablet()
    {
        return getenv('TESTS_MODE') == 'tablet';
    }

    /**
     * Check if in Desktop mode
     */
    public static function isDesktop()
    {
        return !self::isPhone() && !self::isTablet();
    }

    /**
     * Returns content of a downloaded file
     */
    public function readDownloadedFile($filename)
    {
        $filename = __DIR__ . "/Browser/downloads/$filename";

        // Give the browser a chance to finish download
        if (!file_exists($filename)) {
            sleep(2);
        }

        Assert::assertFileExists($filename);

        return file_get_contents($filename);
    }

    /**
     * Removes downloaded file
     */
    public function removeDownloadedFile($filename)
    {
        @unlink(__DIR__ . "/Browser/downloads/$filename");

        return $this;
    }

    /**
     * Execute code within body context.
     * Useful to execute code that selects elements outside of a component context
     */
    public function withinBody($callback)
    {
        if ($this->resolver->prefix != 'body') {
            $orig_prefix = $this->resolver->prefix;
            $this->resolver->prefix = 'body';
        }

        call_user_func($callback, $this);

        if (isset($orig_prefix)) {
            $this->resolver->prefix = $orig_prefix;
        }

        return $this;
    }
}
