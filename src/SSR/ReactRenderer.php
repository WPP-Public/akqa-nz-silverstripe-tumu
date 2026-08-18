<?php

declare(strict_types=1);

namespace Akqa\SilverStripe\SSR;

use InvalidArgumentException;
use JsonException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Model\ArrayData;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use Throwable;

/**
 * Renders registered React components to HTML via a Node.js SSR bundle, then
 * wraps the markup in a mount node the client can hydrate.
 *
 * Opt-in: enable with SS_REACT_SSR_ENABLED=true (or YAML `enabled: true`) and
 * provide a Vite-built entry at `app/client/dist/ssr.js` (configurable).
 *
 * When Vite HMR is running, SSR is skipped so the client bundle can mount into
 * an empty node instead of hydrating a stale server render.
 */
class ReactRenderer
{
    use Configurable;
    use Injectable;

    private static bool $enabled = false;

    private static string $node_binary = 'node';

    private static string $entry = 'app/client/dist/ssr.js';

    private static int $timeout_ms = 5000;

    private static bool $fallback_on_error = true;

    private static bool $skip_when_vite_hot = true;

    public function __construct(
        private NodeProcess $nodeProcess,
        private LoggerInterface $logger,
    ) {
    }


    /**
     * Render a named React component. Always returns a mount node; inner HTML
     * is populated only when SSR ran successfully.
     */
    public function render(string $name, mixed $props = []): DBHTMLText
    {
        $markup = $this->renderMarkup($name, $props);

        /** @var DBHTMLText $field */
        $field = DBField::create_field('HTMLFragment', $markup);

        return $field;
    }


    public function shouldRenderServerSide(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        if ($this->config()->get('skip_when_vite_hot') && $this->isViteHot()) {
            return false;
        }

        return true;
    }


    protected function renderMarkup(string $name, mixed $props): string
    {
        $this->assertComponentName($name);
        $propsArray = $this->normaliseProps($props);
        $propsJson = $this->encodeProps($propsArray);
        $innerHtml = '';

        if ($this->shouldRenderServerSide()) {
            try {
                $innerHtml = $this->renderServerSide($name, $propsArray);
            } catch (Throwable $e) {
                if (!$this->config()->get('fallback_on_error')) {
                    throw $e;
                }

                $this->logger->warning(
                    'React SSR failed for {component}, falling back to client render',
                    [
                        'component' => $name,
                        'exception' => $e,
                    ]
                );
            }
        }

        return sprintf(
            '<div data-component="%s" data-props="%s">%s</div>',
            Convert::raw2att($name),
            Convert::raw2att($propsJson),
            $innerHtml
        );
    }


    /**
     * @param array<string, mixed> $props
     */
    protected function renderServerSide(string $name, array $props): string
    {
        $entry = $this->getEntryPath();

        if (!is_file($entry)) {
            throw new RuntimeException(sprintf(
                'SSR entry not found at %s. Run your Vite SSR build, or disable SS_REACT_SSR_ENABLED.',
                $entry
            ));
        }

        $payload = json_encode(
            [
                'component' => $name,
                'props' => $props === [] ? new \stdClass() : $props,
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $result = $this->nodeProcess->run(
            [$this->getNodeBinary(), $entry],
            $payload,
            $this->getTimeoutMs(),
            Director::baseFolder()
        );

        if (!$result->isSuccessful()) {
            $error = trim($result->stderr);
            if ($error === '') {
                $error = trim($result->stdout);
            }
            if ($error === '') {
                $error = 'exit code ' . $result->exitCode;
            }

            throw new RuntimeException(sprintf(
                'SSR process failed for %s: %s',
                $name,
                $error
            ));
        }

        // Trailing newlines from the process would become extra text nodes and
        // break React hydration.
        return rtrim($result->stdout, "\r\n");
    }


    /**
     * @return array<string, mixed>
     */
    protected function normaliseProps(mixed $props): array
    {
        if ($props === null || $props === '') {
            return [];
        }

        if ($props instanceof ArrayData) {
            /** @var array<string, mixed> $map */
            $map = $props->toMap();

            return $map;
        }

        if (is_string($props)) {
            try {
                $decoded = json_decode($props, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new InvalidArgumentException(
                    'React component props must be a JSON object: ' . $e->getMessage(),
                    0,
                    $e
                );
            }

            if (!is_array($decoded) || (array_is_list($decoded) && $decoded !== [])) {
                throw new InvalidArgumentException('React component props must be a JSON object');
            }

            /** @var array<string, mixed> $decoded */
            return $decoded;
        }

        if (is_array($props)) {
            if (array_is_list($props) && $props !== []) {
                throw new InvalidArgumentException('React component props must be a JSON object');
            }

            /** @var array<string, mixed> $props */
            return $props;
        }

        throw new InvalidArgumentException(
            'React component props must be an array, JSON object string, or ArrayData'
        );
    }


    /**
     * @param array<string, mixed> $props
     */
    protected function encodeProps(array $props): string
    {
        if ($props === []) {
            return '{}';
        }

        return json_encode(
            $props,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
        );
    }


    protected function assertComponentName(string $name): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid React component name "%s". Use a PascalCase identifier registered in your client registry.',
                $name
            ));
        }
    }


    protected function isEnabled(): bool
    {
        $env = Environment::getEnv('SS_REACT_SSR_ENABLED');

        if ($env === 'false' || $env === '0') {
            return false;
        }

        if ($env === 'true' || $env === '1') {
            return true;
        }

        return (bool) $this->config()->get('enabled');
    }


    protected function isViteHot(): bool
    {
        return Environment::getEnv('SS_ENVIRONMENT_TYPE') == 'dev'
            && Environment::getEnv('SS_USE_VITE_DEV_SERVER') == 'true';
    }


    protected function getEntryPath(): string
    {
        $entry = Environment::getEnv('SS_REACT_SSR_ENTRY');
        if (!is_string($entry) || $entry === '') {
            $entry = (string) $this->config()->get('entry');
        }

        if ($entry === '') {
            throw new RuntimeException('React SSR entry path is empty');
        }

        if ($this->isAbsolutePath($entry)) {
            return $entry;
        }

        return Director::baseFolder() . '/' . ltrim($entry, '/');
    }


    protected function getNodeBinary(): string
    {
        $binary = Environment::getEnv('SS_REACT_SSR_NODE');
        if (is_string($binary) && $binary !== '') {
            return $binary;
        }

        return (string) $this->config()->get('node_binary');
    }


    protected function getTimeoutMs(): int
    {
        $timeout = Environment::getEnv('SS_REACT_SSR_TIMEOUT');
        if (is_numeric($timeout) && (int) $timeout > 0) {
            return (int) $timeout;
        }

        return (int) $this->config()->get('timeout_ms');
    }


    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || (strlen($path) > 1 && ctype_alpha($path[0]) && $path[1] === ':');
    }
}
