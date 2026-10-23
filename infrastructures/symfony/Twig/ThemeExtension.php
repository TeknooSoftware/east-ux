<?php

/*
 * East UX.
 *
 * LICENSE
 *
 * This source file is subject to the 3-Clause BSD license
 * it is available in LICENSE file at the root of this package
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to richard@teknoo.software so we can send you a copy immediately.
 *
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 *
 * @link        https://teknoo.software/east-collection/ux Project website
 *
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */

declare(strict_types=1);

namespace Teknoo\East\UXBundle\Twig;

use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Teknoo\East\UX\Contracts\Enum\Theme\Mode;
use Teknoo\East\UX\Contracts\Enum\Theme\Style;
use Teknoo\East\UX\Object\DTO\Theme\ThemePreference;
use Teknoo\East\UX\Service\ThemeService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use function htmlspecialchars;
use function implode;
use function is_string;

use const ENT_HTML5;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Twig functions of the System.css x Bootstrap theme, to use on `<html>` and in `<head>`:
 * - `east_ux_theme()`: the theme preference of the visitor (`ThemePreference`),
 * - `east_ux_theme_class(style, mode)`: the class of `<html>` (`system-modern` or an empty string),
 * - `east_ux_theme_attributes(style, mode, url)`: the attributes of `<html>`: `data-bs-theme` and the `theme` Stimulus
 *   controller with its values (style, mode, storage and the URL notified of the changes),
 * - `east_ux_theme_script(nonce)`: an inline script, to put in `<head>`, resolving the `auto` mode from
 *   `prefers-color-scheme` and, with the `browser` storage, applying the choice kept in the localStorage, before the
 *   first paint.
 *
 * Without arguments, the functions render the preference of the visitor (`ThemeService`). An application keeping the
 * choice elsewhere, for example in the account of the user, passes its style and mode (an invalid value is replaced by
 * the preference of the visitor). With the `session` storage, the URL notified is the route `update_route` (when it
 * exists); with the `browser` storage, only the `url` argument, for example an endpoint of the application saving the
 * choice.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
class ThemeExtension extends AbstractExtension
{
    public const string STORAGE_SESSION = 'session';

    public const string STORAGE_BROWSER = 'browser';

    public const string CONTROLLER = 'theme';

    /**
     * Same rules as `ThemeService::getHtmlClass()` and `ThemeService::getBootstrapTheme()`, and the `theme` Stimulus
     * controller.
     */
    private const string SCRIPT = <<<'JS'
        (function (root) {
            var data = root.dataset, style = data.themeStyleValue, mode = data.themeModeValue;
            if ('browser' === data.themeStorageValue) {
                try {
                    var key = data.themeStorageKeyValue || 'east-ux-theme';
                    var storedStyle = localStorage.getItem(key + ':style');
                    var storedMode = localStorage.getItem(key + ':mode');
                    if ('legacy' === storedStyle || 'modern' === storedStyle) {
                        style = data.themeStyleValue = storedStyle;
                    }
                    if ('light' === storedMode || 'dark' === storedMode || 'auto' === storedMode) {
                        mode = data.themeModeValue = storedMode;
                    }
                } catch (e) {}
            }
            var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
            var dark = 'dark' === mode || ('auto' === mode && !!media && media.matches);
            root.classList.toggle('system-modern', 'modern' === style);
            if (dark) {
                root.setAttribute('data-bs-theme', 'dark');
            } else if ('modern' === style) {
                root.setAttribute('data-bs-theme', 'light');
            } else {
                root.removeAttribute('data-bs-theme');
            }
        })(document.documentElement);
        JS;

    public function __construct(
        private readonly ThemeService $themeService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $storage = self::STORAGE_SESSION,
        private readonly ?string $updateRoute = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('east_ux_theme', $this->getPreference(...)),
            new TwigFunction('east_ux_theme_class', $this->getClass(...)),
            new TwigFunction('east_ux_theme_attributes', $this->renderAttributes(...), ['is_safe' => ['html']]),
            new TwigFunction('east_ux_theme_script', $this->renderScript(...), ['is_safe' => ['html']]),
        ];
    }

    public function getPreference(Style|string|null $style = null, Mode|string|null $mode = null): ThemePreference
    {
        $preference = $this->themeService->getPreference();

        return $preference->with(
            style: is_string($style) ? Style::tryFrom($style) : $style,
            mode: is_string($mode) ? Mode::tryFrom($mode) : $mode,
        );
    }

    public function getClass(Style|string|null $style = null, Mode|string|null $mode = null): string
    {
        return $this->themeService->getHtmlClass($this->getPreference($style, $mode));
    }

    private function getUpdateUrl(): ?string
    {
        if (self::STORAGE_SESSION !== $this->storage || null === $this->updateRoute) {
            return null;
        }

        try {
            return $this->urlGenerator->generate($this->updateRoute);
        } catch (RouteNotFoundException) {
            // The routes of the theme are not imported: the choice of the visitor is not sent
            return null;
        }
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    public function renderAttributes(
        Style|string|null $style = null,
        Mode|string|null $mode = null,
        ?string $url = null,
    ): string {
        $preference = $this->getPreference($style, $mode);

        $attributes = [];
        $bootstrapTheme = $this->themeService->getBootstrapTheme($preference);
        if (null !== $bootstrapTheme) {
            $attributes['data-bs-theme'] = $bootstrapTheme;
        }

        $attributes['data-controller'] = self::CONTROLLER;
        $attributes['data-theme-style-value'] = $preference->style->value;
        $attributes['data-theme-mode-value'] = $preference->mode->value;
        $attributes['data-theme-storage-value'] = $this->storage;

        $url ??= $this->getUpdateUrl();
        if (null !== $url && '' !== $url) {
            $attributes['data-theme-url-value'] = $url;
        }

        $html = [];
        foreach ($attributes as $name => $value) {
            $html[] = $name . '="' . self::escape($value) . '"';
        }

        return implode(' ', $html);
    }

    public function renderScript(?string $nonce = null): string
    {
        $nonceAttribute = '';
        if (null !== $nonce && '' !== $nonce) {
            $nonceAttribute = ' nonce="' . self::escape($nonce) . '"';
        }

        return '<script' . $nonceAttribute . '>' . self::SCRIPT . '</script>';
    }
}
