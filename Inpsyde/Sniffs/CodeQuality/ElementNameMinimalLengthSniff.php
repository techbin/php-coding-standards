<?php declare(strict_types=1); # -*- coding: utf-8 -*-
/*
 * This file is part of the php-coding-standards package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Inpsyde\CodingStandard\Sniffs\CodeQuality;

use Inpsyde\CodingStandard\Helpers;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * @package php-coding-standards
 * @license MIT
 */
final class ElementNameMinimalLengthSniff implements Sniff
{

    /**
     * @var int
     */
    public $minLength = 3;

    /**
     * @var string[]
     */
    public $allowedShortNames = ['i', 'id', 'to', 'up', 'ok', 'no', 'go', 'it'];

    /**
     * @return int[]
     */
    public function register(): array
    {
        return [T_CLASS, T_TRAIT, T_INTERFACE, T_CONST, T_FUNCTION, T_VARIABLE];
    }

    /**
     * @param File $file
     * @param int $position
     */
    public function process(File $file, $position): void
    {
        $elementName = Helpers::tokenName($file, $position);
        $elementNameLength = mb_strlen($elementName);

        if ($this->shouldBeSkipped($elementNameLength, $elementName)) {
            return;
        }

        $typeName = Helpers::tokenTypeName($file, $position);
        $message = sprintf(
            '%s name "%s" is only %d chars long. Must be at least %d.',
            $typeName,
            $elementName,
            $elementNameLength,
            $this->minLength
        );

        $file->addError($message, $position, 'ElementNameMinimalLength');
    }

    /**
     * @param int $elementNameLength
     * @param string $elementName
     * @return bool
     */
    private function shouldBeSkipped(
        int $elementNameLength,
        string $elementName
    ): bool {
        if ($elementNameLength >= $this->minLength) {
            return true;
        }

        if ($this->isShortNameAllowed($elementName)) {
            return true;
        }

        return false;
    }

    /**
     * @param string $variableName
     * @return bool
     */
    private function isShortNameAllowed(string $variableName): bool
    {
        return in_array($variableName, $this->allowedShortNames);
    }
}