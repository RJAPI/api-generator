<?php

namespace SoliDry\Helpers;

/**
 * Class Console - provides primitive console operations
 *
 * @package SoliDry\Helpers
 */
class Console
{
    // actions
    public const CREATED = 'created';

    // user colors
    public const COLOR_RED    = 'red';
    public const COLOR_GREEN  = 'green';
    public const COLOR_YELLOW = 'yellow';

    public const ANSI_COLOR_RED    = "\x1b[31m";
    public const ANSI_COLOR_GREEN  = "\x1b[32m";
    public const ANSI_COLOR_YELLOW = "\x1b[33m";
    public const ANSI_COLOR_RESET  = "\x1b[0m";

    /**
     * @var array|string[]
     */
    private static array $colorMaps = [
        self::COLOR_RED    => self::ANSI_COLOR_RED,
        self::COLOR_GREEN  => self::ANSI_COLOR_GREEN,
        self::COLOR_YELLOW => self::ANSI_COLOR_YELLOW,
    ];

    /**
     * Console output helper/beautifier
     *
     * @param string $str
     * @param null $color
     */
    public static function out(string $str, $color = null) : void
    {
        echo (($color === null) ? '' : self::$colorMaps[$color]) . $str . (($color === null) ? '' : self::ANSI_COLOR_RESET) . PHP_EOL;
    }
}