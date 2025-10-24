#!/usr/bin/php
<?php
declare(strict_types=1);

/**
 * FPP Pre-Show Countdown Script
 * PHP 8.2+ compatible countdown timer for Falcon Player
 */

// Strict error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

#############################################################################
# Configuration Class
#############################################################################

final class CountdownConfig
{
    public function __construct(
        public readonly int $duration,
        public readonly string $topText = 'Next Show',
        public readonly string $topColor = '#00FF00',
        public readonly string $bottomColor = '#8000FF',
        public readonly bool $verbose = false,
        public readonly string $host = 'localhost',
        public readonly string $topModelName = 'Matrix_Overlay_Top',
        public readonly string $bottomModelName = 'Matrix_Overlay_Bottom',
        public readonly string $font = 'NimbusSans-Regular',
        public readonly int $topFontSize = 26,
        public readonly int $baseFontSize = 46,
        public readonly int $largeFontSize = 42,
        public readonly string $position = 'Center',
        public readonly int $pixelsPerSecond = 0,
        public readonly bool $antiAlias = true,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->duration <= 0) {
            throw new InvalidArgumentException("Duration must be positive, got: {$this->duration}");
        }

        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $this->topColor)) {
            throw new InvalidArgumentException("Invalid top-color format: {$this->topColor}. Use #RRGGBB");
        }

        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $this->bottomColor)) {
            throw new InvalidArgumentException("Invalid bottom-color format: {$this->bottomColor}. Use #RRGGBB");
        }
    }

    public static function fromCommandLine(array $options): self
    {
        return new self(
            duration: (int)($options['duration'] ?? 0),
            topText: $options['top-text'] ?? 'Next Show',
            topColor: $options['top-color'] ?? '#00FF00',
            bottomColor: $options['bottom-color'] ?? '#8000FF',
            verbose: isset($options['verbose']),
        );
    }
}

#############################################################################
# FPP API Client Class
#############################################################################

final class FPPClient
{
    private readonly \CurlHandle $curl;

    public function __construct(
        private readonly string $host,
        private readonly bool $verbose = false
    ) {
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, 5);
    }

    public function __destruct()
    {
        curl_close($this->curl);
    }

    public function clearOverlay(string $modelName): bool
    {
        $url = "http://{$this->host}/api/overlays/model/{$modelName}/clear";
        return $this->get($url) !== false;
    }

    public function setOverlayState(string $modelName, int $state): bool
    {
        $url = "http://{$this->host}/api/overlays/model/{$modelName}/state";
        $data = ['State' => $state];
        return $this->put($url, $data);
    }

    public function updateOverlayText(
        string $modelName,
        string $message,
        string $color,
        string $font,
        int $fontSize,
        bool $antiAlias,
        string $position,
        int $pixelsPerSecond
    ): bool {
        $url = "http://{$this->host}/api/overlays/model/{$modelName}/text";
        $data = [
            'Message' => $message,
            'Color' => $color,
            'Font' => $font,
            'FontSize' => $fontSize,
            'AntiAlias' => $antiAlias,
            'Position' => $position,
            'PixelsPerSecond' => $pixelsPerSecond,
        ];
        return $this->put($url, $data);
    }

    private function get(string $url): string|false
    {
        $result = @file_get_contents($url);
        if ($result === false && $this->verbose) {
            error_log("Failed to GET: {$url}");
        }
        return $result;
    }

    private function put(string $url, array $data): bool
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);

        curl_setopt_array($this->curl, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);

        $result = curl_exec($this->curl);
        $httpCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);

        if ($result === false || $httpCode < 200 || $httpCode >= 300) {
            if ($this->verbose) {
                $error = curl_error($this->curl);
                error_log("Failed to PUT {$url}: HTTP {$httpCode}, {$error}");
            }
            return false;
        }

        return true;
    }
}

#############################################################################
# Countdown Timer Class
#############################################################################

final class CountdownTimer
{
    private readonly DateTimeImmutable $startTime;
    private readonly DateTimeImmutable $endTime;
    private bool $running = true;
    private int $currentFontSize;

