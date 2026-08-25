<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use TacticMedia\QaBundle\Review\CapturedScreen;
use TacticMedia\QaBundle\Review\FeedbackItem;
use TacticMedia\QaBundle\Review\FeedbackStore;
use TacticMedia\QaBundle\Review\ScreenMetadata;
use TacticMedia\QaBundle\Review\ScreenshotCatalog;
use TacticMedia\QaBundle\Review\SelectionContext;
use TacticMedia\QaBundle\Review\SelectionCropper;
use TacticMedia\QaBundle\Review\SelectionResolver;

/**
 * Shows the screenshots that the journeys wrote and collects visual feedback on
 * them. The prompt action converts that feedback into instructions for an agent.
 *
 * The routes exist only where the host imports config/routes.php, which belongs
 * under `when@dev`. The host sends /_dev through its dev firewall. On Symfony 7.2
 * and later the bundle registers the CSRF token id as stateless. Below 7.2 the
 * token is session-backed and the firewall must permit a session.
 */
#[Route('/_dev/screenshots', requirements: self::REQUIREMENTS)]
final class ScreenshotReviewController extends AbstractController
{
    public const ROUTE_INDEX = 'qa_screenshots_index';
    public const ROUTE_GROUP = 'qa_screenshots_group';
    public const ROUTE_ANNOTATE = 'qa_screenshots_annotate';
    public const ROUTE_RAW = 'qa_screenshots_raw';
    public const ROUTE_PROMPT = 'qa_screenshots_prompt';
    public const ROUTE_PROMPT_RAW = 'qa_screenshots_prompt_raw';
    public const ROUTE_FEEDBACK_CREATE = 'qa_screenshots_feedback_create';
    public const ROUTE_FEEDBACK_UPDATE = 'qa_screenshots_feedback_update';
    public const ROUTE_FEEDBACK_DELETE = 'qa_screenshots_feedback_delete';
    public const ROUTE_FEEDBACK_CLEAR = 'qa_screenshots_feedback_clear';
    public const CSRF_TOKEN = 'screenshot-review';

    /**
     * {name} accepts the same character set that JourneyScreenshots::fileSafe()
     * produces. That set excludes "." and "/", so path traversal is not possible.
     * {mode} and {viewport} use the same method.
     */
    private const REQUIREMENTS = [
        'mode' => '[a-z][a-z0-9-]*',
        'viewport' => '\d+x\d+',
        'name' => '[A-Za-z0-9 _-]+',
    ];

