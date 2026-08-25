<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Security\Csrf\SameOriginCsrfTokenManager;
use TacticMedia\QaBundle\Controller\ScreenshotReviewController;

/**
 * Registers the screenshot review page and its services. The capture trait
 * ({@see Test\JourneyScreenshots}) does not use them, because it runs in PHPUnit
 * with no container.
 *
 * The host selects the environment: enable the bundle for `dev` only in
 * config/bundles.php, and import config/routes.php under `when@dev`.
 */
final class TacticMediaQaBundle extends AbstractBundle
{
    protected string $extensionAlias = 'qa';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->import('../config/definition.php');
    }

    public function prependExtension(ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        // Stateless ids exist from Symfony 7.2. Below that version the token of the page is
        // session-backed, so the host must not put /_dev behind a firewall with no session.
        if (class_exists(SameOriginCsrfTokenManager::class)) {
            $container->prependExtensionConfig('framework', [
                'csrf_protection' => [
                    'stateless_token_ids' => [ScreenshotReviewController::CSRF_TOKEN],
                ],
            ]);
        }

        if ($this->isAssetMapperAvailable($container)) {
            $container->prependExtensionConfig('framework', [
                'asset_mapper' => [
                    'paths' => [
                        __DIR__.'/../assets/dist' => '@tacticmedia/qa-bundle',
                    ],
                ],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $this->assertTreesAreSeparate($container, (string) $config['screenshots_dir'], (string) $config['review_dir']);

        $container->setParameter('qa.screenshots_dir', $config['screenshots_dir']);
        $container->setParameter('qa.review_dir', $config['review_dir']);

        $configurator->import('../config/services.php');
    }

    /**
     * Each journey run empties screenshots_dir, so a review_dir inside it loses the notes and
     * the crops with no message. Env placeholders compare as opaque strings, so two trees named
     * by different variables always pass, and two trees built from one variable are rejected.
     */
    private function assertTreesAreSeparate(ContainerBuilder $container, string $screenshotsDir, string $reviewDir): void
    {
        $parameters = $container->getParameterBag();
        $screenshots = rtrim((string) $parameters->resolveValue($screenshotsDir), '/').'/';
        $review = rtrim((string) $parameters->resolveValue($reviewDir), '/').'/';

        if (str_starts_with($review, $screenshots)) {
            throw new InvalidConfigurationException(\sprintf('qa.review_dir ("%s") sits inside qa.screenshots_dir ("%s"), which every journey run empties.', $reviewDir, $screenshotsDir));
        }
    }

    private function isAssetMapperAvailable(ContainerBuilder $container): bool
    {
        if (!interface_exists(AssetMapperInterface::class)) {
            return false;
        }

        $bundles = $container->getParameter('kernel.bundles_metadata');

        return \is_array($bundles)
            && \is_array($bundles['FrameworkBundle'] ?? null)
            && is_file($bundles['FrameworkBundle']['path'].'/Resources/config/asset_mapper.php');
    }
}
