<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 *
 * @version //autogentag//
 */
namespace eZ\Publish\Core\FieldType\XmlText\XmlTextStorage;

use Ibexa\Core\FieldType\Url\UrlStorage\Gateway as UrlGateway;
use Ibexa\Contracts\Core\FieldType\StorageGateway;
use Ibexa\Contracts\Core\Persistence\Content\Field;
use Ibexa\Contracts\Core\Persistence\Content\VersionInfo;

/**
 * Abstract gateway class for XmlText type.
 * Handles data that is not directly included in raw XML value from the field (i.e. URLs).
 */
abstract class Gateway extends StorageGateway
{
    /**
     * @var \Ibexa\Core\FieldType\Url\UrlStorage\Gateway
     */
    protected $urlGateway;

    public function __construct(UrlGateway $urlGateway)
    {
        $this->urlGateway = $urlGateway;
    }

    /**
     * Populates $field->value->externalData with external data.
     *
     * @param \Ibexa\Contracts\Core\Persistence\Content\Field $field
     */
    abstract public function getFieldData(Field $field);

    /**
     * Stores data, external to XMLText type.
     *
     * @param \Ibexa\Contracts\Core\Persistence\Content\VersionInfo $versionInfo
     * @param \Ibexa\Contracts\Core\Persistence\Content\Field $field
     *
     * @return bool
     */
    abstract public function storeFieldData(VersionInfo $versionInfo, Field $field);

    /**
     * Returns a list of URLs for a list of URL ids.
     *
     * Non-existent ids are ignored.
     *
     * @param int[]|string[] $ids An array of URL ids
     *
     * @return array An array of URLs, with ids as keys
     */
    public function getIdUrlMap(array $ids)
    {
        return $this->urlGateway->getIdUrlMap($ids);
    }

    /**
     * Returns a list of URL ids for a list of URLs.
     *
     * Non-existent URLs are ignored.
     *
     * @param string[] $urls An array of URLs
     *
     * @return array An array of URL ids, with URLs as keys
     */
    public function getUrlIdMap(array $urls)
    {
        return $this->urlGateway->getUrlIdMap($urls);
    }

    /**
     * Inserts a new $url and returns its id.
     *
     * @param string $url The URL to insert in the database
     *
     * @return int|string
     */
    public function insertUrl($url)
    {
        return $this->urlGateway->insertUrl($url);
    }

    /**
     * Creates link to URL with $urlId for field with $fieldId in $versionNo.
     *
     * @param int|string $urlId
     * @param int|string $fieldId
     * @param int $versionNo
     */
    public function linkUrl($urlId, $fieldId, $versionNo)
    {
        $this->urlGateway->linkUrl($urlId, $fieldId, $versionNo);
    }

    /**
     * Removes link to URL for $fieldId in $versionNo and cleans up possibly orphaned URLs.
     *
     * @param int|string $fieldId
     * @param int $versionNo
     */
    public function unlinkUrl($fieldId, $versionNo)
    {
        $this->urlGateway->unlinkUrl($fieldId, $versionNo);
    }
}