    public function __construct(
        private readonly ScreenshotCatalog $catalog,
        private readonly FeedbackStore $feedback,
        private readonly SelectionResolver $resolver,
        private readonly SelectionCropper $cropper,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: self::ROUTE_INDEX, methods: [Request::METHOD_GET])]
    public function index(): Response
    {
        $groups = $this->catalog->groups();

        if ([] === $groups) {
            return $this->renderPage('@TacticMediaQa/empty.html.twig', [
                'ignored' => $this->catalog->allIgnored(),
            ]);
        }

        return $this->redirectToRoute(self::ROUTE_GROUP, [
            'mode' => $groups[0]->mode,
            'viewport' => $groups[0]->viewport,
        ]);
    }

    #[Route('/prompt', name: self::ROUTE_PROMPT, methods: [Request::METHOD_GET])]
    public function prompt(): Response
    {
        return $this->renderPage('@TacticMediaQa/prompt.html.twig', [
            'prompt' => $this->buildPrompt(),
        ]);
    }

    #[Route('/prompt.txt', name: self::ROUTE_PROMPT_RAW, methods: [Request::METHOD_GET])]
    public function promptRaw(): Response
    {
        return new Response($this->buildPrompt(), Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    #[Route('/feedback', name: self::ROUTE_FEEDBACK_CREATE, methods: [Request::METHOD_POST])]
    public function createFeedback(Request $request): Response
    {
        $this->assertCsrfToken($request);

        $mode = (string) $request->request->get('mode');
        $viewport = (string) $request->request->get('viewport');
        $name = (string) $request->request->get('name');

        $screen = $this->catalog->find($mode, $viewport, $name);

        if (!$screen instanceof CapturedScreen) {
            throw $this->createNotFoundException();
        }

        $imageSize = $this->submittedImageSize($request);
        $rectangle = $this->submittedRectangle($request, $imageSize);
        $note = trim((string) $request->request->get('note'));

        if ('' === $note) {
            return $this->renderAnnotate($mode, $viewport, $screen, [
                'note_missing' => true,
                'pending_rectangle' => $rectangle,
                'pending_image_size' => $imageSize,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->feedback->add(FeedbackItem::create(
            $mode,
            $viewport,
            $name,
            $rectangle,
            $imageSize,
            $note,
            $this->clock->now(),
        ));

        return $this->redirectToRoute(self::ROUTE_ANNOTATE, [
            'mode' => $mode,
            'viewport' => $viewport,
            'name' => $name,
        ], Response::HTTP_SEE_OTHER);
    }

    #[Route('/feedback/{id}/update', name: self::ROUTE_FEEDBACK_UPDATE, methods: [Request::METHOD_POST])]
    public function updateFeedback(Request $request, string $id): RedirectResponse
    {
        $this->assertCsrfToken($request);

        $item = $this->feedback->get($id);

        if (!$item instanceof FeedbackItem) {
            throw $this->createNotFoundException();
        }

        $note = trim((string) $request->request->get('note'));

        if ('' !== $note) {
            $this->feedback->updateNote($id, $note);
        }

        return $this->backToScreenshot($item);
    }

    #[Route('/feedback/{id}/delete', name: self::ROUTE_FEEDBACK_DELETE, methods: [Request::METHOD_POST])]
    public function deleteFeedback(Request $request, string $id): RedirectResponse
    {
        $this->assertCsrfToken($request);

        $item = $this->feedback->get($id);

        if (!$item instanceof FeedbackItem) {
            throw $this->createNotFoundException();
        }

        $this->feedback->remove($id);

        return $this->backToScreenshot($item);
    }

    #[Route('/feedback/clear', name: self::ROUTE_FEEDBACK_CLEAR, methods: [Request::METHOD_POST])]
    public function clearFeedback(Request $request): RedirectResponse
    {
        $this->assertCsrfToken($request);

        $this->feedback->clear();

        return $this->redirectToRoute(self::ROUTE_INDEX, [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/raw/{mode}/{viewport}/{name}.png', name: self::ROUTE_RAW, methods: [Request::METHOD_GET])]
    public function raw(Request $request, string $mode, string $viewport, string $name): BinaryFileResponse
    {
        $path = $this->catalog->absolutePath($mode, $viewport, $name);

        if (null === $path) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path, Response::HTTP_OK, ['Content-Type' => 'image/png'], autoEtag: true);
        $response->setPrivate();
        $response->isNotModified($request);

        return $response;
    }

    #[Route('/{mode}/{viewport}', name: self::ROUTE_GROUP, methods: [Request::METHOD_GET])]
    public function group(string $mode, string $viewport): Response
    {
        $screens = $this->catalog->screens($mode, $viewport);

        if ([] === $screens) {
            throw $this->createNotFoundException();
        }

        $byClass = [];

        foreach ($screens as $screen) {
            $byClass[$screen->class][] = $screen;
        }

        return $this->renderPage('@TacticMediaQa/group.html.twig', [
            'mode' => $mode,
            'viewport' => $viewport,
            'screens_by_class' => $byClass,
            'counts_by_screen' => $this->feedback->countsByScreen($mode, $viewport),
            'ignored' => $this->catalog->ignored($mode, $viewport),
        ]);
    }

    #[Route('/{mode}/{viewport}/{name}', name: self::ROUTE_ANNOTATE, methods: [Request::METHOD_GET])]
    public function annotate(string $mode, string $viewport, string $name): Response
    {
        $screen = $this->catalog->find($mode, $viewport, $name);

        if (!$screen instanceof CapturedScreen) {
            throw $this->createNotFoundException();
        }

        return $this->renderAnnotate($mode, $viewport, $screen);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderAnnotate(
        string $mode,
        string $viewport,
        CapturedScreen $screen,
        array $context = [],
        int $status = Response::HTTP_OK,
    ): Response {
        return $this->renderPage('@TacticMediaQa/annotate.html.twig', [
            'mode' => $mode,
            'viewport' => $viewport,
            'orientation' => $this->catalog->orientation($mode, $viewport),
            'screen' => $screen,
            'notes' => $this->feedback->forScreenshot($mode, $viewport, $screen->name),
            'navigation' => $this->catalog->navigation($mode, $viewport, $screen->name),
            'metadata_missing' => !$this->catalog->metadata($mode, $viewport, $screen->name) instanceof ScreenMetadata,
            ...$context,
        ], $status);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderPage(string $template, array $context = [], int $status = Response::HTTP_OK): Response
    {
        $counts = $this->feedback->countsByGroup();

        return $this->render($template, [
            'groups' => $this->catalog->groups(),
            'feedback_counts' => $counts,
            'feedback_total' => array_sum($counts),
            'mode' => null,
            'viewport' => null,
            ...$context,
        ], new Response(status: $status));
    }

    /**
     * Symfony 6.4 has no #[IsCsrfTokenValid]. The manager is the same-origin
     * manager where the host supports stateless ids, and the session-backed
     * manager below that version.
     */
    private function assertCsrfToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN, (string) $request->request->get('_token'))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
    }

    private function backToScreenshot(FeedbackItem $item): RedirectResponse
    {
        return $this->redirectToRoute(self::ROUTE_ANNOTATE, [
            'mode' => $item->mode,
            'viewport' => $item->viewport,
            'name' => $item->name,
        ], Response::HTTP_SEE_OTHER);
    }

    private function buildPrompt(): string
    {
        $this->cropper->clear();

        $entries = [];

        foreach ($this->feedback->all() as $feedbackItem) {
            $captured = $this->catalog->find($feedbackItem->mode, $feedbackItem->viewport, $feedbackItem->name);

            // A new run can remove a screen. Its basename still gives the test.
            $screen = $captured ?? $this->catalog->describe($feedbackItem->name);
            $metadata = $this->catalog->metadata($feedbackItem->mode, $feedbackItem->viewport, $feedbackItem->name);

            $entries[$screen instanceof CapturedScreen ? $screen->class : 'Unknown'][$feedbackItem->name][] = [
                'item' => $feedbackItem,
                'screen' => $screen,
                'stale' => !$captured instanceof CapturedScreen,
                'orientation' => $this->catalog->orientation($feedbackItem->mode, $feedbackItem->viewport) ?? 'unknown',
                'path' => $this->catalog->displayPath($feedbackItem->mode, $feedbackItem->viewport, $feedbackItem->name),
                'metadata' => $metadata,
                'resolution' => $this->resolve($feedbackItem, $metadata),
                'crop' => $this->cropSelection($feedbackItem),
            ];
        }

        return $this->renderView('@TacticMediaQa/prompt.txt.twig', ['entries' => $entries]);
    }

    private function resolve(FeedbackItem $item, ?ScreenMetadata $metadata): ?SelectionContext
    {
        if (!$metadata instanceof ScreenMetadata || null === $item->rectangle || null === $item->imageSize) {
            return null;
        }

        return $this->resolver->resolve($metadata, $item->rectangle, $item->imageSize);
    }

    private function cropSelection(FeedbackItem $item): ?string
    {
        if (null === $item->rectangle) {
            return null;
        }

        $path = $this->catalog->absolutePath($item->mode, $item->viewport, $item->name);

        return null === $path ? null : $this->cropper->crop($path, $item->rectangle, $item->id);
    }

    /**
     * @param array{width: int, height: int}|null $imageSize
     *
     * @return array{x: int, y: int, width: int, height: int}|null
     */
    private function submittedRectangle(Request $request, ?array $imageSize): ?array
    {
        if (null === $imageSize) {
            return null;
        }

        $values = [];

        foreach (['x', 'y', 'width', 'height'] as $field) {
            $value = $request->request->get($field);

            if (!is_numeric($value)) {
                return null;
            }

            $values[$field] = (int) $value;
        }

        if (1 > $values['width'] || 1 > $values['height']) {
            return null;
        }

        return [
            'x' => max(0, min($values['x'], $imageSize['width'])),
            'y' => max(0, min($values['y'], $imageSize['height'])),
            'width' => min($values['width'], $imageSize['width']),
            'height' => min($values['height'], $imageSize['height']),
        ];
    }

    /**
     * @return array{width: int, height: int}|null
     */
    private function submittedImageSize(Request $request): ?array
    {
        $width = $request->request->get('image_width');
        $height = $request->request->get('image_height');

        if (!is_numeric($width) || !is_numeric($height) || 1 > (int) $width || 1 > (int) $height) {
            return null;
        }

        return ['width' => (int) $width, 'height' => (int) $height];
    }
}
