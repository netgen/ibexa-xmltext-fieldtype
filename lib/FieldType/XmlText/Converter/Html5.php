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
use Ibexa\Core\Base\Exceptions\InvalidArgumentType;
use eZ\Publish\Core\FieldType\XmlText\Converter;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use RuntimeException;
use XSLTProcessor;

/**
 * Converts internal XmlText representation to HTML5.
 */
class Html5 implements Converter
{
    /**
     * Path to stylesheet to use.
     *
     * @var string
     */
    protected $stylesheet;

    /**
     * @var \Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface
     */
    protected $configResolver;

    /**
     * Array of XSL stylesheets to add to the main one, grouped by priority.
     *
     * @var array
     */
    protected $customStylesheets = [];

    /**
     * @var \XSLTProcessor
     */
    protected $xsltProcessor;

    /**
     * Array of converters that needs to be called before actual processing.
     *
     * @var \eZ\Publish\Core\FieldType\XmlText\Converter[]
     */
    private $preConverters;

    /**
     * Constructor.
     *
     * @param string $stylesheet Stylesheet to use for conversion
     * @param \Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface $configResolver
     * @param array $customStylesheets Array of XSL stylesheets. Each entry consists in a hash having "path" and "priority" keys.
     * @param \eZ\Publish\Core\FieldType\XmlText\Converter[] $preConverters Array of pre-converters
     *
     * @throws \Ibexa\Core\Base\Exceptions\InvalidArgumentType
     */
    public function __construct($stylesheet, ConfigResolverInterface $configResolver, array $customStylesheets = [], array $preConverters = [])
    {
        $this->stylesheet = $stylesheet;
        $this->configResolver = $configResolver;
        $this->setCustomStylesheets($customStylesheets);

        foreach ($preConverters as $preConverter) {
            if (!$preConverter instanceof Converter) {
                throw new InvalidArgumentType('$preConverters', 'eZ\\Publish\\Core\\FieldType\\XmlText\\Converter[]', $preConverter);
            }
        }

        $this->preConverters = $preConverters;
    }

    /**
     * Sets the custom stylesheets grouped by priority.
     *
     * @param array $customStylesheets Array of XSL stylesheets. Each entry
     * consists in a hash having "path" and "priority" keys.
     */
    public function setCustomStylesheets($customStylesheets)
    {
        $this->customStylesheets = [];
        if (!$customStylesheets) {
            return;
        }
        foreach ($customStylesheets as $stylesheet) {
            if (!isset($this->customStylesheets[$stylesheet['priority']])) {
                $this->customStylesheets[$stylesheet['priority']] = [];
            }

            $this->customStylesheets[$stylesheet['priority']][] = $stylesheet['path'];
        }
    }

    /**
     * Adds a pre-converter to the list.
     * Use a pre-converter when you need some processing before XSLT transformation (e.g. for custom tags).
     *
     * @param Converter $preConverter
     */
    public function addPreConverter(Converter $preConverter)
    {
        $this->preConverters[] = $preConverter;
    }

    /**
     * @return array|\eZ\Publish\Core\FieldType\XmlText\Converter[]
     */
    public function getPreConverters()
    {
        return $this->preConverters;
    }

    /**
     * Returns the XSLTProcessor to use to transform internal XML to HTML5.
     *
     * @return \XSLTProcessor
     */
    protected function getXSLTProcessor()
    {
        if (isset($this->xsltProcessor)) {
            return $this->xsltProcessor;
        }

        $xslDoc = new DOMDocument();
        $xslDoc->load($this->stylesheet);

        // Now loading custom xsl stylesheets dynamically.
        // According to XSL spec, each <xsl:import> tag MUST be loaded BEFORE any other element.
        $insertBeforeEl = $xslDoc->documentElement->firstChild;

        $this->setCustomStylesheets($this->configResolver->getParameter('fieldtypes.ezxml.custom_xsl'));
        foreach ($this->getSortedCustomStylesheets() as $stylesheet) {
            if (!file_exists($stylesheet)) {
                throw new RuntimeException("Cannot find XSL stylesheet for XMLText rendering: $stylesheet");
            }

            $newEl = $xslDoc->createElement('xsl:import');
            $hrefAttr = $xslDoc->createAttribute('href');
            $hrefAttr->value = str_replace('\\', '/', $stylesheet);
            $newEl->appendChild($hrefAttr);
            $xslDoc->documentElement->insertBefore($newEl, $insertBeforeEl);
        }
        // Now reload XSL DOM to "refresh" it.
        $xslDoc->loadXML($xslDoc->saveXML());

        $this->xsltProcessor = new XSLTProcessor();
        $this->xsltProcessor->importStyleSheet($xslDoc);
        $this->xsltProcessor->registerPHPFunctions();

        return $this->xsltProcessor;
    }

    /**
     * Returns custom stylesheets to load, sorted.
     * The order is from the lowest priority to the highest since in case of a conflict,
     * the last loaded XSL template always wins.
     *
     * @return array
     */
    private function getSortedCustomStylesheets()
    {
        $sortedStylesheets = [];
        ksort($this->customStylesheets);
        foreach ($this->customStylesheets as $stylesheets) {
            $sortedStylesheets = array_merge($sortedStylesheets, $stylesheets);
        }

        return $sortedStylesheets;
    }

    /**
     * Convert $xmlDoc from internal representation DOMDocument to HTML5.
     *
     * @param \DOMDocument $xmlDoc
     *
     * @return string
     */
    public function convert(DOMDocument $xmlDoc)
    {
        foreach ($this->getPreConverters() as $preConverter) {
            $preConverter->convert($xmlDoc);
        }

        $xsl = $this->getXSLTProcessor();

        return $xsl->transformToXML($xmlDoc);
    }
}
