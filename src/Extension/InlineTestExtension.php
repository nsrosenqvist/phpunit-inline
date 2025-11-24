<?php

declare(strict_types=1);

namespace PHPUnit\InlineTests\Extension;

use PHPUnit\Event\Application\Started;
use PHPUnit\Event\Application\StartedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

final class InlineTestExtension implements Extension
{
    private static ?ParameterCollection $staticParameters = null;

    public function bootstrap(
        Configuration $configuration,
        Facade $facade,
        ParameterCollection $parameters
    ): void {
        // Store parameters statically so InlineTestLoader can access them
        self::$staticParameters = $parameters;

        // Register a subscriber to log that the extension is loaded
        $facade->registerSubscriber(
            new class () implements StartedSubscriber {
                public function notify(Started $event): void
                {
                    // Extension is active - inline tests will be discovered
                    // through the InlineTestLoader when PHPUnit loads test suites
                }
            }
        );
    }

    /**
     * @return array<string>
     */
    public static function getScanDirectories(): array
    {
        if (self::$staticParameters === null) {
            return [];
        }

        if (!self::$staticParameters->has('scanDirectories')) {
            return [];
        }

        $value = self::$staticParameters->get('scanDirectories');

        return array_map(
            'trim',
            explode(',', $value)
        );
    }
}
