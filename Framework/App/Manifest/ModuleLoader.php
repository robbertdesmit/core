<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest;

use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\UriInterface;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class ModuleLoader
{
    /**
     * @var EntityRepositoryInterface
     */
    private $appRepository;

    /**
     * @var string
     */
    private $shopUrl;

    /**
     * @var ShopIdProvider
     */
    private $shopIdProvider;

    public function __construct(
        EntityRepositoryInterface $appRepository,
        string $shopUrl,
        ShopIdProvider $shopIdProvider
    ) {
        $this->appRepository = $appRepository;
        $this->shopUrl = $shopUrl;
        $this->shopIdProvider = $shopIdProvider;
    }

    public function loadModules(Context $context): array
    {
        $criteria = new Criteria();
        $containsModulesFilter = new NotFilter(
            MultiFilter::CONNECTION_AND,
            [new EqualsFilter('modules', '[]')]
        );
        $appActiveFilter = new EqualsFilter('active', true);
        $criteria->addFilter($containsModulesFilter, $appActiveFilter)
            ->addAssociation('translations.language.locale');

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search($criteria, $context)->getEntities();

        return $this->formatPayload($apps);
    }

    private function formatPayload(AppCollection $apps): array
    {
        $appModules = [];

        foreach ($apps as $app) {
            $modules = $this->formatModules($app);

            if (empty($modules)) {
                continue;
            }

            $appModules[] = [
                'name' => $app->getName(),
                'label' => $this->mapTranslatedLabels($app),
                'modules' => $modules,
            ];
        }

        return $appModules;
    }

    private function formatModules(AppEntity $app): array
    {
        $modules = [];

        try {
            $shopId = $this->shopIdProvider->getShopId();
        } catch (AppUrlChangeDetectedException $e) {
            return [];
        }

        foreach ($app->getModules() as $module) {
            $module['source'] = $this->getModuleUrlWithQuery($app, $shopId, $module);
            $modules[] = $module;
        }

        return $modules;
    }

    private function mapTranslatedLabels(AppEntity $app): array
    {
        $labels = [];

        foreach ($app->getTranslations() as $translation) {
            $labels[$translation->getLanguage()->getLocale()->getCode()] = $translation->getLabel();
        }

        return $labels;
    }

    private function getModuleUrlWithQuery(AppEntity $app, string $shopId, array $module): ?string
    {
        $registeredSource = $module['source'] ?? null;

        if ($registeredSource === null) {
            return null;
        }

        $uri = $this->generateQueryString($registeredSource, $shopId);

        /** @var string $secret */
        $secret = $app->getAppSecret();
        $signature = hash_hmac('sha256', $uri->getQuery(), $secret);

        return Uri::withQueryValue(
            $uri,
            'shopware-shop-signature',
            $signature
        )->__toString();
    }

    private function generateQueryString(string $uri, string $shopId): UriInterface
    {
        $date = new \DateTime();
        $uri = new Uri($uri);

        return Uri::withQueryValues($uri, [
            'shop-id' => $shopId,
            'shop-url' => $this->shopUrl,
            'timestamp' => $date->getTimestamp(),
        ]);
    }
}
