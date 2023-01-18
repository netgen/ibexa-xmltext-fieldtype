<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 *
 * @version //autogentag//
 */
namespace eZ\Publish\Core\FieldType\XmlText\Converter;

use DOMDocument;
use DOMElement;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException as APINotFoundException;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Ibexa\Core\Base\Exceptions\UnauthorizedException;
use eZ\Publish\Core\FieldType\XmlText\Converter;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpKernel\Fragment\FragmentHandler;

/**
 * Converts embedded elements from internal XmlText representation to HTML5.
 */
class EmbedToHtml5 implements Converter
{
    /**
     * Content link resource.
     *
     * @const string
     */
    const LINK_RESOURCE_CONTENT = 'CONTENT';

    /**
     * Location link resource.
     *
     * @const string
     */
    const LINK_RESOURCE_LOCATION = 'LOCATION';

    /**
     * URL link resource.
     *
     * @const string
     */
    const LINK_RESOURCE_URL = 'URL';

    /**
     * List of disallowed attributes.
     *
     * @var array
     */
    protected $excludedAttributes = [];

    /**
     * @var \Symfony\Component\HttpKernel\Fragment\FragmentHandler
     */
    protected $fragmentHandler;

    /**
     * @var \Ibexa\Contracts\Core\Repository\Repository
     */
    protected $repository;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(
        FragmentHandler $fragmentHandler,
        Repository $repository,
        array $excludedAttributes,
        LoggerInterface $logger = null
    ) {
        $this->fragmentHandler = $fragmentHandler;
        $this->repository = $repository;
        $this->excludedAttributes = array_fill_keys($excludedAttributes, true);
        $this->logger = $logger;
    }

    /**
     * Process embed tags for a single tag type (embed or embed-inline).
     *
     * @param \DOMDocument $xmlDoc
     * @param $tagName string name of the tag to extract
     */
    protected function processTag(DOMDocument $xmlDoc, $tagName)
    {
        /** @var $embed \DOMElement */
        foreach ($xmlDoc->getElementsByTagName($tagName) as $embed) {
            if (!$view = $embed->getAttribute('view')) {
                $view = $tagName;
            }

            $embedContent = null;
            $parameters = $this->getParameters($embed);

            if ($contentId = $embed->getAttribute('object_id')) {
                try {
                    /** @var \Ibexa\Contracts\Core\Repository\Values\Content\Content $content */
                    $content = $this->repository->sudo(
                        static function (Repository $repository) use ($contentId) {
                            return $repository->getContentService()->loadContent($contentId);
                        }
                    );

                    if (
                        !$this->repository->getPermissionResolver()->canUser('content', 'read', $content)
                        && !$this->repository->getPermissionResolver()->canUser('content', 'view_embed', $content)
                    ) {
                        throw new UnauthorizedException('content', 'read', ['contentId' => $contentId]);
                    }

                    // Check published status of the Content
                    if (
                        $content->getVersionInfo()->status !== VersionInfo::STATUS_PUBLISHED
                        && !$this->repository->getPermissionResolver()->canUser('content', 'versionread', $content)
                    ) {
                        throw new UnauthorizedException('content', 'versionread', ['contentId' => $contentId]);
                    }

                    $embedContent = $this->fragmentHandler->render(
                        new ControllerReference(
                            'ibexa_content:embedAction',
                            [
                                'contentId' => $contentId,
                                'viewType' => $view,
                                'layout' => false,
                                'params' => $parameters,
                            ]
                        )
                    );
                } catch (APINotFoundException $e) {
                    if ($this->logger) {
                        $this->logger->error(
                            'While generating embed for xmltext, could not locate ' .
                            'Content object with ID ' . $contentId
                        );
                    }
                }
            } elseif ($locationId = $embed->getAttribute('node_id')) {
                try {
                    /** @var \Ibexa\Contracts\Core\Repository\Values\Content\Location $location */
                    $location = $this->repository->sudo(
                        static function (Repository $repository) use ($locationId) {
                            return $repository->getLocationService()->loadLocation($locationId);
                        }
                    );

                    if (
                        !$this->repository->getPermissionResolver()->canUser('content', 'read', $location->getContentInfo(), [$location])
                        && !$this->repository->getPermissionResolver()->canUser('content', 'view_embed', $location->getContentInfo(), [$location])
                    ) {
                        throw new UnauthorizedException('content', 'read', ['locationId' => $location->id]);
                    }

                    $embedContent = $this->fragmentHandler->render(
                        new ControllerReference(
                            'ibexa_content:embedAction',
                            [
                                'contentId' => $location->getContentInfo()->id,
                                'locationId' => $location->id,
                                'viewType' => $view,
                                'layout' => false,
                                'params' => $parameters,
                            ]
                        )
                    );
                } catch (APINotFoundException $e) {
                    if ($this->logger) {
                        $this->logger->error(
                            'While generating embed for xmltext, could not locate ' .
                            'Location with ID ' . $locationId
                        );
                    }
                }
            }

            if ($embedContent === null) {
                // Remove empty embed
                $embed->parentNode->removeChild($embed);
            } else {
                while ($embed->hasChildNodes()) {
                    $embed->removeChild($embed->firstChild);
                }
                $embed->appendChild($xmlDoc->createCDATASection($embedContent));
            }
        }
    }

