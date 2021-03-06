<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 *
 * @version //autogentag//
 */
namespace eZ\Publish\Core\FieldType\XmlText\Input;

use DOMDocument;
use Ibexa\Core\Base\Exceptions\InvalidArgumentException;
use eZ\Publish\Core\FieldType\XmlText\Input;

class EzXml extends Input
{
    /**
     * Constructor.
     *
     * @param string $xmlString The eZ XML content
     * @param string $schemaPath Path to XSD file
     *
     * @throws \Ibexa\Core\Base\Exceptions\InvalidArgumentException if content does not validate
     */
    public function __construct($xmlString, $schemaPath = null)
    {
        if ($schemaPath === null) {
            $schemaPath = __DIR__ . '/Resources/schemas/ezxml.xsd';
        }

        if (!file_exists($schemaPath)) {
            throw new InvalidArgumentException('schemaPath', "Validation of XML content cannot be performed, file '$schemaPath' does not exist.");
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        $doc->loadXML($xmlString);
        if (!$doc->schemaValidate($schemaPath)) {
            $messages = [];

            foreach (libxml_get_errors() as $error) {
                $messages[] = trim($error->message);
            }

            throw new InvalidArgumentException('xmlString', 'Validation of XML content failed: ' . implode("\n", $messages));
        }

        $this->internalRepresentation = $xmlString;
    }
}
