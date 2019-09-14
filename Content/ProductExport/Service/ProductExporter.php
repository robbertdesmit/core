<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\ProductExport\Exception\ExportInvalidException;
use Shopware\Core\Content\ProductExport\Exception\ExportNotFoundException;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductExporter implements ProductExporterInterface
{
    /** @var EntityRepositoryInterface */
    private $productExportRepository;

    /** @var FilesystemInterface */
    private $fileSystem;

    /** @var ProductExportGeneratorInterface */
    private $productExportGenerator;

    /** @var string */
    private $exportDirectory;

    public function __construct(
        EntityRepositoryInterface $productExportRepository,
        FilesystemInterface $fileSystem,
        ProductExportGeneratorInterface $productExportGenerator,
        string $exportDirectory
    ) {
        $this->productExportRepository = $productExportRepository;
        $this->fileSystem = $fileSystem;
        $this->productExportGenerator = $productExportGenerator;
        $this->exportDirectory = $exportDirectory;
    }

    public function export(
        SalesChannelContext $context,
        ExportBehavior $behavior,
        ?string $productExportId = null
    ): void {
        $criteria = new Criteria(array_filter([$productExportId]));
        $criteria
            ->addAssociation('salesChannel')
            ->addAssociation('salesChannelDomain.salesChannel')
            ->addAssociation('productStream.filters.queries')
            ->addFilter(
                new MultiFilter(
                    'OR',
                    [
                        new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId()),
                        new EqualsFilter('salesChannelDomain.salesChannel.id', $context->getSalesChannel()->getId()),
                    ]
                )
            );

        if (!$behavior->includeInactive()) {
            $criteria->addFilter(new EqualsFilter('salesChannel.active', true));
        }

        $productExports = $this->productExportRepository->search($criteria, $context->getContext());

        if ($productExports->count() === 0) {
            throw new ExportNotFoundException($productExportId);
        }

        /** @var ProductExportEntity $productExport */
        foreach ($productExports as $productExport) {
            $this->createFile($productExport, $context, $behavior);
        }
    }

    public function getFilePath(ProductExportEntity $productExport): string
    {
        $this->ensureDirectoryExists();

        return sprintf(
            '%s/%s',
            $this->exportDirectory,
            $productExport->getFileName()
        );
    }

    private function createFile(
        ProductExportEntity $productExport,
        SalesChannelContext $context,
        ExportBehavior $behavior
    ): void {
        if ($this->isValidFile($behavior, $productExport)) {
            return;
        }

        $result = $this->productExportGenerator->generate($productExport, $behavior, $context);

        if($result->hasErrors()) {
            throw new ExportInvalidException($result->getErrors());
        }

        $filePath = $this->getFilePath($productExport);

        if ($this->fileSystem->has($filePath)) {
            $this->fileSystem->delete($filePath);
        }

        $this->productExportRepository->update(
            [
                [
                    'id' => $productExport->getId(),
                    'generatedAt' => new \DateTime(),
                ],
            ],
            $context->getContext()
        );

        $this->fileSystem->write(
            $filePath,
            $result->getContent()
        );
    }

    private function isValidFile(ExportBehavior $behavior, ProductExportEntity $productExport): bool
    {
        $filePath = $this->getFilePath($productExport);

        if (!$this->fileSystem->has($filePath)) {
            return false;
        }

        return $productExport->isGenerateByCronjob()
            || (!$productExport->isGenerateByCronjob() && !$this->isCacheExpired($behavior, $productExport));
    }

    private function isCacheExpired(ExportBehavior $behavior, ProductExportEntity $productExport): bool
    {
        if ($behavior->ignoreCache() || $productExport->getGeneratedAt() === null) {
            return true;
        }

        $expireTimestamp = $productExport->getGeneratedAt()->getTimestamp() + $productExport->getInterval();

        return (new \DateTime())->getTimestamp() > $expireTimestamp;
    }

    private function ensureDirectoryExists(): void
    {
        if (!$this->fileSystem->has($this->exportDirectory)) {
            $this->fileSystem->createDir($this->exportDirectory);
        }
    }
}