    /**
     * Returns embed's parameters.
     *
     * @param \DOMElement $embed
     *
     * @return array
     */
    protected function getParameters(DOMElement $embed)
    {
        $parameters = [
            'noLayout' => true,
            'objectParameters' => [],
        ];

        $linkParameters = $this->getLinkParameters($embed);

        if ($linkParameters !== null) {
            $parameters['linkParameters'] = $linkParameters;
        }

        foreach ($embed->attributes as $attribute) {
            // We only consider tags in the custom namespace, and skip disallowed names
            if (
                !isset($this->excludedAttributes[$attribute->localName])
                && $attribute->localName !== 'url'
                && strpos($attribute->localName, EmbedLinking::TEMP_PREFIX) !== 0
            ) {
                $parameters['objectParameters'][$attribute->localName] = $attribute->nodeValue;
            }
        }

        return $parameters;
    }

    /**
     * Returns embed's link parameters, or null if embed is not linked.
     *
     * @param \DOMElement $embed
     *
     * @return array|null
     */
    protected function getLinkParameters(DOMElement $embed)
    {
        if (!$embed->hasAttribute('url')) {
            return null;
        }

        $target = $embed->getAttribute(EmbedLinking::TEMP_PREFIX . 'target');
        $title = $embed->getAttribute(EmbedLinking::TEMP_PREFIX . 'title');
        $id = $embed->getAttribute(EmbedLinking::TEMP_PREFIX . 'id');
        $class = $embed->getAttribute(EmbedLinking::TEMP_PREFIX . 'class');
        $resourceFragmentIdentifier = $embed->getAttribute(EmbedLinking::TEMP_PREFIX . 'anchor_name');
        $resourceType = static::LINK_RESOURCE_URL;
        $resourceId = null;

        if ($embed->hasAttribute(EmbedLinking::TEMP_PREFIX . 'object_id')) {
            $resourceType = static::LINK_RESOURCE_CONTENT;
            $resourceId = $embed->getAttribute(EmbedLinking::TEMP_PREFIX . 'object_id');
        } elseif ($embed->hasAttribute(EmbedLinking::TEMP_PREFIX . 'node_id')) {
            $resourceType = static::LINK_RESOURCE_LOCATION;
            $resourceId = $embed->getAttribute(EmbedLinking::TEMP_PREFIX . 'node_id');
        }

        $parameters = [
            'href' => $embed->getAttribute('url'),
            'resourceType' => $resourceType,
            'resourceId' => $resourceId,
            'wrapped' => $this->isLinkWrapped($embed),
        ];

        if (!empty($resourceFragmentIdentifier)) {
            $parameters['resourceFragmentIdentifier'] = $resourceFragmentIdentifier;
        }

        if (!empty($target)) {
            $parameters['target'] = $target;
        }

        if (!empty($title)) {
            $parameters['title'] = $title;
        }

        if (!empty($id)) {
            $parameters['id'] = $id;
        }

        if (!empty($class)) {
            $parameters['class'] = $class;
        }

        return $parameters;
    }

    /**
     * Returns boolean signifying if the embed is contained in a link element of not.
     *
     * After EmbedLinking converter pass this should be possible only for inline level embeds.
     *
     * @param \DOMElement $element
     *
     * @return bool
     */
    protected function isLinkWrapped(DOMElement $element)
    {
        $parentNode = $element->parentNode;

        if ($parentNode instanceof DOMDocument) {
            return false;
        } elseif ($parentNode->localName === 'link') {
            $childCount = 0;

            /** @var \DOMText|\DOMElement $node */
            foreach ($parentNode->childNodes as $node) {
                if (!($node->nodeType === XML_TEXT_NODE && $node->isWhitespaceInElementContent())) {
                    ++$childCount;
                }
            }

            return $childCount !== 1;
        }

        return $this->isLinkWrapped($parentNode);
    }

    /**
     * Converts embed elements in $xmlDoc from internal representation to HTML5.
     *
     * @param \DOMDocument $xmlDoc
     */
    public function convert(DOMDocument $xmlDoc)
    {
        $this->processTag($xmlDoc, 'embed');
        $this->processTag($xmlDoc, 'embed-inline');
    }
}
