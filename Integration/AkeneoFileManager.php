<?php

namespace Oro\Bundle\AkeneoBundle\Integration;

use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\IntegrationBundle\Provider\ConnectorContextMediator;

class AkeneoFileManager
{
    /** @var string[] */
    private $mediaFiles = [];

    /** @var string[] */
    private $assets = [];

    /** @var string[] */
    private $referenceEntityMediaFile = [];

    /** @var AkeneoTransport */
    private $akeneoTransport;

    /** @var ConnectorContextMediator */
    private $contextMediator;

    public function __construct(ConnectorContextMediator $contextMediator)
    {
        $this->contextMediator = $contextMediator;
    }

    public function initTransport(ContextInterface $context)
    {
        $this->akeneoTransport = $this->contextMediator->getTransport($context, true);
        $this->akeneoTransport->init($this->contextMediator->getChannel($context)->getTransport());
    }

    public function registerMediaFile(string $uri): void
    {
        $this->mediaFiles[basename($uri)] = $uri;
    }

    public function registerAsset(string $code, string $uri): void
    {
        $this->assets[basename($uri)] = [$code, $uri];
    }

    public function registerReferenceEntityMediaFile(string $uri): void
    {
        $this->assets[basename($uri)] = $uri;
    }

    public function download(File $file, string $path): void
    {
        if (!$this->akeneoTransport) {
            return;
        }

        $basename = basename($path);
        if (array_key_exists($basename, $this->mediaFiles)) {
            $this->akeneoTransport->downloadAndSaveMediaFile($this->mediaFiles[$basename]);
        }

        if (array_key_exists($basename, $this->assets)) {
            $this->akeneoTransport->downloadAndSaveAsset(...$this->assets[$basename]);
        }

        if (array_key_exists($basename, $this->referenceEntityMediaFile)) {
            $this->akeneoTransport->downloadAndSaveReferenceEntityMediaFile(
                $this->referenceEntityMediaFile[$basename]
            );
        }

        unset(
            $this->mediaFiles[$basename],
            $this->assets[$basename],
            $this->referenceEntityMediaFile[$basename]
        );
    }
}
