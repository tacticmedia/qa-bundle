<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Review;

/**
 * Reads the screenshot tree the journeys write
 * ({@see \TacticMedia\QaBundle\Test\JourneyScreenshots}). The basename carries
 * the capture's origin - test class, method, capture sequence and screen label -
 * and the .json sidecar beside each PNG carries the captured page
 * ({@see ScreenMetadata}).
 *
 * Modes and viewports are whatever the tree holds, so a journey that captures a
 * new viewport or a third colour scheme needs no change here.
 */
final readonly class ScreenshotCatalog
{
    private const MODE = '/^[a-z][a-z0-9-]*$/';
    private const VIEWPORT = '/^(?<width>\d+)x(?<height>\d+)$/';
    private const ORIENTATIONS = ['portrait', 'landscape'];
    private const BASENAME = '/^(?<class>\w+)-(?<method>\w+)-(?<sequence>\d{3})_(?<label>[A-Za-z0-9 _-]+)$/';

    public function __construct(
        private string $root,
        private ?string $projectDirectory = null,
    ) {
    }

    /**
     * @return list<ScreenshotGroup>
     */
    public function groups(): array
    {
        $groups = [];

        foreach ($this->discover() as [$mode, $viewport, $orientation]) {
            $count = \count($this->screens($mode, $viewport));

            if (0 < $count) {
                $groups[] = new ScreenshotGroup($mode, $viewport, $orientation, $count);
            }
        }

        return $groups;
    }

    /**
     * @return list<CapturedScreen> ordered by test class, then capture sequence
     */
    public function screens(string $mode, string $viewport): array
    {
        $directory = $this->directory($mode, $viewport);

        if (null === $directory) {
            return [];
        }

        $screens = [];

        foreach (glob($directory.'/*.png') ?: [] as $file) {
            $screen = $this->parse(basename($file, '.png'));

            if ($screen instanceof CapturedScreen) {
                $screens[] = $screen;
            }
        }

        usort(
            $screens,
            static fn (CapturedScreen $a, CapturedScreen $b): int => [$a->class, $a->sequence] <=> [$b->class, $b->sequence],
        );

        return $screens;
    }

    public function find(string $mode, string $viewport, string $name): ?CapturedScreen
    {
        $screen = $this->parse($name);

        if (!$screen instanceof CapturedScreen || null === $this->absolutePath($mode, $viewport, $name)) {
            return null;
        }

        return $screen;
    }

    /**
     * The origin encoded in a basename, whether or not the file still exists - a
     * re-run replaces the tree, and stale feedback must still name its test.
     */
    public function describe(string $name): ?CapturedScreen
    {
        return $this->parse($name);
    }

    public function orientation(string $mode, string $viewport): ?string
    {
        foreach ($this->discover() as [$candidateMode, $candidateViewport, $orientation]) {
            if ($candidateMode === $mode && $candidateViewport === $viewport) {
                return $orientation;
            }
        }

        return null;
    }

    public function absolutePath(string $mode, string $viewport, string $name): ?string
    {
        return $this->filePath($mode, $viewport, $name, 'png');
    }

    public function metadataPath(string $mode, string $viewport, string $name): ?string
    {
        return $this->filePath($mode, $viewport, $name, 'json');
    }

    /**
     * The capture as the prompt should print it: relative to the project when it
     * sits inside it, so an agent can open the path it reads.
     */
    public function displayPath(string $mode, string $viewport, string $name): ?string
    {
        $path = $this->absolutePath($mode, $viewport, $name);
        $prefix = null === $this->projectDirectory ? null : rtrim($this->projectDirectory, '/').'/';

        if (null === $path || null === $prefix || !str_starts_with($path, $prefix)) {
            return $path;
        }

        return substr($path, \strlen($prefix));
    }

    public function metadata(string $mode, string $viewport, string $name): ?ScreenMetadata
    {
        $path = $this->metadataPath($mode, $viewport, $name);

        return null === $path ? null : ScreenMetadata::fromJson((string) file_get_contents($path));
    }

    /**
     * Everywhere the reviewer can step from $name: along its own group, and across
     * to the same screen in the adjacent groups. Both axes wrap.
     *
     * @throws \InvalidArgumentException when $mode/$viewport is not a group, or holds no capture named $name
     */
    public function navigation(string $mode, string $viewport, string $name): ScreenNavigation
    {
        $screens = $this->screens($mode, $viewport);
        $index = array_find_key($screens, static fn (CapturedScreen $screen): bool => $screen->name === $name)
            ?? throw new \InvalidArgumentException(\sprintf('No capture named "%s" in %s/%s.', $name, $mode, $viewport));

        $groups = $this->groups();
        $group = array_find_key($groups, static fn (ScreenshotGroup $candidate): bool => $candidate->mode === $mode && $candidate->viewport === $viewport)
            ?? throw new \InvalidArgumentException(\sprintf('No screenshot group %s/%s.', $mode, $viewport));

        $above = $groups[$group - 1] ?? $groups[\count($groups) - 1];
        $below = $groups[$group + 1] ?? $groups[0];

        return new ScreenNavigation(
            $index + 1,
            \count($screens),
            $screens[$index - 1] ?? $screens[\count($screens) - 1],
            $screens[$index + 1] ?? $screens[0],
            $above,
            $this->find($above->mode, $above->viewport, $name),
            $below,
            $this->find($below->mode, $below->viewport, $name),
        );
    }

    /**
     * Every <mode>/<WxH>/<orientation> directory under the root, modes
     * lexicographic and viewports ascending by width then height.
     *
     * @return list<array{string, string, string}>
     */
    private function discover(): array
    {
        $found = [];

        foreach (glob($this->root.'/*', \GLOB_ONLYDIR) ?: [] as $modePath) {
            $mode = basename($modePath);

            if (1 !== preg_match(self::MODE, $mode)) {
                continue;
            }

            foreach (glob($modePath.'/*', \GLOB_ONLYDIR) ?: [] as $viewportPath) {
                $viewport = basename($viewportPath);

                if (1 !== preg_match(self::VIEWPORT, $viewport, $size)) {
                    continue;
                }

                foreach (self::ORIENTATIONS as $orientation) {
                    if (is_dir($viewportPath.'/'.$orientation)) {
                        $found[] = [(int) $size['width'], (int) $size['height'], [$mode, $viewport, $orientation]];
                    }
                }
            }
        }

        usort($found, static fn (array $a, array $b): int => [$a[2][0], $a[0], $a[1]] <=> [$b[2][0], $b[0], $b[1]]);

        return array_column($found, 2);
    }

    private function filePath(string $mode, string $viewport, string $name, string $extension): ?string
    {
        $directory = $this->directory($mode, $viewport);

        if (null === $directory || !$this->parse($name) instanceof CapturedScreen) {
            return null;
        }

        $path = \sprintf('%s/%s.%s', $directory, $name, $extension);

        return is_file($path) ? $path : null;
    }

    private function directory(string $mode, string $viewport): ?string
    {
        $orientation = $this->orientation($mode, $viewport);

        return null === $orientation
            ? null
            : \sprintf('%s/%s/%s/%s', $this->root, $mode, $viewport, $orientation);
    }

    private function parse(string $name): ?CapturedScreen
    {
        if (1 !== preg_match(self::BASENAME, $name, $matches)) {
            return null;
        }

        return new CapturedScreen(
            $name,
            $matches['class'],
            $matches['method'],
            (int) $matches['sequence'],
            $matches['label'],
        );
    }
}