    public function __construct(
        private readonly CountdownConfig $config,
        private readonly FPPClient $client
    ) {
        $this->currentFontSize = $config->baseFontSize;

        // Set timezone from system
        $timezone = trim(@file_get_contents('/etc/timezone') ?: 'UTC');
        date_default_timezone_set($timezone);

        // Calculate aligned start time
        $now = new DateTimeImmutable();
        $seconds = (int)$now->format('s');

        // Align to nearest minute
        $this->startTime = $seconds >= 30
            ? $now->modify('+1 minute')->setTime((int)$now->format('H'), (int)$now->format('i'), 0)
            : $now->setTime((int)$now->format('H'), (int)$now->format('i'), 0);

        $this->endTime = $this->startTime->modify("+{$config->duration} minutes");

        if ($config->verbose) {
            $this->log("Script started: " . $now->format('H:i:s'));
            $this->log("Aligned start: " . $this->startTime->format('H:i:s'));
            $this->log("Show time: " . $this->endTime->format('H:i:s'));
            $this->log("Duration: {$config->duration} minutes");
        }

        // Setup signal handler for graceful shutdown
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, fn() => $this->stop());
        pcntl_signal(SIGTERM, fn() => $this->stop());
    }

    public function run(): void
    {
        // Initialize overlays
        $this->initializeOverlays();

        if ($this->config->verbose) {
            $this->log("Top: \"{$this->config->topText}\" ({$this->config->topColor})");
            $this->log("Bottom color: {$this->config->bottomColor}");
            $this->log("Starting countdown loop...");
        }

        // Main countdown loop
        while ($this->running) {
            $message = $this->getCountdownMessage();

	    if ($this->config->verbose) {
                $this->log("Message: " . $message);
            }

            $this->client->updateOverlayText(
                $this->config->bottomModelName,
                $message,
                $this->config->bottomColor,
                $this->config->font,
                $this->currentFontSize,
                $this->config->antiAlias,
                $this->config->position,
                $this->config->pixelsPerSecond
            );

            if (!$this->running) {
                break;
            }

            // Sleep until next second boundary
            $this->sleepUntilNextSecond();
        }

        if ($this->config->verbose) {
            $this->log("Countdown complete at " . (new DateTimeImmutable())->format('H:i:s'));
        }

        // Cleanup
        $this->cleanupOverlays();
    }

    private function initializeOverlays(): void
    {
        // Clear overlays
        $this->client->clearOverlay($this->config->topModelName);
        $this->client->clearOverlay($this->config->bottomModelName);

        // Enable overlays
        $this->client->setOverlayState($this->config->topModelName, 1);
        $this->client->setOverlayState($this->config->bottomModelName, 1);

        // Set top section (static)
        $this->client->updateOverlayText(
            $this->config->topModelName,
            $this->config->topText,
            $this->config->topColor,
            $this->config->font,
            $this->config->topFontSize,
            $this->config->antiAlias,
            $this->config->position,
            $this->config->pixelsPerSecond
        );
    }

    private function getCountdownMessage(): string
    {
        $now = new DateTimeImmutable();
        $diff = $this->endTime->getTimestamp() - $now->getTimestamp();

        // Stop if countdown complete
        if ($diff <= 0) {
            $this->running = false;
            return '0:00';
        }

        $minutes = (int)($diff / 60);
        $seconds = $diff % 60;

        $message = sprintf('%d:%02d', $minutes, $seconds);

        // Adjust font size for long countdowns (>99:59)
        $this->currentFontSize = strlen($message) > 5
            ? $this->config->largeFontSize
            : $this->config->baseFontSize;

        return $message;
    }

    private function sleepUntilNextSecond(): void
    {
        $microtime = microtime(true);
        $microseconds = (int)((1.0 - ($microtime - floor($microtime))) * 1_000_000);
        usleep($microseconds);
    }

    private function cleanupOverlays(): void
    {
        $this->client->clearOverlay($this->config->bottomModelName);
        $this->client->setOverlayState($this->config->bottomModelName, 0);

        $this->client->clearOverlay($this->config->topModelName);
        $this->client->setOverlayState($this->config->topModelName, 0);

        if ($this->config->verbose) {
            $this->log('Overlays cleared and disabled');
        }
    }

    private function stop(): void
    {
        if ($this->config->verbose) {
            $this->log('Received signal, shutting down...');
        }
        $this->running = false;
    }

    private function log(string $message): void
    {
        echo "[" . date('H:i:s') . "] {$message}\n";
    }
}

#############################################################################
# Main Program
#############################################################################

function showHelp(): void
{
    echo <<<'HELP'
FPP Pre-Show Countdown Script

Usage:
  countdown.php --duration=90 [options]

Required:
  --duration=M         Countdown duration in minutes

Optional:
  --top-text="TEXT"    Static text for top section (default: "Next Show")
  --top-color="#HEX"   Color for top section (default: #00FF00)
  --bottom-color="#HEX" Color for countdown (default: #8000FF)
  --verbose            Enable verbose logging
  --help               Show this help

Examples:
  countdown.php --duration=90
  countdown.php --duration=90 --top-text="Halloween" --top-color="#FF6600" --bottom-color="#FFFFFF"
  countdown.php --duration=120 --verbose

HELP;
}

function main(): int
{
    $options = getopt('', [
        'duration:',
        'top-text:',
        'top-color:',
        'bottom-color:',
        'verbose',
        'help'
    ]);

    if (isset($options['help'])) {
        showHelp();
        return 0;
    }

    try {
        $config = CountdownConfig::fromCommandLine($options);
        $client = new FPPClient($config->host, $config->verbose);
        $timer = new CountdownTimer($config, $client);

        $timer->run();

        return 0;
    } catch (InvalidArgumentException $e) {
        error_log("ERROR: {$e->getMessage()}");
        return 1;
    } catch (Throwable $e) {
        error_log("FATAL ERROR: {$e->getMessage()}");
        if ($config->verbose ?? false) {
            error_log($e->getTraceAsString());
        }
        return 1;
    }
}

exit(main());
