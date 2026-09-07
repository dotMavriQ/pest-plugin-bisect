<?php

declare(strict_types=1);

namespace Pest\Bisect\Support;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Printer
{
    public function __construct(private readonly OutputInterface $output) {}

    public function searching(int $attempts): void
    {
        $this->badge('blue', 'BISECT', sprintf('Searching for a failing order (up to %d attempts).', $attempts));
    }

    public function seedFound(int $seed, int $attempt): void
    {
        $this->line(sprintf(
            '  <fg=gray>Reproduced</> with seed <options=bold>%d</> <fg=gray>on attempt %d.</>',
            $seed,
            $attempt,
        ));
        $this->output->writeln('');
    }

    public function noFailure(int $attempts): void
    {
        $this->badge('green', 'BISECT', sprintf('No order-dependent failure in %d random orders.', $attempts));
    }

    public function seedPassed(int $seed): void
    {
        $this->badge('green', 'BISECT', sprintf('The suite passed with seed %d. Nothing to bisect.', $seed));
    }

    public function failsOnItsOwn(string $victim): void
    {
        $this->badge('red', 'BISECT', 'Not an order dependency.');
        $this->line(sprintf('  <fg=default>%s</> is the first test in the failing run, so nothing poisoned it.', $victim));
        $this->output->writeln('');
    }

    public function cannotReproduce(): void
    {
        $this->badge('red', 'BISECT', 'Could not reproduce the failure at file granularity.');
        $this->line('  <fg=gray>The failure may depend on test order within a single file, or on state this run did not recreate.</>');
        $this->output->writeln('');
    }

    public function bisecting(int $seed, string $victim, int $before): void
    {
        $this->output->writeln([
            '',
            sprintf('  <fg=gray>Seed</>       <fg=default>%d</>', $seed),
            sprintf('  <fg=gray>Failing</>    <fg=default>%s</>', $victim),
            sprintf('  <fg=gray>Before it</>  <fg=default>%d file%s</>', $before, $before === 1 ? '' : 's'),
            '',
        ]);
    }

    public function step(int $run, int $remaining): void
    {
        if (! $this->output->isDecorated()) {
            return;
        }

        $this->output->write(sprintf(
            "\r  <fg=gray>run %d · %d file%s left</>\033[K",
            $run,
            $remaining,
            $remaining === 1 ? '' : 's',
        ));
    }

    /**
     * @param  list<string>  $order
     */
    public function result(int $seed, string $victimName, int $before, array $order): void
    {
        if ($this->output->isDecorated()) {
            $this->output->write("\r\033[K");
        }

        $culprits = array_slice($order, 0, -1);
        $victimFile = $order[count($order) - 1];

        $this->badge('green', 'BISECT', sprintf('Narrowed %d files down to %d.', $before + 1, count($order)));

        $this->output->writeln(['  <fg=gray>This order reproduces the failure:</>', '']);

        foreach ($culprits as $index => $file) {
            $this->output->writeln(sprintf('  <fg=gray>%2d</>  <fg=yellow>%s</>', $index + 1, $file));
        }

        $this->output->writeln(sprintf(
            '  <fg=gray>%2d</>  <fg=red>%s</>  <fg=gray>← %s</>',
            count($order),
            $victimFile,
            $victimName,
        ));

        $this->output->writeln([
            '',
            '  <fg=gray>Replay it:</>',
            '',
            sprintf('  <options=bold>pest --bisect=%d</>', $seed),
            '',
        ]);
    }

    private function badge(string $color, string $label, string $message): void
    {
        $this->output->writeln([
            '',
            sprintf('  <fg=white;options=bold;bg=%s> %s </> %s', $color, $label, $message),
            '',
        ]);
    }

    private function line(string $line): void
    {
        $this->output->writeln($line);
    }
}
